<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * Modèle Incident.
 *
 * Représente une urgence déclarée par un citoyen.
 */
class Incident extends Model
{
    /**
     * Statuts possibles d'un incident.
     */
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_AFFECTE    = 'AFFECTE';
    public const STATUT_EN_ROUTE   = 'EN_ROUTE';
    public const STATUT_SUR_PLACE  = 'SUR_PLACE';
    public const STATUT_TERMINE    = 'TERMINE';
    public const STATUT_ANNULE     = 'ANNULE';


    /**
     * Liste des attributs pouvant être remplis
     * automatiquement lors de la création ou de
     * la mise à jour d'un incident.
     */
    /**
 * Attributs autorisés pour l'assignation de masse.
 */
    protected $fillable = [
        'type_urgence',
        'latitude',
        'longitude',
        'adresse',
        'description',
        'citoyen_nom',
        'citoyen_telephone',
        'statut',
        'commentaire',
        'date_intervention',
        'structure_id',
        'agent_id',
    ];


    /**
     * Retourne la structure
     * chargée de traiter l'incident.
     */
    public function structure()
    {
        return $this->belongsTo(\App\Models\Structure::class);
    }


    /**
     * Retourne l'agent responsable
     * de la prise en charge de l'incident.
     */
    public function agent()
    {
        /**
 * Retourne tous les agents associés à l'incident.
 *
 * Relation plusieurs-à-plusieurs avec les utilisateurs.
 */
        return $this->belongsTo(User::class, 'agent_id');
    }


    /**
     * Retourne la liste des agents
     * affectés à l'incident.
     */
    public function agents()
    {
        return $this->belongsToMany(
            User::class,
            'incident_agents',
            'incident_id',
            'user_id'
        )->select(
            'users.id',
            'users.nom',
            'users.prenom',
            'users.identifiant'
        );
    }


    /**
     * Retourne les victimes liées
     * à cet incident.
     */
    public function victimes()
    {
        return $this->hasMany(Victime::class);
    }
}