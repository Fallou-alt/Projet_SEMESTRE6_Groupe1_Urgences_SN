<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Victime
 * Représente une personne impliquée dans un incident.
 * Une victime est toujours rattachée à un incident existant.
 */
class Victime extends Model
{
    protected $fillable = [
        'incident_id', 'nom', 'prenom', 'age', 'sexe',
        'telephone', 'groupe_sanguin', 'etat', 'observations',
    ];

    protected $casts = [
        'age' => 'integer',
    ];

    // états possibles d'une victime
    public const ETATS = ['leger', 'grave', 'critique', 'decede', 'inconnu'];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    // vérifie si la victime est dans un état critique ou décédée
    public function estEnDanger(): bool
    {
        return in_array($this->etat, ['critique', 'decede']);
    }
}
