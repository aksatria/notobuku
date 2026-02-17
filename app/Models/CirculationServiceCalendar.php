<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CirculationServiceCalendar extends Model
{
    protected $fillable = [
        'institution_id',
        'branch_id',
        'name',
        'is_active',
        'exclude_weekends',
        'priority',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'branch_id' => 'integer',
        'is_active' => 'boolean',
        'exclude_weekends' => 'boolean',
        'priority' => 'integer',
    ];
}
