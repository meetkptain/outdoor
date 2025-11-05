<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Liste des notifications pour l'utilisateur authentifié
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        $query = Notification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('recipient', $user->email);
        });

        // Filtrer par type
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filtrer par statut
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filtrer par réservation
        if ($request->has('reservation_id')) {
            $query->where('reservation_id', $request->get('reservation_id'));
        }

        // Filtrer non lues (vérifier si le champ read_at existe)
        if ($request->boolean('unread_only')) {
            // Utiliser le statut 'sent' pour identifier les notifications non lues
            // Ou créer un champ read_at si nécessaire
            $query->where('status', 'sent');
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Détails d'une notification
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        $notification = Notification::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('recipient', $user->email);
            })
            ->firstOrFail();

        // Marquer comme lue (mettre à jour le statut si nécessaire)
        // Note: Pour une vraie fonctionnalité "lue", il faudrait ajouter un champ read_at

        return response()->json([
            'success' => true,
            'data' => $notification,
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        $notification = Notification::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('recipient', $user->email);
            })
            ->firstOrFail();

        // Mettre à jour le statut pour indiquer que c'est lu
        // Note: Pour une vraie fonctionnalité "lue", il faudrait ajouter un champ read_at
        // Pour l'instant, on peut utiliser metadata ou un autre champ
        $metadata = $notification->metadata ?? [];
        $metadata['read_at'] = now()->toISOString();
        $notification->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue',
            'data' => $notification->fresh(),
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        // Marquer toutes comme lues via metadata
        $notifications = Notification::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('recipient', $user->email);
            })
            ->get();

        $count = 0;
        foreach ($notifications as $notification) {
            $metadata = $notification->metadata ?? [];
            if (!isset($metadata['read_at'])) {
                $metadata['read_at'] = now()->toISOString();
                $notification->update(['metadata' => $metadata]);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marquées comme lues",
            'count' => $count,
        ]);
    }

    /**
     * Compter les notifications non lues
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        // Compter les non lues via metadata
        $notifications = Notification::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('recipient', $user->email);
            })
            ->get();

        $count = 0;
        foreach ($notifications as $notification) {
            $metadata = $notification->metadata ?? [];
            if (!isset($metadata['read_at'])) {
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }
}
