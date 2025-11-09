<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\Activity;
use App\Models\ActivitySession;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Site;
use App\Models\User;

class MultiNicheDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $organization = Organization::firstOrCreate(
                ['slug' => 'demo-multi-niche'],
                [
                    'name' => 'Demo Multi-Niche Adventures',
                    'domain' => 'demo.parapente.test',
                    'primary_color' => '#2563EB',
                    'secondary_color' => '#0EA5E9',
                    'branding' => [
                        'logo_url' => null,
                        'copy' => [
                            'tagline' => 'Multi-activité outdoor',
                        ],
                    ],
                    'features' => ['multi_niche', 'branding', 'instant_booking'],
                ]
            );

            $admin = User::firstOrCreate(
                ['email' => 'admin+demo@parapente.test'],
                [
                    'name' => 'Demo Admin',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'phone' => '+33100000000',
                ]
            );

            $organization->users()->syncWithoutDetaching([
                $admin->id => [
                    'role' => 'admin',
                    'permissions' => ['*'],
                ],
            ]);

            $paraglidingSite = Site::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'PARA-VAL',
                ],
                [
                    'name' => 'Vallée de Chamonix',
                    'description' => 'Décollage panoramique avec vue sur le Mont-Blanc.',
                    'location' => 'Chamonix, France',
                    'latitude' => 45.9237,
                    'longitude' => 6.8694,
                    'difficulty_level' => 'intermediate',
                    'orientation' => 'NNE',
                    'wind_conditions' => 'Laminar, idéal 10-20 km/h',
                    'landing_zone_info' => 'Plaine en vallée, facile d’accès',
                    'is_active' => true,
                ]
            );

            $surfSite = Site::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'SURF-BID',
                ],
                [
                    'name' => 'Plage des Cavaliers',
                    'description' => 'Spot réputé pour ses vagues régulières.',
                    'location' => 'Anglet, France',
                    'latitude' => 43.5042,
                    'longitude' => -1.5335,
                    'difficulty_level' => 'beginner',
                    'orientation' => 'WSW',
                    'wind_conditions' => 'Offshore recommandé',
                    'landing_zone_info' => 'Poste de secours à proximité',
                    'is_active' => true,
                ]
            );

            $tandemGlider = Resource::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'GLIDER-ALPHA',
                ],
                [
                    'name' => 'Supair SORA 2',
                    'type' => 'equipment',
                    'description' => 'Parapente biplace homologué.',
                    'specifications' => [
                        'size' => '42m²',
                        'weight_range' => '120-220 kg',
                    ],
                    'is_active' => true,
                ]
            );

            $surfBoards = Resource::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'SURF-BOARD-SET',
                ],
                [
                    'name' => 'Quiver SoftTop',
                    'type' => 'equipment',
                    'description' => 'Ensemble de planches mousse pour cours collectif.',
                    'specifications' => [
                        'sizes' => ['7.0', '8.0', '9.0'],
                    ],
                    'is_active' => true,
                ]
            );

            $paraglidingActivity = Activity::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'activity_type' => 'paragliding',
                ],
                [
                    'name' => 'Baptême de l’air tandem',
                    'description' => 'Vol découverte de 25 minutes avec pilote certifié.',
                    'duration_minutes' => 90,
                    'max_participants' => 2,
                    'min_participants' => 1,
                    'pricing_config' => [
                        'model' => 'per_participant',
                        'base_price' => 150,
                        'deposit_amount' => 50,
                        'variants' => [
                            [
                                'key' => 'media_pack',
                                'label' => 'Pack photo/vidéo',
                                'amount' => 35,
                            ],
                        ],
                    ],
                    'constraints_config' => [
                        'participants' => [
                            'min' => 1,
                            'max' => 2,
                        ],
                        'weight' => [
                            'max' => 110,
                        ],
                        'required_metadata' => ['experience_level'],
                    ],
                    'metadata' => [
                        'session_strategy' => 'per_participant',
                        'workflow' => 'classic_assignation',
                    ],
                    'is_active' => true,
                ]
            );

            $surfActivity = Activity::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'activity_type' => 'surfing',
                ],
                [
                    'name' => 'Cours de surf collectif',
                    'description' => 'Session de 2h pour débutants et intermédiaires.',
                    'duration_minutes' => 120,
                    'max_participants' => 6,
                    'min_participants' => 2,
                    'pricing_config' => [
                        'model' => 'tiered',
                        'tiers' => [
                            ['up_to' => 3, 'price' => 60],
                            ['up_to' => 6, 'price' => 55],
                        ],
                        'deposit_amount' => 20,
                    ],
                    'constraints_config' => [
                        'participants' => [
                            'min' => 2,
                            'max' => 6,
                        ],
                        'required_metadata' => ['swimming_level'],
                        'enums' => [
                            'swimming_level' => ['beginner', 'intermediate', 'advanced'],
                        ],
                    ],
                    'metadata' => [
                        'session_strategy' => 'per_reservation',
                        'workflow' => 'instant_booking',
                    ],
                    'is_active' => true,
                ]
            );

            $pilotUser = User::firstOrCreate(
                ['email' => 'pilot+paragliding@parapente.test'],
                [
                    'name' => 'Alice Pilot',
                    'password' => Hash::make('password'),
                    'role' => 'instructor',
                    'phone' => '+33111111111',
                ]
            );

            $surfCoachUser = User::firstOrCreate(
                ['email' => 'coach+surf@parapente.test'],
                [
                    'name' => 'Bruno Coach',
                    'password' => Hash::make('password'),
                    'role' => 'instructor',
                    'phone' => '+33122222222',
                ]
            );

            $organization->users()->syncWithoutDetaching([
                $pilotUser->id => [
                    'role' => 'instructor',
                    'permissions' => ['reservations.view', 'sessions.manage'],
                ],
                $surfCoachUser->id => [
                    'role' => 'instructor',
                    'permissions' => ['reservations.view', 'sessions.manage'],
                ],
            ]);

            $pilot = Instructor::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $pilotUser->id,
                ],
                [
                    'activity_types' => ['paragliding'],
                    'license_number' => 'FFVL-PI-2024-001',
                    'certifications' => ['FFVL Biplace', 'Secourisme PSC1'],
                    'experience_years' => 8,
                    'availability' => [
                        'days' => [3, 4, 5, 6, 7],
                        'hours' => range(8, 17),
                        'exceptions' => [],
                    ],
                    'max_sessions_per_day' => 4,
                    'can_accept_instant_bookings' => false,
                    'is_active' => true,
                ]
            );

            $surfCoach = Instructor::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $surfCoachUser->id,
                ],
                [
                    'activity_types' => ['surfing'],
                    'license_number' => 'Fédération Surf-2023-077',
                    'certifications' => ['BPJEPS Surf', 'BNSSA'],
                    'experience_years' => 5,
                    'availability' => [
                        'days' => [1, 3, 5, 6, 7],
                        'hours' => range(7, 18),
                        'exceptions' => [],
                    ],
                    'max_sessions_per_day' => 3,
                    'can_accept_instant_bookings' => true,
                    'is_active' => true,
                ]
            );

            $paraglidingReservation = Reservation::create([
                'organization_id' => $organization->id,
                'activity_id' => $paraglidingActivity->id,
                'activity_type' => $paraglidingActivity->activity_type,
                'user_id' => $admin->id,
                'customer_email' => 'client.parapente@example.com',
                'customer_phone' => '+33600000000',
                'customer_first_name' => 'Camille',
                'customer_last_name' => 'Montagne',
                'participants_count' => 1,
                'status' => 'scheduled',
                'scheduled_at' => Carbon::now()->addDays(3)->setTime(10, 0),
                'scheduled_time' => '10:00',
                'instructor_id' => $pilot->id,
                'site_id' => $paraglidingSite->id,
                'base_amount' => 150,
                'total_amount' => 185,
                'deposit_amount' => 50,
                'payment_status' => 'authorized',
                'payment_type' => 'deposit',
                'metadata' => [
                    'equipment_id' => $tandemGlider->id,
                    'experience_level' => 'first_flight',
                    'options' => ['media_pack' => true],
                ],
            ]);

            ActivitySession::create([
                'organization_id' => $organization->id,
                'activity_id' => $paraglidingActivity->id,
                'reservation_id' => $paraglidingReservation->id,
                'scheduled_at' => Carbon::now()->addDays(3)->setTime(10, 0),
                'duration_minutes' => 90,
                'instructor_id' => $pilot->id,
                'site_id' => $paraglidingSite->id,
                'status' => 'scheduled',
                'metadata' => [
                    'participant' => [
                        'first_name' => 'Camille',
                        'last_name' => 'Montagne',
                    ],
                    'source' => 'demo_seed',
                ],
            ]);

            $surfReservation = Reservation::create([
                'organization_id' => $organization->id,
                'activity_id' => $surfActivity->id,
                'activity_type' => $surfActivity->activity_type,
                'user_id' => $admin->id,
                'customer_email' => 'client.surf@example.com',
                'customer_phone' => '+33699999999',
                'customer_first_name' => 'Léa',
                'customer_last_name' => 'Vague',
                'participants_count' => 4,
                'status' => 'scheduled',
                'scheduled_at' => Carbon::now()->addDays(1)->setTime(9, 30),
                'scheduled_time' => '09:30',
                'instructor_id' => $surfCoach->id,
                'site_id' => $surfSite->id,
                'base_amount' => 220,
                'total_amount' => 260,
                'deposit_amount' => 80,
                'payment_status' => 'authorized',
                'payment_type' => 'deposit',
                'metadata' => [
                    'equipment_id' => $surfBoards->id,
                    'swimming_level' => 'beginner',
                    'notes' => 'Prévoir combinaisons taille M/L',
                ],
            ]);

            ActivitySession::create([
                'organization_id' => $organization->id,
                'activity_id' => $surfActivity->id,
                'reservation_id' => $surfReservation->id,
                'scheduled_at' => Carbon::now()->addDays(1)->setTime(9, 30),
                'duration_minutes' => 120,
                'instructor_id' => $surfCoach->id,
                'site_id' => $surfSite->id,
                'status' => 'scheduled',
                'metadata' => [
                    'participants_count' => 4,
                    'participants' => [
                        ['first_name' => 'Léa', 'last_name' => 'Vague'],
                        ['first_name' => 'Malo', 'last_name' => 'Houle'],
                        ['first_name' => 'Inès', 'last_name' => 'Marée'],
                        ['first_name' => 'Tom', 'last_name' => 'Break'],
                    ],
                    'source' => 'demo_seed',
                ],
            ]);
        });
    }
}

