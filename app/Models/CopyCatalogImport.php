<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyCatalogImport extends Model
{
    protected $fillable = [
        'institution_id',
        'user_id',
        'source_id',
        'biblio_id',
        'external_id',
        'title',
        'status',
        'error_message',
        'raw_json',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'user_id' => 'integer',
        'source_id' => 'integer',
        'biblio_id' => 'integer',
        'raw_json' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(CopyCatalogSource::class, 'source_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }
}

