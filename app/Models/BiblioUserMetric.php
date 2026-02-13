<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiblioUserMetric extends Model
{
    protected $table = 'biblio_user_metrics';

    protected $fillable = [
        'institution_id',
        'user_id',
        'biblio_id',
        'click_count',
        'borrow_count',
        'last_clicked_at',
        'last_borrowed_at',
    ];

    protected $casts = [
        'click_count' => 'integer',
        'borrow_count' => 'integer',
        'last_clicked_at' => 'datetime',
        'last_borrowed_at' => 'datetime',
    ];

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }
}
