<?php

namespace App\Modules\Surfing\Models;

use App\Models\Instructor;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SurfingInstructor étend Instructor pour le module Surfing
 * 
 * Ce modèle hérite de toutes les fonctionnalités d'Instructor
 * et ajoute des fonctionnalités spécifiques au surf
 */
class SurfingInstructor extends Instructor
{
    protected $table = 'instructors';

    // Relations spécifiques au surf
    public function surfingSessions(): HasMany
    {
        return $this->hasMany(SurfingSession::class, 'instructor_id', 'id')
            ->whereHas('activity', function ($query) {
                $query->where('activity_type', 'surfing');
            });
    }

    // Helpers spécifiques au surf
    public function getCertifications(): array
    {
        if (is_array($this->certifications)) {
            return $this->certifications;
        }
        return json_decode($this->certifications ?? '[]', true) ?? [];
    }

    public function hasSurfingCertification(): bool
    {
        $certifications = $this->getCertifications();
        return in_array('ISA', $certifications) || in_array('Federation', $certifications);
    }

    // Scope pour rétrocompatibilité
    public function scopeSurfing($query)
    {
        return $query->forActivityType('surfing');
    }
}

