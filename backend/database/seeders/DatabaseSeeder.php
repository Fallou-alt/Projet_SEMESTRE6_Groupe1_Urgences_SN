<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
   * Insère les données de base requises
   * pour le fonctionnement de l'application.
   */
    public function run(): void
    {
        // ─── Admin plateforme ─────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['identifiant' => 'admin'],
            [
                'nom'          => 'Admin',
                'prenom'       => 'Super',
                'mot_de_passe' => Hash::make('admin123'),
                'role'         => 'ADMIN',
                'structure_id' => null,
            ]
        );
        // Création des structures de secours utilisées par le système.

        // ─── Structures ───────────────────────────────────────────────────────
        $structuresData = [
            ['nom' => 'Sapeurs-Pompiers de Dakar', 'sigle' => 'SPD',  'type' => 'pompiers',          'region' => 'Dakar'],
            ['nom' => 'SAMU National',              'sigle' => 'SAMU', 'type' => 'samu',              'region' => 'Dakar'],
            ['nom' => 'Police Nationale',           'sigle' => 'PN',   'type' => 'police',            'region' => 'Dakar'],
            ['nom' => 'Gendarmerie Nationale',      'sigle' => 'GN',   'type' => 'gendarmerie',       'region' => 'Dakar'],
            ['nom' => 'Marine Nationale',           'sigle' => 'MN',   'type' => 'marine',            'region' => 'Dakar'],
            ['nom' => 'Protection Civile',          'sigle' => 'PC',   'type' => 'protection_civile', 'region' => 'Dakar'],
        ];
        
        foreach ($structuresData as $s) {
            Structure::firstOrCreate(['sigle' => $s['sigle']], $s);
        }
         // Récupération des principales structures pour les associations suivantes.
        $pompiers = Structure::where('sigle', 'SPD')->first();
        $samu     = Structure::where('sigle', 'SAMU')->first();
        // Création des comptes responsables des différentes structures.

        // ─── Responsables ─────────────────────────────────────────────────────
        $respPompiers = User::firstOrCreate(
            ['identifiant' => 'resp_pompiers'],
            [
                'nom'          => 'Diop',
                'prenom'       => 'Mamadou',
                'mot_de_passe' => Hash::make('resp123'),
                'role'         => 'RESPONSABLE',
                'structure_id' => $pompiers?->id,
            ]
        );

        $respSamu = User::firstOrCreate(
            ['identifiant' => 'resp_samu'],
            [
                'nom'          => 'Fall',
                'prenom'       => 'Aminata',
                'mot_de_passe' => Hash::make('resp123'),
                'role'         => 'RESPONSABLE',
                'structure_id' => $samu?->id,
            ]
        );

        // Rattacher les responsables aux structures correspondantes.
        if ($pompiers && !$pompiers->responsable_id) {
            $pompiers->update(['responsable_id' => $respPompiers->id]);
        }
        if ($samu && !$samu->responsable_id) {
            $samu->update(['responsable_id' => $respSamu->id]);
        }

        // ─── Agents ───────────────────────────────────────────────────────────
        $agentsData = [
            ['identifiant' => 'agent_pompier1', 'nom' => 'Ndiaye',  'prenom' => 'Ibrahima', 'mot_de_passe' => Hash::make('agent123'), 'role' => 'AGENT', 'structure_id' => $pompiers?->id],
            ['identifiant' => 'agent_pompier2', 'nom' => 'Ba',      'prenom' => 'Oumar',    'mot_de_passe' => Hash::make('agent123'), 'role' => 'AGENT', 'structure_id' => $pompiers?->id],
            ['identifiant' => 'agent_samu1',    'nom' => 'Sow',     'prenom' => 'Fatou',    'mot_de_passe' => Hash::make('agent123'), 'role' => 'AGENT', 'structure_id' => $samu?->id],
            ['identifiant' => 'agent_samu2',    'nom' => 'Camara',  'prenom' => 'Lamine',   'mot_de_passe' => Hash::make('agent123'), 'role' => 'AGENT', 'structure_id' => $samu?->id],
        ];

        foreach ($agentsData as $a) {
            User::firstOrCreate(['identifiant' => $a['identifiant']], $a);
        }

        // ─── Incidents de démonstration ───────────────────────────────────────
        $incidentsData = [
            [
                'type_urgence' => 'incendie', 'latitude' => 14.6937, 'longitude' => -17.4441,
                'adresse' => 'Plateau, Dakar', 'description' => 'Incendie dans un immeuble',
                'statut' => 'EN_ATTENTE', 'structure_id' => $pompiers?->id,
            ],
            [
                'type_urgence' => 'medical', 'latitude' => 14.7167, 'longitude' => -17.4677,
                'adresse' => 'Parcelles Assainies', 'description' => 'Malaise cardiaque',
                'statut' => 'AFFECTE', 'structure_id' => $samu?->id,
            ],
            [
                'type_urgence' => 'accident', 'latitude' => 14.7500, 'longitude' => -17.3500,
                'adresse' => 'Route de Rufisque', 'description' => 'Accident de la route',
                'statut' => 'EN_ROUTE', 'structure_id' => $pompiers?->id,
            ],
            [
                'type_urgence' => 'medical', 'latitude' => 14.6800, 'longitude' => -17.4300,
                'adresse' => 'Medina, Dakar', 'description' => 'Femme enceinte en détresse',
                'statut' => 'EN_ATTENTE', 'structure_id' => $samu?->id,
            ],
            [
                'type_urgence' => 'autre', 'latitude' => 14.7300, 'longitude' => -17.4600,
                'adresse' => 'Guediawaye', 'description' => 'Inondation quartier résidentiel',
                'statut' => 'TERMINE', 'structure_id' => $pompiers?->id,
            ],
        ];
         // Insertion des incidents de démonstration dans la base de données. 
        foreach ($incidentsData as $i) {
            Incident::create($i);
        }
    }
}

