<?php

namespace App\Domain\Monitoring\Data;

readonly class RemoteCommandResult
{
    public function __construct(public bool $successful, public ?string $output, public ?string $message) {}
}
