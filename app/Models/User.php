<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relations
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_roles')
            ->withPivot('role', 'permissions')
            ->withTimestamps()
            ->using(new class extends \Illuminate\Database\Eloquent\Relations\Pivot {
                protected $casts = [
                    'permissions' => 'array',
                ];
            });
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function biplaceur(): HasOne
    {
        return $this->hasOne(Biplaceur::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // Scopes
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeBiplaceurs($query)
    {
        return $query->where('role', 'biplaceur');
    }

    public function scopeClients($query)
    {
        return $query->where('role', 'client');
    }

    // Helpers
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBiplaceur(): bool
    {
        return $this->role === 'biplaceur';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    // Multi-tenant helpers
    protected $currentOrganizationId = null;

    public function setCurrentOrganization(?Organization $organization): void
    {
        if ($organization && $this->organizations->contains($organization->id)) {
            $this->currentOrganizationId = $organization->id;
            session(['organization_id' => $organization->id]);
        }
    }

    public function getCurrentOrganizationId(): ?int
    {
        if ($this->currentOrganizationId !== null) {
            return $this->currentOrganizationId;
        }

        if (session()->has('organization_id')) {
            return session('organization_id');
        }

        // Par défaut, retourner la première organisation de l'utilisateur
        $firstOrg = $this->organizations()->first();
        if ($firstOrg) {
            session(['organization_id' => $firstOrg->id]);
            return $firstOrg->id;
        }

        return null;
    }

    public function getCurrentOrganization(): ?Organization
    {
        $orgId = $this->getCurrentOrganizationId();
        if ($orgId) {
            return $this->organizations()->find($orgId);
        }
        return null;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations->contains($organization->id);
    }

    public function getRoleInOrganization(Organization $organization): ?string
    {
        $pivot = $this->organizations()->where('organization_id', $organization->id)->first()?->pivot;
        return $pivot->role ?? null;
    }
}

