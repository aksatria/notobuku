<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeLine extends Model
{
    protected $fillable = [
        'stock_take_id',
        'item_id',
        'barcode',
        'expected',
        'found',
        'scan_status',
        'status_snapshot',
        'condition_snapshot',
        'title_snapshot',
        'notes',
        'scanned_at',
    ];

    protected $casts = [
        'stock_take_id' => 'integer',
        'item_id' => 'integer',
        'expected' => 'boolean',
        'found' => 'boolean',
        'scanned_at' => 'datetime',
    ];

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class, 'stock_take_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}

