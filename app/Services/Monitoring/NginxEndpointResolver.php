<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\WebEndpointSourceData;
use Illuminate\Support\Facades\Cache;

class NginxEndpointResolver implements WebServerEndpointResolver
{
    public function __construct(
        private readonly RemoteCommandService $commands
    ) {}

    public function resolve(MonitoredServer $server, array $endpoints): array
    {
        $rules = Cache::remember("nginx_config_rules_{$server->id}", 300, function () use ($server) {
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
            $cmd = 'nginx -T 2>/dev/null || sudo nginx -T 2>/dev/null || cat /etc/nginx/nginx.conf /etc/nginx/conf.d/*.conf /etc/nginx/sites-enabled/* 2>/dev/null';
            $result = $this->commands->execute($server, $cmd);
            if (!$result->successful || empty($result->output)) {
                return [];
            }
            return $this->parseConfigString($result->output);
        } catch (\Throwable $e) {
            logger()->warning("Failed to fetch Nginx config for server {$server->name}: " . $e->getMessage());
            return [];
        }
    }

    public function parseConfigString(string $config): array
    {
        // Strip comments
        $config = preg_replace('/#.*$/m', '', $config);

        // Find all server { ... } blocks
        // We do a simple brace matching or regex to find server blocks
        $rules = [];
        $offset = 0;
        while (($pos = strpos($config, 'server {', $offset)) !== false) {
            $block = $this->extractBraceBlock($config, $pos + 7);
            if ($block) {
                $rules[] = $this->parseServerBlock($block);
            }
            $offset = $pos + 8;
        }

        return $rules;
    }

    private function extractBraceBlock(string $str, int $start): ?string
    {
        $len = strlen($str);
        $braces = 1;
        $pos = $start + 1;
        while ($pos < $len) {
            $char = $str[$pos];
            if ($char === '{') {
                $braces++;
            } elseif ($char === '}') {
                $braces--;
                if ($braces === 0) {
                    return substr($str, $start + 1, $pos - $start - 1);
                }
            }
            $pos++;
        }
        return null;
    }

    private function parseServerBlock(string $block): array
    {
        $serverNames = [];
        if (preg_match('/server_name\s+([^;]+);/', $block, $matches)) {
            $serverNames = preg_split('/\s+/', trim($matches[1]));
        }

        $globalRoot = null;
        if (preg_match('/(?<!location\s)\broot\s+([^;]+);/', $block, $matches)) {
            $globalRoot = trim($matches[1]);
        }

        $locations = [];
        // Find location blocks
        $offset = 0;
        while (preg_match('/location\s+([^{]+)\{/', $block, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $pattern = trim($matches[1][0]);
            $matchPos = $matches[0][1];
            $locBlock = $this->extractBraceBlock($block, $matchPos + strlen($matches[0][0]) - 1);
            if ($locBlock) {
                $locRules = $this->parseLocationBlock($pattern, $locBlock, $globalRoot);
                if ($locRules) {
                    $locations[] = $locRules;
                }
            }
            $offset = $matchPos + strlen($matches[0][0]);
        }

        return [
            'server_names' => $serverNames,
            'root' => $globalRoot,
            'locations' => $locations,
        ];
    }

    private function parseLocationBlock(string $pattern, string $block, ?string $globalRoot): array
    {
        // Parse modifiers: e.g. = /api, ~ ^/api, etc.
        $patternClean = trim(preg_replace('/^(=|~|~\*|\^~)\s+/', '', $pattern));

        $root = null;
        if (preg_match('/\broot\s+([^;]+);/', $block, $matches)) {
            $root = trim($matches[1]);
        }

        $alias = null;
        if (preg_match('/\balias\s+([^;]+);/', $block, $matches)) {
            $alias = trim($matches[1]);
        }

        $proxyPass = null;
        if (preg_match('/\bproxy_pass\s+([^;]+);/', $block, $matches)) {
            $proxyPass = trim($matches[1]);
        }

        return [
            'raw_pattern' => $pattern,
            'pattern' => $patternClean,
            'root' => $root ?? $globalRoot,
            'alias' => $alias,
            'proxy_pass' => $proxyPass,
        ];
    }

    private function resolveEndpoint(string $endpoint, array $serverRules): WebEndpointSourceData
    {
        if (empty($serverRules)) {
            return new WebEndpointSourceData($endpoint, 'unknown', null, null, null, null, false);
        }

        // We'll search across all defined servers. If a default is needed, we'll pick the first one.
        $bestMatch = null;
        $bestLength = -1;
        $virtualHost = null;

        foreach ($serverRules as $server) {
            $vhost = $server['server_names'][0] ?? 'localhost';
            
            // Check if there is a location match
            foreach ($server['locations'] as $loc) {
                $pattern = $loc['pattern'];
                // Clean regex or simple prefix matching
                // We'll support standard prefix match (longest match wins)
                if (str_starts_with($endpoint, $pattern)) {
                    $len = strlen($pattern);
                    if ($len > $bestLength) {
                        $bestLength = $len;
                        $bestMatch = $loc;
                        $virtualHost = $vhost;
                    }
                }
            }

            // If no location matches but server has a root, that's a fallback root match
            if ($bestMatch === null && $server['root'] !== null) {
                $bestMatch = [
                    'raw_pattern' => '/',
                    'pattern' => '/',
                    'root' => $server['root'],
                    'alias' => null,
                    'proxy_pass' => null,
                ];
                $virtualHost = $vhost;
            }
        }

        if ($bestMatch) {
            if (!empty($bestMatch['proxy_pass'])) {
                return new WebEndpointSourceData(
                    $endpoint,
                    'proxy',
                    null,
                    "PROXY → " . $bestMatch['proxy_pass'],
                    $virtualHost,
                    $bestMatch['raw_pattern'],
                    true
                );
            }

            if (!empty($bestMatch['alias'])) {
                // If alias is /var/www/assets/images/ and pattern is /images/
                // And endpoint is /images/logo.png, path is /var/www/assets/images/logo.png
                $relPath = ltrim(substr($endpoint, strlen($bestMatch['pattern'])), '/');
                $fullPath = rtrim($bestMatch['alias'], '/') . '/' . $relPath;
                return new WebEndpointSourceData(
                    $endpoint,
                    'alias',
                    $fullPath,
                    null,
                    $virtualHost,
                    $bestMatch['raw_pattern'],
                    true
                );
            }

            if (!empty($bestMatch['root'])) {
                $fullPath = rtrim($bestMatch['root'], '/') . '/' . ltrim($endpoint, '/');
                return new WebEndpointSourceData(
                    $endpoint,
                    'document_root',
                    $fullPath,
                    null,
                    $virtualHost,
                    $bestMatch['raw_pattern'],
                    true
                );
            }
        }

        return new WebEndpointSourceData($endpoint, 'unknown', null, null, null, null, false);
    }
}
