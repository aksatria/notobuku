<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'institution_id',
        'branch_id',
        'shelf_id',
        'biblio_id',

        'barcode',
        'accession_number',
        'inventory_code',

        // tambahan migrasi perpusnas-ish
        'inventory_number',
        'location_note',
        'circulation_status',
        'is_reference',
        'acquisition_source',

        'status',
        'condition', // ✅ TAMBAH KOLOM INI
        'acquired_at',
        'price',
        'source',
        'notes',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'branch_id' => 'integer',
        'shelf_id' => 'integer',
        'biblio_id' => 'integer',

        'acquired_at' => 'date',
        'price' => 'decimal:2',
        'is_reference' => 'boolean',
    ];

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class, 'shelf_id');
    }

    // ============================
    // Scopes (opsional, aman)
    // ============================

    public function scopeInBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeInInstitution($query, int $institutionId)
    {
        return $query->where('institution_id', $institutionId);
    }
}
