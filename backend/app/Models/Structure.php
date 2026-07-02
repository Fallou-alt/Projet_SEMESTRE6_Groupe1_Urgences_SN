<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Structure extends Model
{
    protected $fillable = [
        'nom', 'sigle', 'type', 'region', 'departement', 'commune',
        'adresse', 'telephone', 'email', 'responsable_id', 'actif',
    ];

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function agents()
    {
        return $this->hasMany(User::class)->where('role', 'AGENT');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
// Documentation ajoutée par Mbene
