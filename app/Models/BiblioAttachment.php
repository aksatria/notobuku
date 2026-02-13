<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiblioAttachment extends Model
{
    protected $table = 'biblio_attachments';

    protected $fillable = [
        'biblio_id',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'visibility',
        'created_by',
    ];

    public function biblio(): BelongsTo
    {
        return $this->belongsTo(Biblio::class, 'biblio_id');
    }
}
