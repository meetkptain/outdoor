<?php

return [
    'defaults' => [
        'name' => env('APP_NAME', 'Multi-Niche Booking'),
        'tagline' => null,
        'emoji' => '✨',
        'logo_url' => null,
        'colors' => [
            'primary' => '#2563EB',
            'secondary' => '#0EA5E9',
            'accent' => '#F97316',
        ],
        'support' => [
            'email' => env('MAIL_FROM_ADDRESS', 'support@example.com'),
            'phone' => null,
            'website' => env('APP_URL', 'https://example.com'),
        ],
        'signature' => [
            'company' => env('APP_NAME', 'Multi-Niche Team'),
            'closing' => 'L’équipe',
        ],
    ],
];

