<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopyCatalogSource extends Model
{
    protected $fillable = [
        'institution_id',
        'name',
        'protocol',
        'endpoint',
        'username',
        'password',
        'settings_json',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'settings_json' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(CopyCatalogImport::class, 'source_id');
    }
}

