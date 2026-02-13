<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialIssue extends Model
{
    protected $table = 'serial_issues';

    protected $fillable = [
        'institution_id',
        'biblio_id',
        'branch_id',
        'issue_code',
        'volume',
        'issue_no',
        'published_on',
        'expected_on',
        'received_at',
        'claimed_at',
        'status',
        'claim_reference',
        'claim_notes',
        'notes',
        'received_by',
        'claimed_by',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'biblio_id' => 'integer',
        'branch_id' => 'integer',
        'published_on' => 'date',
        'expected_on' => 'date',
        'received_at' => 'datetime',
        'claimed_at' => 'datetime',
        'received_by' => 'integer',
        'claimed_by' => 'integer',
    ];

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
