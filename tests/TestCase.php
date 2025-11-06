<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Utiliser le driver array pour le cache dans les tests (plus rapide et pas besoin de table)
        // Cela évite les erreurs "no such table: cache" dans les tests
        config(['cache.default' => 'array']);
        \Illuminate\Support\Facades\Cache::flush();
    }
}

