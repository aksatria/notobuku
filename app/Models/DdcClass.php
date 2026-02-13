<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DdcClass extends Model
{
    protected $table = 'ddc_classes';

    protected $fillable = [
        'code',
        'name',
        'normalized_name',
        'parent_id',
        'level',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function biblios(): BelongsToMany
    {
        return $this->belongsToMany(Biblio::class, 'biblio_ddc', 'ddc_class_id', 'biblio_id')
            ->withTimestamps();
    }
}
