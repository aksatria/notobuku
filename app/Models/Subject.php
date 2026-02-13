<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $fillable = [
        'name',
        'term',
        'normalized_term',
        'scheme',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (Subject $s) {
            if ((empty($s->name) || trim((string)$s->name) === '') && !empty($s->term)) {
                $s->name = $s->term;
            }

            if ((empty($s->normalized_term) || trim((string)$s->normalized_term) === '') && !empty($s->term)) {
                $s->normalized_term = Str::of($s->term)->lower()->trim()->squish()->toString();
            }

            if (empty($s->scheme)) {
                $s->scheme = 'local';
            }
        });
    }

    public function biblios(): BelongsToMany
    {
        return $this->belongsToMany(Biblio::class, 'biblio_subject', 'subject_id', 'biblio_id')
            ->withPivot(['type', 'sort_order'])
            ->withTimestamps();
    }
}
