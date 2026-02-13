<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'vendors';

    protected $fillable = [
        'name',
        'normalized_name',
        'contact_json',
        'notes',
    ];

    protected $casts = [
        'contact_json' => 'array',
    ];
}
