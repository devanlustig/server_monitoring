<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Monitoring\NginxEndpointResolver;
use App\Services\Monitoring\ApacheEndpointResolver;
use App\Services\Monitoring\RemoteCommandService;

class EndpointResolverTest extends TestCase
{
    public function test_nginx_config_parsing_and_resolution()
    {
        $mockService = $this->createMock(RemoteCommandService::class);
        $resolver = new NginxEndpointResolver($mockService);

        $nginxConfig = <<<NGINX
server {
    server_name example.com;
    root /var/www/html;

    location /images/ {
        alias /var/www/assets/images/;
    }

    location /api/ {
        proxy_pass http://127.0.0.1:8000;
    }
}
NGINX;

        $rules = $resolver->parseConfigString($nginxConfig);

        $this->assertCount(1, $rules);
        $this->assertEquals(['example.com'], $rules[0]['server_names']);
        $this->assertEquals('/var/www/html', $rules[0]['root']);
        $this->assertCount(2, $rules[0]['locations']);

        // Test resolution
        $resolveMethod = new \ReflectionMethod(NginxEndpointResolver::class, 'resolveEndpoint');
        $resolveMethod->setAccessible(true);

        // 1. Root match
        $res1 = $resolveMethod->invoke($resolver, '/products', $rules);
        $this->assertEquals('document_root', $res1->sourceType);
        $this->assertEquals('/var/www/html/products', $res1->sourcePath);
        $this->assertEquals('example.com', $res1->virtualHost);

        // 2. Alias match
        $res2 = $resolveMethod->invoke($resolver, '/images/logo.png', $rules);
        $this->assertEquals('alias', $res2->sourceType);
        $this->assertEquals('/var/www/assets/images/logo.png', $res2->sourcePath);
        $this->assertEquals('example.com', $res2->virtualHost);

        // 3. Proxy pass match
        $res3 = $resolveMethod->invoke($resolver, '/api/orders', $rules);
        $this->assertEquals('proxy', $res3->sourceType);
        $this->assertEquals('PROXY → http://127.0.0.1:8000', $res3->proxyTarget);
        $this->assertEquals('example.com', $res3->virtualHost);
    }

    public function test_apache_config_parsing_and_resolution()
    {
        $mockService = $this->createMock(RemoteCommandService::class);
        $resolver = new ApacheEndpointResolver($mockService);

        $apacheConfig = <<<APACHE
DocumentRoot "/var/www/global"

<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/html

    Alias /images /var/www/assets/images

    ProxyPass /api http://127.0.0.1:8000/api
</VirtualHost>
APACHE;

        $rules = $resolver->parseConfigString($apacheConfig);

        // 2 rules (1 virtual host + 1 global fallback)
        $this->assertCount(2, $rules);

        $vhostRule = $rules[0];
        $this->assertEquals(['example.com'], $vhostRule['server_names']);
        $this->assertEquals('/var/www/html', $vhostRule['root']);
        $this->assertCount(1, $vhostRule['aliases']);
        $this->assertCount(1, $vhostRule['proxies']);

        // Test resolution
        $resolveMethod = new \ReflectionMethod(ApacheEndpointResolver::class, 'resolveEndpoint');
        $resolveMethod->setAccessible(true);

        // 1. Document root fallback
        $res1 = $resolveMethod->invoke($resolver, '/products', $rules);
        $this->assertEquals('document_root', $res1->sourceType);
        $this->assertEquals('/var/www/html/products', $res1->sourcePath);

        // 2. Alias
        $res2 = $resolveMethod->invoke($resolver, '/images/logo.png', $rules);
        $this->assertEquals('alias', $res2->sourceType);
        $this->assertEquals('/var/www/assets/images/logo.png', $res2->sourcePath);

        // 3. Proxy
        $res3 = $resolveMethod->invoke($resolver, '/api/orders', $rules);
        $this->assertEquals('proxy', $res3->sourceType);
        $this->assertEquals('PROXY → http://127.0.0.1:8000/api', $res3->proxyTarget);
    }
}
