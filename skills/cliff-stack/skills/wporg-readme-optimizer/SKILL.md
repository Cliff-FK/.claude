---
name: wporg-readme-optimizer
description: "Optimizes a WordPress plugin's listing in the WordPress.org plugin directory's INTERNAL search engine (directory/ASO-like SEO for the wordpress.org repo) — readme.txt, plugin title, tags, short description, 'Tested up to', ratings/reviews and support resolution. Audits keyword coverage per target query against the real Elasticsearch ranking formula, scores gaps, and produces an optimized readme.txt diff plus a reviews/support action plan. Use when asked to optimize/audit a plugin readme.txt, rank higher in the wordpress.org plugin search/repo, do the 'WordPress.org SEO' / directory SEO of a plugin, improve plugin discoverability on the WordPress.org directory, choose plugin tags, write the plugin title/short description for the repo, 'why does my plugin not show up in WordPress.org search', or 'why is my plugin ranked low in the plugin search even though it has more installs than competitors'. STRICTLY scoped to the WordPress.org directory listing — NOT Google/web SEO (use seo-launch), NOT pricing/licensing/Freemius monetization (use pricing-strategist/freemius), NOT plugin PHP/JS code (use wp-native), NOT the repo-compliance gate (use wp-plugin-check)."
---

# wporg-readme-optimizer — classer un plugin dans le moteur de recherche du dépôt WordPress.org

> **Langue : réponds toujours en français** (accents complets). Termes techniques (readme.txt, Elasticsearch, n-gram, active installs…) et identifiants inchangés.

Optimise la **fiche d'un plugin sur le dépôt WordPress.org** pour son classement dans le **moteur de recherche interne** du répertoire (`wordpress.org/plugins/`). Couvre uniquement : `readme.txt`, titre du plugin (Plugin Name), tags, short description, `Tested up to`, notes/avis, fils de support résolus, screenshots/assets de la page repo.

## Frontière dure — ce que ce skill NE fait PAS (déléguer)

Ce skill traite **exclusivement le répertoire WordPress.org**. Il ne touche ni à ces domaines, ni n'y fait référence comme à des leviers ; il **renvoie** :
- **SEO web / Google / AEO** (ranker sur Google, schema, backlinks, contenu de blog) → `[[seo-launch]]`.
- **Pricing, licences, plans, Freemius, monétisation** → `[[pricing-strategist]]` / `[[freemius]]`.
- **Code du plugin (PHP/JS, blocs, hooks), header de plugin dans le `.php`** → `[[wp-native]]`.
- **Conformité du plugin pour le repo (Plugin Check, sécurité, i18n)** → `[[wp-plugin-check]]`.
Si la demande sort du périmètre repo, le dire et pointer le bon skill — ne pas improviser hors couloir.

## RÈGLE NON NÉGOCIABLE — la formule réelle, pas la légende

Le ranking du search repo est piloté par une **vraie formule Elasticsearch** documentée par l'ingénieur qui l'a construite (Greg Brown / Automattic, `data.blog`, 2017). **Beaucoup d'articles « SEO wordpress.org » (dont le blog Freemius) répètent des chiffres FAUX.** Vérité encodée ici, à ne jamais contredire de mémoire :

- **Formule de ranking (source primaire = CODE du plugin directory, `class-plugin-search.php`)** — `function_score` multiplicatif, chaque facteur avec une **valeur `missing`** (substituée quand le champ est vide) :
  - `active_installs` : factor **0.375**, `log2p`, **missing 1**
  - `support_threads_resolved` : factor **0.25**, `log2p`, **missing 0.5**
  - `rating` : factor **0.25**, `sqrt`, **missing 2.5**
  → **active_installs (0.375) domine le rating (0.25)**, pas l'inverse.
- **Un plugin SANS aucun avis démarre à 2,5/5 dans le RANKING** (paramètre ES `'missing' => 2.5`, codé en dur) — pas à 0. ⚠️ **Distinction clé** : ce 2,5 est interne à la requête de tri ; la **note AFFICHÉE** sur la fiche reste « no ratings yet ». Donc « 2,5 par défaut » = vrai pour le classement, **jamais** une note publique. Premier avis 5★ : fait passer le facteur de `sqrt(2.5)≈1.58` à `sqrt(5)≈2.24` — gain réel mais modéré.
- **Matching texte = AND** (en 2017) sur un champ `all_content` (titre + author + slug + content + tags) : tous les mots de la requête devaient apparaître quelque part, sinon exclusion. ⚠️ **Daté** : une source 2025 suggère un possible passage à un split OR des mots composés → présenter le AND comme « historiquement strict, peut-être assoupli aujourd'hui », jamais comme certitude actuelle.
- **Titre = champ texte le plus boosté** (`title.ngram^2`). Y placer la phrase-clé exacte la plus importante.

### Mythes à corriger activement (le user peut arriver avec)
| Mythe répandu | Réalité sourcée |
|---|---|
| « Seuls les **5 tags** comptent » | **12 tags indexés pour la recherche**, 5 seulement **affichés**. Poids des tags **divisé par 2** → signal faible, ne pas surinvestir. Tag unique à un seul plugin = non affiché. |
| « La **note pèse plus que les installs** » | Réfuté par le code (rating 0.25 vs installs 0.375). Le rating compte, mais n'écrase pas les installs. |
| « Support **50% par défaut / 100% au 1ᵉʳ ticket** » | Confusion avec le `missing 0.5` du facteur support : c'est une **valeur de substitution** dans un `log2p(support_threads_resolved)` (compte de fils résolus), **pas un taux** 50%/100%. Plus de fils résolus = mieux, en rendement décroissant. |

