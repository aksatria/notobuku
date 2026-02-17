<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTake extends Model
{
    protected $fillable = [
        'institution_id',
        'user_id',
        'branch_id',
        'shelf_id',
        'name',
        'scope_status',
        'status',
        'expected_items_count',
        'found_items_count',
        'missing_items_count',
        'unexpected_items_count',
        'scanned_items_count',
        'summary_json',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'user_id' => 'integer',
        'branch_id' => 'integer',
        'shelf_id' => 'integer',
        'expected_items_count' => 'integer',
        'found_items_count' => 'integer',
        'missing_items_count' => 'integer',
        'unexpected_items_count' => 'integer',
        'scanned_items_count' => 'integer',
        'summary_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class, 'shelf_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTakeLine::class, 'stock_take_id');
    }
}

