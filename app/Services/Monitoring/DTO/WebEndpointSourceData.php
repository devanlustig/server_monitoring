<?php

namespace App\Services\Monitoring\DTO;

class WebEndpointSourceData
{
    public function __construct(
        public string $endpoint,
        public string $sourceType,
        public ?string $sourcePath,
        public ?string $proxyTarget,
        public ?string $virtualHost,
        public ?string $locationPattern,
        public bool $resolved
    ) {}

    public function toArray(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'sourceType' => $this->sourceType,
            'sourcePath' => $this->sourcePath,
            'proxyTarget' => $this->proxyTarget,
            'virtualHost' => $this->virtualHost,
            'locationPattern' => $this->locationPattern,
            'resolved' => $this->resolved,
        ];
    }
}
