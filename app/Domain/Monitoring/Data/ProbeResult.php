<?php

namespace App\Domain\Monitoring\Data;

use Carbon\CarbonImmutable;

readonly class ProbeResult
{
    public function __construct(
        public bool $isReachable,
        public ?int $responseTimeMs,
        public ?string $message,
        public CarbonImmutable $checkedAt,
    ) {
    }
}
