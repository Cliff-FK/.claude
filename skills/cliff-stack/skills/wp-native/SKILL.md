---
name: wp-native
description: Use when implementing or modifying ANY WordPress feature on a WordPress project — Gutenberg blocks, block editor extensions (InspectorControls, HOC/filters), render_block/PHP hooks, theme.json, ACF blocks, REST, admin UI. Enforces 2026 WP-native best practices: core blocks + block supports first, native filters/HOCs over custom, @wordpress/* React components (never raw React), APIs verified via Context7, official coding standards. Triggers on requests like "ajoute/code une feature/un bloc/un réglage/un panneau/une option WP|Gutenberg|ACF". Inclut l'IMPLÉMENTATION éditeur d'un upsell freemium une fois la stratégie posée (Placeholder Pro dans edit(), registerBlockVariation scope:['inserter'], Pro-badge sur contrôle désactivé) — la STRATÉGIE de placement/timing reste à in-product-upgrade-prompts, le gating de licence à freemius. NOT la procédure de vrai save humain éditeur (clic Enregistrer réel pour déclencher cache/rest_after_insert/preSavePost) → wp-save-ui-test.
---

# wp-native — feature WordPress en natif, bonnes pratiques 2026

Objectif : toute feature WP est codée **avec le maximum de natif WP**, **sourcée** (pas de mémoire), et **idiomatique au projet courant**. Universel : ne suppose aucun chemin/préfixe — **découvre les conventions du projet avant de coder**.

> **RÈGLE NON NÉGOCIABLE** — Toute API / signature / hook / composant WP est **vérifié via Context7 (MCP) AVANT d'écrire la moindre ligne**, jamais d'après mémoire. Si Context7 est indisponible → le dire et fallback README GitHub/`node_modules`, ne pas deviner. IDs Context7 pré-résolus en §0.2.

## 0. Avant de coder (obligatoire, dans l'ordre)
1. **Découvre l'existant** (Grep/Read) : y a-t-il déjà un bloc/filtre/HOC/helper qui fait ça ou s'en approche ? Réutilise/étends (DRY) plutôt que créer. Repère le style maison (auto-loader, render.php, JS inline `wp.*` vs build @wordpress/*, theme.json v2/v3, conventions de nommage).
2. **Source l'API via Context7** (jamais de mémoire pour une signature). IDs directs :
   - Patterns / « the right way » : `/wordpress/gutenberg`
   - Composants @wordpress/components (props) : `/websites/wp-gb` puis `/wordpress/gutenberg`
   - Fonctions/hooks/classes PHP core : `/websites/developer_wordpress_reference_functions`, `/websites/developer_wordpress_reference_hooks`, `/websites/developer_wordpress_reference_classes`
   - Standards exigés : `/websites/developer_wordpress_coding-standards`, `/wordpress/wpcs-docs`
   - Fallback composant exotique/non documenté : README GitHub `packages/components/src/<C>/README.md` (WebFetch) ou `node_modules/@wordpress/components/...` si installé (version pinnée = vérité terrain).
3. **Vérifie la version réelle** (slugs presets, blocs, supports) : `wp_get_global_settings()`, `WP_Block_Type_Registry`, code core installé (`wp-includes/`). Ne jamais inventer un slug/preset par défaut.

## 1. Hiérarchie de nativité (toujours du + natif au - natif)
1. **Bloc core + block supports** (spacing/color/border/typography/layout/dimensions) — préfère un réglage natif à du custom. Le contrôle d'alignement (`align` wide/full) **exige aussi `supports.layout`** (type `default` = sans max-width imposée) — sinon la toolbar reste vide ; WP l'injecte alors automatiquement, pas de control manuel.
2. **Extension par filtres/HOC natifs** sans nouveau bloc : `blocks.registerBlockType` (attributs), `editor.BlockEdit` / `editor.BlockListBlock` (HOC UI/preview), `render_block` / `render_block_data` (front), `register_block_type_args` (déclare l'attribut **côté serveur** si SSR/REST, sinon rejet « attributes invalides »).
3. **Variations / block styles / patterns** plutôt qu'un bloc dédié quand c'est cosmétique.
4. **Bloc custom** en dernier : `block.json` **apiVersion 3**, `useBlockProps()` / `useInnerBlocksProps()`, `render.php` pour le dynamique.

> **Modes de save/render** (statique C1 / dynamique C2 / hybride C3 ; sources d'attributs `html`/`rich-text`/`attribute`/Block Bindings ; **pipeline de restitution serveur UNIVERSEL** : filtre `render_block`/`render_block_data` — distinct du `render_callback`, s'applique même aux C1 —, **block supports** générant classes/styles hors `save()` via `get_block_wrapper_attributes` au render, `apiVersion 3`/`useBlockProps` au save, résolution des **Block Bindings** et transformation propre du markup via **`WP_HTML_Tag_Processor`** ; compositions InnerBlocks ; cas spéciaux core ; **angle mort des chemins de save non-REST** : `save_post` toujours émis par `wp_insert_post`, `wp_after_insert_post` sauf si `fire_after_hooks=false`, `rest_after_insert_*` strictement REST ; **attribut synthétique** injecté en JS, absent du `WP_Block_Type_Registry`) → **`references/block-save-render-matrix.md`**. À lire dès qu'une feature touche le pipeline de rendu (post-traitement de markup, cache, re-render serveur, variante de contenu, rich-text, block supports, ou un réglage maison ajouté à des blocs core par filtre/HOC).

## 2. React = 100 % WP natif (RÈGLE FORTE — vaut pour TOUTE UI admin/éditeur)
**Toute interface admin — page de réglages, meta box, panneau d'inspecteur, barre d'outils, modale, sidebar plugin — se construit avec les composants React natifs de WP. JAMAIS de `<input>`/`<select>`/`<button>` HTML bruts stylés à la main, JAMAIS de lib UI tierce (MUI, AntD, Bootstrap…), JAMAIS de React importé hors `@wordpress/element`.** Un contrôle natif existe quasi toujours — le chercher (Context7 `/websites/wp-gb`) avant d'en bricoler un.
- Briques : `@wordpress/element` (PAS React brut), `@wordpress/components` (ToggleControl, PanelBody/PanelRow, ToolsPanel/ToolsPanelItem, RangeControl, SelectControl, TextControl, ColorPalette/ColorPicker, ComboboxControl, Button, Notice, Spinner, Modal, Card…), `@wordpress/block-editor` (InspectorControls, BlockControls, useSettings…), `@wordpress/data` (useSelect/useDispatch), `@wordpress/core-data` (entités/réglages), `@wordpress/i18n` (`__`, `_x`), `@wordpress/hooks` (addFilter), `@wordpress/compose` (createHigherOrderComponent), `@wordpress/plugins` + `@wordpress/edit-post`/`edit-site` (PluginSidebar, PluginDocumentSettingPanel).
- **Deux contextes, deux réponses natives** :
  - **Écran qui charge déjà React/Gutenberg** (éditeur, site editor, ou page où l'on monte React volontairement pour de l'interactif riche) → `@wordpress/components` (ci-dessus), monté via `createRoot` (`@wordpress/element`) + `@wordpress/api-fetch` pour le REST.
  - **Admin basique SANS React** (page d'options simple, meta box statique) → **NE PAS charger React inutilement**. Utiliser le **markup & les classes admin natifs WP** (`.wrap`, `.form-table`, `.button`/`.button-primary`, `.notice`, dashicons, `.postbox`) + la **Settings API** (`register_setting`, `add_settings_section/field`, `settings_fields()`, `do_settings_sections()`). Zéro CSS de contrôle réinventé, zéro lib externe. (jQuery seulement si déjà la convention du projet et qu'aucune alternative native simple n'existe.)
- Respecte le **mode de livraison du projet** : si build (`src/*.js` + `@wordpress/scripts`/Vite) → imports ES + `wp-scripts` ; si pas de build → `wp_add_inline_script` avec `wp.*` global (mêmes APIs). Ne mélange pas les deux.
- `ToolsPanelItem` pour s'intégrer aux panneaux natifs ; `isShownByDefault` selon l'UX voulue.

## 3. Conventions 2026 (PHP + sécurité)
- **theme.json v3**, supports > CSS inline ; presets via `var(--wp--preset--…)`.
- Post-traitement de markup : **`WP_HTML_Tag_Processor`** (jamais de regex sur du HTML).
- **i18n** systématique (`__()/esc_html__()` + textdomain) ; **escaping** en sortie (`esc_html/esc_attr/esc_url/wp_kses`) ; **sanitization** en entrée ; nonces/capabilities pour toute écriture.
- Hooks idiomatiques, priorités explicites ; pas de requête lourde non cachée (transient/cache si chaud).
- Pas de `__experimental`/`__unstable` si une API stable existe ; si inévitable, isole + commente la dette.

## 4. Sortie & validation
- **Diff minimal, modifier > ajouter, pas de code mort.** Code idiomatique au projet (comments density, nommage).
- **Signale tout risque de régression** (SSR/REST, ordre de hooks, cache, specificity CSS).
- **Baseline WP-natif avant de qualifier un « bug »** : avant d'imputer un comportement au code projet, vérifie que ce n'est pas le comportement de WP core **sans** le plugin (ex. un bloc image core fige aussi son `src` résolu dans `post_content` → la « péremption d'URL média » est WP-native, pas un bug du plugin ; REST refuse les meta `_*` protected → c'est une contrainte WP, pas un choix arbitraire). Si WP core fait pareil → comportement hérité, pas une régression à corriger. **Un diagnostic d'agent reste une hypothèse** : confirme par signal direct (lecture/grep/`wp eval`/DOM réel) et **tente de le réfuter** (chercher un mécanisme compensatoire, un autre consommateur, le bon build) AVANT de le remonter ; une absence dans *un* fichier n'est pas une absence dans le système.
- `php -l` sur le PHP touché. Lint projet si présent (PHPCS/ESLint) — sinon le proposer.
- Vérif comportement réel via **Playwright** quand pertinent (UI éditeur réelle, save humain, resize front) — pas de proxy.
- Présente le plan/diff **avant** d'écrire si la tâche n'est pas triviale (règle propose-before-acting).

## Anti-patterns à refuser
- Raw React / lib UI tierce dans l'éditeur • regex sur HTML • slug/preset/chemin en dur quand découvrable • attribut custom non déclaré serveur sur bloc SSR • dupliquer un helper existant • `register_block_type` sans `block.json` • sortie non échappée • API d'après mémoire sans check Context7.
