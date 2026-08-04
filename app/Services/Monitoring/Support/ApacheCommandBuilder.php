<?php
namespace App\Services\Monitoring\Support;
use App\Models\MonitoredServer;
class ApacheCommandBuilder
{
    public function build(MonitoredServer $server, int $lines = 5000): string
    {
        $logPaths = [
            '/var/log/apache2/access.log',
            '/var/log/apache2/*access*.log',
            '/var/log/httpd/access_log',
            '/var/log/httpd/*access*.log',
        ];
        $pathsString = implode(' ', $logPaths);
        return "sh -c 'LOG_FILE=$(ls -1t {$pathsString} 2>/dev/null | head -n 1); if [ -n \"\$LOG_FILE\" ] && [ -f \"\$LOG_FILE\" ]; then tail -n {$lines} \"\$LOG_FILE\"; else echo \"LOG_NOT_FOUND\"; fi'";
    }
}