<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarcPolicySet extends Model
{
    protected $table = 'marc_policy_sets';

    protected $fillable = [
        'institution_id',
        'name',
        'version',
        'status',
        'payload_json',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'approved_at' => 'datetime',
    ];
}
