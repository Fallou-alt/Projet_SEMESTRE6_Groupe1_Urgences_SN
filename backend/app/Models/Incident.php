<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Incident
 *
 * Représente une urgence déclarée par un citoyen.
 */
class Incident extends Model
{
    /**
     * Statuts centralisés d'un incident
     */
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_EN_COURS   = 'EN_COURS';
    public const STATUT_TERMINE    = 'TERMINE';
    public const STATUT_ANNULE     = 'ANNULE';

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
     * Structure liée à l'incident
     */
    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    /**
     * Agent assigné
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Victimes liées à l'incident
     */
    public function victimes()
    {
        return $this->hasMany(Victime::class);
    }
}