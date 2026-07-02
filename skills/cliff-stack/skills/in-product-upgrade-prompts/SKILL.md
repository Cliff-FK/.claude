---
name: in-product-upgrade-prompts
description: "Designs the IN-EDITOR free→pro conversion strategy for a freemium WordPress plugin — how to surface Pro blocks/features inside the Gutenberg block editor (inserter teasers, in-block upsell placeholders, Pro badges on disabled controls, contextual upgrade CTAs at the point of friction) and when to trigger an upgrade prompt vs a review request. Decides the conversion STRATEGY and the UX placement; delegates the actual block/JS implementation to wp-native and the licensing/checkout wiring to freemius. Use when asked how to convert free users to pro from inside the editor, show/tease premium blocks or options in Gutenberg, design Pro badges/locked previews/upgrade modals in the editor, place an upsell without getting rejected from the WordPress.org repo, or decide upgrade-prompt timing/triggers. NOT generic landing-page copy (use copywriting-landing), NOT email upsells (use email-lifecycle), NOT the price/tier design (use pricing-strategist), NOT the SDK gating code itself (use freemius/wp-native), NOT the underlying persuasion/bias theory (use marketing-psychology)."
---

# in-product-upgrade-prompts — convertir free→pro DEPUIS l'éditeur Gutenberg

> **Langue : réponds toujours en français** (accents complets). Identifiants d'API (`registerBlockVariation`, `Placeholder`, `PluginSidebar`…) et noms de composants inchangés.

Conçoit la **stratégie de conversion in-product** d'un plugin WP freemium : où et comment montrer le Pro **dans l'éditeur de blocs**, à quel moment déclencher un prompt d'upgrade. Le point de conversion d'un plugin WP est **dans l'éditeur**, pas sur la landing — c'est l'angle que `copywriting-landing` et `email-lifecycle` ne couvrent pas.

## Frontière dure — ce skill DÉCIDE, il ne code pas (déléguer)

- **Implémentation des blocs/variations/JS éditeur** (registerBlockType, build, enqueue) → `[[wp-native]]`.
- **Gating de licence / checkout / entitlements** (qui a le droit, débloquer après achat) → `[[freemius]]` (gater le code premium avec `can_use_premium_code()`, pas `is_paying()`).
- **Niveau de prix, quelles features dans quel palier** → `[[pricing-strategist]]`.
- **Copy de landing / page de vente** → `[[copywriting-landing]]`. **Upsell par email** → `[[email-lifecycle]]`.
- **Audit a11y / dark-pattern du prompt** → `[[design-auditor]]`.
Ce skill produit : la stratégie de placement, les triggers, la microcopy in-context, et la liste des API à utiliser — pas le code de prod.

## RÈGLE NON NÉGOCIABLE #1 — ne pas faire rejeter le plugin du repo

