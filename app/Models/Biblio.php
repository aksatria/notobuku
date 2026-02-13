<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Biblio extends Model
{
    protected $table = 'biblio';

    protected $fillable = [
        "institution_id",
        "title",
        "normalized_title",
        "subtitle",
        "responsibility_statement",
        "isbn",
        "issn",
        "publisher",
        "place_of_publication",
        "publish_year",
        "language",
        "material_type",
        "media_type",
        "audience",
        "is_reference",
        "frequency",
        "former_frequency",
        "serial_beginning",
        "serial_ending",
        "serial_first_issue",
        "serial_last_issue",
        "serial_source_note",
        "serial_preceding_title",
        "serial_preceding_issn",
        "serial_succeeding_title",
        "serial_succeeding_issn",
        "holdings_summary",
        "holdings_supplement",
        "holdings_index",
        "edition",
        "series_title",
        "physical_desc",
        "extent",
        "dimensions",
        "illustrations",
        "ddc",
        "call_number",
        "cover_path", // ✅ tambah
        "notes",
        "bibliography_note",
        "general_note",
        "ai_summary",
        "ai_suggested_subjects_json",
        "ai_suggested_tags_json",
        "ai_suggested_ddc",
        "ai_status",
    ];

    protected $casts = [
        'publish_year' => 'integer',
        'ai_suggested_subjects_json' => 'array',
        'ai_suggested_tags_json' => 'array',
    ];

    protected $appends = [
        'display_title',
    ];

    // ✅ TAMBAH INI untuk withCount
    protected $withCount = [
        'items',
        'availableItems as available_items_count'
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'biblio_id');
    }

    public function availableItems(): HasMany
    {
        return $this->hasMany(Item::class, 'biblio_id')->where('status', 'available');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'biblio_author', 'biblio_id', 'author_id')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderBy('biblio_author.sort_order');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'biblio_subject', 'biblio_id', 'subject_id')
            ->withPivot(['type', 'sort_order'])
            ->withTimestamps()
            ->orderBy('biblio_subject.sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'biblio_tag', 'biblio_id', 'tag_id')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderBy('biblio_tag.sort_order');
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(BiblioMetadata::class, 'biblio_id');
    }

    public function metric(): HasOne
    {
        return $this->hasOne(BiblioMetric::class, 'biblio_id');
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(BiblioIdentifier::class, 'biblio_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BiblioAttachment::class, 'biblio_id');
    }

    public function ddcClasses(): BelongsToMany
    {
        return $this->belongsToMany(DdcClass::class, 'biblio_ddc', 'biblio_id', 'ddc_class_id')
            ->withTimestamps();
    }

    public function getDisplayTitleAttribute(): string
    {
        $title = trim((string) ($this->title ?? ''));
        if ($title === '') {
            return $title;
        }

        return $this->stripDisplayQuotes($title);
    }

    private function stripDisplayQuotes(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $pairs = [
            '"' => '"',
            "'" => "'",
            '“' => '”',
            '‘' => '’',
            '«' => '»',
            '‹' => '›',
            '(' => ')',
            '[' => ']',
            '{' => '}',
        ];

        $lenFn = function (string $v): int {
            return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
        };
        $subFn = function (string $v, int $start, ?int $length = null): string {
            if (function_exists('mb_substr')) {
                return $length === null
                    ? mb_substr($v, $start, null, 'UTF-8')
                    : mb_substr($v, $start, $length, 'UTF-8');
            }
            return $length === null ? substr($v, $start) : substr($v, $start, $length);
        };

        while (true) {
            $len = $lenFn($value);
            if ($len < 2) {
                break;
            }
            $first = $subFn($value, 0, 1);
            $last = $subFn($value, $len - 1, 1);
            if (!isset($pairs[$first]) || $pairs[$first] !== $last) {
                break;
            }
            $value = $subFn($value, 1, $len - 2);
            $value = trim($value);
        }

        return $value;
    }
}
