<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    /**
     * Déclaration d'un incident par un citoyen (sans authentification)
     * On essaie d'affecter automatiquement à la bonne structure selon le type
     */
    public function declarer(Request $request)
    {
        $request->validate([
            'type_urgence' => 'required|in:incendie,accident,medical,autre',
        ]);

        // selon le type on cherche la structure adaptée
        // TODO: améliorer ça pour prendre en compte la région du citoyen
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
            'type_urgence'      => $request->type_urgence,
            'latitude'          => $request->latitude,
            'longitude'         => $request->longitude,
            'adresse'           => $request->adresse,
            'description'       => $request->description,
            'citoyen_nom'       => $request->citoyen_nom,
            'citoyen_telephone' => $request->citoyen_telephone,
            'statut'            => 'EN_ATTENTE',
            'structure_id'      => $structure?->id,
        ]);

        return response()->json(['succes' => true, 'id' => $incident->id], 201);
    }

    // suivi public d'un incident par son ID (page citoyen)
    public function suivi($id)
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

    // stats affichées sur la page d'accueil publique
    public function statistiquesPubliques()
    {
        return response()->json([
            'total'    => Incident::count(),
            'en_cours' => Incident::whereNotIn('statut', ['TERMINE', 'ANNULE'])->count(),
            'jour'     => Incident::whereDate('created_at', today())->count(),
            'agents'   => User::where('role', 'AGENT')->where('actif', true)->count(),
        ]);
    }
}
