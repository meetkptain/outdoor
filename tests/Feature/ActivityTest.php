<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_activity(): void
    {
        $organization = Organization::factory()->create();

        $activity = Activity::create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
            'name' => 'Vol en Parapente',
            'description' => 'Vol biplace en parapente',
            'duration_minutes' => 90,
            'max_participants' => 1,
            'min_participants' => 1,
            'constraints_config' => [
                'weight' => ['min' => 40, 'max' => 120],
                'height' => ['min' => 140, 'max' => 250],
            ],
        ]);

        $this->assertDatabaseHas('activities', [
            'activity_type' => 'paragliding',
            'name' => 'Vol en Parapente',
        ]);
    }

    public function test_activity_has_constraints(): void
    {
        $organization = Organization::factory()->create();

        $activity = Activity::create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
            'name' => 'Test',
            'constraints_config' => [
                'weight' => ['min' => 40, 'max' => 120],
            ],
        ]);

        $this->assertTrue($activity->hasConstraint('weight'));
        $weightConstraint = $activity->getConstraint('weight');
        $this->assertEquals(['min' => 40, 'max' => 120], $weightConstraint);
        $this->assertEquals(40, $weightConstraint['min'] ?? null);
    }

    public function test_activity_can_have_sessions(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
        ]);

        $session = $activity->sessions()->create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->assertTrue($activity->sessions->contains($session));
    }

    public function test_activity_scope_by_type(): void
    {
        $organization = Organization::factory()->create();

        // Nettoyer les activités existantes pour cette organisation dans ce test
        Activity::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->delete();

        // Créer les activités sans utiliser le scope tenant (car on teste juste le scope by type)
        Activity::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
            'name' => 'Paragliding Activity',
            'is_active' => true,
        ]);

        Activity::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'surfing',
            'name' => 'Surfing Activity',
            'is_active' => true,
        ]);

        $paraglidingActivities = Activity::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->ofType('paragliding')
            ->get();
        
        $this->assertCount(1, $paraglidingActivities);
        $this->assertEquals('paragliding', $paraglidingActivities->first()->activity_type);
    }
}
