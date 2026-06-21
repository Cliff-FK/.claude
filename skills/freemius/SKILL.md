---
name: freemius
description: Use for ANYTHING Freemius (freemius.com) — the monetization platform for WordPress plugins/themes, SaaS and apps. Covers BOTH code (the @freemius/sdk JS/TS SDK, freemius-php-sdk, freemius-node-sdk, WordPress SDK, Checkout JS, webhooks, REST API, entitlements, customer portal) AND product/strategy (plans & pricing, subscriptions, trials, licensing, multi-currency, VAT/US sales tax, dunning, coupons, affiliate, refunds, deployments/releases). Enforces: real SDK surface (never invented field names), Context7 as source of truth before coding, security guardrails (HMAC webhook, server-only secrets, no IDOR). Triggers on "Freemius", "@freemius/sdk", licence/abonnement/webhook/checkout/portal Freemius, entitlements, déploiement/release Freemius. NOT le "vendre/monétiser mon plugin" générique sans Freemius nommé — modèle de prix/paliers → pricing-strategist, copy de page de vente → copywriting-landing, acquisition → seo-launch/paid-ads.
---

# freemius — référent Freemius (code + produit), sourcé et sécurisé

> **Réponds toujours en français** (accents complets). Termes techniques (entitlement, webhook, install…) et identifiants de code inchangés.

Référent Freemius **complet** : coder le SDK **et** conseiller la monétisation (plans, pricing, fiscalité, trials, licensing, dunning, affiliation). Tout **sourcé** (jamais de mémoire pour une signature/un champ), **idiomatique au projet**, **sûr** (le secret ne fuite jamais côté client).

> **RÈGLE NON NÉGOCIABLE #1 (ne rien inventer)** — Aucune méthode SDK, nom de champ, endpoint REST ou comportement webhook écrit **de mémoire**. Confirmer dans l'ordre : (1) `reference/sdk-surface.md`, (2) **Context7** (§1), (3) `node_modules/@freemius/sdk` du projet, (4) doc officielle (`reference/api-map.md`). Sinon → le dire, ne pas inventer.

> **RÈGLE NON NÉGOCIABLE #2 (surface SDK ≠ surface API — anti-faux-négatif)** — Le wrapper `fs.api.*` du SDK ne couvre qu'une **fraction** des routes REST réelles. **NE JAMAIS** répondre « impossible / non supporté / pas de voie » sur la seule absence d'une méthode `fs.api.*`. Avant TOUT verdict de faisabilité **négatif**, grep le **schéma OpenAPI réel** (= `node_modules/@freemius/sdk/dist/index.d.ts`, généré depuis l'OpenAPI officiel) pour la ressource concernée. Cf. **§2bis (protocole de faisabilité)**. Faux-négatifs déjà avérés (corrigés) : lister les installs d'une licence, modifier un profil user, désactiver un install — TOUS possibles en REST, AUCUN wrappé. Ne pas les reproduire.

## 0. Avant d'agir
1. **Découvre l'existant** (Grep/Read) : couche billing / adapter / webhook / `.env` Freemius déjà là ? Conventions (ports & adapters ? secrets `astro:env` ? proxy PHP ?). **Étendre, pas dupliquer.**
2. **Version réelle du SDK** (`package.json` → `@freemius/sdk`). `reference/sdk-surface.md` documente **0.3.x** ; si ≠, re-vérifier via Context7/`node_modules`.
3. **Code vs produit** : « annuler un abonnement » → SDK ; « plan lifetime ? TVA UE ? » → produit (`reference/api-map.md` + skill `pricing-strategist`).

