<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarcSetting extends Model
{
    protected $table = 'marc_settings';

    protected $fillable = [
        'key',
        'value_json',
    ];

    protected $casts = [
        'value_json' => 'array',
    ];
}
