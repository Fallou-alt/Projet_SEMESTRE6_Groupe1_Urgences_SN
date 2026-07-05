<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(array $attrs = []): User
    {
        return User::create(array_merge([
            'identifiant'  => 'test_user',
            'mot_de_passe' => Hash::make('secret123'),
            'nom'          => 'Test',
            'prenom'       => 'User',
            'role'         => 'AGENT',
            'actif'        => true,
        ], $attrs));
    }

    public function test_connexion_reussie(): void
    {
        $this->creerUtilisateur();

        $response = $this->postJson('/api/connexion', [
            'identifiant'  => 'test_user',
            'mot_de_passe' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'utilisateur' => ['id', 'nom', 'role']]);
    }

    public function test_connexion_mauvais_mot_de_passe(): void
    {
        $this->creerUtilisateur();

        $response = $this->postJson('/api/connexion', [
            'identifiant'  => 'test_user',
            'mot_de_passe' => 'mauvais',
        ]);

        $response->assertStatus(401);
    }

    public function test_connexion_compte_desactive(): void
    {
        $this->creerUtilisateur(['actif' => false]);

        $response = $this->postJson('/api/connexion', [
            'identifiant'  => 'test_user',
            'mot_de_passe' => 'secret123',
        ]);

        $response->assertStatus(403);
    }

    public function test_connexion_identifiant_inexistant(): void
    {
        $response = $this->postJson('/api/connexion', [
            'identifiant'  => 'inexistant',
            'mot_de_passe' => 'secret123',
        ]);

        $response->assertStatus(401);
    }

    public function test_deconnexion(): void
    {
        $user = $this->creerUtilisateur();
        $user->update(['token' => 'token_test_123']);

        $response = $this->postJson('/api/deconnexion', [], [
            'Authorization' => 'Bearer token_test_123',
        ]);

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertNull($user->fresh()->token);
    }
}
