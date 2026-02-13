<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shelf extends Model
{
    use HasFactory;

    protected $table = 'shelves';

    protected $fillable = [
        'institution_id',
        'branch_id',
        'name',
        'code',
        'location',
        'notes',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'branch_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'shelf_id');
    }
}
