<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiskFileSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'server_id',
        'directory_path',
        'file_path',
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
