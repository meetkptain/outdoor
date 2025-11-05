<?php

namespace App\Modules\Surfing\Models;

use App\Models\ActivitySession;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SurfingSession étend ActivitySession pour le module Surfing
 * 
 * Ce modèle hérite de toutes les fonctionnalités d'ActivitySession
 * et ajoute des fonctionnalités spécifiques aux sessions de surf
 */
class SurfingSession extends ActivitySession
{
    protected $table = 'activity_sessions';

    // Relations spécifiques au surf
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reservation::class);
    }

    // Helpers spécifiques au surf
    public function getEquipmentRentedAttribute(): array
    {
        $metadata = is_array($this->metadata) 
            ? $this->metadata 
            : (json_decode($this->metadata ?? '{}', true) ?? []);
        return $metadata['equipment_rented'] ?? [];
    }

    public function getTideLevelAttribute(): ?string
    {
        $metadata = is_array($this->metadata) 
            ? $this->metadata 
            : (json_decode($this->metadata ?? '{}', true) ?? []);
        return $metadata['tide_level'] ?? null;
    }

    public function getWaveHeightAttribute(): ?float
    {
        $metadata = is_array($this->metadata) 
            ? $this->metadata 
            : (json_decode($this->metadata ?? '{}', true) ?? []);
        return $metadata['wave_height'] ?? null;
    }

    public function getWaterTemperatureAttribute(): ?float
    {
        return $this->metadata['water_temperature'] ?? null;
    }

    public function getParticipantLevelAttribute(): ?string
    {
        return $this->metadata['participant_level'] ?? null;
    }

    // Méthodes pour gérer l'équipement
    public function setEquipmentRented(array $equipment): void
    {
        $metadata = is_array($this->metadata) 
            ? $this->metadata 
            : (json_decode($this->metadata, true) ?? []);
        $metadata['equipment_rented'] = $equipment;
        $this->update(['metadata' => $metadata]);
    }

    public function addEquipment(string $equipment): void
    {
        $equipmentRented = $this->getEquipmentRentedAttribute();
        if (!in_array($equipment, $equipmentRented)) {
            $equipmentRented[] = $equipment;
            $this->setEquipmentRented($equipmentRented);
        }
    }

    // Scope pour rétrocompatibilité
    public function scopeSurfing($query)
    {
        return $query->whereHas('activity', function ($query) {
            $query->where('activity_type', 'surfing');
        });
    }
}