## 1. Context7 — doc à jour (IDs officiels pré-résolus)
- **`/freemius/freemius-js`** — `@freemius/sdk` JS/TS (ID principal pour le code SDK).
- **`/freemius/wordpress-sdk`** — WordPress SDK (PHP, plugin/thème, `fs_*`).
- **`/freemius/freemius-checkout-js`** — Checkout JS (bouton d'achat).
- **`/websites/freemius_help`** — base d'aide (questions **non-code** : produit, vente, fiscalité).

## 2. Pièges qui causent des bugs réels (détail + surface complète : `reference/sdk-surface.md`)
- État annulé d'abonnement = **`canceled_at`** / `next_payment` null — **PAS `is_canceled`**. Licence annulée = **`is_cancelled`** (double L).
- **`fs.api.license.retrieveInstalls()` (wrapper) n'existe pas** en 0.3.x → MAIS la **route REST existe** : `GET /products/{id}/licenses/{license_id}/installs.json` (et `GET .../users/{user_id}/installs.json`). « Pas wrappé » ≠ « impossible » (cf. RÈGLE #2). Appeler le client REST sous-jacent.
- Customer portal → `{ token?, link? }` : le champ URL est **`link`**. Montant d'abonnement = **`total_gross`** (pas `gross`). Champs réponse = **snake_case**.
- **`subscription.cancel()` existe** (DELETE) → annulation **inline possible**, pas forcément portail.
- **Prix multi-devises NATIFS** : `retrieve-pricing-table-data` → `all_plans_single_site_pricing[plan_id]` avec `monthly/annual/lifetime_price` en **`{ usd, eur, gbp }`**. Ne PAS conclure « USD-only » depuis `pricing.retrieve()` (vue `Pricing` mono-devise ≠ vue pricing-table multi-devises).
- **`track_event()` (WP SDK) est GATÉ** : la fonction existe mais l'API **ignore silencieusement** l'appel selon le plugin ID, sauf clients whitelistés (docstring SDK). **Ne pas s'en servir pour un "aha moment"/analytics custom** → couche maison. Détail : `reference/billing-mechanics.md` §5.
- **Gater le code premium avec `can_use_premium_code()`** (couvre trial + licence active), **pas** `is_paying()` (rate le trial). À l'expiration : updates+support toujours coupés ; features = configurable par plan.
- **Dunning natif = J+1/J+3/J+5 fixe** + rappels de renouvellement **J-30/J-7/J-2/J+1** déjà natifs (le « pré-dunning J-7 à bâtir » est un faux besoin). Exemptions dev/staging auto. Détail : `reference/billing-mechanics.md`.

## 2bis. Protocole de FAISABILITÉ (toute question « est-ce possible ? ») — 3 niveaux
Énoncer un verdict **par niveau**, jamais un « impossible » sec :
1. **Wrapper** — méthode `fs.api.*` / service dédié existe ? (= confort, pas la faisabilité).
2. **REST** — la route existe-t-elle dans `node_modules/@freemius/sdk/dist/index.d.ts` (schéma OpenAPI réel) ? **C'EST CE NIVEAU QUI TRANCHE LA FAISABILITÉ.** One-liner :
   ```bash
   # remplace <ressource> (installs, users, licenses, payments, subscriptions…)
   grep -noE '"/products/[^"]*<ressource>[^"]*\.json"' node_modules/@freemius/sdk/dist/index.d.ts
   # puis lire le bloc pour le verbe HTTP (get/put/post/delete) :
   #   sed -n 'L,+30p' index.d.ts | grep -oE '^\s*(get|put|post|delete|patch):'
   ```
3. **Empirique** — shape (noms de champs) confirmée par un **appel sandbox réel** ? Tant que non → `TODO(sandbox)`, ne pas câbler l'UI sur des champs supposés.

→ Verdict honnête type : « possible au niveau REST (`…/installs.json`, GET, l.1828 du .d.ts), non wrappé en 0.3.x, shape à confirmer sandbox ». Le fallback portail est un **choix d'UX**, jamais une excuse pour déclarer une capability `unsupported` sans avoir fait le grep REST.

## 3. Sécurité (garde-fous durs — critères de refus)
- **Secrets serveur uniquement** : `apiKey`/`secretKey` jamais dans un bundle/island/`PUBLIC_*`. SDK complet côté serveur (BFF/proxy), jamais importé par un composant client.
- **Webhook : vérifier la signature HMAC AVANT tout traitement** (fail-closed). Header `X-Signature` = HMAC-SHA256 du body **brut** avec la **Secret Key produit** (pas de « webhook secret » dédié). Préférer `fs.webhook` (vérif native).
- **Identité depuis la session serveur**, jamais d'un paramètre client (`fsUserId`) — anti-IDOR.
- Freemius = **source de vérité** ; `entitlements` locaux = cache rempli **par webhook**. Factures/cartes jamais stockées.

## 4. Sortie
- **Diff minimal, étendre > ajouter.** Idiomatique (TS strict, couche existante).
- Toute signature/champ **traçable** à une source. Shape non confirmée empiriquement ⇒ **`TODO(sandbox)`**, pas une devinette.
- Vérifier le comportement réel quand possible (achat sandbox, webhook reçu, statut DB) — **signal direct, jamais proxy**.
- Plan/diff **avant** d'écrire si non trivial.

## État écosystème IA Freemius (juin 2026)
**Aucun MCP / skill / agent officiel** — ce skill est la réponse maison. Officiel embryonnaire : `Freemius/freemius-ai` (exemples vibe-coding, « skill assets planned ») — surveiller. Tiers : `itaides/freemius-mcp` (MCP communautaire ~140 ops, non publié npm) = inspiration, pas à installer.

## Références (charger à la demande)
- `reference/sdk-surface.md` — surface complète `@freemius/sdk@0.3.x` (services, namespaces, signatures, champs, pièges).
- `reference/api-map.md` — carte `llms.txt` : endpoints REST + doc produit.
- `reference/integration-recipes.md` — recettes : webhook HMAC, portal, entitlements, checkout, ports & adapters.
- `reference/billing-mechanics.md` — mécaniques commerciales vérifiées : dunning (J+1/J+3/J+5), rappels renouvellement (J-30/J-7/J-2/J+1), exemptions dev/staging, coupons first-payment/renewals, `track_event` gaté, gating à l'expiration.

## Anti-patterns à refuser
Champ/méthode inventé « de mémoire » • `is_canceled` (au lieu de `canceled_at`/`is_cancelled`) • **conclure « impossible » sans avoir grep le schéma REST `index.d.ts` (viole RÈGLE #2)** • **déclarer une capability `unsupported`/`portal` sur la seule absence de wrapper `fs.api.*`** • « USD-only » sans vérifier `all_plans_single_site_pricing` (eur/gbp) • secret Freemius côté client / `PUBLIC_` • webhook sans vérif HMAC • `fsUserId` d'un paramètre client • stocker factures/cartes • dupliquer une couche billing au lieu d'étendre l'adapter • shape non confirmée non flaguée `TODO(sandbox)`.
