<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $table = 'reservations';

    protected $fillable = [
        'institution_id',
        'member_id',
        'biblio_id',
        'item_id',
        'status',
        'queue_no',
        'reserved_at',
        'expires_at',
        'fulfilled_at',
        'handled_by',
        'notes',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    // Relations (optional, tidak memaksa schema lain)
    public function member()
    {
        return $this->belongsTo(\App\Models\Member::class, 'member_id');
    }

    public function biblio()
    {
        // sebagian project pakai Biblio, sebagian pakai BiblioModel. ini aman jika modelnya ada.
        return $this->belongsTo(\App\Models\Biblio::class, 'biblio_id');
    }

    public function item()
    {
        return $this->belongsTo(\App\Models\Item::class, 'item_id');
    }

    public function handledByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'handled_by');
    }
}
