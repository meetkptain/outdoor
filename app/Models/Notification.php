<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'user_id',
        'type',
        'template',
        'recipient',
        'subject',
        'content',
        'status',
        'sent_at',
        'error_message',
        'message_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
