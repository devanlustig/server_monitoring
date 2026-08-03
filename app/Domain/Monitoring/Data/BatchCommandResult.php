<?php

namespace App\Domain\Monitoring\Data;

class BatchCommandResult
{
    public function __construct(
        public readonly array $results,
    ) {
    }

    public function get(string $key): ?string
    {
        return $this->results[$key] ?? null;
    }
}