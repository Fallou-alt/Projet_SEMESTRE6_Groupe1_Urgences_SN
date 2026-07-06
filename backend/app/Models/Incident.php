<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Incident
 *
 * Représente une urgence déclarée par un citoyen dans le système.
 * Chaque incident peut être assigné à une structure et à des agents.
 */
class Incident extends Model
{
   /**
 * Statuts possibles d'un incident dans le système
 */
public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
public const STATUT_EN_COURS = 'EN_COURS';
public const STATUT_TERMINE = 'TERMINE';
public const STATUT_ANNULE = 'ANNULE';

    /**
     * Champs autorisés en écriture
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
 * Structure responsable de l'incident
 */
public function structure()
{
    return $this->belongsTo(\App\Models\Structure::class);
}

    /**
     * Agent assigné
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
 * Liste des victimes liées à l'incident
 */
public function victimes()
{
    return $this->hasMany(\App\Models\Victime::class);
}
}