<?php

namespace App\Services\Monitoring\DTO;

class PostgreSqlIncidentData
{
    public function __construct(

        public readonly string $capturedAt,

        public readonly string $server,

        public readonly array $sections,

    ) {
    }
}