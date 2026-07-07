<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
/**
 * Représente une structure d'intervention du système
 * (pompiers, SAMU, police, gendarmerie, etc.).
 */
class Structure extends Model
{
    /**
 * Liste des attributs pouvant être assignés en masse.
 */
    protected $fillable = [
        'nom', 'sigle', 'type', 'region', 'departement', 'commune',
        'adresse', 'telephone', 'email', 'responsable_id', 'actif',
    ];
    /**
    * Récupère le responsable lié à cette structure.
    */

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function agents()
    {
        return $this->hasMany(User::class)->where('role', 'AGENT');
    }
     /**
     * Renvoie tous les utilisateurs rattachés à cette structure.
     */

    public function users()
    {
        return $this->hasMany(User::class);
    }
      /**
     * Liste des incidents pris en charge par cette structure.
     */
    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}

