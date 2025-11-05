<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->resolveOrganization($request);

        if ($organization) {
            // Définir l'organisation dans la session
            session(['organization_id' => $organization->id]);
            
            // Si l'utilisateur est authentifié, définir l'organisation courante
            if (auth()->check() && auth()->user()->belongsToOrganization($organization)) {
                auth()->user()->setCurrentOrganization($organization);
            }

            // Stocker dans la config pour utilisation globale
            config(['app.current_organization' => $organization]);
        }

        return $next($request);
    }

    /**
     * Résoudre l'organisation depuis différentes sources
     */
    protected function resolveOrganization(Request $request): ?Organization
    {
        // 1. Depuis le header HTTP (priorité haute pour API)
        if ($request->hasHeader('X-Organization-ID')) {
            return Organization::find($request->header('X-Organization-ID'));
        }

        // 2. Depuis le sous-domaine (ex: club1.platform.com)
        $host = $request->getHost();
        $hostParts = explode('.', $host);
        
        // Si on a au moins 3 parties (sous-domaine.domain.extension)
        if (count($hostParts) >= 3) {
            $subdomain = $hostParts[0];
            
            // Ignorer les sous-domaines système
            if (!in_array($subdomain, ['www', 'api', 'admin', 'app'])) {
                $organization = Organization::where('slug', $subdomain)->first();
                if ($organization) {
                    return $organization;
                }
            }
        }

        // 3. Depuis le domaine personnalisé (ex: club1.com)
        $organization = Organization::where('domain', $host)->first();
        if ($organization) {
            return $organization;
        }

        // 4. Depuis la session (si déjà défini)
        if (session()->has('organization_id')) {
            return Organization::find(session('organization_id'));
        }

        // 5. Si utilisateur authentifié, utiliser sa première organisation
        if (auth()->check()) {
            $user = auth()->user();
            $firstOrg = $user->organizations()->first();
            if ($firstOrg) {
                return $firstOrg;
            }
        }

        return null;
    }
}
