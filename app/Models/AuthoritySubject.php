<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthoritySubject extends Model
{
    protected $table = 'authority_subjects';

    protected $fillable = [
        'preferred_term',
        'normalized_term',
        'scheme',
        'parent_id',
        'aliases',
        'external_ids',
    ];

    protected $casts = [
        'aliases' => 'array',
        'external_ids' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
