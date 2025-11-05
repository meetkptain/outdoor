<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\GlobalTenantScope;

class Instructor extends Model
{
    use HasFactory, SoftDeletes, GlobalTenantScope;

    protected $fillable = [
        'organization_id',
        'user_id',
        'activity_types',
        'license_number',
        'certifications',
        'experience_years',
        'availability',
        'max_sessions_per_day',
        'can_accept_instant_bookings',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'activity_types' => 'array',
        'certifications' => 'array',
        'availability' => 'array',
        'can_accept_instant_bookings' => 'boolean',
        'is_active' => 'boolean',
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

    public function sessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForActivityType($query, string $activityType)
    {
        return $query->whereJsonContains('activity_types', $activityType);
    }

    public function scopeCanAcceptInstantBookings($query)
    {
        return $query->where('can_accept_instant_bookings', true);
    }

    // Helpers
    public function canTeachActivity(string $activityType): bool
    {
        return in_array($activityType, $this->activity_types ?? []);
    }

    public function addActivityType(string $activityType): void
    {
        $types = $this->activity_types ?? [];
        if (!in_array($activityType, $types)) {
            $types[] = $activityType;
            $this->update(['activity_types' => $types]);
        }
    }

    public function removeActivityType(string $activityType): void
    {
        $types = $this->activity_types ?? [];
        $this->update(['activity_types' => array_values(array_diff($types, [$activityType]))]);
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

    public function getSessionsToday()
    {
        return $this->sessions()
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'completed'])
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getCalendarSessions(string $startDate, string $endDate)
    {
        return $this->sessions()
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereIn('status', ['scheduled', 'completed'])
            ->orderBy('scheduled_at')
            ->get();
    }
}
