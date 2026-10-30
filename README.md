# Flexi Projets

Plateforme de gestion de projets de Flexilivre, inspirée d'Asana.
Laravel 12 · Vue 3 (Inertia) · MariaDB / MySQL · Tailwind.

## Fonctionnalités (V1)

- **Projets** avec description, couleur, dates de début et de fin, sections personnalisables.
- **Tâches** : responsable, dates de début et d'échéance, priorité, description, sous-tâches, commentaires, historique.
- **Quatre vues** sur les mêmes tâches :
  - **Kanban** : glisser-déposer entre sections, ajout rapide, renommage des sections (double-clic) ;
  - **Liste** : tableau groupé par section ;
  - **Calendrier** : vue mensuelle ;
  - **Planning** : diagramme de Gantt, on glisse une barre pour la décaler ou son bord droit pour changer l'échéance.
- **Collaborateurs** avec trois rôles : administrateur (gère le projet et les membres), éditeur (modifie tâches et règles), lecteur (consulte et commente). Inviter une adresse inconnue crée le compte et envoie un lien pour choisir son mot de passe.
- **Règles « quand… alors… »** : quand une tâche est créée, déplacée dans une section, terminée, réassignée ou que son échéance change, alors déplacer, terminer, assigner, changer la priorité, fixer l'échéance à J + n, notifier ou commenter. Les règles ne s'enchaînent pas entre elles, il n'y a donc pas de boucle possible.
- **Notifications** dans l'application (assignation, commentaire, ajout à un projet, règles) et page **Mes tâches**.
- **Connexion** par e-mail et mot de passe, ou avec Google Workspace (limitable au domaine `flexilivre.com`).

## Installation sur votre serveur

Prérequis : PHP 8.2+ (extensions `pdo_mysql`, `mbstring`, `intl`, `xml`, `curl`), Composer, Node.js 20+, MariaDB 10.6+ (ou MySQL 8).

```bash
git clone https://github.com/kamel-boughaleb/flexiproject.git && cd flexiproject
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```

Créez la base, puis renseignez `.env` :

```sql
CREATE DATABASE flexiproject CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'flexi'@'localhost' IDENTIFIED BY 'un-mot-de-passe-solide';
GRANT ALL ON flexiproject.* TO 'flexi'@'localhost';
```

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://projets.flexilivre.com

DB_CONNECTION=mariadb
DB_DATABASE=flexiproject
DB_USERNAME=flexi
DB_PASSWORD=un-mot-de-passe-solide

# Envoi des e-mails (invitations, mot de passe oublié)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=projets@flexilivre.com
```

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Faites pointer le serveur web (Nginx ou Apache) sur le dossier `public/`, et donnez à l'utilisateur du serveur web les droits d'écriture sur `storage/` et `bootstrap/cache/`.

Le premier compte se crée depuis `/register`. Les suivants peuvent s'inscrire de la même façon ou être invités depuis l'onglet « Collaborateurs » d'un projet.

### Connexion Google Workspace (facultatif)

1. Dans [Google Cloud Console](https://console.cloud.google.com/), créez un projet, configurez l'écran de consentement OAuth avec l'audience « Interne » (comptes flexilivre.com uniquement).
2. Créez un identifiant « ID client OAuth » de type « Application Web », avec comme URI de redirection autorisés `http://localhost:8000/auth/google/callback` (développement) et `https://projets.flexilivre.com/auth/google/callback` (production).
3. Renseignez dans `.env` :

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
GOOGLE_ALLOWED_DOMAINS=flexilivre.com
```

puis `php artisan config:clear`. Le bouton « Continuer avec Google » est toujours affiché sur les pages de connexion et d'inscription ; tant que les clés ne sont pas renseignées, il renvoie vers la page de connexion avec un message explicatif. Un compte Google dont l'adresse correspond à un compte existant s'y rattache automatiquement.

## Développement

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # données de démo, comptes kamel@flexilivre.com… / mot de passe « password »
composer dev                 # serveur Laravel + Vite + file d'attente
```

Tests : `php artisan test` (SQLite en mémoire par défaut).

## Organisation du code

| Dossier | Contenu |
| --- | --- |
| `app/Models` | `Project`, `Section`, `Task`, `Comment`, `Rule`, `Activity`, `User` |
| `app/Services/RuleEngine.php` | Exécution des règles d'automatisation |
| `app/Policies/ProjectPolicy.php` | Droits par rôle (lecteur, éditeur, administrateur) |
| `app/Http/Controllers` | Projets, sections, tâches, commentaires, membres, règles, notifications, connexion Google |
| `resources/js/pages/Projects` | Pages d'un projet (vues, règles, collaborateurs, paramètres) |
| `resources/js/components/project` | Kanban, liste, calendrier, planning, fiche tâche |
