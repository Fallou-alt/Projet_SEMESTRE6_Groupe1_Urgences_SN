<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ResponsableController;
use App\Http\Controllers\AgentController;
use App\Http\Middleware\AuthToken;
use Illuminate\Support\Facades\Route;

// Déclaration d'urgence et suivi public (citoyens)
Route::post('/incidents',            [IncidentController::class, 'declarer']);
Route::get('/incidents/{id}/suivi',  [IncidentController::class, 'suivi']);
Route::get('/stats',                 [IncidentController::class, 'statistiquesPubliques']);

// Authentification
Route::post('/connexion',  [AuthController::class, 'connexion']);
// TODO: ajouter une route d'inscription pour les citoyens qui veulent suivre leurs incidents

Route::middleware(AuthToken::class)->group(function () {
    Route::post('/deconnexion',       [AuthController::class, 'deconnexion']);
    Route::patch('/profil/password',  [AuthController::class, 'modifierMotDePasse']);
    Route::patch('/profil/identite',  [AuthController::class, 'modifierProfil']);
});

// Espace administrateur
Route::middleware(AuthToken::class . ':ADMIN')->prefix('admin')->group(function () {
    Route::get('/tableau',                      [AdminController::class, 'tableau']);

    Route::get('/structures',                   [AdminController::class, 'listeStructures']);
    Route::post('/structures',                  [AdminController::class, 'creerStructure']);
    Route::put('/structures/{id}',              [AdminController::class, 'modifierStructure']);
    Route::delete('/structures/{id}',           [AdminController::class, 'supprimerStructure']);
    Route::patch('/structures/{id}/toggle',     [AdminController::class, 'toggleStructure']);

    Route::get('/responsables',                 [AdminController::class, 'listeResponsables']);
    Route::post('/responsables',                [AdminController::class, 'creerResponsable']);
    Route::patch('/utilisateurs/{id}/toggle',   [AdminController::class, 'toggleUtilisateur']);

    Route::get('/incidents',                    [AdminController::class, 'listeIncidents']);
    Route::get('/statistiques',                 [AdminController::class, 'statistiques']);
    Route::get('/export-csv',                   [AdminController::class, 'exporterCsv']);
});

// Espace responsable de structure
Route::middleware(AuthToken::class . ':RESPONSABLE')->prefix('responsable')->group(function () {
    Route::get('/tableau',                      [ResponsableController::class, 'tableau']);

    Route::get('/structure',                    [ResponsableController::class, 'maStructure']);
    Route::patch('/structure',                  [ResponsableController::class, 'modifierMaStructure']);

    Route::get('/agents',                       [ResponsableController::class, 'listeAgents']);
    Route::post('/agents',                      [ResponsableController::class, 'creerAgent']);
    Route::patch('/agents/{id}',                [ResponsableController::class, 'modifierAgent']);
    Route::patch('/agents/{id}/toggle',         [ResponsableController::class, 'toggleAgent']);
    Route::delete('/agents/{id}',               [ResponsableController::class, 'supprimerAgent']);

    Route::get('/incidents',                    [ResponsableController::class, 'listeIncidents']);
    Route::patch('/incidents/{id}/affecter',    [ResponsableController::class, 'affecterAgent']);
    Route::patch('/incidents/{id}/annuler',     [ResponsableController::class, 'annulerIncident']);

    Route::get('/incidents/{id}/victimes',      [ResponsableController::class, 'listeVictimes']);
    Route::post('/incidents/{id}/victimes',     [ResponsableController::class, 'ajouterVictime']);
    Route::delete('/victimes/{id}',             [ResponsableController::class, 'supprimerVictime']);

    Route::get('/rapport',                      [ResponsableController::class, 'rapport']);
});

// Espace agent
Route::middleware(AuthToken::class . ':AGENT')->prefix('agent')->group(function () {
    Route::get('/tableau',                      [AgentController::class, 'tableau']);
    Route::get('/missions',                     [AgentController::class, 'mesMissions']);
    Route::get('/historique',                   [AgentController::class, 'historique']);
    Route::patch('/missions/{id}/statut',       [AgentController::class, 'changerStatut']);
    Route::patch('/missions/{id}/commentaire',  [AgentController::class, 'ajouterCommentaire']);
});
