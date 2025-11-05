<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeConnectController extends Controller
{
    /**
     * Créer un compte Stripe Connect pour l'organisation
     */
    public function createAccount(Request $request): JsonResponse
    {
        $request->validate([
            'country' => 'required|string|size:2',
        ]);

        $organization = auth()->user()->currentOrganization;

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        try {
            // Créer un compte Express Connect
            $account = \Stripe\Account::create([
                'type' => 'express',
                'country' => $request->country,
                'email' => $organization->billing_email ?? $organization->users()->first()->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);

            // Mettre à jour l'organisation
            $organization->update([
                'stripe_account_id' => $account->id,
                'stripe_account_status' => $account->details_submitted ? 'active' : 'pending',
                'stripe_onboarding_completed' => $account->details_submitted,
            ]);

            // Créer le lien d'onboarding
            $onboardingLink = $this->createOnboardingLink($account->id, $request);

            return response()->json([
                'account_id' => $account->id,
                'onboarding_url' => $onboardingLink->url,
                'status' => $organization->stripe_account_status,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe Connect account creation failed', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to create Stripe account'], 500);
        }
    }

    /**
     * Créer un lien d'onboarding Stripe Connect
     */
    public function createOnboardingLink(string $accountId, Request $request): \Stripe\AccountLink
    {
        $returnUrl = $request->input('return_url', config('app.url') . '/admin/stripe/connect/return');
        $refreshUrl = $request->input('refresh_url', config('app.url') . '/admin/stripe/connect/refresh');

        return \Stripe\AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }

    /**
     * Récupérer le statut du compte Stripe Connect
     */
    public function getAccountStatus(): JsonResponse
    {
        $organization = auth()->user()->currentOrganization;

        if (!$organization || !$organization->stripe_account_id) {
            return response()->json([
                'connected' => false,
                'status' => null,
            ]);
        }

        try {
            $account = \Stripe\Account::retrieve($organization->stripe_account_id);

            // Mettre à jour le statut dans la base
            $organization->update([
                'stripe_account_status' => $account->details_submitted ? 'active' : 'pending',
                'stripe_onboarding_completed' => $account->details_submitted,
            ]);

            return response()->json([
                'connected' => true,
                'status' => $organization->stripe_account_status,
                'onboarding_completed' => $organization->stripe_onboarding_completed,
                'charges_enabled' => $account->charges_enabled ?? false,
                'payouts_enabled' => $account->payouts_enabled ?? false,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json(['error' => 'Failed to retrieve account status'], 500);
        }
    }

    /**
     * Récupérer le lien de login Stripe Connect
     */
    public function getLoginLink(): JsonResponse
    {
        $organization = auth()->user()->currentOrganization;

        if (!$organization || !$organization->stripe_account_id) {
            return response()->json(['error' => 'Stripe account not connected'], 404);
        }

        try {
            $loginLink = \Stripe\Account::createLoginLink($organization->stripe_account_id);

            return response()->json([
                'url' => $loginLink->url,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json(['error' => 'Failed to create login link'], 500);
        }
    }

    /**
     * Webhook pour les événements Stripe Connect
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = $payload['type'] ?? null;

        // Traiter les événements Stripe Connect
        switch ($eventType) {
            case 'account.updated':
                $this->handleAccountUpdated($payload['data']['object']);
                break;
            case 'account.application.deauthorized':
                $this->handleAccountDeauthorized($payload['data']['object']);
                break;
        }

        return response()->json(['received' => true]);
    }

    protected function handleAccountUpdated(array $account): void
    {
        $organization = Organization::where('stripe_account_id', $account['id'])->first();

        if ($organization) {
            $organization->update([
                'stripe_account_status' => $account['details_submitted'] ? 'active' : 'pending',
                'stripe_onboarding_completed' => $account['details_submitted'],
            ]);
        }
    }

    protected function handleAccountDeauthorized(array $account): void
    {
        $organization = Organization::where('stripe_account_id', $account['id'])->first();

        if ($organization) {
            $organization->update([
                'stripe_account_id' => null,
                'stripe_account_status' => null,
                'stripe_onboarding_completed' => false,
            ]);
        }
    }
}
