<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Victime;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    // tableau de bord de l'agent connecté
    public function tableau(Request $request)
    {
        $agent    = $request->get('_user');
        $missions = Incident::where('agent_id', $agent->id)->get();

        return response()->json([
            'statistiques' => [
                'total'    => $missions->count(),
                'en_cours' => $missions->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])->count(),
                'termines' => $missions->where('statut', 'TERMINE')->count(),
            ],
            // la mission en cours la plus récente
            'mission_en_cours' => Incident::where('agent_id', $agent->id)
                ->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])
                ->with('victimes')
                ->latest()->first(),
        ]);
    }

    // liste des missions actives de l'agent (affectées mais pas encore terminées)
    public function mesMissions(Request $request)
    {
        $agent = $request->get('_user');

        return response()->json(
            Incident::where('agent_id', $agent->id)
                ->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])
                ->with('victimes')
                ->latest()->get()
        );
    }

    public function historique(Request $request)
    {
        $agent = $request->get('_user');

        // on retourne seulement les missions terminées
        return response()->json(
            Incident::where('agent_id', $agent->id)
                ->where('statut', 'TERMINE')
                ->latest()->get()
        );
    }

    // l'agent fait avancer le statut de sa mission sur le terrain
    public function changerStatut(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:EN_ROUTE,SUR_PLACE,TERMINE',
        ]);

        $agent    = $request->get('_user');
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);

        $donnees = ['statut' => $request->statut];

        // si l'agent termine la mission il peut laisser un commentaire
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

    public function listeVictimes(Request $request, $id)
    {
        $agent    = $request->get('_user');
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);
        return response()->json($incident->victimes);
    }

    public function ajouterVictime(Request $request, $id)
    {
        $request->validate([
            'nom'    => 'required|string',
            'prenom' => 'required|string',
            'etat'   => 'required|in:leger,grave,critique,decede,inconnu',
        ]);

        $agent    = $request->get('_user');
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);

        $victime = Victime::create([
            'incident_id'    => $incident->id,
            'nom'            => $request->nom,
            'prenom'         => $request->prenom,
            'age'            => $request->age,
            'sexe'           => $request->sexe ?? 'inconnu',
            'telephone'      => $request->telephone,
            'groupe_sanguin' => $request->groupe_sanguin ?? 'inconnu',
            'etat'           => $request->etat,
            'observations'   => $request->observations,
        ]);

        return response()->json(['succes' => true, 'victime' => $victime], 201);
    }

    public function supprimerVictime(Request $request, $id)
    {
        $agent   = $request->get('_user');
        $victime = Victime::findOrFail($id);

        // vérifier que la victime appartient bien à une mission de cet agent
        Incident::where('agent_id', $agent->id)->findOrFail($victime->incident_id);

        $victime->delete();
        return response()->json(['succes' => true]);
    }
}

