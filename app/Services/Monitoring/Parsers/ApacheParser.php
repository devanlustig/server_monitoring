<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\ApacheLogEntryData;
use DateTimeImmutable;

class ApacheParser
{
    /**
     * Supported Apache LogFormat patterns.
     *
     * Semua response time diasumsikan menggunakan %D
     * (%D = microseconds).
     */
    private const PATTERNS = [

        // Combined + VirtualHost + Response Time (%v ... %D)
        [
            'hasVirtualHost' => true,
            'regex' => '/^(\S+)\s+(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s*(\S+)?(?:\s+HTTP\/\d\.\d)?"\s+(\d{3})\s+(\d+|-)\s+(\d+|-)\s+"([^"]*)"\s+"([^"]*)"/i',
            'map' => [
                'virtualHost' => 1,
                'ip' => 2,
                'timestamp' => 3,
                'method' => 4,
                'uri' => 5,
                'status' => 6,
                'bytes' => 7,
                'rt' => 8,
                'referer' => 9,
                'ua' => 10,
            ],
        ],

        // Combined + Response Time (%D)
        [
            'hasVirtualHost' => false,
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
                'rt' => 9,
            ],
        ],

        // Combined + VirtualHost
        [
            'hasVirtualHost' => true,
            'regex' => '/^(\S+)\s+(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s*(\S+)?(?:\s+HTTP\/\d\.\d)?"\s+(\d{3})\s+(\d+|-)\s+"([^"]*)"\s+"([^"]*)"/i',
            'map' => [
                'virtualHost' => 1,
                'ip' => 2,
                'timestamp' => 3,
                'method' => 4,
                'uri' => 5,
                'status' => 6,
                'bytes' => 7,
                'referer' => 8,
                'ua' => 9,
                'rt' => null,
            ],
        ],

        // Standard Combined Log
        [
            'hasVirtualHost' => false,
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
                'rt' => null,
            ],
        ],
    ];

    public function parse(string $rawOutput): array
    {
        $rawOutput = trim($rawOutput);

        if ($rawOutput === '' || str_contains($rawOutput, 'LOG_NOT_FOUND')) {
            return [
                'logFound' => false,
                'entries' => [],
            ];
        }

        $entries = [];

        foreach (explode("\n", $rawOutput) as $line) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            foreach (self::PATTERNS as $pattern) {

                if (!preg_match($pattern['regex'], $line, $matches)) {
                    continue;
                }

                $map = $pattern['map'];

                $virtualHost = $pattern['hasVirtualHost']
                    ? ($matches[$map['virtualHost']] ?? null)
                    : null;

                $ip = $matches[$map['ip']] ?? '-';

                $rawTimestamp = $matches[$map['timestamp']] ?? null;

                $method = strtoupper(
                    $matches[$map['method']] ?? 'GET'
                );

                $rawUri = $matches[$map['uri']] ?? '/';

                $statusCode = (int) (
                    $matches[$map['status']] ?? 200
                );

                $bytes = (
                    ($matches[$map['bytes']] ?? '-') === '-'
                )
                    ? 0
                    : (int) $matches[$map['bytes']];

                $referer = $matches[$map['referer']] ?? null;

                $userAgent = $matches[$map['ua']] ?? null;

                $rawResponseTime = null;

                if (
                    $map['rt'] !== null &&
                    isset($matches[$map['rt']]) &&
                    $matches[$map['rt']] !== '-'
                ) {
                    $rawResponseTime = (float) $matches[$map['rt']];
                }

                $endpoint = parse_url(
                    $rawUri,
                    PHP_URL_PATH
                );

                if (!$endpoint) {
                    $endpoint = '/';
                }

                $dateTime = null;

                if ($rawTimestamp) {
                    $dateTime = DateTimeImmutable::createFromFormat(
                        'd/M/Y:H:i:s O',
                        $rawTimestamp
                    ) ?: null;
                }

                /**
                 * Apache %D
                 * microseconds -> milliseconds
                 */
                $responseTimeMs = null;

                if ($rawResponseTime !== null) {
                    $responseTimeMs = round(
                        $rawResponseTime / 1000,
                        2
                    );
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

                // stop checking other patterns
                continue 2;
            }
        }

        return [
            'logFound' => true,
            'entries' => $entries,
        ];
    }
}