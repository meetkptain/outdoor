<?php

return [
    'name' => 'Surfing',
    'version' => '1.0.0',
    'activity_type' => 'surfing',
    'models' => [
        'reservation' => \App\Models\Reservation::class,
        'session' => \App\Modules\Surfing\Models\SurfingSession::class,
        'instructor' => \App\Modules\Surfing\Models\SurfingInstructor::class,
    ],
    'constraints' => [
        'age' => ['min' => 8],
        'swimming_level' => ['required' => true],
    ],
    'features' => [
        'equipment_rental' => true,
        'weather_dependent' => true,
        'tide_dependent' => true,
        'session_duration' => 60, // minutes
        'instant_booking' => true, // Réservation instantanée possible
    ],
    'workflow' => [
        'stages' => ['pending', 'confirmed', 'completed'],
        'auto_schedule' => true, // Réservation instantanée possible
    ],
];

