<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CirculationServiceClosure extends Model
{
    protected $fillable = [
        'calendar_id',
        'closed_on',
        'is_recurring_yearly',
        'label',
    ];

    protected $casts = [
        'calendar_id' => 'integer',
        'closed_on' => 'date',
        'is_recurring_yearly' => 'boolean',
    ];
}
