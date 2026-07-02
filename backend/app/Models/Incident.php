<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Structure;
use App\Models\User;
use App\Models\Victime;

/**
 * Modèle Incident
 * Gestion des urgences déclarées
 */
class Incident extends Model
{
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
     * Structure responsable
     */
    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    /**
     * Agent affecté
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Victimes liées à l’incident
     */
    public function victimes()
    {
        return $this->hasMany(Victime::class);
    }
}