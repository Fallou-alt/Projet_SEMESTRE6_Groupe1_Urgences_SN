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
 * Déclare un nouvel incident dans le système.
 *
 * Cette méthode :
 * - valide les informations envoyées ;
 * - détermine automatiquement la structure compétente ;
 * - crée l'incident avec un statut initial ;
 * - retourne l'identifiant du nouvel incident.
 *
 * @param Request $request Requête contenant les données du citoyen.
 * @return JsonResponse Réponse JSON après création.
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
        // Retour de la réponse après création de l'incident.
        $response = [
            'success' => true,
            'id' => $incident->id,
        ];

        return response()->json($response, 201);
    }

    /**
     * Suivi public d'un incident par son ID (page citoyen).
     */
    public function suivi(int $id): JsonResponse
    {
        $incident = Incident::findOrFail($id);

       $data = [
    'id'           => $incident->id,
    'type_urgence' => $incident->type_urgence,
    'statut'       => $incident->statut,
    'adresse'      => $incident->adresse,
    'cree_le'      => $incident->created_at,
    'mis_a_jour'   => $incident->updated_at,
];

return response()->json($data);
    }

   /**
 * Retourne les statistiques publiques du système.
 *
 * Ces informations sont utilisées pour alimenter
 * le tableau de bord accessible aux citoyens.
 */
    public function statistiquesPubliques(): JsonResponse
    {
        // Construction des statistiques retournées à l'interface publique.
        $statistiques = [
            'total'    => Incident::count(),
            'en_cours' => Incident::whereNotIn('statut', ['TERMINE', 'ANNULE'])->count(),
            'jour'     => Incident::whereDate('created_at', today())->count(),
            'agents'   => User::where('role', 'AGENT')->where('actif', true)->count(),
        ];

return response()->json($statistiques);

    }
}
