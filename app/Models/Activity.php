<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\GlobalTenantScope;

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
}
