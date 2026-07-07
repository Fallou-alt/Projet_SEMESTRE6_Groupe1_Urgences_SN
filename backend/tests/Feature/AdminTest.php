<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function creerAdmin(): User
    {
        $admin = User::create([
            'identifiant'  => 'admin',
            'mot_de_passe' => Hash::make('admin123'),
            'nom'          => 'Admin',
            'prenom'       => 'Super',
            'role'         => 'ADMIN',
            'actif'        => true,
            'token'        => 'token_admin_test',
        ]);
        return $admin;
    }

    private function creerStructure(array $attrs = []): Structure
    {
        return Structure::create(array_merge([
            'nom'  => 'Pompiers Dakar',
            'type' => 'pompiers',
            'actif' => true,
        ], $attrs));
    }

    public function test_tableau_bord_admin(): void
    {
        $this->creerAdmin();

        $response = $this->getJson('/api/admin/tableau', [
            'Authorization' => 'Bearer token_admin_test',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['statistiques', 'incidents_recents']);
    }

    public function test_creer_structure(): void
    {
        $this->creerAdmin();

        $response = $this->postJson('/api/admin/structures', [
            'nom'  => 'SAMU National',
            'type' => 'samu',
        ], ['Authorization' => 'Bearer token_admin_test']);

        $response->assertStatus(201)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('structures', ['nom' => 'SAMU National']);
    }

    public function test_creer_structure_type_invalide(): void
    {
        $this->creerAdmin();

        $response = $this->postJson('/api/admin/structures', [
            'nom'  => 'Test',
            'type' => 'type_invalide',
        ], ['Authorization' => 'Bearer token_admin_test']);

        $response->assertStatus(422);
    }

    public function test_supprimer_structure_avec_incidents_actifs_bloquee(): void
    {
        $this->creerAdmin();
        $structure = $this->creerStructure();

        Incident::create([
            'type_urgence' => 'incendie',
            'statut'       => 'EN_ATTENTE',
            'structure_id' => $structure->id,
        ]);

        $response = $this->deleteJson("/api/admin/structures/{$structure->id}", [], [
            'Authorization' => 'Bearer token_admin_test',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseHas('structures', ['id' => $structure->id]);
    }

    public function test_supprimer_structure_sans_incidents(): void
    {
        $this->creerAdmin();
        $structure = $this->creerStructure();

        $response = $this->deleteJson("/api/admin/structures/{$structure->id}", [], [
            'Authorization' => 'Bearer token_admin_test',
        ]);

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseMissing('structures', ['id' => $structure->id]);
    }

    public function test_acces_refuse_sans_token(): void
    {
        $response = $this->getJson('/api/admin/tableau');
        $response->assertStatus(401);
    }

    public function test_acces_refuse_role_agent(): void
    {
        User::create([
            'identifiant'  => 'agent1',
            'mot_de_passe' => Hash::make('agent123'),
            'nom'          => 'Agent',
            'prenom'       => 'Test',
            'role'         => 'AGENT',
            'actif'        => true,
            'token'        => 'token_agent_test',
        ]);

        $response = $this->getJson('/api/admin/tableau', [
            'Authorization' => 'Bearer token_agent_test',
        ]);

        $response->assertStatus(403);
    }
}
