<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorityAuthor extends Model
{
    protected $table = 'authority_authors';

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
