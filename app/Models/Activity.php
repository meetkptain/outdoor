<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\GlobalTenantScope;
use App\Helpers\CacheHelper;

class Activity extends Model
{
    use HasFactory, SoftDeletes, GlobalTenantScope;

    protected $fillable = [
        'organization_id',
        'activity_type',
        'name',
        'description',
        'duration_minutes',
        'max_participants',
        'min_participants',
        'pricing_config',
        'constraints_config',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'pricing_config' => 'array',
        'constraints_config' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    // Relations
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class);
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

    public function scopeOfType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    // Helpers
    public function getConstraint(string $key, $default = null)
    {
        return $this->constraints_config[$key] ?? $default;
    }

    public function hasConstraint(string $key): bool
    {
        return isset($this->constraints_config[$key]);
    }

    /**
     * Récupérer la configuration de contraintes avec cache
     * 
     * @return array
     */
    public function getCachedConstraintsConfig(): array
    {
        $cacheKey = CacheHelper::activityConfigKey($this->id) . ':constraints';
        
        return CacheHelper::remember(
            $this->organization_id,
            $cacheKey,
            3600, // 1 heure
            fn() => $this->constraints_config ?? []
        );
    }

    /**
     * Récupérer la configuration de prix avec cache
     * 
     * @return array
     */
    public function getCachedPricingConfig(): array
    {
        $cacheKey = CacheHelper::activityConfigKey($this->id) . ':pricing';
        
        return CacheHelper::remember(
            $this->organization_id,
            $cacheKey,
            3600, // 1 heure
            fn() => $this->pricing_config ?? []
        );
    }

    /**
     * Boot method pour les événements du modèle
     */
    protected static function booted(): void
    {
        // Invalider le cache lors de la mise à jour
        static::updated(function ($activity) {
            CacheHelper::invalidateActivity($activity->organization_id, $activity->id);
            CacheHelper::invalidateActivitiesList($activity->organization_id);
        });

        // Invalider le cache lors de la création
        static::created(function ($activity) {
            CacheHelper::invalidateActivitiesList($activity->organization_id);
        });

        // Invalider le cache lors de la suppression
        static::deleted(function ($activity) {
            CacheHelper::invalidateActivity($activity->organization_id, $activity->id);
            CacheHelper::invalidateActivitiesList($activity->organization_id);
        });
    }
}
