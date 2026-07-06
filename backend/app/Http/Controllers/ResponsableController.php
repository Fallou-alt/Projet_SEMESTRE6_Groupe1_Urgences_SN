<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use App\Models\Victime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Contrôleur destiné au "Responsable" d'une structure (caserne, hôpital, etc.).
 * Toutes les actions ici sont automatiquement filtrées sur la structure
 * du responsable connecté : il ne peut jamais voir ou modifier les
 * données d'une autre structure.
 */
class ResponsableController extends Controller
{
    // helper : récupère l'id de la structure du responsable connecté
    /**
     * Petit helper interne qui récupère l'id de la structure
     * du responsable actuellement connecté.
     * On suppose que le middleware d'authentification a déjà
     * injecté l'utilisateur dans la requête via '_user'.
     */
    private function structureId(Request $request): int
    {
        return $request->get('_user')->structure_id;
    }

    /**
     * Tableau de bord du responsable : renvoie des statistiques
     * globales sur sa structure + les 10 derniers incidents.
     */
    public function tableau(Request $request)
    {
        $structureId = $this->structureId($request);

        // On récupère TOUS les incidents de la structure une seule fois,
        // puis on filtre en mémoire (via Collection) pour éviter
        // de refaire plusieurs requêtes SQL séparées.
        $incidents = Incident::where('structure_id', $structureId)->get();

        return response()->json([
            'statistiques' => [
                // Nombre d'agents actifs rattachés à cette structure
                'agents'      => User::where('role', 'AGENT')->where('structure_id', $structureId)->count(),

                // Nombre total d'incidents (toutes périodes confondues)
                'total'       => $incidents->count(),

                // Incidents pas encore pris en charge
                'en_attente'  => $incidents->where('statut', 'EN_ATTENTE')->count(),

                // Incidents en cours de traitement (3 statuts intermédiaires regroupés)
                'en_cours'    => $incidents->whereIn('statut', ['AFFECTE', 'EN_ROUTE', 'SUR_PLACE'])->count(),

                // Incidents clôturés
                'termines'    => $incidents->where('statut', 'TERMINE')->count(),

                // Incidents créés aujourd'hui (requête SQL séparée car
                // la collection $incidents ne contient pas de filtre date précis ici)
                'aujourd_hui' => Incident::where('structure_id', $structureId)
                    ->whereDate('created_at', today())->count(),
            ],

            // Les 10 incidents les plus récents, avec le nom de l'agent affecté
            'incidents_recents' => Incident::where('structure_id', $structureId)
                ->with('agent:id,nom,prenom') // eager loading : évite le problème N+1
                ->latest()->take(10)->get(),
        ]);
    }

    /**
     * Renvoie les informations complètes de la structure du responsable,
     * avec le nom du responsable et le nombre d'agents rattachés.
     */
    public function maStructure(Request $request)
    {
        return response()->json(
            Structure::with('responsable:id,nom,prenom') // relation vers le responsable
                ->withCount('agents')                     // ajoute automatiquement 'agents_count'
                ->findOrFail($this->structureId($request))
        );
    }

    /**
     * Permet au responsable de modifier les informations de sa propre structure
     * (nom, adresse, contact...). On utilise only() pour éviter qu'un champ
     * sensible (ex: id, timestamps) ne soit modifié accidentellement.
     */
    public function modifierMaStructure(Request $request)
    {
        $structure = Structure::findOrFail($this->structureId($request));

        $structure->update($request->only(
            'nom', 'sigle', 'region', 'departement',
            'commune', 'adresse', 'telephone', 'email'
        ));

        return response()->json(['succes' => true, 'structure' => $structure]);
    }

    // agents de la structure du responsable connecté
    /**
     * Liste des agents (rôle AGENT) rattachés à la structure du responsable.
     * On sélectionne uniquement les colonnes utiles pour alléger la réponse.
     */
    public function listeAgents(Request $request)
    {
        return response()->json(
            User::where('role', 'AGENT')
                ->where('structure_id', $this->structureId($request))
                ->select('id', 'identifiant', 'nom', 'prenom', 'actif', 'created_at')
                ->get()
        );
    }

    /**
     * Création d'un nouvel agent rattaché automatiquement
     * à la structure du responsable connecté.
     */
    public function creerAgent(Request $request)
    {
        // Validation des champs obligatoires avant toute création
        $request->validate([
            'identifiant'  => 'required|unique:users', // doit être unique dans la table users
            'mot_de_passe' => 'required|min:6',
            'nom'          => 'required',
            'prenom'       => 'required',
        ]);

        $agent = User::create([
            'identifiant'  => $request->identifiant,
            'mot_de_passe' => Hash::make($request->mot_de_passe), // hachage sécurisé du mot de passe
            'nom'          => $request->nom,
            'prenom'       => $request->prenom,
            'role'         => 'AGENT', // rôle imposé, jamais fourni par le client
            'structure_id' => $this->structureId($request), // rattachement forcé à la bonne structure
        ]);

        return response()->json([
            'succes' => true,
            // On ne renvoie jamais le mot de passe, même haché
            'agent'  => $agent->only('id', 'identifiant', 'nom', 'prenom', 'actif'),
        ], 201); // 201 = ressource créée
    }

