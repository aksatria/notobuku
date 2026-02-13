<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    protected $table = 'authors';

    protected $fillable = [
        'name',
        'normalized_name',
        'birth_year',
        'death_year',
        'notes',
    ];

    protected $casts = [
        'birth_year' => 'integer',
        'death_year' => 'integer',
    ];

    public function biblios(): BelongsToMany
    {
        return $this->belongsToMany(Biblio::class, 'biblio_author', 'author_id', 'biblio_id')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }
}
