<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
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

    public function victimes()
    {
        return $this->hasMany(Victime::class);
    }
}
