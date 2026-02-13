<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $table = 'goods_receipt_lines';

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_line_id',
        'quantity_received',
    ];

    protected $casts = [
        'goods_receipt_id' => 'integer',
        'purchase_order_line_id' => 'integer',
        'quantity_received' => 'integer',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }
}