**À noter (vrai, et souvent mal formulé) : « 2,5/5 par défaut sans avis » est CORRECT pour le ranking** (`missing 2.5` dans le code) — voir la règle ci-dessus. Le seul abus de langage est de le présenter comme une note publique.

⚠️ **Garde anti-péremption** : le cœur quantitatif (coefficients, `missing 2.5`, matching AND) est daté **2017** (+ confirmation code). Si la question porte sur les coefficients/le matching **eux-mêmes**, ou si l'enjeu de classement est fort, **revérifier le code vivant** (`class-plugin-search.php` sur le miroir GitHub trunk, URL en référence) avant d'affirmer ces chiffres comme l'état 2026 — l'algo a déjà migré (ES7) et peut avoir changé. Ne pas présenter les valeurs 2017 comme une certitude actuelle.

Détail complet, citations et URLs : `reference/wporg-search-algorithm.md`.

## Leviers réels, par ordre d'impact (sourcés)

1. **Titre du plugin (Plugin Name)** — champ le plus boosté. Phrase-clé exacte, descriptif, mémorable, < ~5 mots utiles. Couvre les mots des requêtes cibles principales.
2. **Active installs** — coefficient le plus fort. Hors readme (s'acquiert), mais c'est le signal n°1 → tout ce qui augmente l'install (titre clair, bons avis) le sert.
3. **Couverture des mots de chaque requête cible** dans `all_content` (titre + short desc + readme content + tags). Logique AND historique → **chaque mot d'une requête composée doit apparaître textuellement** au moins une fois.
4. **Short description (Excerpt)** — 2ᵉ champ texte le plus visible après le titre. Mots-clés pertinents, **pas de stuffing** (le handbook officiel l'interdit explicitement et c'est inutile).
5. **Notes/avis** — `sqrt(rating)` : obtenir les premiers avis positifs a un effet réel (sort le facteur de zéro). Prioriser le **premier avis authentique**.
6. **Fils de support résolus** — `log2p(...)` : résoudre publiquement des tickets améliore le rang (rendement décroissant).
7. **`Tested up to`** à jour — les recherches génériques favorisent les plugins testés avec une version WP récente (signal de fraîcheur/compat).
8. **Tags** — 12 indexés / 5 affichés, **poids faible** : remplir intelligemment (≤12, les 5 premiers = ceux qu'on veut voir affichés), sans en attendre de miracle ni faire de tag-stuffing.

## Méthode (workflow du skill)

1. **Collecter les intrants** : le `readme.txt` (ou les infos : titre, short desc, tags, tested-up-to) + une **liste de requêtes cibles**. Si absente, la **déduire** de la fonction du plugin (proposer la liste et la faire valider).
2. **Auditer la couverture par requête** : pour chaque requête cible, vérifier que **chaque mot** apparaît dans `all_content`. → Exécuter `scripts/coverage.py` (déterministe, voir ci-dessous). Sortir un **score de couverture par requête + mots manquants**.
3. **Auditer les champs** : titre (phrase-clé exacte présente ? < 5 mots utiles ?), short description (mots-clés des requêtes prioritaires présents ? pas de stuffing ?), tags (≤12, 5 premiers pertinents, pas de tag mono-plugin inutile), `Tested up to` (= dernière version WP majeure ?).
4. **Produire un diff de `readme.txt` optimisé** : intégrer les mots manquants **naturellement** (titre/short desc/sections, pas en bourrage), corriger les champs. Montrer avant/après.
5. **Plan d'action reviews + support** : comment obtenir le premier avis authentique (timing après succès produit, jamais d'incitation interdite par les guidelines), et résoudre/marquer des fils de support.
6. **Signaler les incertitudes datées** (AND vs OR 2025) au lieu de promettre.

## Garde-fous (refus / vigilance)

- **Jamais de keyword-stuffing** ni de tag-stuffing : interdit par les guidelines officielles ET inefficace (le handbook le dit). Refuser, proposer l'intégration naturelle.
- **Jamais d'incitation aux avis contraire aux guidelines WordPress.org** (pas d'avis achetés, pas d'avis en échange d'une contrepartie, pas de gating de fonctionnalité contre un avis). Proposer uniquement des demandes légitimes au bon moment.
- **Ne pas promettre de positions** : le ranking est multi-facteurs et l'algo a évolué (et continue). Donner des leviers, pas des garanties.
- **Ne pas présenter les chiffres Freemius (2,5 ; 50%) comme officiels** — si le user les cite, les corriger avec la source primaire.
- **Rester dans le couloir repo** : à la moindre dérive vers Google/pricing/code, déléguer (cf. Frontière dure).

## Script bundlé

`scripts/coverage.py` — analyse **déterministe** de couverture de mots-clés. Entrée : un readme.txt (ou texte `all_content`) + une liste de requêtes cibles. Sortie : par requête, les mots couverts/manquants et un verdict AND (inclus/exclu) + un score global. **À exécuter** (`python scripts/coverage.py <readme> <queries.txt>`), pas à réimplémenter de tête. Détails d'usage en tête du script.

## Référence (chargée à la demande, 1 niveau)
- `reference/wporg-search-algorithm.md` — formule Elasticsearch verbatim, pondérations réelles, sources primaires datées, tableau mythes/réalité détaillé, règles officielles tags/readme.
