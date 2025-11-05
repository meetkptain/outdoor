<?php

namespace App\Modules\Paragliding\Models;

use App\Models\ActivitySession;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Flight étend ActivitySession pour le module Paragliding
 * 
 * Ce modèle hérite de toutes les fonctionnalités d'ActivitySession
 * et ajoute des fonctionnalités spécifiques aux vols en parapente
 */
class Flight extends ActivitySession
{
    protected $table = 'flights';

    // Relations spécifiques au parapente
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reservation::class);
    }

    // Helpers spécifiques au parapente
    public function getParticipantFirstNameAttribute(): ?string
    {
        return $this->metadata['participant_first_name'] ?? null;
    }

    public function getParticipantLastNameAttribute(): ?string
    {
        return $this->metadata['participant_last_name'] ?? null;
    }

    public function getParticipantWeightAttribute(): ?int
    {
        return $this->metadata['participant_weight'] ?? null;
    }

    public function getMaxAltitudeAttribute(): ?float
    {
        return $this->metadata['max_altitude'] ?? null;
    }

    public function getPhotoIncludedAttribute(): bool
    {
        return $this->metadata['photo_included'] ?? false;
    }

    public function getVideoIncludedAttribute(): bool
    {
        return $this->metadata['video_included'] ?? false;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->metadata['photo_url'] ?? null;
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->metadata['video_url'] ?? null;
    }

    // Alias pour compatibilité
    public function getFlightDateAttribute(): ?\DateTime
    {
        return $this->scheduled_at;
    }

    public function setFlightDateAttribute(?\DateTime $value): void
    {
        $this->scheduled_at = $value;
    }

    // Scope pour rétrocompatibilité
    public function scopePending($query)
    {
        return $query->where('status', 'scheduled');
    }
}

