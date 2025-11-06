<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de rate limiting par tenant (organisation)
 * 
 * Permet d'isoler les limites de requêtes par organisation
 * pour éviter qu'un tenant malveillant n'affecte les autres
 */
class ThrottlePerTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1, string $keyPrefix = 'tenant'): Response
    {
        // Récupérer l'organization_id depuis le contexte
        $organizationId = $this->getOrganizationId($request);
        
        if (!$organizationId) {
            // Si pas d'organisation, utiliser l'IP comme fallback
            $key = $keyPrefix . ':' . $request->ip();
        } else {
            // Clé unique par organisation
            $key = $keyPrefix . ':org:' . $organizationId;
        }

        // Vérifier les limites via Redis/Cache
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $seconds,
            ], 429)
            ->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->timestamp,
                'Retry-After' => $seconds,
            ]);
        }

        // Incrémenter le compteur
        RateLimiter::hit($key, $decayMinutes * 60);

        // Obtenir la réponse
        $response = $next($request);

        // Ajouter les headers de rate limiting
        $remaining = max(0, $maxAttempts - RateLimiter::attempts($key));
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
        ]);
    }

    /**
     * Récupérer l'organization_id depuis différentes sources
     */
    protected function getOrganizationId(Request $request): ?int
    {
        // 1. Header HTTP (priorité)
        if ($request->hasHeader('X-Organization-ID')) {
            return (int) $request->header('X-Organization-ID');
        }

        // 2. Session
        if (session()->has('organization_id')) {
            return (int) session('organization_id');
        }

        // 3. User authentifié
        if ($request->user()) {
            $orgId = $request->user()->getCurrentOrganizationId();
            if ($orgId) {
                return (int) $orgId;
            }
        }

        // 4. Config (fallback)
        if (config('app.current_organization')) {
            return (int) config('app.current_organization');
        }

        return null;
    }
}

