<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\WebEndpointSourceData;
use Illuminate\Support\Facades\Cache;

class ApacheEndpointResolver implements WebServerEndpointResolver
{
    private ?string $activeVirtualHost = null;

    public function __construct(
        private readonly RemoteCommandService $commands
    ) {}

    public function setVirtualHost(?string $vhost): self
    {
        $this->activeVirtualHost = $vhost;
        return $this;
    }

    public function resolve(MonitoredServer $server, array $endpoints): array
    {
        $rules = Cache::remember("apache_config_rules_{$server->id}", 300, function () use ($server) {
            return $this->fetchAndParseConfig($server);
        });

        $resolved = [];
        foreach ($endpoints as $endpoint) {
            $resolved[] = $this->resolveEndpoint($endpoint, $rules);
        }

        return $resolved;
    }

    private function fetchAndParseConfig(MonitoredServer $server): array
    {
        try {
            $cmd = 'cat /etc/apache2/sites-enabled/* /etc/apache2/apache2.conf /etc/httpd/conf.d/*.conf /etc/httpd/conf/httpd.conf 2>/dev/null || apache2ctl -t -D DUMP_CONFIG 2>/dev/null || apachectl -t -D DUMP_CONFIG 2>/dev/null';
            $result = $this->commands->execute($server, $cmd);
            if (!$result->successful || empty($result->output)) {
                return [];
            }
            return $this->parseConfigString($result->output);
        } catch (\Throwable $e) {
            logger()->warning("Failed to fetch Apache config for server {$server->name}: " . $e->getMessage());
            return [];
        }
    }