    /**
     * Modification des informations (nom/prénom) d'un agent existant.
     * Sécurité : on vérifie que l'agent appartient bien à la structure
     * du responsable avant toute modification (findOrFail échoue sinon).
     */
    public function modifierAgent(Request $request, $id)
    {
        // vérification : l'agent doit appartenir à cette structure
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

    /**
     * Active ou désactive un agent (bascule simple du booléen 'actif').
     * Utile pour suspendre un agent sans supprimer son compte.
     */
    public function toggleAgent(Request $request, $id)
    {
        $agent = User::where('role', 'AGENT')
            ->where('structure_id', $this->structureId($request))
            ->findOrFail($id);

        $agent->update(['actif' => !$agent->actif]); // inversion de la valeur actuelle
        return response()->json(['succes' => true, 'actif' => $agent->actif]);
    }

    /**
     * Suppression définitive d'un agent de la structure.
     * Attention : aucune confirmation ni soft-delete ici, la suppression est directe.
     */
    public function supprimerAgent(Request $request, $id)
    {
        User::where('role', 'AGENT')
            ->where('structure_id', $this->structureId($request))
            ->findOrFail($id)
            ->delete();

        return response()->json(['succes' => true]);
    }

    // incidents de la structure avec agents et victimes
    /**
     * Liste complète des incidents de la structure, avec :
     * - l'agent principal affecté
     * - la liste de tous les agents impliqués (relation many-to-many)
     * - les victimes associées
     * Utilisé aussi bien pour la vue "en cours" que pour l'historique.
     */
    public function listeIncidents(Request $request)
    {
        return response()->json(
            Incident::where('structure_id', $this->structureId($request))
                ->with(['agent:id,nom,prenom', 'agents:users.id,users.nom,users.prenom', 'victimes'])
                ->latest()->get()
        );
    }

    // affectation d'un agent principal à un incident (statut -> AFFECTE)
    /**
     * Affecte un agent principal à un incident.
     * Cette action fait automatiquement passer l'incident
     * au statut 'AFFECTE' et enregistre la date d'intervention.
     */
    public function affecterAgent(Request $request, $id)
    {
        $request->validate([
            'agent_id' => 'required|exists:users,id', // l'agent doit exister dans la BDD
        ]);

        $structureId = $this->structureId($request);

        // Vérifie que l'agent appartient bien à la structure du responsable
        $agent = User::where('id', $request->agent_id)
            ->where('role', 'AGENT')
            ->where('structure_id', $structureId)
            ->firstOrFail();

        $incident = Incident::where('structure_id', $structureId)->findOrFail($id);

        $incident->update([
            'agent_id'          => $agent->id,
            'statut'            => 'AFFECTE',
            'date_intervention' => now(), // horodatage de l'affectation
        ]);

        return response()->json([
            'succes'   => true,
            'incident' => $incident->load('agent:id,nom,prenom'), // recharge la relation à jour
        ]);
    }

    /**
     * Change le statut d'un incident (cycle de vie de l'intervention).
     * La liste des statuts autorisés est strictement validée
     * pour éviter d'enregistrer une valeur incohérente.
     */
    public function changerStatut(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:EN_ATTENTE,AFFECTE,EN_ROUTE,SUR_PLACE,TERMINE,ANNULE',
        ]);

        $incident = Incident::where('structure_id', $this->structureId($request))->findOrFail($id);
        $incident->update(['statut' => $request->statut]);

