<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPolicyRule extends Model
{
    protected $table = 'reservation_policy_rules';

    protected $fillable = [
        'institution_id',
        'branch_id',
        'member_type',
        'collection_type',
        'max_active_reservations',
        'max_queue_per_biblio',
        'hold_hours',
        'priority_weight',
        'is_enabled',
        'label',
        'notes',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'branch_id' => 'integer',
        'max_active_reservations' => 'integer',
        'max_queue_per_biblio' => 'integer',
        'hold_hours' => 'integer',
        'priority_weight' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
