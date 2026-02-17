<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationEvent extends Model
{
    protected $table = 'reservation_events';

    protected $fillable = [
        'institution_id',
        'reservation_id',
        'member_id',
        'biblio_id',
        'item_id',
        'actor_user_id',
        'event_type',
        'status_from',
        'status_to',
        'queue_no',
        'wait_minutes',
        'meta',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'reservation_id' => 'integer',
        'member_id' => 'integer',
        'biblio_id' => 'integer',
        'item_id' => 'integer',
        'actor_user_id' => 'integer',
        'queue_no' => 'integer',
        'wait_minutes' => 'integer',
    ];
}
