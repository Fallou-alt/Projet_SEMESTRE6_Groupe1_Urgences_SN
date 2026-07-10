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
    // Données du tableau de bord admin : statistiques globales + les 10 derniers incidents
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

        $request->validate([
            'nom'  => 'sometimes|required|string',
            'type' => 'sometimes|required|in:pompiers,samu,police,gendarmerie,marine,protection_civile,autre',
        ]);

        $structure->update($request->only(
            'nom', 'sigle', 'type', 'region', 'departement',
            'commune', 'adresse', 'telephone', 'email',
            'responsable_nom', 'responsable_titre'
        ));
        return response()->json(['succes' => true, 'structure' => $structure]);
    }

    public function supprimerStructure($id)
    {
        $structure = Structure::findOrFail($id);

        // On empêche la suppression tant qu'il existe des incidents
        // non terminés/non annulés rattachés à la structure.
        $incidentsActifs = Incident::where('structure_id', $id)
            ->whereNotIn('statut', ['TERMINE', 'ANNULE'])
            ->count();

        if ($incidentsActifs > 0) {
            return response()->json([
                'succes'  => false,
                'message' => "Impossible de supprimer cette structure : {$incidentsActifs} incident(s) actif(s) y sont rattachés.",
            ], 409);
        }

        // On empêche également la suppression tant que du personnel
        // (responsables ou agents) est encore rattaché à la structure.
        // Sinon ces utilisateurs se retrouvent avec un structure_id pointant
        // vers une structure inexistante.
        $personnelRattache = User::where('structure_id', $id)->count();

        if ($personnelRattache > 0) {
            return response()->json([
                'succes'  => false,
                'message' => "Impossible de supprimer cette structure : {$personnelRattache} membre(s) du personnel y sont rattaché(s).",
            ], 409);
        }

        $structure->delete();
        return response()->json(['succes' => true]);
    }

    public function toggleStructure($id)
    {
        $structure = Structure::findOrFail($id);
        $structure->update(['actif' => !$structure->actif]);
        return response()->json(['succes' => true, 'actif' => $structure->actif]);
    }

    // Liste tout le personnel (responsables + agents)
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
            'structure_id' => 'required|exists:structures,id',
        ]);

        // La vérification "a-t-elle déjà un responsable ?" et l'assignation
        // se font dans la même transaction, avec verrou pessimiste sur
        // la ligne de la structure. Ainsi, si deux requêtes concurrentes
        // arrivent en même temps pour la même structure, la deuxième
        // attend que la première ait fini avant de relire l'état à jour de
        // responsable_id, ce qui évite d'assigner deux responsables à la
        // même structure.
        $resultat = DB::transaction(function () use ($request) {
            $structure = Structure::where('id', $request->structure_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($structure->responsable_id) {
                return null;
            }

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

        if ($resultat === null) {
            return response()->json([
                'succes'  => false,
                'message' => 'Cette structure a déjà un responsable assigné. Retirez-le avant d\'en assigner un nouveau.',
            ], 422);
        }

        return response()->json([
            'succes'      => true,
            // Model::only() n'existe pas sur Eloquent Model (c'est une méthode
            // de Request/Collection) : on passe donc par collect() pour
            // pouvoir filtrer les champs à renvoyer sans exposer mot_de_passe.
            'responsable' => collect($resultat)->only(['id', 'identifiant', 'nom', 'prenom', 'role', 'actif', 'structure_id']),
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
            // Model::only() n'existe pas sur Eloquent Model (c'est une méthode
            // de Request/Collection) : on passe donc par collect() pour
            // pouvoir filtrer les champs à renvoyer sans exposer mot_de_passe.
            'agent'  => collect($utilisateur)->only(['id', 'identifiant', 'nom', 'prenom', 'role', 'actif', 'structure_id']),
        ], 201);
    }

    public function toggleUtilisateur($id)
    {
        $utilisateur = User::findOrFail($id);
        $utilisateur->update(['actif' => !$utilisateur->actif]);
        return response()->json(['succes' => true, 'actif' => $utilisateur->actif]);
    }

    public function supprimerUtilisateur($id)
    {
        $utilisateur = User::findOrFail($id);

        if ($utilisateur->role === 'ADMIN') {
            return response()->json(['succes' => false, 'message' => 'Impossible de supprimer un administrateur.'], 403);
        }

        // Libérer la structure si c'était un responsable
        if ($utilisateur->role === 'RESPONSABLE') {
            Structure::where('responsable_id', $utilisateur->id)->update(['responsable_id' => null]);
        }

        $utilisateur->delete();
        return response()->json(['succes' => true]);
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
        $request->validate([
            'annee' => 'sometimes|integer|digits:4',
            'mois'  => 'sometimes|integer|between:1,12',
        ]);

        $annee   = (int) $request->get('annee', date('Y'));
        // Cast explicite en entier (ou null), pour que le mois soit
        // toujours un vrai entier (ou null) dans le JSON de sortie, et
        // jamais une chaîne de caractères.
        $mois    = $request->filled('mois') ? (int) $request->get('mois') : null;
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

    // Export CSV pour le bilan annuel
    public function exporterCsv(Request $request)
    {
        $request->validate([
            'annee' => 'sometimes|integer|digits:4',
        ]);

        $annee     = (int) $request->get('annee', date('Y'));
        $incidents = Incident::whereYear('created_at', $annee)
            ->with(['agent', 'structure'])
            ->latest()->get();

        // Le BOM UTF-8 (\xEF\xBB\xBF) est placé en tête du fichier pour
        // qu'Excel, qui suppose de l'ANSI/Latin-1 par défaut, reconnaisse
        // l'encodage UTF-8 et affiche correctement les caractères accentués
        // (noms, adresses, structures...) au lieu de les afficher mal
        // (ex: "Ã©" au lieu de "é").
        $csv = "\xEF\xBB\xBF";
        $csv .= "ID,Type,Statut,Adresse,Citoyen,Telephone,Date,Structure\n";

        foreach ($incidents as $incident) {
            $structure = $incident->structure?->nom ?? 'Non assignée';
            $csv .= implode(',', [
                $incident->id,
                // Ces champs passent tous par la sécurisation CSV, par
                // cohérence et par précaution si les valeurs autorisées
                // évoluent un jour.
                '"' . $this->champCsvSecurise($incident->type_urgence) . '"',
                '"' . $this->champCsvSecurise($incident->statut) . '"',
                '"' . $this->champCsvSecurise($incident->adresse) . '"',
                '"' . $this->champCsvSecurise($incident->citoyen_nom) . '"',
                '"' . $this->champCsvSecurise($incident->citoyen_telephone) . '"',
                '"' . $this->champCsvSecurise((string) $incident->created_at) . '"',
                '"' . $this->champCsvSecurise($structure) . '"',
            ]) . "\n";
        }

        return response($csv, 200, [
            // Le charset=UTF-8 est précisé explicitement dans le
            // Content-Type, en complément du BOM, pour que tout client
            // HTTP ou tableur qui lit l'en-tête sache que le corps est
            // bien encodé en UTF-8.
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=bilan_urgences_{$annee}.csv",
        ]);
    }

    /**
     * Prépare une valeur pour l'export CSV : échappe les guillemets et
     * neutralise les caractères de début de formule (=, +, -, @) afin
     * qu'un tableur comme Excel n'interprète jamais le contenu comme une formule.
     */
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