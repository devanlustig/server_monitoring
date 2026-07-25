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
        'total_ram_bytes', 'total_disk_bytes', 'last_successful_connection_at', 'is_online',
        'last_checked_at','last_error','postgres_port',
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
            'is_online' => 'boolean',
            'last_checked_at' => 'immutable_datetime',
        ];
    }

    public function cpuMetrics(): HasMany
    {
        return $this->hasMany(
            CpuMetric::class,
            'server_id',
            'id'
        );
    }

    public function memoryMetrics(): HasMany
    {
        return $this->hasMany(
            MemoryMetric::class,
            'server_id',
            'id'
        );
    }

    public function diskMetrics(): HasMany
    {
        return $this->hasMany(
            DiskMetric::class,
            'server_id'
        );
    }
}
