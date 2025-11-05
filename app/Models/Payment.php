<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_refund_id',
        'type',
        'amount',
        'refunded_amount',
        'currency',
        'status',
        'payment_method_type',
        'payment_method_id',
        'last4',
        'brand',
        'stripe_data',
        'metadata',
        'failure_reason',
        'refund_reason',
        'authorized_at',
        'captured_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'stripe_data' => 'array',
        'metadata' => 'array',
        'authorized_at' => 'datetime',
        'captured_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isAuthorized(): bool
    {
        return in_array($this->status, ['requires_capture', 'succeeded']);
    }

    public function canBeCaptured(): bool
    {
        return $this->status === 'requires_capture';
    }

    public function canBeRefunded(): bool
    {
        return $this->status === 'succeeded' && $this->refunded_amount < $this->amount;
    }
}
