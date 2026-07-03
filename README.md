# Urgences SN : Plateforme nationale de gestion des urgences

Projet de fin de licence en Informatique de Gestion.

## Contexte

Au Sénégal, la coordination entre les différents corps de secours (pompiers, SAMU, police) reste un défi majeur. Les citoyens ne savent pas toujours quel numéro appeler, et les structures de secours manquent d'outils numériques pour gérer et suivre les interventions en temps réel.

Ce projet propose une plateforme web qui permet :
- aux **citoyens** de signaler une urgence en quelques secondes depuis leur téléphone
- aux **structures de secours** (pompiers, SAMU) de gérer leurs interventions et leurs équipes
- à l'**administration** de superviser l'ensemble des activités sur le territoire

## Stack technique

- **Backend** : Laravel 11 (API REST)
- **Frontend** : HTML/CSS/JS vanilla + Bootstrap 5
- **Base de données** : MySQL
- **Cartographie** : Leaflet.js + OpenStreetMap

J'ai choisi de ne pas utiliser de framework JS (React, Vue) pour le frontend car l'objectif était de rester simple et maintenable, et de me concentrer sur la logique métier plutôt que sur la configuration d'un environnement complexe.

## Installation

### Prérequis
- PHP >= 8.1
- Composer
- MySQL

### Backend

```bash
cd backend
composer install
cp .env.example .env
# configurer DB_DATABASE, DB_USERNAME, DB_PASSWORD dans .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Frontend

Ouvrir `frontend/pages/index.html` directement dans le navigateur, ou servir avec un serveur local.  
L'URL de l'API est configurée dans `frontend/pages/api.js` (`API_URL`).

## Comptes de test

| Rôle | Identifiant | Mot de passe |
|------|-------------|--------------|
| Administrateur | `admin` | `admin123` |
| Responsable Pompiers | `resp_pompiers` | `resp123` |
| Responsable SAMU | `resp_samu` | `resp123` |
| Agent Pompiers | `agent_pompier1` | `agent123` |
| Agent SAMU | `agent_samu1` | `agent123` |

## Fonctionnalités

### Côté citoyen (sans compte)
- Signalement d'urgence avec géolocalisation GPS
- Suivi en temps réel du statut de son incident
- Page d'accueil avec statistiques en direct

### Côté responsable de structure
- Tableau de bord des interventions actives
- Affectation d'agents aux incidents
- Gestion de l'équipe (créer, modifier, activer/désactiver des agents)
- Enregistrement des victimes avec état de santé
- Historique complet avec agents affectés et victimes

### Côté administrateur
- Supervision globale de toutes les structures
- Gestion du personnel (responsables et agents)
- Bilan statistique avec export CSV
- Carte interactive des incidents

## Structure du projet

```
urgences-sn/
├── backend/                  # API Laravel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/  # logique métier
│   │   │   └── Middleware/   # authentification par token
│   │   └── Models/           # Incident, User, Structure, Victime
│   ├── database/
│   │   ├── migrations/       # structure de la BDD
│   │   └── seeders/          # données de test
│   └── routes/api.php        # toutes les routes API
└── frontend/
    └── pages/
        ├── index.html        # accueil citoyen
        ├── sos.html          # formulaire de signalement
        ├── suivi.html        # suivi d'un incident
        ├── login.html        # connexion professionnels
        ├── dashboard-admin.html
        ├── dashboard-pompiers.html
        ├── dashboard-samu.html
        ├── dashboard-agent.html
        ├── api.js            # fonctions communes (fetch, auth, utils)
        └── style.css         # styles globaux + thème dark/light
```

## Difficultés rencontrées

- La gestion des rôles avec un seul middleware a demandé quelques ajustements, notamment pour que l'admin puisse accéder aux routes des responsables sans avoir de `structure_id`
- La géolocalisation GPS sur mobile nécessite HTTPS en production, j'ai mis un fallback sur Dakar pour les tests en local
- Le système de plusieurs agents par incident a nécessité une table pivot `incident_agents` que j'ai ajoutée en cours de développement (la première version n'avait qu'un seul `agent_id`)

## Améliorations possibles

- Notifications push en temps réel (WebSockets / Pusher)
- Application mobile React Native
- Prise en compte de la région pour affecter la structure la plus proche
- Authentification plus robuste (JWT ou Laravel Sanctum)
- Pagination sur les listes d'incidents

---

*Projet réalisé dans le cadre du mémoire de licence — Université de Dakar, 2025*
