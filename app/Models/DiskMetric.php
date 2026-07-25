<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiskMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [

        'server_id',

        'hostname',

        'total',

        'used',

        'available',

        'usage_percent',

        'collected_at',

    ];

    protected function casts(): array
    {
        return [

            'collected_at'=>'immutable_datetime',

            'usage_percent'=>'float',

        ];
    }
}