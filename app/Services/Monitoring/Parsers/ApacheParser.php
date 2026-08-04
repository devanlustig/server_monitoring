<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\ApacheLogEntryData;
use DateTimeImmutable;

class ApacheParser
{
    public function parse(string $rawOutput): array
    {
        $rawOutput = trim($rawOutput);

        if ($rawOutput === '' || str_contains($rawOutput, 'LOG_NOT_FOUND')) {
            return [
                'logFound' => false,
                'entries' => [],
            ];
        }

        $lines = explode("\n", $rawOutput);
        $entries = [];

        // Try these patterns sequentially to support combined, combined+%D, combined+%v+%D, combined+%v, etc.
        $patterns = [
            // 1. Combined + %v + %D before referer (%v %h %l %u %t "%r" %>s %b/%O %D "%{Referer}i" "%{User-Agent}i")
            [
                'regex' => '/^(\S+)\s+(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s*(\S+)?(?:\s+HTTP\/\d\.\d)?"\s+(\d{3})\s+(\d+|-)\s+(\d+|-)\s+"([^"]*)"\s+"([^"]*)"/i',
                'map' => [
                    'ip' => 2,
                    'timestamp' => 3,
                    'method' => 4,
                    'uri' => 5,
                    'status' => 6,
                    'bytes' => 7,
                    'rt' => 8,
                    'referer' => 9,
                    'ua' => 10
                ]
            ],
            // 2. Combined + %D at the end (%h %l %u %t "%r" %>s %b "%{Referer}i" "%{User-Agent}i" %D)
            [
                'regex' => '/^(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s*(\S+)?(?:\s+HTTP\/\d\.\d)?"\s+(\d{3})\s+(\d+|-)\s+"([^"]*)"\s+"([^"]*)"\s+(\d+|-)/i',
                'map' => [
                    'ip' => 1,
                    'timestamp' => 2,
                    'method' => 3,
                    'uri' => 4,
                    'status' => 5,
                    'bytes' => 6,
                    'referer' => 7,
                    'ua' => 8,
                    'rt' => 9
                ]
            ],
            // 3. Combined + %v but no %D (%v %h %l %u %t "%r" %>s %b "%{Referer}i" "%{User-Agent}i")
            [
                'regex' => '/^(\S+)\s+(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s*(\S+)?(?:\s+HTTP\/\d\.\d)?"\s+(\d{3})\s+(\d+|-)\s+"([^"]*)"\s+"([^"]*)"/i',
                'map' => [
                    'ip' => 2,
                    'timestamp' => 3,
                    'method' => 4,
                    'uri' => 5,
                    'status' => 6,
                    'bytes' => 7,
                    'referer' => 8,
                    'ua' => 9,
                    'rt' => null
                ]
            ],
            // 4. Combined biasa / standard (%h %l %u %t "%r" %>s %b "%{Referer}i" "%{User-Agent}i")
            [
                'regex' => '/^(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s*(\S+)?(?:\s+HTTP\/\d\.\d)?"\s+(\d{3})\s+(\d+|-)\s+"([^"]*)"\s+"([^"]*)"/i',
                'map' => [
                    'ip' => 1,
                    'timestamp' => 2,
                    'method' => 3,
                    'uri' => 4,
                    'status' => 5,
                    'bytes' => 6,
                    'referer' => 7,
                    'ua' => 8,
                    'rt' => null
                ]
            ],
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $matched = false;
            foreach ($patterns as $p) {
                if (preg_match($p['regex'], $line, $matches)) {
                    $map = $p['map'];

                    $ip = $matches[$map['ip']] ?? '-';
                    $rawTimestamp = $matches[$map['timestamp']] ?? null;
                    $method = strtoupper($matches[$map['method']] ?? 'GET');
                    $rawUri = $matches[$map['uri']] ?? '/';
                    $statusCode = (int) ($matches[$map['status']] ?? 200);
                    $bytes = ($matches[$map['bytes']] ?? '-') === '-' ? 0 : (int) $matches[$map['bytes']];
                    $referer = $matches[$map['referer']] ?? null;
                    $userAgent = $matches[$map['ua']] ?? null;
                    
                    $rawResponseTime = null;
                    if ($map['rt'] !== null && isset($matches[$map['rt']]) && $matches[$map['rt']] !== '') {
                        $rawResponseTime = (float) $matches[$map['rt']];
                    }

                    $endpoint = parse_url($rawUri, PHP_URL_PATH) ?? '/';

                    $dateTime = null;
                    if ($rawTimestamp) {
                        $dt = DateTimeImmutable::createFromFormat('d/M/Y:H:i:s O', $rawTimestamp);
                        if ($dt !== false) {
                            $dateTime = $dt;
                        }
                    }

                    $responseTimeMs = null;
                    if ($rawResponseTime !== null) {
                        if ($rawResponseTime > 1000) {
                            // Microseconds (%D) -> convert to milliseconds
                            $responseTimeMs = round($rawResponseTime / 1000.0, 2);
                        } else {
                            // Seconds (%T) -> convert to milliseconds
                            $responseTimeMs = round($rawResponseTime * 1000.0, 2);
                        }
                    }

                    $virtualHost = null;

                    if (isset($matches[1])) {
                        // Pattern dengan %v
                        if (str_contains($line, $matches[1])) {
                            $virtualHost = $matches[1];
                        }
                    }

                    $entries[] = new ApacheLogEntryData(
                        virtualHost: $virtualHost,
                        ip: $ip,
                        timestamp: $rawTimestamp,
                        dateTime: $dateTime,
                        method: $method,
                        endpoint: $endpoint,
                        statusCode: $statusCode,
                        bytes: $bytes,
                        referer: $referer,
                        userAgent: $userAgent,
                        responseTimeMs: $responseTimeMs,
                    );

                    $matched = true;
                    break;
                }
            }
        }

        return [
            'logFound' => true,
            'entries' => $entries,
        ];
    }
}
