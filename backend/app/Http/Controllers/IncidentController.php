<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
/**
 * API de déclaration d'incident avec affectation automatique
 * selon le type d'urgence.
 */
class IncidentController extends Controller
{
    /**
     * Déclarer un nouvel incident.
     *
     * Cette méthode reçoit les informations envoyées par un citoyen,
     * recherche automatiquement une structure adaptée puis crée
     * un incident avec le statut EN_ATTENTE.
     */
    public function declarer(Request $request): JsonResponse
    {
        // Validation stricte des données envoyées par le citoyen
        $validated = $request->validate([
            'type_urgence' => 'required|in:incendie,accident,medical,autre',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'adresse' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'citoyen_nom' => 'nullable|string|max:255',
            'citoyen_telephone' => 'nullable|string|max:20',
        ]);

        // Sélection de la structure selon le type d'urgence
        $typeStructure = match ($request->type_urgence) {
            'medical' => 'samu',
            'incendie', 'accident' => 'pompiers',
            default => null,
        };

        $structure = $typeStructure
            ? Structure::where('type', $typeStructure)
                ->where('actif', true)
                ->first()
            : Structure::where('actif', true)->first();

        // Création de l'incident
        $incident = Incident::create([
            ...$validated,
            'statut' => Incident::STATUT_EN_ATTENTE,
            'structure_id' => $structure?->id,
        ]);

        return response()->json([
            'success' => true,
            'id' => $incident->id,
        ], 201);
    }

    /**
     * Consulter le suivi public d'un incident.
     */
    public function suivi(int $id): JsonResponse
    {
        $incident = Incident::findOrFail($id);

        return response()->json([
            'id' => $incident->id,
            'type_urgence' => $incident->type_urgence,
            'statut' => $incident->statut,
            'adresse' => $incident->adresse,
            'cree_le' => $incident->created_at,
            'mis_a_jour' => $incident->updated_at,
        ]);
    }

    /**
     * Retourner les statistiques publiques.
     */
    public function statistiquesPubliques(): JsonResponse
    {
        return response()->json([
            'total' => Incident::count(),
// Incidents actifs (non terminés et non annulés)
            'en_cours' => Incident::whereNotIn('statut', [
                Incident::STATUT_TERMINE,
                Incident::STATUT_ANNULE,
            ])->count(),

            'jour' => Incident::whereDate('created_at', today())->count(),

            'agents' => User::where('role', 'AGENT')
                ->where('actif', true)
                ->count(),
        ]);
    }
}