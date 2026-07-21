<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpuMetric extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = [
        'server_id', 'hostname', 'usage_percent', 'load_1', 'load_5', 'load_15',
        'core_count', 'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_percent' => 'decimal:2',
            'load_1' => 'decimal:2',
            'load_5' => 'decimal:2',
            'load_15' => 'decimal:2',
            'collected_at' => 'immutable_datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(MonitoredServer::class);
    }
}
