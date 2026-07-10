# Architecture technique — Urgences SN

## Authentification

Authentification par token aléatoire (60 chars) stocké en base. Le token est transmis via header `Authorization: Bearer <token>` ou en query param pour l'export CSV.

Le middleware `AuthToken` gère deux niveaux :
- sans rôle : tout utilisateur connecté
- avec rôle (`ADMIN`, `RESPONSABLE`, `AGENT`) : accès restreint, sauf ADMIN qui bypasse

Protection contre le timing attack : `Hash::check` s'exécute même si l'identifiant n'existe pas, pour éviter de révéler l'existence d'un compte via le temps de réponse.

## Rôles et périmètres

| Rôle | Périmètre |
|------|-----------|
| `ADMIN` | Supervision globale, gestion structures et personnel |
| `RESPONSABLE` | Sa structure uniquement — agents, incidents, victimes |
| `AGENT` | Ses missions uniquement — statut, commentaire, victimes |
| Citoyen | Sans compte — déclaration et suivi public |

## Structure de la base de données

```
users           → id, identifiant, mot_de_passe, nom, prenom, role, structure_id, token, actif
structures      → id, nom, sigle, type, region, responsable_id, actif
incidents       → id, type_urgence, statut, adresse, lat/lng, citoyen_*, structure_id, agent_id
incident_agents → incident_id, user_id  (table pivot multi-agents)
victimes        → id, incident_id, nom, prenom, etat, ...
```

## Affectation automatique des incidents

À la déclaration, l'incident est affecté à la structure active correspondant au type d'urgence :

```
incendie / accident → pompiers
medical             → samu
autre               → première structure active disponible
```

## Sécurité

- Injection CSV neutralisée : échappement des guillemets + préfixe `'` sur les caractères de formule (`=`, `+`, `-`, `@`)
- Validation `annee` sur 4 chiffres avant usage dans SQL et nom de fichier
- Race condition sur `creerResponsable` évitée via transaction DB avec `lockForUpdate`
- Suppression de structure bloquée si incidents actifs rattachés

## Choix techniques

- Pas de JWT ni Sanctum : token simple suffisant pour le périmètre du projet
- Pas de framework JS frontend : HTML/CSS/JS vanilla pour rester maintenable
- Table pivot `incident_agents` pour gérer plusieurs agents par incident sans casser le champ `agent_id` existant (rétrocompatibilité)
