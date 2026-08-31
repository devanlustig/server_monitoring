<?php

namespace App\Services\Monitoring;

use InvalidArgumentException;

class EndpointSourceResolverFactory
{
    public function __construct(
        private readonly ApacheEndpointResolver $apacheResolver,
        private readonly NginxEndpointResolver $nginxResolver,
    ) {}

    public function make(string $webServer): WebServerEndpointResolver
    {
        return match (strtolower($webServer)) {
            'apache' => $this->apacheResolver,
            'nginx' => $this->nginxResolver,
            default => throw new InvalidArgumentException("Unsupported web server type: {$webServer}"),
        };
    }
}
