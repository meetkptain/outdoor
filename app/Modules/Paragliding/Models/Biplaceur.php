<?php

namespace App\Modules\Paragliding\Models;

use App\Models\Instructor;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Biplaceur étend Instructor pour le module Paragliding
 * 
 * Ce modèle hérite de toutes les fonctionnalités d'Instructor
 * et ajoute des fonctionnalités spécifiques au parapente
 */
class Biplaceur extends Instructor
{
    protected $table = 'biplaceurs';

    // Relations spécifiques au parapente
    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class, 'instructor_id', 'instructor_id')
            ->whereHas('activity', function ($query) {
                $query->where('activity_type', 'paragliding');
            });
    }

    // Helpers spécifiques au parapente
    public function getTotalFlights(): int
    {
        return $this->metadata['total_flights'] ?? 0;
    }

    public function canTapToPay(): bool
    {
        return $this->metadata['can_tap_to_pay'] ?? false;
    }

    public function getStripeTerminalLocationId(): ?string
    {
        return $this->metadata['stripe_terminal_location_id'] ?? null;
    }

    public function incrementFlights(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['total_flights'] = ($metadata['total_flights'] ?? 0) + 1;
        $this->update(['metadata' => $metadata]);
    }

    // Alias pour compatibilité avec le code existant
    public function getMaxFlightsPerDayAttribute(): ?int
    {
        return $this->max_sessions_per_day;
    }

    public function setMaxFlightsPerDayAttribute(?int $value): void
    {
        $this->max_sessions_per_day = $value;
    }

    // Scope pour rétrocompatibilité
    public function scopeCanTapToPay($query)
    {
        return $query->whereJsonContains('metadata->can_tap_to_pay', true);
    }
}

