<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_number',
        'vendor_id',
        'branch_id',
        'status',
        'currency',
        'total_amount',
        'ordered_at',
        'received_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'vendor_id' => 'integer',
        'total_amount' => 'decimal:2',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'purchase_order_id');
    }
}
