<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    /**
     * Déclarer un incident (citoyen)
     */
    public function declarer(Request $request)
    {
        $validated = $request->validate([
            'type_urgence' => 'required|in:incendie,accident,medical,autre',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'adresse' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'citoyen_nom' => 'nullable|string|max:255',
            'citoyen_telephone' => 'nullable|string|max:20',
        ]);

        $incident = Incident::create([
            ...$validated,
            'statut' => 'EN_ATTENTE',
        ]);

        return response()->json([
            'success' => true,
            'data' => $incident
        ], 201);
    }

    /**
     * Suivi d’un incident
     */
    public function suivi($id)
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
     * Statistiques publiques
     */
    public function statistiquesPubliques()
    {
        return response()->json([
            'total' => Incident::count(),
            'en_cours' => Incident::whereNotIn('statut', ['TERMINE', 'ANNULE'])->count(),
            'jour' => Incident::whereDate('created_at', today())->count(),
            'agents' => User::where('role', 'AGENT')
                ->where('actif', true)
                ->count(),
        ]);
    }
}