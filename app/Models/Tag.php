<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = [
        'name',
        'normalized_name',
    ];

    public function biblios(): BelongsToMany
    {
        return $this->belongsToMany(Biblio::class, 'biblio_tag', 'tag_id', 'biblio_id')
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }
}
