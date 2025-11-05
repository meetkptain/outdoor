<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait GlobalTenantScope
{
    /**
     * Boot the global scope for tenant isolation
     */
    protected static function bootGlobalTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            // Récupérer l'organization_id depuis différentes sources
            $organizationId = static::getCurrentOrganizationId();
            
            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }
        });
    }

    /**
     * Get the current organization ID from various sources
     */
    protected static function getCurrentOrganizationId(): ?int
    {
        // 1. Depuis l'utilisateur authentifié
        if (auth()->check()) {
            $user = auth()->user();
            
            // Si l'utilisateur a une organization courante
            if (method_exists($user, 'getCurrentOrganizationId')) {
                return $user->getCurrentOrganizationId();
            }
            
            // Sinon, essayer depuis la session
            if (session()->has('organization_id')) {
                return session('organization_id');
            }
        }
        
        // 2. Depuis le header HTTP
        if (request()->hasHeader('X-Organization-ID')) {
            return (int) request()->header('X-Organization-ID');
        }
        
        // 3. Depuis la session
        if (session()->has('organization_id')) {
            return session('organization_id');
        }
        
        return null;
    }

    /**
     * Scope a query to bypass tenant filtering (use with caution)
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}

