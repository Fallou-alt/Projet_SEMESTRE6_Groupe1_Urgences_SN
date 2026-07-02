<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use App\Models\Victime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResponsableController extends Controller
{
    private function structureId(Request $request): int
    {
        return $request->get('_user')->structure_id;
    }

    public function tableau(Request $request)
    {
        $structureId = $this->structureId($request);
        $incidents   = Incident::where('structure_id', $structureId)->get();

        return response()->json([
            'statistiques' => [
                'agents'      => User::where('role', 'AGENT')->where('structure_id', $structureId)->count(),
                'total'       => $incidents->count(),
                'en_attente'  => $incidents->where('statut', 'EN_ATTENTE')->count(),
                'en_cours'    => $incidents->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])->count(),
                'termines'    => $incidents->where('statut', 'TERMINE')->count(),
                'aujourd_hui' => Incident::where('structure_id', $structureId)
                    ->whereDate('created_at', today())->count(),
            ],
            'incidents_recents' => Incident::where('structure_id', $structureId)
                ->with('agent:id,nom,prenom')
                ->latest()->take(10)->get(),
        ]);
    }

    public function maStructure(Request $request)
    {
        return response()->json(
            Structure::with('responsable:id,nom,prenom')
                ->withCount('agents')
                ->findOrFail($this->structureId($request))
        );
    }

    public function modifierMaStructure(Request $request)
    {
        $structure = Structure::findOrFail($this->structureId($request));
        $structure->update($request->only(
            'nom', 'sigle', 'region', 'departement',
            'commune', 'adresse', 'telephone', 'email'
        ));
        return response()->json(['succes' => true, 'structure' => $structure]);
    }

    public function listeAgents(Request $request)
    {
        return response()->json(
            User::where('role', 'AGENT')
                ->where('structure_id', $this->structureId($request))
                ->select('id', 'identifiant', 'nom', 'prenom', 'actif', 'created_at')
                ->get()
        );
    }

    public function creerAgent(Request $request)
    {
        $request->validate([
            'identifiant'  => 'required|unique:users',
            'mot_de_passe' => 'required|min:6',
            'nom'          => 'required',
            'prenom'       => 'required',
        ]);

        $agent = User::create([
            'identifiant'  => $request->identifiant,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'nom'          => $request->nom,
            'prenom'       => $request->prenom,
            'role'         => 'AGENT',
            'structure_id' => $this->structureId($request),
        ]);

        return response()->json([
            'succes' => true,
            'agent'  => $agent->only('id', 'identifiant', 'nom', 'prenom', 'actif'),
        ], 201);
    }

    public function modifierAgent(Request $request, $id)
    {
        $agent = User::where('role', 'AGENT')
            ->where('structure_id', $this->structureId($request))
            ->findOrFail($id);

        $request->validate([
            'nom'    => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
        ]);

        $agent->update($request->only('nom', 'prenom'));
        return response()->json(['succes' => true, 'agent' => $agent]);
    }

    public function toggleAgent(Request $request, $id)
    {
        $agent = User::where('role', 'AGENT')
            ->where('structure_id', $this->structureId($request))
            ->findOrFail($id);

        $agent->update(['actif' => !$agent->actif]);
        return response()->json(['succes' => true, 'actif' => $agent->actif]);
    }

    public function supprimerAgent(Request $request, $id)
    {
        User::where('role', 'AGENT')
            ->where('structure_id', $this->structureId($request))
            ->findOrFail($id)
            ->delete();

        return response()->json(['succes' => true]);
    }

    public function listeIncidents(Request $request)
    {
        return response()->json(
            Incident::where('structure_id', $this->structureId($request))
                ->with('agent:id,nom,prenom')
                ->latest()->get()
        );
    }

    public function affecterAgent(Request $request, $id)
    {
        $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        $structureId = $this->structureId($request);

        $agent = User::where('id', $request->agent_id)
            ->where('role', 'AGENT')
            ->where('structure_id', $structureId)
            ->firstOrFail();

        $incident = Incident::where('structure_id', $structureId)->findOrFail($id);

        $incident->update([
            'agent_id'          => $agent->id,
            'statut'            => 'AFFECTE',
            'date_intervention' => now(),
        ]);

        return response()->json([
            'succes'   => true,
            'incident' => $incident->load('agent:id,nom,prenom'),
        ]);
    }

    public function changerStatut(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:EN_ATTENTE,AFFECTE,EN_ROUTE,SUR_PLACE,TERMINE,ANNULE',
        ]);

        $incident = Incident::where('structure_id', $this->structureId($request))->findOrFail($id);
        $incident->update(['statut' => $request->statut]);

        return response()->json(['succes' => true, 'incident' => $incident->load('agent:id,nom,prenom')]);
    }

    public function annulerIncident(Request $request, $id)
    {
        $incident = Incident::where('structure_id', $this->structureId($request))->findOrFail($id);
        $incident->update(['statut' => 'ANNULE']);
        return response()->json(['succes' => true]);
    }

    public function listeVictimes(Request $request, $incidentId)
    {
        $incident = Incident::where('structure_id', $this->structureId($request))
            ->findOrFail($incidentId);

        return response()->json($incident->victimes);
    }

    public function ajouterVictime(Request $request, $incidentId)
    {
        $request->validate([
            'nom'    => 'required|string',
            'prenom' => 'required|string',
            'etat'   => 'required|in:leger,grave,critique,decede,inconnu',
        ]);

        $incident = Incident::where('structure_id', $this->structureId($request))
            ->findOrFail($incidentId);

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
        $victime = Victime::findOrFail($id);

        Incident::where('structure_id', $this->structureId($request))
            ->findOrFail($victime->incident_id);

        $victime->delete();
        return response()->json(['succes' => true]);
    }

    public function rapport(Request $request)
    {
        $structureId = $this->structureId($request);
        $annee       = $request->get('annee', date('Y'));
        $mois        = $request->get('mois');
        $requete     = Incident::where('structure_id', $structureId)->whereYear('created_at', $annee);

        if ($mois) {
            $requete->whereMonth('created_at', $mois);
        }

        $incidents = $requete->get();
        $ids       = $incidents->pluck('id');
        $victimes  = Victime::whereIn('incident_id', $ids)->get();

        return response()->json([
            'annee'           => $annee,
            'mois'            => $mois,
            'total_incidents' => $incidents->count(),
            'par_type' => [
                'incendie' => $incidents->where('type_urgence', 'incendie')->count(),
                'accident' => $incidents->where('type_urgence', 'accident')->count(),
                'medical'  => $incidents->where('type_urgence', 'medical')->count(),
                'autre'    => $incidents->where('type_urgence', 'autre')->count(),
            ],
            'par_statut' => [
                'EN_ATTENTE' => $incidents->where('statut', 'EN_ATTENTE')->count(),
                'AFFECTE'    => $incidents->where('statut', 'AFFECTE')->count(),
                'EN_ROUTE'   => $incidents->where('statut', 'EN_ROUTE')->count(),
                'SUR_PLACE'  => $incidents->where('statut', 'SUR_PLACE')->count(),
                'TERMINE'    => $incidents->where('statut', 'TERMINE')->count(),
                'ANNULE'     => $incidents->where('statut', 'ANNULE')->count(),
            ],
            'victimes' => [
                'total'    => $victimes->count(),
                'leger'    => $victimes->where('etat', 'leger')->count(),
                'grave'    => $victimes->where('etat', 'grave')->count(),
                'critique' => $victimes->where('etat', 'critique')->count(),
                'decede'   => $victimes->where('etat', 'decede')->count(),
                'inconnu'  => $victimes->where('etat', 'inconnu')->count(),
            ],
        ]);
    }
}
