<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Structure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_declarer_incident_sans_auth(): void
    {
        $response = $this->postJson('/api/incidents', [
            'type_urgence' => 'incendie',
            'adresse'      => 'Plateau, Dakar',
            'citoyen_nom'  => 'Diallo Mamadou',
        ]);

        $response->assertStatus(201)->assertJson(['succes' => true]);
        $this->assertDatabaseHas('incidents', ['type_urgence' => 'incendie']);
    }

    public function test_declarer_incident_type_invalide(): void
    {
        $response = $this->postJson('/api/incidents', [
            'type_urgence' => 'type_invalide',
        ]);

        $response->assertStatus(422);
    }

    public function test_declarer_affecte_automatiquement_structure(): void
    {
        Structure::create([
            'nom'  => 'Pompiers Dakar',
            'type' => 'pompiers',
            'actif' => true,
        ]);

        $response = $this->postJson('/api/incidents', [
            'type_urgence' => 'incendie',
        ]);

        $response->assertStatus(201);
        $incident = Incident::first();
        $this->assertNotNull($incident->structure_id);
    }

    public function test_suivi_incident(): void
    {
        $incident = Incident::create([
            'type_urgence' => 'medical',
            'statut'       => 'EN_ATTENTE',
        ]);

        $response = $this->getJson("/api/incidents/{$incident->id}/suivi");

        $response->assertStatus(200)
                 ->assertJsonStructure(['id', 'type_urgence', 'statut', 'cree_le']);
    }

    public function test_suivi_incident_inexistant(): void
    {
        $response = $this->getJson('/api/incidents/9999/suivi');
        $response->assertStatus(404);
    }

    public function test_statistiques_publiques(): void
    {
        Incident::create(['type_urgence' => 'incendie', 'statut' => 'EN_ATTENTE']);
        Incident::create(['type_urgence' => 'medical',  'statut' => 'TERMINE']);

        $response = $this->getJson('/api/stats');

        $response->assertStatus(200)
                 ->assertJsonStructure(['total', 'en_cours', 'jour', 'agents']);
    }
}
