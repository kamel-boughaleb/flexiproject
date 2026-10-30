# Flexi Projets — Design handoff (refonte UI)

Spec d'implémentation pour Claude Code. Application : https://flexiproject.dev
Source design : canevas « Flexi Projets — fiche tâche » (claude.ai), design system Flexilivre.

> **Pour Claude Code :** lis ce fichier en entier, puis `tokens/tokens.css`, puis les maquettes de `screens/` correspondant à l'écran que tu implémentes. Les `.dc.html` sont des **références visuelles** (HTML + styles inline + un petit bloc JS de logique d'état), pas du code à copier tel quel : reproduis-les dans la stack et les composants existants du projet. Ne change pas le modèle de données ni les routes sans le signaler.

---

## 1. Principes

- **Inspiration Asana** : la fiche tâche s'ouvre en **panneau latéral** à droite du board (pas en modale), avec une option plein écran. Les champs sont des lignes « libellé à gauche / valeur cliquable à droite », sans bordures de formulaire ; l'édition se fait en place (popover / select au clic).
- **Design system Flexilivre** : police Quicksand, rose `primary` pour l'action, bleu `secondary` pour l'info/les liens, tout arrondi (pilules pour boutons et badges, 20px pour cartes et blocs), ombres douces teintées bleu nuit, pas de dégradés, pas d'emoji.
- **Accessibilité** : vrais `<button>` / `<a href>` / `<input>` + `<label>`, `aria-label` sur les boutons icône, cibles tactiles ≥ 44px sur mobile, texte ≥ 4,5:1.
  - Le texte blanc **ne va jamais** sur `primary-500` (#FF489C) sauf en gras ≥ 18px (bouton Large « Enregistrer », « Créer la tâche »). Pour un bouton plein avec du texte 14px (« Envoyer », « Inviter », FAB), utiliser `primary-active` (#D10553).
  - Le rouge « en retard » utilise `primary-active` (#D10553) et non `danger` (#FC0900, contraste insuffisant).

## 2. Tokens

Voir `tokens/tokens.css` (variables CSS) et `tokens/tokens.json` (source Figma).
Ajouts hors design system utilisés dans les maquettes (neutres d'interface et teintes d'état) — déclarés en fin de `tokens.css`, section « App » :

| Variable | Valeur | Usage |
|---|---|---|
| `--app-bg` | #F6F7F9 | fond de l'app derrière la carte principale |
| `--app-surface-2` | #FAFBFC | en-têtes de groupe, colonnes de board |
| `--app-hover` | #F1F2F4 | survol de ligne / bouton fantôme, piste des segmented controls |
| `--app-border` | #ECEDEF | séparateurs et bordures de cartes |
| `--app-border-strong` | #DCDDE0 | bordures de champs |
| `--app-icon-muted` | #8A8B8E | cercle de case à cocher vide, icônes secondaires |
| `--app-text-strong` | #343434 | texte de bouton secondaire |
| `--success-tint` / `--success-text` | #EAF6E0 / #2E6B00 | état « terminée » |
| `--warning-tint` / `--warning-text` | #FFF1DE / #8A4B00 | priorité Moyenne |
| `--primary-wash` | #FFF7FB | aujourd'hui dans le calendrier, zone sensible |

**Typo** : Quicksand 400/500/600/700 (Google Fonts). Titre de page tâche 35px/700 (plein écran), 26px (panneau), 22px (mobile). Corps 14–16px. Labels de section en capitales 12px/700, `letter-spacing: .04em`.

**Rayons** : pilule 100px · carte/bloc 20px · carte de tâche 14–16px · champ 12px · bouton icône 10px.
**Ombres** : `--shadow-soft` (cartes, carte principale), hover carte `0 4px 10px #112a3b26`, `--shadow-product` (FAB, feuilles mobiles).

## 3. Composants à créer / refaire

| Composant | Détails |
|---|---|
| **Avatar** | cercle, initiales, fond `secondary-tint`, texte `secondary-deep`, tailles 24/28/32/44 |
| **Badge priorité** | pilule 12px/700. Basse : `secondary-tint`/`secondary-deep` · Moyenne : `warning-tint`/`warning-text` · Haute : `primary-tint`/`primary-active` |
| **Sélecteur priorité** | 3 pilules radio (`role=radio`), bordure 2px, état actif = couleurs du badge + bordure de la couleur 500 |
| **Pastille section** | pilule avec point coloré + chevron, ouvre un menu de sections |
| **Case à cocher ronde** | 18–22px, contour 1.5px `--app-icon-muted` ; cochée = fond `success`, coche blanche |
| **Bouton « Marquer comme terminée »** | pilule contour 2px gris ; état terminé = fond `success-tint`, bordure `success`, texte `success-text`, libellé « Terminée ». Ajoute une ligne d'activité. |
| **Segmented control** | piste `--app-hover`, onglet actif blanc + `shadow-soft` (filtres Activité, échelle Calendrier/Planning, colonnes mobile) |
| **Switch** | 48×28, `role=switch`, actif `success`, inactif `grey-medium` |
| **Boutons** | Primaire plein `primary-active` (texte < 18px) ou `primary-500` (Large 18px) · Secondaire contour `primary-500` texte `primary-active` · Fantôme (hover `--app-hover`) · Icône 40×40 (44×44 mobile) |
| **Carte de tâche (board)** | blanc, radius 14, `shadow-soft`, check + titre 14/600, ligne méta : badge priorité, échéance (rouge si dépassée), compteur commentaires, sous-tâches x/y, avatar à droite. Carte ouverte = bordure 2px `primary-500`. |
| **Sidebar** | logo carré rose « F », nav (Accueil, Mes tâches, Notifications + compteur, Équipe), section PROJETS avec projet actif sur fond `primary-tint` |
| **En-tête projet** | carré couleur + nom + badge date, avatars, bouton « Ajouter une tâche » (secondaire), onglets avec soulignement 2px `primary-500` sur l'actif |

## 4. Écrans desktop (`screens/desktop/`)

Layout commun : sidebar 232px sur fond `--app-bg`, contenu dans une carte blanche (radius 20, `shadow-soft`, marge 10px). Responsive : sous ~800px la sidebar passe au-dessus (flex-wrap) — en prod, basculer sur la version mobile.

1. **`Main.dc.html` — Kanban + panneau tâche**
   - Board : colonnes 272px sur `--app-hover`, radius 20, scroll horizontal ; « + Ajouter une section » en pointillés.
   - Panneau (≤ 640px, à droite, bordure gauche + ombre) : barre d'outils (Terminer · lien · pièce jointe · plein écran · … · fermer) → fil d'Ariane projet › section → titre éditable → grille de champs (Responsable + statut « En attente », Dates début→échéance + badge « En retard », Section, Priorité, Projet, Dépendances) → Description (édition en place) → Sous-tâches (compteur + barre de progression) → Pièces jointes (cartes + zone de dépôt) → Activité (Commentaires / Tout / Historique, commentaires en bulles, événements en lignes grises) → **compositeur collé en bas** (textarea, joindre, @mention, réaction, Envoyer) + collaborateurs / « Ne plus suivre ».
2. **`Tache.dc.html` — Tâche plein écran** : 2 colonnes, contenu à gauche, carte « Détails » + « Collaborateurs » à droite (≤ 380px). Bouton réduire → retour au panneau.
3. **`Liste.dc.html`** : barre (recherche, Filtrer, Trier, Regrouper : Section), tableau groupé par section repliable, colonnes Nom / Responsable / Échéance / Priorité / Sous-tâches / Activité, en-tête sticky, « Ajouter une tâche… » en fin de groupe.
4. **`Calendrier.dc.html`** : vue mois (lundi en premier), barres de tâches multi-jours qui coupent à la semaine (coins carrés à la coupure), aujourd'hui en pastille `primary-active`, week-ends `--app-surface-2`, case « Afficher les tâches terminées », Semaine/Mois.
5. **`Planning.dc.html`** : Gantt, colonne tâches 260px sticky, 34px par jour, en-tête mois + jours, week-ends grisés, ligne aujourd'hui 2px `primary-active`, barres en pilule colorées par priorité, **dépassement d'échéance hachuré** jusqu'à aujourd'hui, Jours/Semaines/Mois.
6. **`Regles.dc.html`** : cartes « QUAND [déclencheur] → ALORS [action(s)] » + switch d'activation, carte inactive à 70 % d'opacité ; « Modèles suggérés ».
7. **`Collaborateurs.dc.html`** : formulaire d'invitation (e-mail + rôle + Inviter), liste des membres, état vide, cartes d'explication des rôles (Propriétaire, Éditeur, Commentateur, Lecteur).
8. **`Parametres.dc.html`** : sections en 2 colonnes (titre+aide / champs) : Général (nom, description, couleur, dates, vue par défaut), Sections (réordonnables, section « Fin »), Notifications (switches), Zone sensible (Archiver, Supprimer), barre Annuler / Enregistrer.

Composants partagés : `Sidebar.dc.html`, `ProjectHeader.dc.html` (prop `active`).

## 5. Écrans mobile (`screens/mobile/`, 390×844)

- **`M-Kanban`** : en-tête (menu, projet, recherche, avatar), puces de vues, **segmented control de colonne** (une colonne affichée à la fois, cartes pleine largeur), FAB `primary-active` 56px, tab bar (Accueil, Mes tâches, Projets, Notifications).
- **`M-Tache`** : bouton Terminer pleine largeur, titre, champs en lignes avec chevron, sous-tâches (zone tactile 44px), pièces jointes en carrousel horizontal, activité, **compositeur fixé en bas** (joindre, champ, envoyer rond).
- **`M-Liste`** : groupes repliables, ligne = check 44px + nom + méta (échéance · priorité · compteur).
- **`M-Calendrier`** : mini-mois avec points, jour sélectionné en `primary-active`, agenda du jour en dessous (cartes avec liseré de couleur + badge).
- **`M-Ajout`** : bottom sheet (poignée, nom, description, puces Responsable/Échéance/Fichier, Section, Priorité, « Créer la tâche »).
- **`M-Menu`** : tiroir latéral 318px (nav, projets avec sous-liens Règles / Collaborateurs / Paramètres, profil en bas).

Planning, Règles, Collaborateurs, Paramètres : pas de maquette mobile dédiée — empiler les blocs desktop en une colonne.

## 5 bis. Connexion (`screens/desktop/Login.dc.html`, `screens/mobile/M-Login.dc.html`)

- **Desktop** : 2 colonnes qui s'empilent sous ~1000px. À gauche, panneau de marque sur fond `primary-tint` (radius 28, formes décoratives pleines `primary-disabled-bg` et `secondary-tint`, **pas de dégradé**) : logo, sous-titre manuscrit Rock Salt en `primary-active` (« Vos projets, bien rangés »), titre 35px, liste d'arguments avec coches, aperçu de board posé avec `shadow-product`. À droite, formulaire centré (max 420px).
- **Formulaire** : titre 32px + sous-titre · bouton Google pilule contour (utiliser le **bouton / logo officiel Google** en prod, la maquette montre un « G » neutre) · séparateur « OU AVEC VOTRE E-MAIL » · champs 52px, radius 14, bordure 2px `--app-border-strong`, icône à gauche, focus = bordure `secondary-500` + halo `secondary-tint` · bouton œil pour afficher/masquer le mot de passe · « Mot de passe oublié ? » en lien bleu gras · case « Se souvenir de moi » · bouton Large « Se connecter » `primary-500` 18px gras · note « accès sur invitation ».
- **États** (tweak `etat` dans la maquette) : *erreur* = bandeau `role=alert` fond `primary-tint` texte `primary-active` + bordures de champs `primary-active` ; *chargement* = libellé « Connexion… », bouton `primary-hover` désactivé.
- **Mobile** : en-tête de marque arrondi en bas (logo, sous-titre manuscrit, titre 26px), puis le même formulaire en pleine largeur, cibles 44px.

## 6. Comportements

- Clic sur une carte du board → ouvre le panneau ; la carte reste surlignée. Échap / bouton fermer → ferme. URL dédiée par tâche recommandée (`/projets/{id}/taches/{id}`) pour le lien copié.
- Tâche en retard = échéance < aujourd'hui et non terminée → échéance en `primary-active` + badge « En retard ».
- Cocher une tâche / sous-tâche met à jour le compteur et la barre de progression immédiatement (optimiste).
- Activité : fusionner commentaires et historique dans un seul fil chronologique, filtrable.
- Priorités : Basse / Moyenne / Haute (valeur par défaut Basse).

## 7. Contenu des maquettes

Les tâches et dates de « Design Paiement extra », « Orientation Poster/Cadre », les sous-tâches et les règles d'exemple sont fictives : elles servent de données de démonstration uniquement.
