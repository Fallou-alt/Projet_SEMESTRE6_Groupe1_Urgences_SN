# Guide de contribution — Urgences SN

## Règles obligatoires

- Chaque membre travaille **uniquement sur sa branche** `feature/prenom`
- **Ne jamais modifier** un fichier qui ne fait pas partie de ta tâche assignée
- Faire `git pull origin develop` avant de commencer à coder
- Créer une PR vers `develop` uniquement — jamais vers `main`
- **Ne pas merger sa propre PR** — attendre la validation de Fallou

## Workflow

```bash
git checkout feature/ton-prenom
git pull origin develop        # toujours synchroniser avant
# ... faire ses modifications ...
git add fichier_modifie.php    # ajouter uniquement les fichiers de ta tâche
git commit -m "feat: description claire"
git push origin feature/ton-prenom
# Créer une PR sur GitHub vers develop
```

## À ne jamais faire

- `git add .` sans vérifier ce qu'on ajoute
- Modifier `dashboard-admin.html`, `api.js`, `style.css` sans autorisation
- Merger sans approbation du chef de projet
