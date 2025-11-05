<?php

return [
    'name' => 'Paragliding',
    'version' => '1.0.0',
    'activity_type' => 'paragliding',
    'models' => [
        'reservation' => \App\Modules\Paragliding\Models\ParaglidingReservation::class,
        'session' => \App\Modules\Paragliding\Models\Flight::class,
        'instructor' => \App\Modules\Paragliding\Models\Biplaceur::class,
    ],
    'constraints' => [
        'weight' => ['min' => 40, 'max' => 120],
        'height' => ['min' => 140, 'max' => 250],
    ],
    'features' => [
        'shuttles' => true,
        'weather_dependent' => true,
        'rotation_duration' => 90, // minutes
        'max_shuttle_capacity' => 9,
        'instant_booking' => false, // Requiert assignation manuelle
    ],
    'workflow' => [
        'stages' => ['pending', 'authorized', 'scheduled', 'completed'],
        'auto_schedule' => false, // Requiert assignation manuelle
    ],
];

