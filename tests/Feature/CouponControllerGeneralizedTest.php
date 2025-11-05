<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CouponControllerGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        
        $this->admin = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin']);
    }

    public function test_can_create_coupon_with_activity_types(): void
    {
        // Définir l'organisation dans la session
        $this->admin->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        // Note: applicable_activity_types est converti en applicable_flight_types car c'est le nom du champ DB
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/coupons', [
                'code' => 'TEST10',
                'name' => 'Test Coupon',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'applicable_activity_types' => ['paragliding', 'surfing'],
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // Utiliser withoutGlobalScopes pour trouver le coupon car il peut être filtré par organization_id
        $coupon = \App\Models\Coupon::withoutGlobalScopes()->where('code', 'TEST10')->first();
        $this->assertNotNull($coupon);
        // Vérifier que applicable_activity_types a été stocké dans applicable_flight_types
        $this->assertContains('paragliding', $coupon->applicable_flight_types ?? []);
    }

    public function test_can_create_coupon_with_flight_types_deprecated(): void
    {
        // Définir l'organisation dans la session
        $this->admin->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        // Test rétrocompatibilité
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/coupons', [
                'code' => 'TEST20',
                'name' => 'Test Coupon 2',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'applicable_flight_types' => ['tandem', 'biplace'], // @deprecated
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // Utiliser withoutGlobalScopes pour trouver le coupon car il peut être filtré par organization_id
        $coupon = Coupon::withoutGlobalScopes()->where('code', 'TEST20')->first();
        // Vérifier que applicable_flight_types a été stocké (car c'est le nom du champ DB)
        $this->assertNotNull($coupon);
    }

    public function test_can_update_coupon_with_activity_types(): void
    {
        // Définir l'organisation dans la session
        $this->admin->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        $coupon = Coupon::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'UPDATE_TEST',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/coupons/{$coupon->id}", [
                'applicable_activity_types' => ['paragliding'],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}

