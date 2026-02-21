<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorCounter extends Model
{
    protected $table = 'visitor_counters';

    protected $fillable = [
        'institution_id',
        'branch_id',
        'member_id',
        'visitor_type',
        'visitor_name',
        'member_code_snapshot',
        'purpose',
        'checkin_at',
        'checkout_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'branch_id' => 'integer',
        'member_id' => 'integer',
        'created_by' => 'integer',
        'checkin_at' => 'datetime',
        'checkout_at' => 'datetime',
    ];
}
