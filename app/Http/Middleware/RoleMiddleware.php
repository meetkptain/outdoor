<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        $user = auth()->user();
        
        // Récupérer l'organisation courante
        $organizationId = $user->getCurrentOrganizationId() 
            ?? $request->header('X-Organization-ID')
            ?? session('organization_id');
        
        if (!$organizationId) {
            // Fallback: utiliser le rôle global si disponible (compatibilité)
            if (property_exists($user, 'role') && in_array($user->role, $roles)) {
                return $next($request);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Organisation non définie',
            ], 403);
        }

        // Vérifier le rôle dans l'organisation
        $organization = \App\Models\Organization::find($organizationId);
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organisation non trouvée',
            ], 404);
        }
        
        $userRole = $user->getRoleInOrganization($organization);
        
        if (!$userRole || !in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        return $next($request);
    }
}