    public function parseConfigString(string $config): array
    {
        // Strip comments
        $config = preg_replace('/^\s*#.*$/m', '', $config);

        $rules = [];

        // 1. Extract VirtualHost blocks
        $offset = 0;
        while (preg_match('/<VirtualHost\s+([^>]+)>/i', $config, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $startPos = $matches[0][1];
            $header = $matches[1][0];
            $endTagPos = stripos($config, '</VirtualHost>', $startPos);
            if ($endTagPos !== false) {
                $blockLength = ($endTagPos + 14) - $startPos;
                $vhostBlock = substr($config, $startPos, $blockLength);
                $rules[] = $this->parseVirtualHostBlock($vhostBlock);
                $offset = $endTagPos + 14;
            } else {
                $offset = $startPos + strlen($matches[0][0]);
            }
        }

        // 2. Parse global directives (outside VirtualHost blocks)
        // Strip out virtual host blocks to get global config
        $globalConfig = preg_replace('/<VirtualHost\s+[^>]+>.*?<\/VirtualHost>/is', '', $config);
        $globalRules = $this->parseDirectives($globalConfig);
        if ($globalRules) {
            $globalRules['server_names'] = ['_default_'];
            $rules[] = $globalRules;
        }

        return $rules;
    }

    private function parseVirtualHostBlock(string $block): array
    {
        $serverName = null;
        if (preg_match('/ServerName\s+(\S+)/i', $block, $matches)) {
            $serverName = trim($matches[1]);
        }

        $directives = $this->parseDirectives($block);
        $directives['server_names'] = $serverName ? [$serverName] : ['_default_'];

        return $directives;
    }

    private function parseDirectives(string $text): array
    {
        $documentRoot = null;
        if (preg_match('/DocumentRoot\s+["\']?([^"\'\r\n]+)["\']?/i', $text, $matches)) {
            $documentRoot = trim($matches[1]);
        }

        $aliases = [];
        $offset = 0;
        while (preg_match('/Alias\s+(\S+)\s+["\']?([^"\'\r\n\s]+)["\']?/i', $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $aliasPattern = $matches[1][0];
            $aliasPath = $matches[2][0];
            $aliases[] = [
                'pattern' => $aliasPattern,
                'path' => $aliasPath,
            ];
            $offset = $matches[0][1] + strlen($matches[0][0]);
        }

        $proxies = [];
        $offset = 0;
        while (preg_match('/ProxyPass\s+(\S+)\s+(\S+)/i', $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $proxyPattern = $matches[1][0];
            $proxyTarget = $matches[2][0];
            if ($proxyTarget !== '!' && !str_starts_with($proxyPattern, 'interpolate')) {
                $proxies[] = [
                    'pattern' => $proxyPattern,
                    'target' => $proxyTarget,
                ];
            }
            $offset = $matches[0][1] + strlen($matches[0][0]);
        }

        return [
            'root' => $documentRoot,
            'aliases' => $aliases,
            'proxies' => $proxies,
        ];
    }

    private function resolveEndpoint(string $endpoint, array $serverRules): WebEndpointSourceData
    {
        $filteredRules = $serverRules;
        if ($this->activeVirtualHost) {
            $matchedRules = [];
            foreach ($serverRules as $rule) {
                foreach ($rule['server_names'] as $name) {
                    if (strcasecmp($name, $this->activeVirtualHost) === 0) {
                        $matchedRules[] = $rule;
                        break;
                    }
                }
            }
            if (!empty($matchedRules)) {
                $filteredRules = $matchedRules;
            }
        }

        if (empty($filteredRules)) {
            return new WebEndpointSourceData($endpoint, 'unknown', null, null, null, null, false);
        }

        $bestMatch = null;
        $bestLength = -1;
        $virtualHost = null;

        foreach ($filteredRules as $server) {
            $vhost = $server['server_names'][0] ?? '_default_';

            // Check ProxyPass (longest pattern match wins)
            if (!empty($server['proxies'])) {
                foreach ($server['proxies'] as $proxy) {
                    if (str_starts_with($endpoint, $proxy['pattern'])) {
                        $len = strlen($proxy['pattern']);
                        if ($len > $bestLength) {
                            $bestLength = $len;
                            $bestMatch = [
                                'type' => 'proxy',
                                'pattern' => $proxy['pattern'],
                                'target' => $proxy['target'],
                            ];
                            $virtualHost = $vhost;
                        }
                    }
                }
            }

            // Check Alias
            if (!empty($server['aliases'])) {
                foreach ($server['aliases'] as $alias) {
                    if (str_starts_with($endpoint, $alias['pattern'])) {
                        $len = strlen($alias['pattern']);
                        if ($len > $bestLength) {
                            $bestLength = $len;
                            $bestMatch = [
                                'type' => 'alias',
                                'pattern' => $alias['pattern'],
                                'path' => $alias['path'],
                            ];
                            $virtualHost = $vhost;
                        }
                    }
                }
            }

            // Fallback to DocumentRoot
            if ($bestMatch === null && !empty($server['root'])) {
                $bestMatch = [
                    'type' => 'document_root',
                    'pattern' => '/',
                    'path' => $server['root'],
                ];
                $virtualHost = $vhost;
            }
        }

        if ($bestMatch) {
            if ($bestMatch['type'] === 'proxy') {
                return new WebEndpointSourceData(
                    $endpoint,
                    'proxy',
                    null,
                    "PROXY → " . $bestMatch['target'],
                    $virtualHost,
                    $bestMatch['pattern'],
                    true
                );
            }

            if ($bestMatch['type'] === 'alias') {
                $relPath = ltrim(substr($endpoint, strlen($bestMatch['pattern'])), '/');
                $fullPath = rtrim($bestMatch['path'], '/') . '/' . $relPath;
                return new WebEndpointSourceData(
                    $endpoint,
                    'alias',
                    $fullPath,
                    null,
                    $virtualHost,
                    $bestMatch['pattern'],
                    true
                );
            }

            if ($bestMatch['type'] === 'document_root') {
                $fullPath = rtrim($bestMatch['path'], '/') . '/' . ltrim($endpoint, '/');
                return new WebEndpointSourceData(
                    $endpoint,
                    'document_root',
                    $fullPath,
                    null,
                    $virtualHost,
                    $bestMatch['pattern'],
                    true
                );
            }
        }

        return new WebEndpointSourceData($endpoint, 'unknown', null, null, null, null, false);
    }
}
