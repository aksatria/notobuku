<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $table = 'members';

    protected $fillable = [
        'institution_id',
        'user_id',
        'member_code',
        'member_type',
        'full_name',
        'phone',
        'address',
        'email',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'user_id' => 'integer',
        'joined_at' => 'date',
    ];
}

