<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\GlobalTenantScope;

class Site extends Model
{
    use HasFactory, GlobalTenantScope;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'location',
        'latitude',
        'longitude',
        'altitude',
        'difficulty_level',
        'orientation',
        'wind_conditions',
        'landing_zone_info',
        'is_active',
        'seasonal_availability',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'seasonal_availability' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
