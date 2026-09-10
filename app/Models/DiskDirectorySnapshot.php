<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiskDirectorySnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'server_id',
        'path',
        'size_bytes',
        'snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_at' => 'immutable_datetime',
            'size_bytes' => 'integer',
        ];
    }
}
