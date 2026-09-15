<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostgresqlDatabaseSizeSnapshot extends Model
{
    public $timestamps = false;
    protected $table = 'postgresql_database_size_snapshots';

    protected $fillable = [
        'server_id',
        'database_oid',
        'database_name',
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
