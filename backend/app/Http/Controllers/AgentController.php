<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function tableau(Request $request)
    {
        $agent     = $request->get('_user');
        $missions  = Incident::where('agent_id', $agent->id)->get();

        return response()->json([
            'statistiques' => [
                'total'    => $missions->count(),
                'en_cours' => $missions->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])->count(),
                'termines' => $missions->where('statut', 'TERMINE')->count(),
            ],
            'mission_en_cours' => Incident::where('agent_id', $agent->id)
                ->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])
                ->with('victimes')
                ->latest()->first(),
        ]);
    }

    public function mesMissions(Request $request){
        $agent = $request -> get('_user');
         return response()->json(
            incident::where('agent_id', $agent->id)
            ->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])
            ->with('victimes')
            ->latest()->get()
         );
        
    }

    public function historique(Request $request)
    {
        $agent = $request->get('_user');

        return response()->json(
            Incident::where('agent_id', $agent->id)
                ->where('statut', 'TERMINE')
                ->latest()->get()
        );
    }

    public function changerStatut(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:EN_ROUTE,SUR_PLACE,TERMINE',
        ]);

        $agent    = $request->get('_user');
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);

        $donnees = ['statut' => $request->statut];

        if ($request->statut === 'TERMINE' && $request->filled('commentaire')) {
            $donnees['commentaire'] = $request->commentaire;
        }

        $incident->update($donnees);

        return response()->json(['succes' => true, 'incident' => $incident]);
    }

    public function ajouterCommentaire(Request $request, $id)
    {
        $request->validate([
            'commentaire' => 'required|string',
        ]);

        $agent    = $request->get('_user');
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);
        $incident->update(['commentaire' => $request->commentaire]);

        return response()->json(['succes' => true]);
    }
}

