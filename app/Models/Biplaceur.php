<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\GlobalTenantScope;

class Biplaceur extends Model
{
    use HasFactory, GlobalTenantScope;

    protected $fillable = [
        'organization_id',
        'user_id',
        'license_number',
        'certifications',
        'experience_years',
        'total_flights',
        'max_flights_per_day',
        'availability',
        'is_active',
        'can_tap_to_pay',
        'stripe_terminal_location_id',
    ];

    protected $casts = [
        'certifications' => 'array',
        'availability' => 'array',
        'experience_years' => 'integer',
        'total_flights' => 'integer',
        'is_active' => 'boolean',
        'can_tap_to_pay' => 'boolean',
    ];

    // Relations
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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

    public function scopeCanTapToPay($query)
    {
        return $query->where('can_tap_to_pay', true);
    }

    // Helpers
    public function incrementFlights(): void
    {
        $this->increment('total_flights');
    }

    public function isAvailableOn(string $date, string $time = null): bool
    {
        if (!$this->availability) {
            return false;
        }

        $dayOfWeek = date('N', strtotime($date)); // 1-7 (Monday-Sunday)
        $availableDays = $this->availability['days'] ?? [];

        if (!in_array($dayOfWeek, $availableDays)) {
            return false;
        }

        // Vérifier les exceptions (dates bloquées)
        $exceptions = $this->availability['exceptions'] ?? [];
        if (in_array($date, $exceptions)) {
            return false;
        }

        // Si time spécifié, vérifier les heures disponibles
        if ($time) {
            $hour = (int) date('G', strtotime($time));
            $availableHours = $this->availability['hours'] ?? [];
            if (!empty($availableHours) && !in_array($hour, $availableHours)) {
                return false;
            }
        }

        return true;
    }

    public function getFlightsToday()
    {
        return $this->reservations()
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getCalendarFlights(string $startDate, string $endDate)
    {
        return $this->reservations()
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereIn('status', ['scheduled', 'confirmed', 'completed'])
            ->orderBy('scheduled_at')
            ->get();
    }
}

