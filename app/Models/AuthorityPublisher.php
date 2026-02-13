<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorityPublisher extends Model
{
    protected $table = 'authority_publishers';

    protected $fillable = [
        'preferred_name',
        'normalized_name',
        'aliases',
        'external_ids',
    ];

    protected $casts = [
        'aliases' => 'array',
        'external_ids' => 'array',
    ];
}
