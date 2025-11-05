<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Tiers d'abonnement disponibles
     */
    protected array $tiers = [
        'free' => [
            'max_activities' => 1,
            'max_reservations_per_month' => 50,
            'max_sites' => 1,
            'features' => [],
        ],
        'starter' => [
            'max_activities' => 3,
            'max_reservations_per_month' => 500,
            'max_sites' => 1,
            'features' => ['api_access', 'basic_analytics'],
        ],
        'pro' => [
            'max_activities' => null, // illimité
            'max_reservations_per_month' => null, // illimité
            'max_sites' => null, // illimité
            'features' => ['api_access', 'advanced_analytics', 'custom_branding', 'priority_support'],
        ],
        'enterprise' => [
            'max_activities' => null,
            'max_reservations_per_month' => null,
            'max_sites' => null,
            'features' => ['api_access', 'advanced_analytics', 'custom_branding', 'priority_support', 'dedicated_support', 'sla'],
        ],
    ];

    /**
     * Créer un abonnement pour une organisation
     */
    public function createSubscription(Organization $organization, string $tier, ?string $stripePriceId = null): Subscription
    {
        if (!isset($this->tiers[$tier])) {
            throw new \InvalidArgumentException("Invalid tier: {$tier}");
        }

        $tierConfig = $this->tiers[$tier];

        // Si Stripe est configuré, créer l'abonnement Stripe
        $stripeSubscriptionId = null;
        if ($stripePriceId && config('services.stripe.secret')) {
            try {
                $stripeSubscription = $this->createStripeSubscription($organization, $stripePriceId);
                $stripeSubscriptionId = $stripeSubscription->id;
            } catch (\Exception $e) {
                Log::error('Failed to create Stripe subscription', [
                    'organization_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);
                // Continuer sans Stripe subscription pour le moment
            }
        }

        // Créer ou mettre à jour l'abonnement
        $subscription = Subscription::updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_price_id' => $stripePriceId,
                'tier' => $tier,
                'status' => 'active',
                'features' => $tierConfig['features'],
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]
        );

        // Mettre à jour l'organisation
        $organization->update([
            'subscription_tier' => $tier,
            'subscription_id' => $stripeSubscriptionId,
            'subscription_status' => 'active',
        ]);

        return $subscription;
    }

    /**
     * Créer un abonnement Stripe
     */
    protected function createStripeSubscription(Organization $organization, string $priceId): \Stripe\Subscription
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        // Créer ou récupérer le customer Stripe
        $customerId = $organization->stripe_customer_id;
        if (!$customerId) {
            $customer = $stripe->customers->create([
                'email' => $organization->billing_email ?? $organization->users()->first()->email,
                'metadata' => [
                    'organization_id' => $organization->id,
                ],
            ]);
            $customerId = $customer->id;
            $organization->update(['stripe_customer_id' => $customerId]);
        }

        // Créer l'abonnement
        $subscription = $stripe->subscriptions->create([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'metadata' => [
                'organization_id' => $organization->id,
            ],
        ]);

        return $subscription;
    }

    /**
     * Annuler un abonnement
     */
    public function cancelSubscription(Organization $organization, bool $immediately = false): Subscription
    {
        $subscription = $organization->subscription;

        if (!$subscription) {
            throw new \Exception('No active subscription found');
        }

        // Annuler l'abonnement Stripe si nécessaire
        if ($subscription->stripe_subscription_id && config('services.stripe.secret')) {
            try {
                $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
                
                if ($immediately) {
                    $stripe->subscriptions->cancel($subscription->stripe_subscription_id);
                } else {
                    $stripe->subscriptions->update($subscription->stripe_subscription_id, [
                        'cancel_at_period_end' => true,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to cancel Stripe subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mettre à jour l'abonnement
        $subscription->update([
            'status' => $immediately ? 'cancelled' : 'active',
            'canceled_at' => $immediately ? now() : ($subscription->canceled_at ?? now()),
        ]);

        if ($immediately) {
            $organization->update([
                'subscription_status' => 'cancelled',
            ]);
        }

        return $subscription->fresh();
    }

    /**
     * Vérifier si une organisation peut utiliser une feature
     */
    public function canUseFeature(Organization $organization, string $feature): bool
    {
        $subscription = $this->getSubscription($organization);

        if (!$subscription) {
            return false;
        }

        return $subscription->hasFeature($feature);
    }

    /**
     * Vérifier les limites d'un tier
     */
    public function checkLimits(Organization $organization): array
    {
        $subscription = $this->getSubscription($organization);

        if (!$subscription) {
            return [
                'tier' => 'free',
                'max_activities' => $this->tiers['free']['max_activities'],
                'max_reservations_per_month' => $this->tiers['free']['max_reservations_per_month'],
                'max_sites' => $this->tiers['free']['max_sites'],
            ];
        }

        $tier = $subscription->tier;
        $tierConfig = $this->tiers[$tier] ?? $this->tiers['free'];

        return [
            'tier' => $tier,
            'max_activities' => $tierConfig['max_activities'],
            'max_reservations_per_month' => $tierConfig['max_reservations_per_month'],
            'max_sites' => $tierConfig['max_sites'],
        ];
    }

    /**
     * Récupérer l'abonnement d'une organisation
     */
    public function getSubscription(Organization $organization): ?Subscription
    {
        return Subscription::where('organization_id', $organization->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    /**
     * Récupérer les tiers disponibles
     */
    public function getAvailableTiers(): array
    {
        return array_keys($this->tiers);
    }
}

