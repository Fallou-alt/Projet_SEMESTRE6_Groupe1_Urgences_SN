<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = [
        'identifiant', 'nom', 'prenom', 'mot_de_passe',
        'role', 'actif', 'token', 'structure_id',
    ];

    protected $hidden = ['mot_de_passe', 'token'];

    // Relations
    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'agent_id');
    }

    public function structureResponsable()
    {
        return $this->hasOne(Structure::class, 'responsable_id');
    }

    // Helpers rôles
    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isResponsable(): bool
    {
        return $this->role === 'RESPONSABLE';
    }

    public function isAgent(): bool
    {
        return $this->role === 'AGENT';
    }

    public function isActif(): bool
    {
        return (bool) $this->actif;
    }
}
