# 🚨 Urgences SN : Plateforme nationale de gestion des urgences

Plateforme web de gestion des urgences pour le Sénégal.  
Permet à tout citoyen de signaler une urgence et aux secours (Pompiers, SAMU) de coordonner leurs interventions en temps réel.

---

## Stack technique

| Couche | Technologie |
|---|---|
| Structure | HTML5 |
| Style | CSS3 + Bootstrap 5.3.3 |
| Logique client | JavaScript Vanilla |
| Backend | Laravel 11 (PHP) |
| Base de données | MySQL |
| Cartes | Leaflet 1.9.4 |

---

## Installation

### Backend

```bash
cd backend
composer install
cp .env.example .env
# Configurer DB_DATABASE, DB_USERNAME, DB_PASSWORD dans .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

### Frontend

Ouvrir `frontend/pages/index.html` dans un navigateur.

---

## Comptes de démonstration

| Identifiant | Mot de passe | Rôle |
|---|---|---|
| admin | admin123 | Administrateur |
| pompier1 | pompier123 | Pompier |
| samu1 | samu123 | SAMU |

---

## Pages

| Page | Description |
|---|---|
| `index.html` | Landing page publique |
| `sos.html` | Signalement d'urgence (citoyen) |
| `suivi.html` | Suivi en temps réel d'un incident |
| `login.html` | Connexion professionnelle |
| `dashboard-pompiers.html` | Interface pompiers |
| `dashboard-samu.html` | Interface SAMU |
| `dashboard-admin.html` | Supervision admin |

---

*© Juin 2026 Urgences SN. Tous droits réservés.*
