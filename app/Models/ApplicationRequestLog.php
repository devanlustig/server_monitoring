<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [

        'method',
        'url',
        'route_name',
        'controller',
        'status_code',
        'response_time_ms',
        'memory_usage_mb',
        'peak_memory_mb',
        'ip_address',
        'user_id',
        'is_slow',
        'created_at',
    ];

    protected $casts = [

        'response_time_ms'=>'float',
        'memory_usage_mb'=>'float',
        'peak_memory_mb'=>'float',
        'is_slow'=>'boolean',
        'created_at'=>'datetime',

    ];
}