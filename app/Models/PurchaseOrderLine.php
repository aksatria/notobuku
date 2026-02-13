<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id',
        'biblio_id',
        'title',
        'author_text',
        'isbn',
        'quantity',
        'unit_price',
        'line_total',
        'status',
        'received_quantity',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'biblio_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'received_quantity' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }
}
