<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoredServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'hostname', 'ip_address', 'environment', 'timezone', 'description',
        'is_active', 'tags', 'metadata',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tags' => 'array', 'metadata' => 'array'];
    }
}
