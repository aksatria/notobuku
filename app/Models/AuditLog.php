<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'format',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
