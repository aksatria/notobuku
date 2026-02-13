<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutocompleteTelemetry extends Model
{
    protected $table = 'autocomplete_telemetry';

    protected $fillable = [
        'user_id',
        'institution_id',
        'field',
        'path',
        'count',
        'day',
    ];

    protected $casts = [
        'day' => 'date',
    ];
}
