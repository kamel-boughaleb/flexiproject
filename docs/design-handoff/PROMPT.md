Copie ce dossier à la racine de ton projet Flexi Projets (ex. `docs/design-handoff/`), puis colle ce message dans Claude Code :

---

Je refais l'UI de Flexi Projets. Toute la spec est dans `docs/design-handoff/` :
- `HANDOFF.md` : principes, tokens, composants, écrans, comportements
- `tokens/tokens.css` : variables CSS
- `screens/desktop/*.dc.html` et `screens/mobile/*.dc.html` : maquettes de référence (HTML avec styles inline ; la logique d'état est dans le bloc `<script type="text/x-dc">`)

Étapes :
1. Lis `HANDOFF.md`, puis explore le code existant (layout, vue Kanban, formulaire de tâche, modèle Task) et propose-moi un plan avant de coder.
2. Intègre `tokens.css` et Quicksand dans le layout global.
3. Crée les composants partagés (Avatar, Badge priorité, Case ronde, Segmented control, Switch, boutons).
4. Remplace la modale de tâche par le **panneau latéral** (`screens/desktop/Main.dc.html`), puis la vue plein écran (`Tache.dc.html`).
5. Refais ensuite Liste, Calendrier, Planning, Règles, Collaborateurs, Paramètres, la page de connexion (`Login.dc.html`), puis la version mobile.

Reste dans la stack existante, garde les routes et le modèle de données (signale-moi tout champ manquant, ex. statut « En attente », dépendances), et fais un commit par étape.
