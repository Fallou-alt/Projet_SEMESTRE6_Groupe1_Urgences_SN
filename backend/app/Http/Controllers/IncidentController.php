<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IncidentController extends Controller
{
    /**
     * Déclarer un nouvel incident (citoyen, sans authentification).
     * Affectation automatique à la structure adaptée selon le type d'urgence.
     * Déclarer un nouvel incident.
     *
     * Cette méthode reçoit les informations envoyées par un citoyen,
     * les valide, crée un nouvel incident avec le statut
     * "EN_ATTENTE" puis retourne une réponse JSON.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function declarer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_urgence'      => 'required|in:incendie,accident,medical,autre',
            'latitude'          => 'nullable|numeric',
            'longitude'         => 'nullable|numeric',
            'adresse'           => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'citoyen_nom'       => 'nullable|string|max:255',
            'citoyen_telephone' => 'nullable|string|max:20',
        ]);

        // TODO: affiner l'affectation en tenant compte de la région du citoyen
        $typeStructure = match($request->type_urgence) {
            'medical'  => 'samu',
            'incendie' => 'pompiers',
            'accident' => 'pompiers',
            default    => null,
        };

        $structure = $typeStructure
            ? Structure::where('type', $typeStructure)->where('actif', true)->first()
            : Structure::where('actif', true)->first();

        $incident = Incident::create([
            ...$validated,
            'statut'       => 'EN_ATTENTE',
            'structure_id' => $structure?->id,
        ]);

        return response()->json(['succes' => true, 'id' => $incident->id], 201);
    }

    /**
     * Suivi public d'un incident par son ID (page citoyen).
     * Consulter le suivi d'un incident.
     *
     * Recherche un incident à partir de son identifiant
     * puis retourne ses principales informations.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function suivi(int $id): JsonResponse
    {
        $incident = Incident::findOrFail($id);

        return response()->json([
            'id'           => $incident->id,
            'type_urgence' => $incident->type_urgence,
            'statut'       => $incident->statut,
            'adresse'      => $incident->adresse,
            'cree_le'      => $incident->created_at,
            'mis_a_jour'   => $incident->updated_at,
        ]);
    }

    /**
     * Statistiques affichées sur la page d'accueil publique.
     * Retourner les statistiques publiques.
     *
     * Ces statistiques peuvent être utilisées sur
     * la page d'accueil ou un tableau de bord public.
     *
     * @return JsonResponse
     */
    public function statistiquesPubliques(): JsonResponse
    {
        return response()->json([
            'total'    => Incident::count(),
            'en_cours' => Incident::whereNotIn('statut', ['TERMINE', 'ANNULE'])->count(),
            'jour'     => Incident::whereDate('created_at', today())->count(),
            'agents'   => User::where('role', 'AGENT')->where('actif', true)->count(),
        ]);
    }
}
