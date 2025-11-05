<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'reported_by',
        'reason',
        'reason_details',
        'original_date',
        'new_date',
        'is_resolved',
    ];

    protected $casts = [
        'original_date' => 'datetime',
        'new_date' => 'datetime',
        'is_resolved' => 'boolean',
    ];

    // Relations
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    // Helpers
    public function resolve(string $newDate = null): void
    {
        $this->update([
            'is_resolved' => true,
            'new_date' => $newDate ? now()->parse($newDate) : null,
        ]);

        if ($this->reservation && $newDate) {
            $this->reservation->update([
                'status' => 'rescheduled',
                'scheduled_at' => $newDate,
            ]);
        }
    }
}

