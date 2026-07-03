<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Incident.
 * Représente une urgence déclarée par un citoyen.
 */
class Incident extends Model
{
<<<<<<< HEAD
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_EN_COURS   = 'EN_COURS';
    public const STATUT_TERMINE    = 'TERMINE';
    public const STATUT_ANNULE     = 'ANNULE';

=======
        /**
     * Statuts possibles d'un incident.
     */
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_TERMINE = 'TERMINE';
    public const STATUT_ANNULE = 'ANNULE';
    /**
     * Attributs pouvant être remplis en masse.
     *
     * @var array<int, string>
     */
>>>>>>> b42be2a (refactor: ajouter des constantes pour les statuts des incidents)
    protected $fillable = [
        'type_urgence', 'latitude', 'longitude', 'adresse', 'description',
        'citoyen_nom', 'citoyen_telephone', 'statut', 'commentaire',
        'date_intervention', 'structure_id', 'agent_id',
    ];

    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function agents()
    {
        return $this->belongsToMany(User::class, 'incident_agents', 'incident_id', 'user_id')
                    ->select('users.id', 'users.nom', 'users.prenom', 'users.identifiant');
    }

    public function victimes()
    {
        return $this->hasMany(Victime::class);
    }
}
