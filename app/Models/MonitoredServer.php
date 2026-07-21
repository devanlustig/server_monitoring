<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'hostname', 'authentication_method', 'ssh_port', 'ssh_username', 'ssh_password',
        'ip_address', 'environment', 'timezone', 'description', 'is_active', 'tags', 'metadata',
        'system_hostname', 'operating_system', 'kernel_version', 'cpu_model', 'cpu_core_count',
        'total_ram_bytes', 'total_disk_bytes', 'last_successful_connection_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ssh_username' => 'encrypted',
            'ssh_password' => 'encrypted',
            'tags' => 'array',
            'metadata' => 'array',
            'last_successful_connection_at' => 'immutable_datetime',
        ];
    }

    public function cpuMetrics(): HasMany
    {
        return $this->hasMany(CpuMetric::class, 'server_id');
    }
}
