<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_organization(): void
    {
        $organization = Organization::create([
            'slug' => 'test-club',
            'name' => 'Test Club',
            'subscription_tier' => 'starter',
        ]);

        $this->assertDatabaseHas('organizations', [
            'slug' => 'test-club',
            'name' => 'Test Club',
            'subscription_tier' => 'starter',
        ]);
    }

    public function test_organization_slug_must_be_unique(): void
    {
        Organization::create([
            'slug' => 'test-club',
            'name' => 'Test Club',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Organization::create([
            'slug' => 'test-club',
            'name' => 'Another Club',
        ]);
    }

    public function test_organization_has_features(): void
    {
        $organization = Organization::create([
            'slug' => 'test-club',
            'name' => 'Test Club',
            'features' => ['paragliding', 'surfing'],
        ]);

        $this->assertTrue($organization->hasFeature('paragliding'));
        $this->assertTrue($organization->hasFeature('surfing'));
        $this->assertFalse($organization->hasFeature('diving'));
    }

    public function test_can_add_feature_to_organization(): void
    {
        $organization = Organization::create([
            'slug' => 'test-club',
            'name' => 'Test Club',
            'features' => ['paragliding'],
        ]);

        $organization->addFeature('surfing');

        $organization->refresh();
        $this->assertTrue($organization->hasFeature('surfing'));
        $this->assertTrue($organization->hasFeature('paragliding'));
    }

    public function test_can_remove_feature_from_organization(): void
    {
        $organization = Organization::create([
            'slug' => 'test-club',
            'name' => 'Test Club',
            'features' => ['paragliding', 'surfing'],
        ]);

        $organization->removeFeature('surfing');

        $organization->refresh();
        $this->assertFalse($organization->hasFeature('surfing'));
        $this->assertTrue($organization->hasFeature('paragliding'));
    }

    public function test_organization_can_have_users(): void
    {
        $organization = Organization::create([
            'slug' => 'test-club',
            'name' => 'Test Club',
        ]);

        $user = User::factory()->create();

        $organization->users()->attach($user->id, [
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->assertTrue($organization->users->contains($user));
        $this->assertEquals('admin', $organization->users->first()->pivot->role);
    }
}
