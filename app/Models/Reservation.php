<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use App\Traits\GlobalTenantScope;
use App\Models\Instructor;
use App\Models\ActivitySession;
use App\Models\Resource;

class Reservation extends Model
{
    use HasFactory, SoftDeletes, GlobalTenantScope;

    protected $fillable = [
        'uuid',
        'organization_id',
        'activity_id',
        'activity_type',
        'user_id',
        'client_id',
        'customer_email',
        'customer_phone',
        'customer_first_name',
        'customer_last_name',
        'customer_birth_date',
        'customer_weight',
        'customer_height',
        // 'flight_type', // ❌ Supprimé - utiliser activity_id + activity_type
        'participants_count',
        'special_requests',
        'status',
        'scheduled_at',
        'scheduled_time',
        // 'biplaceur_id', // ❌ Supprimé - utiliser instructor_id
        'instructor_id',
        'site_id',
        // 'tandem_glider_id', // ❌ Supprimé - utiliser metadata['equipment_id']
        'vehicle_id',
        'coupon_id',
        'gift_card_id',
        'coupon_code',
        'base_amount',
        'options_amount',
        'discount_amount',
        'total_amount',
        'deposit_amount',
        'authorized_amount',
        'stripe_payment_intent_id',
        'payment_status',
        'payment_type',
        'metadata',
        'internal_notes',
        'cancellation_reason',
        'reminder_sent',
        'reminder_sent_at',
        'completed_at',
    ];

    protected $casts = [
        'customer_birth_date' => 'date',
        'scheduled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'base_amount' => 'decimal:2',
        'options_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'authorized_amount' => 'decimal:2',
        'metadata' => 'array',
        'reminder_sent' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            if (empty($reservation->uuid)) {
                $reservation->uuid = (string) Str::uuid();
            }
        });
    }

    // Relations
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @deprecated Utiliser instructor() à la place
     * Cette méthode est conservée pour rétrocompatibilité
     */
    public function biplaceur()
    {
        // Pour rétrocompatibilité, retourner la relation même si elle pointe vers une table vide
        // Les anciens appels doivent utiliser $reservation->instructor() à la place
        if (class_exists(\App\Models\Biplaceur::class)) {
            return $this->belongsTo(\App\Models\Biplaceur::class);
        }
        // Si Biplaceur n'existe plus, retourner une relation vers Instructor comme fallback
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    /**
     * Relation vers l'instructeur assigné (générique)
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @deprecated Utiliser getEquipment() pour récupérer l'équipement depuis metadata
     */
    public function tandemGlider(): BelongsTo
    {
        // Récupérer depuis metadata si disponible
        $equipmentId = $this->metadata['equipment_id'] ?? $this->metadata['tandem_glider_id'] ?? null;
        if ($equipmentId) {
            return $this->belongsTo(Resource::class, $equipmentId);
        }
        // Fallback sur l'ancienne relation si elle existe encore en DB
        return $this->belongsTo(Resource::class, 'tandem_glider_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'vehicle_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    /**
     * @deprecated Utiliser activitySessions() à la place
     */
    public function flights(): HasMany
    {
        // Pour rétrocompatibilité, retourner les activitySessions correspondants
        if (class_exists(\App\Models\Flight::class)) {
            return $this->hasMany(\App\Models\Flight::class);
        }
        // Sinon, retourner une collection vide
        return $this->hasMany(ActivitySession::class)->whereRaw('1=0');
    }

    /**
     * Relation vers les sessions d'activité (générique)
     */
    public function activitySessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class);
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'reservation_options')
            ->withPivot(['quantity', 'unit_price', 'total_price', 'added_at_stage', 'added_at', 'is_delivered', 'delivered_at', 'delivery_notes'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ReservationHistory::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function signature(): HasOne
    {
        return $this->hasOne(Signature::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        // Alias pour rétrocompatibilité, utilise 'scheduled' maintenant
        return $query->where('status', 'scheduled');
    }

    public function scopeAuthorized($query)
    {
        return $query->where('status', 'authorized');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeRescheduled($query)
    {
        return $query->where('status', 'rescheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeWithPaymentAuthorized($query)
    {
        return $query->where('payment_status', 'authorized');
    }

    // Méthodes
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAssigned(): bool
    {
        return in_array($this->status, ['scheduled', 'confirmed']);
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isRescheduled(): bool
    {
        return $this->status === 'rescheduled';
    }

    public function canAddOptions(): bool
    {
        return in_array($this->status, ['pending', 'authorized', 'scheduled', 'confirmed']);
    }

    public function canCapturePayment(): bool
    {
        return in_array($this->payment_status, ['authorized', 'partially_captured']) 
            && $this->status === 'completed';
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->deposit_amount);
    }

    public function getAmountToCaptureAttribute(): float
    {
        $captured = $this->payments()
            ->whereIn('type', ['capture', 'deposit'])
            ->where('status', 'succeeded')
            ->sum('amount');

        return max(0, $this->total_amount - $captured);
    }

    public function addHistory(string $action, array $oldValues = null, array $newValues = null, string $notes = null): void
    {
        $this->history()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'notes' => $notes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Récupérer l'équipement depuis metadata
     * Remplacé tandem_glider_id qui était spécifique au paragliding
     */
    public function getEquipment(): ?Resource
    {
        $equipmentId = $this->metadata['equipment_id'] ?? $this->metadata['tandem_glider_id'] ?? null;
        if ($equipmentId) {
            return Resource::find($equipmentId);
        }
        return null;
    }

    /**
     * Définir l'équipement dans metadata
     */
    public function setEquipment(?int $equipmentId): void
    {
        $metadata = $this->metadata ?? [];
        if ($equipmentId) {
            $metadata['equipment_id'] = $equipmentId;
        } else {
            unset($metadata['equipment_id']);
        }
        $this->update(['metadata' => $metadata]);
    }
}
