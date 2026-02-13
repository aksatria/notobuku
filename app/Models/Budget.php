<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $table = 'budgets';

    protected $fillable = [
        'year',
        'branch_id',
        'amount',
        'spent',
        'meta_json',
    ];

    protected $casts = [
        'year' => 'integer',
        'branch_id' => 'integer',
        'amount' => 'decimal:2',
        'spent' => 'decimal:2',
        'meta_json' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
