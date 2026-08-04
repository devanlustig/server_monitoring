<?php

namespace App\Services\Monitoring\DTO;

use DateTimeImmutable;

class ApacheLogEntryData
{
    public function __construct(
        public readonly ?string $virtualHost,
        public readonly string $ip,
        public readonly ?string $timestamp,
        public readonly ?DateTimeImmutable $dateTime,
        public readonly string $method,
        public readonly string $endpoint,
        public readonly int $statusCode,
        public readonly int $bytes,
        public readonly ?string $referer,
        public readonly ?string $userAgent,
        public readonly ?float $responseTimeMs,
    ) {}
}

?>