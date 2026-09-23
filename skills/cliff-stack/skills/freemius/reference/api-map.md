# Carte de la doc & API Freemius (depuis `llms.txt`)

> Deux index machine-readable officiels :
> - Produit/aide : `https://freemius.com/llms.txt`
> - API/dev : `https://docs.freemius.com/llms.txt`
> OpenAPI : `https://freemius.com/help/documentation/api/openapi.yaml`
> Dashboard : `https://dashboard.freemius.com`

Utiliser cette carte pour **router vers la bonne page** au lieu de deviner une URL. Pour le contenu à jour, préférer Context7 `/websites/freemius_help`.

## API REST (docs.freemius.com/api)
Auth : Bearer token — `https://docs.freemius.com/api/section/bearer-token-auth` ; scopes : `.../section/other-scopes-and-authentication`.

**Users**
- List users : `/api/users/list` • licences : `/api/users/list-licenses` • paiements : `/api/users/list-payments` • abonnements : `/api/users/list-subscriptions`
- Créer un checkout token : `/api/users/create-checkout-token`

**Licenses**
- Activer : `/api/licenses/activate` • Désactiver : `/api/licenses/deactivate`
- Lien d'upgrade : `/api/licenses/generate-upgrade-link` • URL d'avis : `/api/licenses/get-review-url`

**Subscriptions & Payments**
- Lister subs : `/api/subscriptions/list` • récupérer : `/api/subscriptions/retrieve` • paiements d'un sub : `/api/subscriptions/list-payments`
- Paiement : `/api/payments/retrieve` • lister : `/api/payments/list`

**Products / Pricing**
- Données de la table de pricing : `/api/products/retrieve-pricing-table-data`
- Reviews : `/api/products/list-reviews` • `/api/products/create-review`

**Deployments (release management)**
- `/api/deployments/create` • `update` • `get-latest` • `list` • `download`

## Doc produit (freemius.com/help/documentation)
**Vente & checkout** : hosted-checkout, embedded checkout (buy-button), CSS customization, proration, refund-payment.
**Plans & pricing** : free-trials, multi-currency, setup-product-pricing-plans-refunds, refund-policy.
**Licensing** : integrating-license-key-activation, license-renewals-mechanism, customize-license-unit-label.
**Intégration dev** : integrating-your-first-product, saas-integration, app-integration, integration-with-sdk, wordpress-sdk, events-webhooks, customer-portal (users-account-management).
**Fiscalité** : us-sales-tax-and-economic-nexus, eu-vat-uk-vat-europe (+ pages refund TVA).
**Marketing/growth** : cart-abandonment-recovery, dunning-failed-payments, special-coupons-discounts, reviews, affiliate-platform.
**Emails** : email-settings, email-style-customization, email-deliverability, transactional-emails.
**Revenue/paiement** : our-pricing, your-earnings.
**Analytics/sécurité** : insights-dashboard, orders-history, team-member-role-management.
**Release** : deployment, staged-rollouts, incremental-update.

## SDK open-source officiels
- JS/TS : `github.com/Freemius/freemius-js` → npm `@freemius/sdk` (voir `sdk-surface.md`)
- PHP : `github.com/Freemius/freemius-php-sdk`
- Node (legacy) : `github.com/Freemius/freemius-node-sdk`
- WordPress (PHP, plugin/thème) : `github.com/Freemius/wordpress-sdk` (Context7 `/freemius/wordpress-sdk`)
- Checkout JS : `github.com/Freemius/freemius-checkout-js` (Context7 `/freemius/freemius-checkout-js`)
