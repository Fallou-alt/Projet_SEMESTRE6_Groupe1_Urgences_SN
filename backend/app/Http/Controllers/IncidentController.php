<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Contrôleur de gestion des incidents.
 *
 * Ce contrôleur permet :
 * - de déclarer un incident ;
 * - de consulter son suivi ;
 * - d'obtenir les statistiques publiques.
 */
class IncidentController extends Controller
{
    /**
 * Déclare un nouvel incident.
 *
 * Les données reçues sont validées puis une structure
 * est sélectionnée automatiquement selon le type d'urgence.
 *
 * @param Request $request
 * @return JsonResponse
 */
    public function declarer(Request $request): JsonResponse
    {
    // Validation des informations envoyées par le citoyen.
        $validated = $request->validate([
            'type_urgence' => 'required|in:incendie,accident,medical,autre',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'adresse' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'citoyen_nom' => 'nullable|string|max:255',
            'citoyen_telephone' => 'nullable|string|max:20',
        ]);
// Détermination du type de structure compétente.
        $typeStructure = match ($request->type_urgence) {
            'medical' => 'samu',
            'incendie', 'accident' => 'pompiers',
            default => null,
        };
// Recherche d'une structure active correspondant au type d'urgence.
        $structure = $typeStructure
            ? Structure::where('type', $typeStructure)->where('actif', true)->first()
            : Structure::where('actif', true)->first();
// Création de l'incident avec son statut initial.
        $incident = Incident::create([
            ...$validated,
            'statut' => Incident::STATUT_EN_ATTENTE,
            'structure_id' => $structure?->id,
        ]);
// Retour de la réponse après création de l'incident.
        return response()->json([
            'success' => true,
            'id' => $incident->id,
        ], 201);
    }

    public function suivi(int $id): JsonResponse
    {
        $incident = Incident::findOrFail($id);

        return response()->json([
            'id' => $incident->id,
            'type_urgence' => $incident->type_urgence,
            'statut' => $incident->statut,
            'adresse' => $incident->adresse,
            'description' => $incident->description,
            'created_at' => $incident->created_at,
            'updated_at' => $incident->updated_at,
        ]);
    }

    public function statistiquesPubliques(): JsonResponse
    {
        return response()->json([
            'total' => Incident::count(),
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