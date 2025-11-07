<?php

namespace Tests\E2E;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Test E2E : Scénario complet d'inscription et connexion
 * 
 * Ce test simule un scénario utilisateur complet :
 * 1. Inscription d'un nouvel utilisateur
 * 2. Connexion
 * 3. Récupération du profil
 * 4. Mise à jour du profil
 */
class AuthenticationE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['cache.default' => 'array']);
        Cache::flush();
        
        $this->organization = Organization::factory()->create();
    }

    public function test_complete_registration_and_login_flow(): void
    {
        // ========== ÉTAPE 1 : Inscription ==========
        $registerData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_id' => $this->organization->id,
        ];

        $registerResponse = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson('/api/v1/auth/register', $registerData);
        
        $registerResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'token',
                ],
            ]);

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertTrue($user->organizations()->where('organizations.id', $this->organization->id)->exists());

        // Vérifier qu'un Client a été créé
        $clientData = $registerResponse->json('data.client');
        $this->assertNotNull($clientData);
        $this->assertTrue(
            Client::withoutTenantScope()->where('user_id', $user->id)->exists()
        );

        $token = $registerResponse->json('data.token');

        // ========== ÉTAPE 2 : Connexion ==========
        $loginData = [
            'email' => 'john.doe@example.com',
            'password' => 'password123',
        ];

        $loginResponse = $this->postJson('/api/v1/auth/login', $loginData);
        
        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'token',
                ],
            ]);

        $this->assertEquals('john.doe@example.com', $loginResponse->json('data.user.email'));

        $newToken = $loginResponse->json('data.token');

        // ========== ÉTAPE 3 : Récupération du profil ==========
        $meResponse = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'phone',
                    ],
                    'client',
                ],
            ]);

        $this->assertEquals('john.doe@example.com', $meResponse->json('data.user.email'));
    }

    public function test_login_with_wrong_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct_password'),
        ]);

        $loginResponse = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong_password',
            ]);

        $loginResponse->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Les identifiants fournis sont incorrects.',
            ]);
    }
}

