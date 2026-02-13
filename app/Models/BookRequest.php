<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'title',
        'author',
        'isbn',
        'reason',
        'status',
        'admin_notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id', 'id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}