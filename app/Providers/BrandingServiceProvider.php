<?php

namespace App\Providers;

use App\Support\Branding\BrandingResolver;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class BrandingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(BrandingResolver::class, function ($app) {
            return new BrandingResolver();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $resolver = $this->app->make(BrandingResolver::class);
            $view->with('branding', $resolver->resolve());
        });
    }
}