        return response()->json(['succes' => true, 'incident' => $incident->load('agent:id,nom,prenom')]);
    }

    /**
     * Raccourci pour annuler directement un incident
     * (équivalent à changerStatut avec 'ANNULE', mais sans validation supplémentaire).
     */
    public function annulerIncident(Request $request, $id)
    {
        $incident = Incident::where('structure_id', $this->structureId($request))->findOrFail($id);
        $incident->update(['statut' => 'ANNULE']);
        return response()->json(['succes' => true]);
    }

    /**
     * Liste des victimes rattachées à un incident précis.
     */
    public function listeVictimes(Request $request, $incidentId)
    {
        // On vérifie d'abord que l'incident appartient à la structure du responsable
        $incident = Incident::where('structure_id', $this->structureId($request))
            ->findOrFail($incidentId);

        return response()->json($incident->victimes);
    }

    /**
     * Ajout d'une nouvelle victime à un incident.
     */
    public function ajouterVictime(Request $request, $incidentId)
    {
        $request->validate([
            'nom'    => 'required|string',
            'prenom' => 'required|string',
            'etat'   => 'required|in:leger,grave,critique,decede,inconnu', // état de santé obligatoire
        ]);

        $incident = Incident::where('structure_id', $this->structureId($request))
            ->findOrFail($incidentId);

        $victime = Victime::create([
            'incident_id'    => $incident->id,
            'nom'            => $request->nom,
            'prenom'         => $request->prenom,
            'age'            => $request->age, // optionnel
            'sexe'           => $request->sexe ?? 'inconnu', // valeur par défaut si non fournie
            'telephone'      => $request->telephone,
            'groupe_sanguin' => $request->groupe_sanguin ?? 'inconnu',
            'etat'           => $request->etat,
            'observations'   => $request->observations,
        ]);

        return response()->json(['succes' => true, 'victime' => $victime], 201);
    }

    /**
     * Suppression d'une victime.
     * Sécurité : on vérifie que la victime appartient bien à un incident
     * de la structure du responsable avant de la supprimer.
     */
    public function supprimerVictime(Request $request, $id)
    {
        $victime = Victime::findOrFail($id);

        // vérification : la victime doit appartenir à un incident de cette structure
        // Vérification de sécurité : l'incident lié doit appartenir à cette structure
        // (sinon findOrFail lève une exception 404)
        Incident::where('structure_id', $this->structureId($request))
            ->findOrFail($victime->incident_id);

        $victime->delete();
        return response()->json(['succes' => true]);
    }

    // gestion de l'équipe affectée à un incident (multi-agents)
    /**
     * Liste de TOUS les agents affectés à un incident donné
     * (relation many-to-many, plusieurs agents possibles par incident).
     */
    public function listeAgentsIncident(Request $request, $incidentId)
    {
        $incident = Incident::where('structure_id', $this->structureId($request))->findOrFail($incidentId);
        return response()->json($incident->agents);
    }

    /**
     * Ajoute un agent à l'équipe d'un incident (relation many-to-many).
     * syncWithoutDetaching() permet d'ajouter l'agent sans supprimer
     * les agents déjà présents, et sans créer de doublon s'il est déjà lié.
     */
    public function ajouterAgentIncident(Request $request, $incidentId)
    {
        $request->validate(['agent_id' => 'required|exists:users,id']);

        $incident = Incident::where('structure_id', $this->structureId($request))->findOrFail($incidentId);
        $incident->agents()->syncWithoutDetaching([$request->agent_id]);

        return response()->json(['succes' => true]);
    }

    /**
     * Retire un agent de l'équipe affectée à un incident
     * (détache la relation many-to-many sans supprimer l'agent lui-même).
     */
    public function retirerAgentIncident(Request $request, $incidentId, $agentId)
    {
        $incident = Incident::where('structure_id', $this->structureId($request))->findOrFail($incidentId);
        $incident->agents()->detach($agentId);

        return response()->json(['succes' => true]);
    }

    // rapport statistique mensuel/annuel de la structure
    /**
     * Génère un rapport statistique de la structure sur une période donnée
     * (année obligatoire, mois optionnel). Utilisé pour les bilans
     * mensuels ou annuels.
     */
    public function rapport(Request $request)
    {
        $structureId = $this->structureId($request);

        // Année par défaut = année en cours si non précisée dans la requête
        $annee = $request->get('annee', date('Y'));
        $mois  = $request->get('mois');

        $requete = Incident::where('structure_id', $structureId)->whereYear('created_at', $annee);

        // Filtre additionnel par mois si fourni
        if ($mois) {
            $requete->whereMonth('created_at', $mois);
        }

        $incidents = $requete->get()

        // Récupère toutes les victimes liées aux incidents de la période
        $ids      = $incidents->pluck('id');
        $victimes = Victime::whereIn('incident_id', $ids)->get();

        return response()->json([
            'annee'           => $annee,
            'mois'            => $mois,
            'total_incidents' => $incidents->count(),

            // Répartition des incidents par type d'urgence
            'par_type' => [
                'incendie' => $incidents->where('type_urgence', 'incendie')->count(),
                'accident' => $incidents->where('type_urgence', 'accident')->count(),
                'medical'  => $incidents->where('type_urgence', 'medical')->count(),
                'autre'    => $incidents->where('type_urgence', 'autre')->count(),
            ],

            // Répartition des incidents par statut (utile pour suivre l'efficacité)
            'par_statut' => [
                'EN_ATTENTE' => $incidents->where('statut', 'EN_ATTENTE')->count(),
                'AFFECTE'    => $incidents->where('statut', 'AFFECTE')->count(),
                'EN_ROUTE'   => $incidents->where('statut', 'EN_ROUTE')->count(),
                'SUR_PLACE'  => $incidents->where('statut', 'SUR_PLACE')->count(),
                'TERMINE'    => $incidents->where('statut', 'TERMINE')->count(),
                'ANNULE'     => $incidents->where('statut', 'ANNULE')->count(),
            ],

            // Répartition des victimes par gravité de leur état
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