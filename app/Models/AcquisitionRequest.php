<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcquisitionRequest extends Model
{
    protected $table = 'acquisitions_requests';

    protected $fillable = [
        'requester_user_id',
        'source',
        'title',
        'author_text',
        'isbn',
        'notes',
        'priority',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'reject_reason',
        'branch_id',
        'estimated_price',
        'book_request_id',
        'purchase_order_id',
        'purchase_order_line_id',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'branch_id' => 'integer',
        'requester_user_id' => 'integer',
        'reviewed_by_user_id' => 'integer',
        'approved_by_user_id' => 'integer',
        'rejected_by_user_id' => 'integer',
        'estimated_price' => 'decimal:2',
        'book_request_id' => 'integer',
        'purchase_order_id' => 'integer',
        'purchase_order_line_id' => 'integer',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function bookRequest(): BelongsTo
    {
        return $this->belongsTo(BookRequest::class, 'book_request_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }
}
