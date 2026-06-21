---
name: wp-save-ui-test
description: Procédure pour déclencher un VRAI save humain dans l'éditeur WordPress/Gutenberg via Playwright (clic réel sur Enregistrer, jamais programmatique), afin de tester un comportement qui ne se déclenche qu'au save UI (cache plugin, hooks rest_after_insert, filtres preSavePost, validation REST). À utiliser quand : on debug/teste un plugin ou thème WordPress dont le comportement dépend d'une sauvegarde réelle de post depuis l'admin, et qu'un save programmatique (wp post update / savePost) ne reproduit pas le cas. Ne s'applique qu'aux projets WordPress.
---

# Tester un save UI humain dans WordPress (Gutenberg)

Certains comportements WordPress ne se déclenchent QUE sur une sauvegarde humaine réelle depuis
l'éditeur (clic « Enregistrer »/Ctrl+S) : hooks `rest_after_insert_{type}`, filtres
`editor.preSavePost` (JS), validation REST, méta posées par l'éditeur. Un save programmatique
(`wp post update`, `wp.data.dispatch().savePost()`) les contourne et produit des **faux négatifs**.
→ Pour tester ces cas, faire un VRAI clic via Playwright. Jamais programmatique.

## Découverte runtime (rien en dur)
- WP-CLI : binaire PHP + wp-cli.phar du projet (voir CLAUDE.md projet pour le wrapper exact).
- URL admin / login : `wp option get siteurl` + `/wp-login.php`.
- Post de test : fourni par la tâche, ou découvert.

## Procédure (étapes, dans l'ordre)

1. **Admin temporaire** : créer via WP-CLI (le helper `bin/wp-user-create.sh` peut planter selon le projet) :
   `wp user create <user> <email> --role=administrator --user_pass=<pass>`
   (supprimer après le test : `wp user delete <user> --yes --reassign=1`).

2. **Login Playwright** : naviguer `/wp-login.php` → remplir `#user_login`, `#user_pass` → cliquer `#wp-submit`.
   (Vérifier d'abord si déjà connecté : si `#user_login` absent, session active.)

3. **Ouvrir l'éditeur** : `/wp-admin/post.php?post=<ID>&action=edit`, attendre ~3-4 s le chargement de Gutenberg.

4. **Fermer la modale « Bienvenue dans l'éditeur »** si présente (`.components-modal__screen-overlay`)
   avec **Escape** — sinon elle intercepte les clics.

5. **Forcer le « dirty »** de façon FIABLE : l'éditeur est dans l'iframe `editor-canvas`. Modifier
   RÉELLEMENT le titre (textbox "Saisissez le titre") avec une valeur **différente** de l'actuelle.
   ⚠️ Espace+Backspace ne marque PAS toujours dirty sur un post publié → le save serait ignoré.
   (Rétablir le vrai titre ensuite si besoin, le post reste dirty.)

6. **Save réel** : cliquer le bouton de publication. La classe DOM réelle du `<button>` est
   `editor-post-publish-button__button` (le `.editor-post-publish-button` sans suffixe est le
   wrapper) → cibler par **libellé** plutôt que par classe (plus robuste aux versions) :
   `button[aria-label*="Enregistrer"], button[aria-label*="Mettre à jour"]`, ou repérer le bouton
   « Enregistrer »/« Mettre à jour » via snapshot. Attendre ~5 s (persistance REST + pipeline serveur).

7. **VÉRIFIER que le save a abouti** AVANT de juger l'effet : `wp eval 'echo get_post(<ID>)->post_modified;'`
   doit avoir changé. Si inchangé → le save n'a pas eu lieu → ne PAS conclure « le fix échoue »
   (erreur de méthode classique). Refaire l'étape 5 avec une vraie modif.

8. **Nettoyer** : supprimer l'admin temporaire.

## Pièges
- Les `ref=` Playwright changent à chaque snapshot/édition → re-snapshot avant un clic si « ref not found ».
- L'éditeur moderne est iframé (`iframe[name="editor-canvas"]`) : titre/blocs sont DANS l'iframe.
- Toujours distinguer « le save n'a pas eu lieu » de « le fix ne marche pas » (étape 7).

## Règle d'or : agir via les VRAIS contrôles du bloc
- Pour modifier un attribut (justification, align, etc.), utiliser le **contrôle réel** du bloc
  (toolbar / inspector) via un clic Playwright — PAS `setAttributes` programmatique. La sélection
  du bloc (`selectBlock`) est OK (c'est une sélection, pas une mutation). Mais le changement de
  valeur doit passer par le contrôle UI (ex. toolbar « Modifier la justification des blocs » →
  menuitem « Justifier les blocs au centre »).
- **Ne jamais inventer un réglage/preset/attribut** absent du bloc : n'utiliser que les contrôles
  réellement présents dans la toolbar/l'inspector du bloc ciblé (les lister via snapshot avant).

## Patron de scénario : prouver l'effet déclenché par le save UI
But générique : montrer qu'un comportement (transformation de contenu au save, méta posée par un
hook, entrée de cache, validation REST…) se produit BIEN au vrai save, et PAS sur un save
programmatique. Adapter chaque étape au plugin/thème testé — rien de spécifique ici.

1. **Préparer l'état d'entrée** via les **vrais contrôles** du bloc/post (toolbar/inspector, clic
   Playwright — pas `setAttributes`), de sorte que l'effet attendu DOIVE se déclencher au save.
2. **Capturer l'état AVANT** (lecture seule WP-CLI) : `wp post get <ID> --field=content`, méta
   concernée (`wp post meta get <ID> <clé>`), et tout artefact serveur du plugin (cache, table…).
3. **Vrai save UI** (étapes 1-7 de la procédure ci-dessus), puis vérifier que `post_modified` a changé.
4. **Comparer APRÈS vs AVANT** par **signal sémantique direct** (le contenu/attribut/méta réel visé,
   jamais un proxy : longueur, présence d'un flag, nom approchant) :
   - le contenu/méta reflète la transformation attendue (et seulement elle) ;
   - les éléments NON concernés sont inchangés (non-régression) ;
   - l'artefact serveur du plugin (cache/table) est cohérent avec le nouvel état ;
   - le **front réel** (`?p=<ID>`) rend l'état attendu.
5. **Contre-épreuve** : refaire la même modif par save **programmatique** (`wp post update`) et
   vérifier que l'effet N'a PAS lieu → confirme que le déclencheur est bien le save UI.
6. **Filet anti-régression unitaire** quand le plugin expose une commande de test sans DB/UI :
   la relancer après toute évolution (doit afficher 100% PASS). Découvrir la commande dans le
   CLAUDE.md du projet — ne rien supposer.
