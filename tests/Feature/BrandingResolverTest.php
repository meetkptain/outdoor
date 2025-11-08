<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Support\Branding\BrandingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_defaults_when_no_organization_is_set(): void
    {
        config(['app.current_organization' => null]);

        $resolver = app(BrandingResolver::class);
        $branding = $resolver->resolve();

        $this->assertSame(config('branding.defaults'), $branding);
    }

    public function test_it_merges_with_organization_branding(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Aero Club',
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
            'branding' => [
                'name' => 'Aero SaaS',
                'emoji' => '🪂',
                'colors' => [
                    'primary' => '#000000',
                ],
                'support' => [
                    'email' => 'support@aero.test',
                ],
            ],
        ]);

        config(['app.current_organization' => $organization]);

        $branding = app(BrandingResolver::class)->resolve();

        $this->assertSame('Aero SaaS', $branding['name']);
        $this->assertSame('🪂', $branding['emoji']);
        $this->assertSame('#000000', $branding['colors']['primary']);
        $this->assertSame('#abcdef', $branding['colors']['secondary']);
        $this->assertSame('support@aero.test', $branding['support']['email']);
    }

    public function test_it_falls_back_to_legacy_fields_when_branding_missing(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Surf Collective',
            'logo_url' => 'https://cdn.test/logo.png',
            'primary_color' => '#336699',
            'secondary_color' => '#ff6600',
            'billing_email' => 'billing@surf.test',
            'branding' => null,
        ]);

        config(['app.current_organization' => $organization]);

        $branding = app(BrandingResolver::class)->resolve();

        $this->assertSame('Surf Collective', $branding['name']);
        $this->assertSame('https://cdn.test/logo.png', $branding['logo_url']);
        $this->assertSame('#336699', $branding['colors']['primary']);
        $this->assertSame('#ff6600', $branding['colors']['secondary']);
        $this->assertSame('billing@surf.test', $branding['support']['email']);
    }
}

