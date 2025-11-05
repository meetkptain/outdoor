<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}

