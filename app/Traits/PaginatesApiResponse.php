<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * Trait pour standardiser les réponses paginées de l'API
 * 
 * Fournit un format cohérent pour toutes les réponses paginées :
 * {
 *   "success": true,
 *   "data": [...],
 *   "pagination": {
 *     "current_page": 1,
 *     "per_page": 15,
 *     "total": 100,
 *     "last_page": 7,
 *     "from": 1,
 *     "to": 15
 *   }
 * }
 */
trait PaginatesApiResponse
{
    /**
     * Retourner une réponse paginée standardisée
     * 
     * @param LengthAwarePaginator|Paginator $paginator
     * @param int $statusCode Code de statut HTTP
     * @return JsonResponse
     */
    protected function paginatedResponse($paginator, int $statusCode = 200): JsonResponse
    {
        // Si c'est déjà un paginator Laravel
        if ($paginator instanceof LengthAwarePaginator || $paginator instanceof Paginator) {
            return response()->json([
                'success' => true,
                'data' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => method_exists($paginator, 'total') ? $paginator->total() : null,
                    'last_page' => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : null,
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ], $statusCode);
        }

        // Si c'est une Collection, on peut la paginer manuellement
        if ($paginator instanceof Collection) {
            return $this->paginateCollection($paginator);
        }

        // Fallback : retourner tel quel
        return response()->json([
            'success' => true,
            'data' => $paginator,
        ], $statusCode);
    }

    /**
     * Paginer une Collection manuellement
     * 
     * @param Collection $collection
     * @param int $perPage Nombre d'éléments par page
     * @param int $page Page actuelle
     * @return JsonResponse
     */
    protected function paginateCollection(Collection $collection, ?int $perPage = null, ?int $page = null): JsonResponse
    {
        $perPage = $perPage ?? request()->get('per_page', 15);
        $page = $page ?? request()->get('page', 1);
        
        $total = $collection->count();
        $offset = ($page - 1) * $perPage;
        $items = $collection->slice($offset, $perPage)->values();
        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => min($offset + $perPage, $total),
                'has_more_pages' => $page < $lastPage,
            ],
        ]);
    }

    /**
     * Helper pour obtenir les paramètres de pagination depuis la requête
     * 
     * @param \Illuminate\Http\Request $request
     * @param int $defaultPerPage Nombre d'éléments par page par défaut
     * @param int $maxPerPage Nombre maximum d'éléments par page
     * @return array ['page' => int, 'per_page' => int]
     */
    protected function getPaginationParams(\Illuminate\Http\Request $request, int $defaultPerPage = 15, int $maxPerPage = 100): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min($maxPerPage, max(1, (int) $request->get('per_page', $defaultPerPage)));

        return [
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Créer un paginator depuis une query builder
     * 
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param int $defaultPerPage Nombre d'éléments par page par défaut
     * @return LengthAwarePaginator
     */
    protected function paginateQuery($query, \Illuminate\Http\Request $request, int $defaultPerPage = 15): LengthAwarePaginator
    {
        $params = $this->getPaginationParams($request, $defaultPerPage);
        return $query->paginate($params['per_page'], ['*'], 'page', $params['page']);
    }
}

