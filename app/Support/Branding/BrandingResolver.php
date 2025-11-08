<?php

namespace App\Support\Branding;

use App\Models\Organization;
use Illuminate\Support\Arr;

class BrandingResolver
{
    /**
     * Resolve branding information for the given organization or the current tenant.
     */
    public function resolve(?Organization $organization = null): array
    {
        $organization = $organization ?: config('app.current_organization');
        $defaults = config('branding.defaults', []);

        if (!$organization) {
            return $defaults;
        }

        $branding = $organization->branding ?? [];

        $resolved = $defaults;

        $resolved['name'] = Arr::get($branding, 'name')
            ?? $organization->name
            ?? $defaults['name'];

        $resolved['tagline'] = Arr::get($branding, 'tagline')
            ?? Arr::get($organization->metadata, 'tagline')
            ?? $defaults['tagline'];

        $resolved['emoji'] = Arr::get($branding, 'emoji')
            ?? Arr::get($organization->metadata, 'emoji')
            ?? $defaults['emoji'];

        $resolved['logo_url'] = Arr::get($branding, 'logo_url')
            ?? $organization->logo_url
            ?? $defaults['logo_url'];

        $resolved['colors']['primary'] = Arr::get($branding, 'colors.primary')
            ?? $organization->primary_color
            ?? Arr::get($defaults, 'colors.primary');

        $resolved['colors']['secondary'] = Arr::get($branding, 'colors.secondary')
            ?? $organization->secondary_color
            ?? Arr::get($defaults, 'colors.secondary');

        $resolved['colors']['accent'] = Arr::get($branding, 'colors.accent')
            ?? Arr::get($defaults, 'colors.accent');

        $resolved['support']['email'] = Arr::get($branding, 'support.email')
            ?? $organization->billing_email
            ?? Arr::get($defaults, 'support.email');

        $resolved['support']['phone'] = Arr::get($branding, 'support.phone')
            ?? Arr::get($organization->metadata, 'support.phone')
            ?? Arr::get($defaults, 'support.phone');

        $resolved['support']['website'] = Arr::get($branding, 'support.website')
            ?? Arr::get($organization->metadata, 'support.website')
            ?? Arr::get($defaults, 'support.website');

        $resolved['signature']['company'] = Arr::get($branding, 'signature.company')
            ?? $resolved['name']
            ?? Arr::get($defaults, 'signature.company');

        $resolved['signature']['closing'] = Arr::get($branding, 'signature.closing')
            ?? Arr::get($defaults, 'signature.closing');

        return $resolved;
    }

    /**
     * Shortcut to retrieve a specific branding key using dot notation.
     */
    public function get(string $key, mixed $default = null, ?Organization $organization = null): mixed
    {
        $branding = $this->resolve($organization);

        return Arr::get($branding, $key, $default);
    }
}

