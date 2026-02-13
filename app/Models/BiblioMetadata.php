<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiblioMetadata extends Model
{
    protected $table = 'biblio_metadata';

    protected $fillable = [
        'biblio_id',
        'dublin_core_json',
        'dublin_core_i18n_json',
        'marc_core_json',
        'global_identifiers_json',
    ];

    protected $casts = [
        'dublin_core_json' => 'array',
        'dublin_core_i18n_json' => 'array',
        'marc_core_json' => 'array',
        'global_identifiers_json' => 'array',
    ];

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }
}
