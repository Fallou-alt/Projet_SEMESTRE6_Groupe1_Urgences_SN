<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use App\Models\Victime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    private function creerAgent(): User
    {
        $structure = Structure::create([
            'nom'  => 'Pompiers Dakar',
            'type' => 'pompiers',
            'actif' => true,
        ]);

        return User::create([
            'identifiant'  => 'agent_test',
            'mot_de_passe' => Hash::make('secret123'),
            'nom'          => 'Fall',
            'prenom'       => 'Ibou',
            'role'         => 'AGENT',
            'actif'        => true,
            'token'        => 'token_agent_test',
            'structure_id' => $structure->id,
        ]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer token_agent_test'];
    }

    private function creerMission(int $agentId, string $statut = 'AFFECTE'): Incident
    {
        return Incident::create([
            'type_urgence' => 'incendie',
            'statut'       => $statut,
            'agent_id'     => $agentId,
        ]);
    }

    // ── Tableau de bord ──────────────────────────────────────────────────────

    public function test_tableau_bord_agent(): void
    {
        $agent = $this->creerAgent();
        $this->creerMission($agent->id, 'AFFECTE');
        $this->creerMission($agent->id, 'TERMINE');

        $response = $this->getJson('/api/agent/tableau', $this->headers());

        $response->assertStatus(200)
                 ->assertJsonStructure(['statistiques' => ['total', 'en_cours', 'termines'], 'mission_en_cours']);
        $this->assertEquals(2, $response->json('statistiques.total'));
        $this->assertEquals(1, $response->json('statistiques.termines'));
    }

    public function test_tableau_bord_sans_auth(): void
    {
        $response = $this->getJson('/api/agent/tableau');
        $response->assertStatus(401);
    }

    // ── Missions ─────────────────────────────────────────────────────────────

    public function test_mes_missions_actives(): void
    {
        $agent = $this->creerAgent();
        $this->creerMission($agent->id, 'EN_ROUTE');
        $this->creerMission($agent->id, 'TERMINE'); // ne doit pas apparaître

        $response = $this->getJson('/api/agent/missions', $this->headers());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('EN_ROUTE', $response->json('0.statut'));
    }

    public function test_historique_missions_terminees(): void
    {
        $agent = $this->creerAgent();
        $this->creerMission($agent->id, 'TERMINE');
        $this->creerMission($agent->id, 'AFFECTE'); // ne doit pas apparaître

        $response = $this->getJson('/api/agent/historique', $this->headers());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('TERMINE', $response->json('0.statut'));
    }

    // ── Changer statut ───────────────────────────────────────────────────────

    public function test_changer_statut_en_route(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id, 'AFFECTE');

        $response = $this->patchJson("/api/agent/missions/{$mission->id}/statut", [
            'statut' => 'EN_ROUTE',
        ], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('incidents', ['id' => $mission->id, 'statut' => 'EN_ROUTE']);
    }

    public function test_changer_statut_termine_avec_commentaire(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id, 'SUR_PLACE');

        $response = $this->patchJson("/api/agent/missions/{$mission->id}/statut", [
            'statut'      => 'TERMINE',
            'commentaire' => 'Intervention terminée sans incident.',
        ], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('incidents', [
            'id'          => $mission->id,
            'statut'      => 'TERMINE',
            'commentaire' => 'Intervention terminée sans incident.',
        ]);
    }

    public function test_changer_statut_invalide(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id);

        $response = $this->patchJson("/api/agent/missions/{$mission->id}/statut", [
            'statut' => 'ANNULE', // non autorisé pour un agent
        ], $this->headers());

        $response->assertStatus(422);
    }

    public function test_changer_statut_mission_autre_agent(): void
    {
        $this->creerAgent();

        $autreAgent = User::create([
            'identifiant'  => 'autre_agent',
            'mot_de_passe' => Hash::make('pass'),
            'nom'          => 'Diop', 'prenom' => 'Awa',
            'role'         => 'AGENT', 'actif' => true,
        ]);

        $mission = $this->creerMission($autreAgent->id, 'AFFECTE');

        $response = $this->patchJson("/api/agent/missions/{$mission->id}/statut", [
            'statut' => 'EN_ROUTE',
        ], $this->headers());

        $response->assertStatus(404);
    }

    // ── Commentaire ──────────────────────────────────────────────────────────

    public function test_ajouter_commentaire(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id, 'SUR_PLACE');

        $response = $this->patchJson("/api/agent/missions/{$mission->id}/commentaire", [
            'commentaire' => 'Deux blessés légers pris en charge.',
        ], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('incidents', [
            'id'          => $mission->id,
            'commentaire' => 'Deux blessés légers pris en charge.',
        ]);
    }

    public function test_ajouter_commentaire_vide(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id);

        $response = $this->patchJson("/api/agent/missions/{$mission->id}/commentaire", [
            'commentaire' => '',
        ], $this->headers());

        $response->assertStatus(422);
    }

    // ── Victimes ─────────────────────────────────────────────────────────────

    public function test_liste_victimes_mission(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id, 'SUR_PLACE');

        Victime::create([
            'incident_id'    => $mission->id,
            'nom'            => 'Sarr',
            'prenom'         => 'Aminata',
            'etat'           => 'grave',
            'sexe'           => 'femme',
            'groupe_sanguin' => 'A+',
        ]);

        $response = $this->getJson("/api/agent/missions/{$mission->id}/victimes", $this->headers());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_ajouter_victime(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id, 'SUR_PLACE');

        $response = $this->postJson("/api/agent/missions/{$mission->id}/victimes", [
            'nom'    => 'Ndiaye',
            'prenom' => 'Lamine',
            'etat'   => 'leger',
        ], $this->headers());

        $response->assertStatus(201)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('victimes', ['nom' => 'Ndiaye', 'etat' => 'leger']);
    }

    public function test_ajouter_victime_etat_invalide(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id, 'SUR_PLACE');

        $response = $this->postJson("/api/agent/missions/{$mission->id}/victimes", [
            'nom'    => 'Ndiaye',
            'prenom' => 'Lamine',
            'etat'   => 'etat_invalide',
        ], $this->headers());

        $response->assertStatus(422);
    }

    public function test_supprimer_victime(): void
    {
        $agent   = $this->creerAgent();
        $mission = $this->creerMission($agent->id, 'SUR_PLACE');

        $victime = Victime::create([
            'incident_id'    => $mission->id,
            'nom'            => 'Gueye',
            'prenom'         => 'Rokhaya',
            'etat'           => 'critique',
            'sexe'           => 'femme',
            'groupe_sanguin' => 'O+',
        ]);

        $response = $this->deleteJson("/api/agent/victimes/{$victime->id}", [], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseMissing('victimes', ['id' => $victime->id]);
    }

    public function test_supprimer_victime_autre_agent(): void
    {
        $this->creerAgent();

        $autreAgent = User::create([
            'identifiant'  => 'autre_agent2',
            'mot_de_passe' => Hash::make('pass'),
            'nom'          => 'Ba', 'prenom' => 'Fatou',
            'role'         => 'AGENT', 'actif' => true,
        ]);

        $mission = $this->creerMission($autreAgent->id, 'SUR_PLACE');

        $victime = Victime::create([
            'incident_id'    => $mission->id,
            'nom'            => 'Diallo',
            'prenom'         => 'Seydou',
            'etat'           => 'leger',
            'sexe'           => 'homme',
            'groupe_sanguin' => 'B+',
        ]);

        $response = $this->deleteJson("/api/agent/victimes/{$victime->id}", [], $this->headers());

        $response->assertStatus(404);
    }
}
