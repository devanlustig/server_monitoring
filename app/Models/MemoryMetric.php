<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [

        'server_id',

        'total',

        'used',

        'free',

        'shared',

        'cache',

        'available',

        'usage_percent',

        'collected_at',

    ];

    protected function casts(): array
    {
        return [

            'collected_at'=>'immutable_datetime',

        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(
            MonitoredServer::class,
            'server_id',
            'id'
        );
    }
}