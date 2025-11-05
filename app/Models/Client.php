<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'weight',
        'height',
        'medical_notes',
        'notes',
        'total_flights',
        'total_spent',
        'last_flight_date',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'integer',
        'height' => 'integer',
        'total_flights' => 'integer',
        'total_spent' => 'decimal:2',
        'last_flight_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function incrementFlights(): void
    {
        $this->increment('total_flights');
        $this->update(['last_flight_date' => now()]);
    }

    public function addToTotalSpent(float $amount): void
    {
        $this->increment('total_spent', $amount);
    }
}

