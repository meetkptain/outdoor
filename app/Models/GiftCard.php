<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GiftCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'code',
        'recipient_email',
        'recipient_name',
        'message',
        'initial_amount',
        'remaining_amount',
        'currency',
        'purchaser_id',
        'purchaser_email',
        'stripe_payment_intent_id',
        'valid_from',
        'valid_until',
        'validity_days',
        'status',
        'usage_history',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'usage_history' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($giftCard) {
            if (empty($giftCard->uuid)) {
                $giftCard->uuid = (string) Str::uuid();
            }
            
            if (empty($giftCard->code)) {
                $giftCard->code = strtoupper(Str::random(12));
            }

            if ($giftCard->validity_days && !$giftCard->valid_until) {
                $giftCard->valid_until = Carbon::now()->addDays($giftCard->validity_days);
            }
        });
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isValid(): bool
    {
        if (!in_array($this->status, ['active', 'partially_used'])) {
            return false;
        }

        $now = Carbon::now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        return $this->remaining_amount > 0;
    }

    public function use(float $amount, int $reservationId): bool
    {
        if (!$this->isValid() || $amount > $this->remaining_amount) {
            return false;
        }

        $this->remaining_amount -= $amount;

        if ($this->remaining_amount <= 0) {
            $this->status = 'used';
        } elseif ($this->status === 'active') {
            $this->status = 'partially_used';
        }

        $history = $this->usage_history ?? [];
        $history[] = [
            'reservation_id' => $reservationId,
            'amount' => $amount,
            'used_at' => Carbon::now()->toDateTimeString(),
            'remaining' => $this->remaining_amount,
        ];
        $this->usage_history = $history;

        return $this->save();
    }
}
