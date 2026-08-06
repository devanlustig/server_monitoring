<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'monitored_server_id',
        'category',
        'metric_name',
        'metric_value',
        'metric_unit',
        'snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_value' => 'double',
            'snapshot_at' => 'immutable_datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(MonitoredServer::class, 'monitored_server_id');
    }
}