L'upsell in-editor est **autorisé mais encadré** par les Plugin Guidelines WordPress.org. À respecter sous peine de rejet :
- **Plugin Directory principal = freemium OK.** **Block Directory = paywall INTERDIT** (« No form of payment is permitted for the use of a Block Plugin »). → Un plugin freemium avec teasing Pro va dans le **Plugin Directory**, jamais le Block Directory.
- **Guideline 11** : les upgrade prompts/notices doivent être **limités, contextuels** (page de réglages du plugin OU au point d'usage), **dismissibles** ou auto-dismiss. **Interdits** : nags admin globaux répétés, notices non-dismissibles, pub dashboard, alertes hors-sujet.
- **Guideline 5** : l'upsell de features ad-hoc est explicitement « acceptable » s'il reste dans les bornes de la 11.
→ Le pattern sûr = **surfacer au point de friction** (le bloc/l'option Pro lui-même), pas interrompre globalement.

## RÈGLE NON NÉGOCIABLE #2 — API réelles, pas inventées

Source de vérité : `reference/gutenberg-upsell-apis.md` (vérifié sur developer.wordpress.org). Avant de proposer une API d'éditeur, la confirmer là ou via **Context7** (`/freemius/wordpress-sdk` pour le gating, packages `@wordpress/*` pour l'UI). Pièges déjà vérifiés :
- ❌ **Il n'existe AUCUNE API native de « premium pattern verrouillé/preview-bloqué dans l'inserter »** (le comportement « verrouillé » observé = bug Gutenberg #55469 ; le Block Locking API verrouille des blocs **déjà insérés**, pas un aperçu premium). Ne pas le promettre.
- ❌ Ne pas détourner `allowedBlocks` (restriction d'InnerBlocks) ni Block Bindings comme mécanismes d'upsell — hors sujet.
- ⚠️ Les SlotFills (`PluginSidebar`, `PluginPrePublishPanel`…) sont désormais dans **`@wordpress/editor`** (plus `@wordpress/edit-post`, déprécié). Vérifier au build.
- ⚠️ **Garde anti-péremption** : avant d'**affirmer l'inexistence** d'une API (le « pattern verrouillé inserter », bug #55469) ou la dépréciation de `@wordpress/edit-post`, **revérifier le statut courant** (bug #55469 sur GitHub, doc `@wordpress/editor` via Context7). Si le bug a été corrigé ou l'API ajoutée, l'inexistence ne tient plus — ne pas refuser à tort une API désormais disponible.

## Les 3 placements recommandés (du moins au plus intrusif)

1. **Option Pro visible mais inerte** dans un panneau de réglages (InspectorControls) : contrôle désactivé + mention « Pro » + CTA. Le plus sûr réglementairement. Pattern observé : Spectra/Stackable/Otter.
2. **Bloc Pro rendu en placeholder d'upsell** : si le bloc Pro apparaît dans l'inserter (via `registerBlockVariation` `scope:['inserter']`), son `edit()` rend un composant **`Placeholder`** (label + instructions + `Button variant="primary"` CTA) au lieu du bloc réel tant que non débloqué. Surface au **point d'insertion** = friction exacte. Pattern façon FooGallery (preview + items Pro marqués).
3. **Zone Pro centralisée** dans un `PluginSidebar` dédié et/ou la page de réglages du plugin (zone explicitement tolérée pour un upsell plus riche).

Modèle le plus conservateur (zéro risque) : **blocs Pro absents tant que le plugin Pro n'est pas installé** (Kadence/GenerateBlocks) — pas de teasing in-inserter du tout. À proposer si le client veut éviter tout risque guideline.

## Triggers — quand prompter (et upgrade vs avis)

- **Au point de friction** : clic sur une option/un bloc Pro → prompt contextuel. C'est le déclencheur le plus légitime et le plus convertissant.
- **Arbitrage upgrade-prompt vs demande-d'avis** : après un **succès produit** (Nᵉ bloc inséré + page publiée), choisir UN objectif. Pour le repo, le **1er avis 5★ prime** (cf. ranking — déléguer la mécanique repo à `[[wporg-readme-optimizer]]`). Ne pas empiler les deux sollicitations.
- **Pas de prompt à l'activation** ni en nag répété (guideline 11). Espacer, rendre dismissible, mémoriser le dismiss.

## Microcopy in-context (principes)

- Court, au présent, orienté bénéfice de CE bloc/CETTE option (« Animer au scroll → Pro »), pas un argumentaire de landing.
- CTA unique et clair (`Button variant="primary"`), lien vers le checkout/réglages.
- Pour la psychologie de persuasion sous-jacente (ancrage, FOMO mesuré) → `[[marketing-psychology]]` ; ici on reste sur le placement et le timing.

## Garde-fous (refus / vigilance)

- **Refuser** tout design qui viole la guideline 11 (nag global, non-dismissible, hors-sujet) ou qui vise le Block Directory avec un paywall.
- **Refuser** les dark patterns (faux compteur, option Pro déguisée en gratuite, désactivation trompeuse) → faire auditer par `[[design-auditor]]`.
- **Ne pas inventer** d'API `@wordpress` (ex. `scope:'premium'`, statut `Notice` hors `warning/success/error/info`).
- **Ne pas coder** le bloc/gating ici → déléguer (cf. Frontière dure). Ce skill livre la stratégie + les API à employer.

## Référence (chargée à la demande, 1 niveau)
- `reference/gutenberg-upsell-apis.md` — API d'éditeur vérifiées (variations/scope, Placeholder, SlotFills, Notice/Button), règles guidelines 5/11 + Block vs Plugin Directory, patterns freemium observés, ce qui N'existe PAS.
