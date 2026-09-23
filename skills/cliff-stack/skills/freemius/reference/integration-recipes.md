# Recettes d'intégration Freemius (sécurisées, idiomatiques)

> Confirmer chaque signature via `sdk-surface.md` / Context7 avant d'écrire. Les exemples reflètent des conventions **anti-vendor-lock-in** (ports & adapters) déjà vues sur les projets de l'utilisateur — réutiliser l'existant, ne pas dupliquer.

## 1. Webhook — vérifier la signature AVANT tout (fail-closed)
Freemius signe chaque webhook : header **`X-Signature` = HMAC-SHA256 du body BRUT** avec la **Secret Key du PRODUIT** (il n'existe **pas** de « webhook secret » dédié). Recalculer sur le corps brut, jamais sur du JSON ré-encodé. Répondre **200 vite**, traitement **idempotent** (les webhooks sont rejouables).

PHP (motif fail-closed) :
```php
$raw = file_get_contents('php://input');
$secret = getenv('FREEMIUS_SECRET_KEY') ?: '';
$sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if ($secret === '' || $sig === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
    http_response_code(401); exit; // ne RIEN traiter avant cette barrière
}
```
JS/TS : préférer **`fs.webhook`** (vérif native du SDK) à un HMAC maison.
Le webhook est le **SEUL** endroit qui écrit le cache `entitlements`. Jamais de remplissage manuel.

## 2. Customer portal (actions sensibles via lien hébergé signé)
```ts
const portal = await fs.api.user.retrieveHostedCustomerPortal(fsUserId);
// portal = { token?, link? }  →  rediriger vers portal.link  (PAS .url)
```
`fsUserId` vient **toujours de la session serveur**, jamais d'un paramètre client (anti-IDOR). Annulation/reprise/changement de carte : soit via portail, soit inline (`subscription.cancel`, voir §4).

## 3. Entitlements = cache de droits (Freemius = source de vérité)
- Table locale `entitlements` remplie **uniquement par webhook**. Lecture rapide des droits sans appel API à chaque requête.
- Colonne `provider` = anti-lock-in (un autre adapter remplit la même table).
- Factures, paiements, cartes : **jamais stockés** ; récupérés à la volée (`user.retrievePayments`, `retrieveInvoice` → Blob PDF).
- `fs.entitlement` (service SDK) aide à valider un droit/licence côté serveur.

## 4. Annulation : inline possible (ne pas tout router vers le portail par défaut)
```ts
await fs.api.subscription.cancel(subscriptionId, feedback?, reasonIds?); // DELETE — inline
```
Choisir inline vs portail selon l'UX voulue, mais documenter la `capability` (`inline` vs `portal`) dans l'adapter. Coupon de rétention : `subscription.applyRenewalCoupon(...)` + `product.retrieveSubscriptionCancellationCoupon()`.

## 5. Checkout / achat
- JS in-app : `fs.checkout` / `fs.purchase` (SDK) ou le **Checkout JS SDK** (`@freemius/freemius-checkout-js`, Context7 `/freemius/freemius-checkout-js`) pour un bouton d'achat.
- Token de checkout serveur : REST `/api/users/create-checkout-token`.
- Upgrade depuis une licence : `license.retrieveCheckoutUpgradeAuthorization(licenseId)` → `settings.authorization`.

## 6. Ports & Adapters (anti-vendor-lock-in) — convention projet
- L'UI ne connaît qu'un **port** agnostique + un **domaine** normalisé ; jamais `if (provider === 'freemius')`. Elle lit une **`capabilities` map** (`inline` / `portal` / `external` / `unsupported`) pour décider quoi afficher.
- L'adapter Freemius **traduit** les champs snake_case réels (cf. `sdk-surface.md`) vers le domaine. Ce qui est propre à Freemius va dans un champ `raw`, jamais dans le cœur typé.
- L'adapter vit **côté serveur uniquement** (détient les secrets). Mapping non confirmé empiriquement (sandbox) ⇒ `TODO(sandbox)`, pas une shape devinée.

## 7. WordPress SDK (PHP, côté plugin/thème vendu)
Pour le produit WordPress lui-même (licensing, mise à jour, opt-in, `fs_*`) : `Freemius/wordpress-sdk` (Context7 `/freemius/wordpress-sdk`). Distinct du SDK serveur `@freemius/sdk` qui interroge l'API depuis ton backend.

## 8. Sandbox d'abord (gratuit, sans vente)
Compte dev Freemius gratuit → produit test → API Token + Product ID dans le dashboard → `FREEMIUS_SANDBOX=true` → achat sandbox (CB de test) → vérifier que le webhook remplit `entitlements` → confirmer empiriquement les shapes avant de retirer les `TODO(sandbox)`.
