<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionService = new SubscriptionService();
    }

    public function test_can_create_subscription(): void
    {
        $organization = Organization::factory()->create();

        $subscription = $this->subscriptionService->createSubscription($organization, 'starter');

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'tier' => 'starter',
            'status' => 'active',
        ]);

        $this->assertEquals('starter', $subscription->tier);
        $this->assertTrue($subscription->isActive());
    }

    public function test_subscription_updates_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->subscriptionService->createSubscription($organization, 'pro');

        $organization->refresh();

        $this->assertEquals('pro', $organization->subscription_tier);
        $this->assertEquals('active', $organization->subscription_status);
    }

    public function test_can_cancel_subscription(): void
    {
        $organization = Organization::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($organization, 'pro');

        $cancelledSubscription = $this->subscriptionService->cancelSubscription($organization, false);

        $this->assertTrue($cancelledSubscription->isActive()); // Encore actif jusqu'à la fin de la période
        $this->assertNotNull($cancelledSubscription->canceled_at);

        // Annulation immédiate
        $immediateCancellation = $this->subscriptionService->cancelSubscription($organization, true);
        $this->assertTrue($immediateCancellation->isCancelled());
    }

    public function test_can_check_limits(): void
    {
        $organization = Organization::factory()->create();

        // Sans abonnement, retourne les limites free
        $limits = $this->subscriptionService->checkLimits($organization);
        $this->assertEquals('free', $limits['tier']);
        $this->assertEquals(1, $limits['max_activities']);

        // Avec abonnement pro
        $this->subscriptionService->createSubscription($organization, 'pro');
        $limits = $this->subscriptionService->checkLimits($organization);
        $this->assertEquals('pro', $limits['tier']);
        $this->assertNull($limits['max_activities']); // Illimité
    }

    public function test_can_check_feature_access(): void
    {
        $organization = Organization::factory()->create();

        // Sans abonnement, pas d'accès aux features
        $this->assertFalse($this->subscriptionService->canUseFeature($organization, 'api_access'));

        // Avec abonnement starter (qui a api_access)
        $this->subscriptionService->createSubscription($organization, 'starter');
        $this->assertTrue($this->subscriptionService->canUseFeature($organization, 'api_access'));
        $this->assertFalse($this->subscriptionService->canUseFeature($organization, 'dedicated_support')); // Enterprise only
    }

    public function test_throws_exception_for_invalid_tier(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->subscriptionService->createSubscription($organization, 'invalid_tier');
    }

    public function test_can_get_available_tiers(): void
    {
        $tiers = $this->subscriptionService->getAvailableTiers();

        $this->assertContains('free', $tiers);
        $this->assertContains('starter', $tiers);
        $this->assertContains('pro', $tiers);
        $this->assertContains('enterprise', $tiers);
    }

    public function test_subscription_has_correct_features(): void
    {
        $organization = Organization::factory()->create();

        $subscription = $this->subscriptionService->createSubscription($organization, 'starter');
        $this->assertTrue($subscription->hasFeature('api_access'));
        $this->assertTrue($subscription->hasFeature('basic_analytics'));

        $subscription = $this->subscriptionService->createSubscription($organization, 'pro');
        $this->assertTrue($subscription->hasFeature('api_access'));
        $this->assertTrue($subscription->hasFeature('advanced_analytics'));
        $this->assertTrue($subscription->hasFeature('custom_branding'));
    }
}
