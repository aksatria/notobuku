<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiblioIdentifier extends Model
{
    protected $table = 'biblio_identifiers';

    protected $fillable = [
        'biblio_id',
        'scheme',
        'value',
        'normalized_value',
        'uri',
    ];

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }
}
