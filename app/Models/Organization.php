<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\OrganizationFactory;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'domain',
        'logo_url',
        'primary_color',
        'secondary_color',
        'custom_css',
        'settings',
        'features',
        'subscription_tier',
        'stripe_account_id',
        'stripe_account_status',
        'stripe_onboarding_completed',
        'stripe_customer_id',
        'subscription_id',
        'subscription_status',
        'commission_rate',
        'billing_email',
        'metadata',
    ];

    protected $casts = [
        'settings' => 'array',
        'features' => 'array',
        'metadata' => 'array',
        'stripe_onboarding_completed' => 'boolean',
        'commission_rate' => 'decimal:2',
    ];

    // Relations
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_roles')
            ->withPivot('role', 'permissions')
            ->withTimestamps()
            ->using(new class extends \Illuminate\Database\Eloquent\Relations\Pivot {
                protected $casts = [
                    'permissions' => 'array',
                ];
            });
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('subscription_status', 'active');
    }

    // Helpers
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function addFeature(string $feature): void
    {
        $features = $this->features ?? [];
        if (!in_array($feature, $features)) {
            $features[] = $feature;
            $this->update(['features' => $features]);
        }
    }

    public function removeFeature(string $feature): void
    {
        $features = $this->features ?? [];
        $this->update(['features' => array_values(array_diff($features, [$feature]))]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return OrganizationFactory::new();
    }
}
