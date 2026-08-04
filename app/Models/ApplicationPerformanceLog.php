<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationPerformanceLog extends Model
{
    protected $fillable = [

        'application',
        'environment',
        'server_name',
        'method',
        'endpoint',
        'route_name',
        'status_code',
        'response_time_ms',
        'memory_usage_mb',
        'peak_memory_mb',
        'ip_address',
        'request_id',
        'extra',
        'requested_at',

    ];

    protected $casts = [

        'extra'=>'array',
        'requested_at'=>'datetime',

    ];
}