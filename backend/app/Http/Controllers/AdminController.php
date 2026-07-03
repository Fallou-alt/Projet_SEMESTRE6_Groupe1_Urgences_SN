<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use App\Models\Victime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // données du tableau de bord admin (stats globales + incidents récents)
    public function tableau()
    {
        return response()->json([
            'statistiques' => [
                'structures'   => Structure::count(),
                'responsables' => User::where('role', 'RESPONSABLE')->count(),
                'agents'       => User::where('role', 'AGENT')->count(),
                'incidents'    => Incident::count(),
                'victimes'     => Victime::count(),
                'en_attente'   => Incident::where('statut', 'EN_ATTENTE')->count(),
                'en_cours'     => Incident::whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])->count(),
                'termines'     => Incident::where('statut', 'TERMINE')->count(),
                'aujourd_hui'  => Incident::whereDate('created_at', today())->count(),
            ],
            'incidents_recents' => Incident::with('structure:id,nom,sigle', 'agent:id,nom,prenom')
                ->latest()->take(10)->get(),
        ]);
    }

    public function listeStructures()
    {
        return response()->json(
            Structure::with('responsable:id,nom,prenom')
                ->withCount('agents')
                ->latest()->get()
        );
    }

    public function creerStructure(Request $request)
    {
        $request->validate([
            'nom'  => 'required|string',
            'type' => 'required|in:pompiers,samu,police,gendarmerie,marine,protection_civile,autre',
        ]);

        $structure = Structure::create($request->only(
            'nom', 'sigle', 'type', 'region', 'departement',
            'commune', 'adresse', 'telephone', 'email',
            'responsable_nom', 'responsable_titre'
        ));

        return response()->json(['succes' => true, 'structure' => $structure], 201);
    }

    public function modifierStructure(Request $request, $id)
    {
        $structure = Structure::findOrFail($id);
        $structure->update($request->only(
            'nom', 'sigle', 'type', 'region', 'departement',
            'commune', 'adresse', 'telephone', 'email',
            'responsable_nom', 'responsable_titre'
        ));
        return response()->json(['succes' => true, 'structure' => $structure]);
    }

    public function supprimerStructure($id)
    {
        // TODO: vérifier qu'il n'y a pas d'incidents actifs avant de supprimer
        Structure::findOrFail($id)->delete();
        return response()->json(['succes' => true]);
    }

    public function toggleStructure($id)
    {
        $structure = Structure::findOrFail($id);
        $structure->update(['actif' => !$structure->actif]);
        return response()->json(['succes' => true, 'actif' => $structure->actif]);
    }

    // liste tout le personnel (responsables + agents)
    public function listeResponsables()
    {
        return response()->json(
            User::whereIn('role', ['RESPONSABLE', 'AGENT'])
                ->with('structure:id,nom,sigle')
                ->select('id', 'identifiant', 'nom', 'prenom', 'role', 'actif', 'structure_id', 'created_at')
                ->get()
        );
    }

    public function creerResponsable(Request $request)
    {
        $request->validate([
            'identifiant'  => 'required|unique:users',
            'mot_de_passe' => 'required|min:6',
            'nom'          => 'required',
            'prenom'       => 'required',
            'structure_id' => 'nullable|exists:structures,id',
        ]);

        $utilisateur = User::create([
            'identifiant'  => $request->identifiant,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'nom'          => $request->nom,
            'prenom'       => $request->prenom,
            'role'         => 'RESPONSABLE',
            'structure_id' => $request->structure_id,
        ]);

        // lier le responsable à sa structure
        if ($request->structure_id) {
            Structure::where('id', $request->structure_id)
                ->update(['responsable_id' => $utilisateur->id]);
        }

        return response()->json([
            'succes'      => true,
            'responsable' => $utilisateur->only('id', 'identifiant', 'nom', 'prenom', 'role', 'actif', 'structure_id'),
        ], 201);
    }

    public function creerAgent(Request $request)
    {
        $request->validate([
            'identifiant'  => 'required|unique:users',
            'mot_de_passe' => 'required|min:6',
            'nom'          => 'required',
            'prenom'       => 'required',
            'structure_id' => 'nullable|exists:structures,id',
        ]);

        $utilisateur = User::create([
            'identifiant'  => $request->identifiant,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'nom'          => $request->nom,
            'prenom'       => $request->prenom,
            'role'         => 'AGENT',
            'structure_id' => $request->structure_id,
        ]);

        return response()->json([
            'succes' => true,
            'agent'  => $utilisateur->only('id', 'identifiant', 'nom', 'prenom', 'role', 'actif', 'structure_id'),
        ], 201);
    }

    public function toggleUtilisateur($id)
    {
        $utilisateur = User::findOrFail($id);
        $utilisateur->update(['actif' => !$utilisateur->actif]);
        return response()->json(['succes' => true, 'actif' => $utilisateur->actif]);
    }

    public function listeIncidents()
    {
        return response()->json(
            Incident::with('structure:id,nom,sigle', 'agent:id,nom,prenom')
                ->latest()->get()
        );
    }

    public function statistiques(Request $request)
    {
        $annee   = $request->get('annee', date('Y'));
        $mois    = $request->get('mois');
        $requete = Incident::whereYear('created_at', $annee);

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
            'par_mois' => collect(range(1, 12))->map(fn($m) => [
                'mois'  => $m,
                'total' => $incidents->filter(
                    fn($i) => (int) date('m', strtotime($i->created_at)) === $m
                )->count(),
            ]),
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

    // export CSV pour le bilan annuel
    public function exporterCsv(Request $request)
    {
        $annee     = $request->get('annee', date('Y'));
        $incidents = Incident::whereYear('created_at', $annee)
            ->with(['agent', 'structure'])
            ->latest()->get();

        $csv = "ID,Type,Statut,Adresse,Citoyen,Telephone,Date,Structure\n";

        foreach ($incidents as $incident) {
            $structure = $incident->structure?->nom ?? 'Non assignée';
            $csv .= implode(',', [
                $incident->id,
                $incident->type_urgence,
                $incident->statut,
                '"' . str_replace('"', '""', $incident->adresse ?? '') . '"',
                '"' . str_replace('"', '""', $incident->citoyen_nom ?? '') . '"',
                $incident->citoyen_telephone ?? '',
                $incident->created_at,
                '"' . $structure . '"',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=bilan_urgences_{$annee}.csv",
        ]);
    }
}
