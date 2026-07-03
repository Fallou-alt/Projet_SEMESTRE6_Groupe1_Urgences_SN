<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use App\Models\Victime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
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
            'commune', 'adresse', 'telephone', 'email'
        ));

        return response()->json(['succes' => true, 'structure' => $structure], 201);
    }

    public function modifierStructure(Request $request, $id)
    {
        $structure = Structure::findOrFail($id);

        // Correction : on valide désormais les champs modifiables,
        // comme c'est déjà le cas à la création.
        $request->validate([
            'nom'  => 'sometimes|required|string',
            'type' => 'sometimes|required|in:pompiers,samu,police,gendarmerie,marine,protection_civile,autre',
        ]);

        $structure->update($request->only(
            'nom', 'sigle', 'type', 'region', 'departement',
            'commune', 'adresse', 'telephone', 'email'
        ));
        return response()->json(['succes' => true, 'structure' => $structure]);
    }

    public function supprimerStructure($id)
    {
        Structure::findOrFail($id)->delete();
        return response()->json(['succes' => true]);
    }

    public function toggleStructure($id)
    {
        $structure = Structure::findOrFail($id);
        $structure->update(['actif' => !$structure->actif]);
        return response()->json(['succes' => true, 'actif' => $structure->actif]);
    }

    public function listeResponsables()
    {
        return response()->json(
            User::where('role', 'RESPONSABLE')
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
            'structure_id' => 'required|exists:structures,id',
        ]);

        $structure = Structure::findOrFail($request->structure_id);
        if ($structure->responsable_id) {
            return response()->json([
                'succes'  => false,
                'message' => 'Cette structure a déjà un responsable assigné.',
            ], 422);
        }

        // Correction : transaction pour éviter un état incohérent
        // (utilisateur créé mais structure non mise à jour, ou inversement).
        $utilisateur = DB::transaction(function () use ($request, $structure) {
            $utilisateur = User::create([
                'identifiant'  => $request->identifiant,
                'mot_de_passe' => Hash::make($request->mot_de_passe),
                'nom'          => $request->nom,
                'prenom'       => $request->prenom,
                'role'         => 'RESPONSABLE',
                'structure_id' => $request->structure_id,
            ]);

            $structure->update(['responsable_id' => $utilisateur->id]);

            return $utilisateur;
        });

        return response()->json([
            'succes'      => true,
            'responsable' => $utilisateur->only('id', 'identifiant', 'nom', 'prenom', 'role', 'actif', 'structure_id'),
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
        // Correction : validation légère des paramètres de la requête.
        $request->validate([
            'annee' => 'sometimes|integer|digits:4',
            'mois'  => 'sometimes|integer|between:1,12',
        ]);

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
                    fn($i) => (int) $i->created_at->format('m') === $m
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
                '"' . $this->champCsvSecurise($incident->adresse) . '"',
                '"' . $this->champCsvSecurise($incident->citoyen_nom) . '"',
                // Correction : le téléphone (souvent préfixé par "+") passe
                // désormais aussi par champCsvSecurise() pour éviter une
                // injection de formule CSV dans Excel/LibreOffice.
                '"' . $this->champCsvSecurise($incident->citoyen_telephone) . '"',
                $incident->created_at,
                '"' . $this->champCsvSecurise($structure) . '"',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=bilan_urgences_{$annee}.csv",
        ]);
    }

    private function champCsvSecurise(?string $valeur): string
    {
        $valeur = $valeur ?? '';
        $valeur = str_replace('"', '""', $valeur);

        if ($valeur !== '' && in_array($valeur[0], ['=', '+', '-', '@'], true)) {
            $valeur = "'" . $valeur;
        }

        return $valeur;
    }
}