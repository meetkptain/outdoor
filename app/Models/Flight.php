<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\GlobalTenantScope;

class Flight extends Model
{
    use HasFactory, GlobalTenantScope;

    protected $fillable = [
        'organization_id',
        'reservation_id',
        'participant_first_name',
        'participant_last_name',
        'participant_birth_date',
        'participant_weight',
        'flight_date',
        'duration_minutes',
        'max_altitude',
        'flight_notes',
        'status',
        'photo_included',
        'video_included',
        'photo_url',
        'video_url',
    ];

    protected $casts = [
        'participant_birth_date' => 'date',
        'flight_date' => 'datetime',
        'max_altitude' => 'decimal:2',
        'photo_included' => 'boolean',
        'video_included' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
