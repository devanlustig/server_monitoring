<?php

namespace App\Domain\Monitoring\Data;

readonly class ConnectionTestResult
{
    public function __construct(public bool $successful, public string $message) {}
}
