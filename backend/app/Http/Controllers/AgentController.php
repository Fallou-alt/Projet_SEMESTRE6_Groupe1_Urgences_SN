<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Victime;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    // ============================================================
    // Retourne le tableau de bord de l'agent actuellement connecté :
    // ses statistiques globales (total, en cours, terminées) ainsi
    // que la dernière mission encore active (non terminée).
    // ============================================================
    public function tableau(Request $request)
    {
        // Récupère l'agent authentifié depuis la requête (injecté par le middleware d'auth)
        $agent    = $request->get('_user');
        // Récupère toutes les missions (incidents) assignées à cet agent
        $missions = Incident::where('agent_id', $agent->id)->get();

        return response()->json([
            // Bloc de statistiques calculées à partir de la collection $missions
            'statistiques' => [
                'total'    => $missions->count(),
                'en_cours' => $missions->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])->count(),
                'termines' => $missions->where('statut', 'TERMINE')->count(),
            ],
            // dernière mission en cours
            // Requête séparée pour récupérer la mission active la plus récente,
            // avec les victimes associées chargées en eager loading
            'mission_en_cours' => Incident::where('agent_id', $agent->id)
                ->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])
                ->with('victimes')
                ->latest()->first(),
        ]);
    }

    // ============================================================
    // Liste les missions actives de l'agent (celles qui ne sont pas
    // encore terminées), triées de la plus récente à la plus ancienne.
    // ============================================================
    // missions actives de l'agent (affectées, non terminées)
    public function mesMissions(Request $request)
    {
        $agent = $request->get('_user');

        return response()->json(
            // Filtre les incidents de l'agent dont le statut indique une mission encore en cours
            Incident::where('agent_id', $agent->id)
                ->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])
                ->with('victimes')
                ->latest()->get()
        );
    }

    // ============================================================
    // Retourne l'historique des missions terminées de l'agent.
    // ============================================================
    public function historique(Request $request)
    {
        $agent = $request->get('_user');

        // uniquement les missions terminées
        return response()->json(
            Incident::where('agent_id', $agent->id)
                ->where('statut', 'TERMINE')
                ->latest()->get()
        );
    }

    // ============================================================
    // Permet à l'agent sur le terrain de mettre à jour le statut
    // de sa propre mission (progression : en route, sur place, terminé).
    // ============================================================
    // mise à jour du statut de la mission par l'agent sur le terrain
    public function changerStatut(Request $request, $id)
    {
        // statut limité aux 3 valeurs que l'agent peut déclencher lui-même
        // (il ne peut pas remettre EN_ATTENTE ni ANNULER un incident)
        $request->validate([
            'statut' => 'required|in:EN_ROUTE,SUR_PLACE,TERMINE',
        ]);

        $agent    = $request->get('_user');
        // Vérifie que l'incident appartient bien à l'agent connecté avant modification
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);

        // Données de base à mettre à jour : le nouveau statut
        $donnees = ['statut' => $request->statut];

        // commentaire optionnel à la clôture
        // Si la mission est clôturée et qu'un commentaire est fourni, on l'ajoute aux données
        if ($request->statut === 'TERMINE' && $request->filled('commentaire')) {
            $donnees['commentaire'] = $request->commentaire;
        }

        // Applique la mise à jour du statut (et éventuellement du commentaire)
        $incident->update($donnees);

        return response()->json(['succes' => true, 'incident' => $incident]);
    }

    // ============================================================
    // Ajoute ou remplace le commentaire d'une mission appartenant
    // à l'agent connecté.
    // ============================================================
    public function ajouterCommentaire(Request $request, $id)
    {
        // commentaire obligatoire : évite d'écraser un commentaire existant avec une valeur vide
        $request->validate([
            'commentaire' => 'required|string',
        ]);

        $agent    = $request->get('_user');
        // Vérifie que l'incident appartient bien à l'agent avant modification
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);
        $incident->update(['commentaire' => $request->commentaire]);

        return response()->json(['succes' => true]);
    }

    // ============================================================
    // Retourne la liste des victimes rattachées à une mission
    // spécifique de l'agent connecté.
    // ============================================================
    public function listeVictimes(Request $request, $id)
    {
        $agent    = $request->get('_user');
        // Vérifie que l'incident appartient bien à l'agent avant de renvoyer ses victimes
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);
        return response()->json($incident->victimes);
    }

    // ============================================================
    // Enregistre une nouvelle victime rattachée à une mission
    // de l'agent connecté.
    // ============================================================
    public function ajouterVictime(Request $request, $id)
    {
        // Validation des champs obligatoires et des valeurs autorisées pour "etat"
        $request->validate([
            'nom'    => 'required|string',
            'prenom' => 'required|string',
            'etat'   => 'required|in:leger,grave,critique,decede,inconnu',
        ]);

        $agent    = $request->get('_user');
        // Vérifie que l'incident appartient bien à l'agent avant d'y rattacher une victime
        $incident = Incident::where('agent_id', $agent->id)->findOrFail($id);

        // Création de la victime avec valeurs par défaut pour les champs optionnels non fournis
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

    // ============================================================
    // Supprime une victime, après avoir vérifié qu'elle est bien
    // rattachée à une mission appartenant à l'agent connecté.
    // ============================================================
    public function supprimerVictime(Request $request, $id)
    {
        $agent   = $request->get('_user');
        $victime = Victime::findOrFail($id);

        // vérification : la victime doit appartenir à une mission de cet agent
        Incident::where('agent_id', $agent->id)->findOrFail($victime->incident_id);

        $victime->delete();
        return response()->json(['succes' => true]);
    }
}