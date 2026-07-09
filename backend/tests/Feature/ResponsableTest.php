<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use App\Models\Victime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResponsableTest extends TestCase
{
    use RefreshDatabase;

    private function creerStructureAvecResponsable(): array
    {
        $structure = Structure::create([
            'nom'  => 'Pompiers Dakar',
            'type' => 'pompiers',
            'actif' => true,
        ]);

        $responsable = User::create([
            'identifiant'  => 'resp_test',
            'mot_de_passe' => Hash::make('secret123'),
            'nom'          => 'Diop',
            'prenom'       => 'Moussa',
            'role'         => 'RESPONSABLE',
            'actif'        => true,
            'token'        => 'token_resp_test',
            'structure_id' => $structure->id,
        ]);

        $structure->update(['responsable_id' => $responsable->id]);

        return [$structure, $responsable];
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer token_resp_test'];
    }

    // ── Tableau de bord ──────────────────────────────────────────────────────

    public function test_tableau_bord_responsable(): void
    {
        $this->creerStructureAvecResponsable();

        $response = $this->getJson('/api/responsable/tableau', $this->headers());

        $response->assertStatus(200)
                 ->assertJsonStructure(['statistiques', 'incidents_recents']);
    }

    public function test_tableau_bord_sans_auth(): void
    {
        $response = $this->getJson('/api/responsable/tableau');
        $response->assertStatus(401);
    }

    // ── Structure ────────────────────────────────────────────────────────────

    public function test_voir_ma_structure(): void
    {
        $this->creerStructureAvecResponsable();

        $response = $this->getJson('/api/responsable/structure', $this->headers());

        $response->assertStatus(200)
                 ->assertJsonStructure(['id', 'nom', 'type']);
    }

    public function test_modifier_ma_structure(): void
    {
        $this->creerStructureAvecResponsable();

        $response = $this->patchJson('/api/responsable/structure', [
            'nom'      => 'Pompiers Dakar Plateau',
            'telephone' => '+221338200000',
        ], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('structures', ['nom' => 'Pompiers Dakar Plateau']);
    }

    // ── Agents ───────────────────────────────────────────────────────────────

    public function test_creer_agent(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $response = $this->postJson('/api/responsable/agents', [
            'identifiant'  => 'agent_test',
            'mot_de_passe' => 'agent123',
            'nom'          => 'Fall',
            'prenom'       => 'Ibou',
        ], $this->headers());

        $response->assertStatus(201)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('users', [
            'identifiant'  => 'agent_test',
            'role'         => 'AGENT',
            'structure_id' => $structure->id,
        ]);
    }

    public function test_creer_agent_identifiant_duplique(): void
    {
        $this->creerStructureAvecResponsable();

        User::create([
            'identifiant'  => 'agent_existant',
            'mot_de_passe' => Hash::make('pass'),
            'nom'          => 'X', 'prenom' => 'Y',
            'role'         => 'AGENT', 'actif' => true,
        ]);

        $response = $this->postJson('/api/responsable/agents', [
            'identifiant'  => 'agent_existant',
            'mot_de_passe' => 'agent123',
            'nom'          => 'Fall',
            'prenom'       => 'Ibou',
        ], $this->headers());

        $response->assertStatus(422);
    }

    public function test_liste_agents(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        User::create([
            'identifiant'  => 'agent1',
            'mot_de_passe' => Hash::make('pass'),
            'nom'          => 'Ndiaye', 'prenom' => 'Awa',
            'role'         => 'AGENT', 'actif' => true,
            'structure_id' => $structure->id,
        ]);

        $response = $this->getJson('/api/responsable/agents', $this->headers());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_toggle_agent(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $agent = User::create([
            'identifiant'  => 'agent_toggle',
            'mot_de_passe' => Hash::make('pass'),
            'nom'          => 'Ba', 'prenom' => 'Fatou',
            'role'         => 'AGENT', 'actif' => true,
            'structure_id' => $structure->id,
        ]);

        $response = $this->patchJson("/api/responsable/agents/{$agent->id}/toggle", [], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true, 'actif' => false]);
    }

    public function test_supprimer_agent(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $agent = User::create([
            'identifiant'  => 'agent_del',
            'mot_de_passe' => Hash::make('pass'),
            'nom'          => 'Sow', 'prenom' => 'Omar',
            'role'         => 'AGENT', 'actif' => true,
            'structure_id' => $structure->id,
        ]);

        $response = $this->deleteJson("/api/responsable/agents/{$agent->id}", [], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseMissing('users', ['id' => $agent->id]);
    }

    // ── Incidents ────────────────────────────────────────────────────────────

    public function test_liste_incidents_structure(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        Incident::create([
            'type_urgence' => 'incendie',
            'statut'       => 'EN_ATTENTE',
            'structure_id' => $structure->id,
        ]);

        $response = $this->getJson('/api/responsable/incidents', $this->headers());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_affecter_agent_incident(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $agent = User::create([
            'identifiant'  => 'agent_aff',
            'mot_de_passe' => Hash::make('pass'),
            'nom'          => 'Diallo', 'prenom' => 'Seydou',
            'role'         => 'AGENT', 'actif' => true,
            'structure_id' => $structure->id,
        ]);

        $incident = Incident::create([
            'type_urgence' => 'accident',
            'statut'       => 'EN_ATTENTE',
            'structure_id' => $structure->id,
        ]);

        $response = $this->patchJson("/api/responsable/incidents/{$incident->id}/affecter", [
            'agent_id' => $agent->id,
        ], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('incidents', [
            'id'       => $incident->id,
            'agent_id' => $agent->id,
            'statut'   => 'AFFECTE',
        ]);
    }

    public function test_annuler_incident(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $incident = Incident::create([
            'type_urgence' => 'medical',
            'statut'       => 'EN_ATTENTE',
            'structure_id' => $structure->id,
        ]);

        $response = $this->patchJson("/api/responsable/incidents/{$incident->id}/annuler", [], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'statut' => 'ANNULE']);
    }

    // ── Victimes ─────────────────────────────────────────────────────────────

    public function test_ajouter_victime(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $incident = Incident::create([
            'type_urgence' => 'accident',
            'statut'       => 'SUR_PLACE',
            'structure_id' => $structure->id,
        ]);

        $response = $this->postJson("/api/responsable/incidents/{$incident->id}/victimes", [
            'nom'    => 'Sarr',
            'prenom' => 'Aminata',
            'etat'   => 'grave',
        ], $this->headers());

        $response->assertStatus(201)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('victimes', ['nom' => 'Sarr', 'etat' => 'grave']);
    }

    public function test_ajouter_victime_etat_invalide(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $incident = Incident::create([
            'type_urgence' => 'accident',
            'statut'       => 'SUR_PLACE',
            'structure_id' => $structure->id,
        ]);

        $response = $this->postJson("/api/responsable/incidents/{$incident->id}/victimes", [
            'nom'    => 'Sarr',
            'prenom' => 'Aminata',
            'etat'   => 'etat_invalide',
        ], $this->headers());

        $response->assertStatus(422);
    }

    public function test_liste_victimes_incident(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $incident = Incident::create([
            'type_urgence' => 'accident',
            'statut'       => 'SUR_PLACE',
            'structure_id' => $structure->id,
        ]);

        Victime::create([
            'incident_id' => $incident->id,
            'nom'         => 'Diop',
            'prenom'      => 'Lamine',
            'etat'        => 'leger',
            'sexe'        => 'homme',
            'groupe_sanguin' => 'O+',
        ]);

        $response = $this->getJson("/api/responsable/incidents/{$incident->id}/victimes", $this->headers());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    public function test_supprimer_victime(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        $incident = Incident::create([
            'type_urgence' => 'accident',
            'statut'       => 'SUR_PLACE',
            'structure_id' => $structure->id,
        ]);

        $victime = Victime::create([
            'incident_id' => $incident->id,
            'nom'         => 'Gueye',
            'prenom'      => 'Rokhaya',
            'etat'        => 'critique',
            'sexe'        => 'femme',
            'groupe_sanguin' => 'A+',
        ]);

        $response = $this->deleteJson("/api/responsable/victimes/{$victime->id}", [], $this->headers());

        $response->assertStatus(200)->assertJson(['succes' => true]);
        $this->assertDatabaseMissing('victimes', ['id' => $victime->id]);
    }

    // ── Rapport ──────────────────────────────────────────────────────────────

    public function test_rapport_structure(): void
    {
        [$structure] = $this->creerStructureAvecResponsable();

        Incident::create(['type_urgence' => 'incendie', 'statut' => 'TERMINE', 'structure_id' => $structure->id]);
        Incident::create(['type_urgence' => 'medical',  'statut' => 'EN_ATTENTE', 'structure_id' => $structure->id]);

        $response = $this->getJson('/api/responsable/rapport?annee=' . date('Y'), $this->headers());

        $response->assertStatus(200)
                 ->assertJsonStructure(['total_incidents', 'par_type', 'par_statut', 'victimes']);
    }
}
