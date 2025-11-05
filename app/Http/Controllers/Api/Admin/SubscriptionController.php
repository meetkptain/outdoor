<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Récupérer l'abonnement actuel
     */
    public function current(): JsonResponse
    {
        $organization = auth()->user()->currentOrganization;

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        $subscription = $this->subscriptionService->getSubscription($organization);
        $limits = $this->subscriptionService->checkLimits($organization);

        return response()->json([
            'subscription' => $subscription,
            'limits' => $limits,
        ]);
    }

    /**
     * Créer un abonnement
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'tier' => 'required|string|in:free,starter,pro,enterprise',
            'stripe_price_id' => 'nullable|string',
        ]);

        $organization = auth()->user()->currentOrganization;

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        try {
            $subscription = $this->subscriptionService->createSubscription(
                $organization,
                $request->tier,
                $request->stripe_price_id
            );

            return response()->json([
                'subscription' => $subscription,
                'limits' => $this->subscriptionService->checkLimits($organization),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Annuler un abonnement
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'immediately' => 'boolean',
        ]);

        $organization = auth()->user()->currentOrganization;

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        try {
            $subscription = $this->subscriptionService->cancelSubscription(
                $organization,
                $request->boolean('immediately', false)
            );

            return response()->json([
                'subscription' => $subscription,
                'message' => 'Subscription cancelled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Lister les abonnements
     */
    public function index(): JsonResponse
    {
        $organization = auth()->user()->currentOrganization;

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        $subscriptions = $organization->subscriptions()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'subscriptions' => $subscriptions,
            'available_tiers' => $this->subscriptionService->getAvailableTiers(),
        ]);
    }
}
