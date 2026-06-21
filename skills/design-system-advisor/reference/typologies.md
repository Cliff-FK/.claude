# Anatomie par typologie

Pour chaque typologie : **structure de page** (sections + ordre), **codes esthétiques**, **navigation**, **pièges**. Les valeurs atomiques (espacement, contraste…) restent gouvernées par `design-auditor/references/`.

## SaaS (produit + app)
**Deux faces** : la *landing produit* (vendre) et l'*app/dashboard* (utiliser).
- **Landing produit** : hero (value prop + CTA + screenshot produit réel) → logos clients → bénéfices (orientés résultat, pas feature) → preuve sociale → pricing → FAQ → CTA final.
- **App/dashboard** : sidebar gauche collapsible (souvent sombre) + contenu clair ; profil en haut à droite ; recherche/breadcrumb en haut. Cartes KPI (chiffre proéminent + label + tendance), tables denses avec hover, modales de formulaire à validation temps réel.
- **Codes** : accent bleu/violet (confiance), dark mode attendu pour power users, densité moyenne, **empty states illustrés** (message utile + CTA « créer votre premier X » + lien doc).
- **Piège** : ne pas appliquer la densité « landing » à l'app, ni l'inverse.

## Vitrine / marque
- **Sections** : hero de marque (headline + subhead + CTA + visuel fort) → storytelling (image+texte alternés gauche/droite) → services/valeurs → équipe → témoignages → CTA contact → footer riche.
- **Codes** : typographie expressive, whitespace généreux (sections 64px+), **photo réelle > stock** (la photo générique = signal amateur #1), hero qui occupe ≥ la moitié de l'écran, CTA répétée (hero + sections + footer).
- **Footer riche** : liens utiles, contact, réseaux, légal — les visiteurs y vont directement pour le contact.

## E-commerce
- **Pages clés** : home boutique → catégorie/listing (filtres à gauche/haut, sous-catégories séparées des filtres) → fiche produit → panier/mini-cart → checkout.
- **Grille produits** : 3-4 colonnes desktop, 2 tablette, 1-2 mobile ; aspect ratio des images **verrouillé** et cohérent ; gutters réguliers.
- **Fiche produit** : galerie (image large + miniatures), zone add-to-cart sticky en mobile, variantes en swatches (pas dropdown si possible), specs/avis/FAQ en accordéon.
- **Checkout** : étapes claires, invité autorisé (pas de compte forcé), **pas de frais surprises tardifs**, badges sécurité près du paiement.
- **Codes** : palette neutre qui laisse parler la photo produit (le roi), prix lisibles (cf. micro-pattern prix), feedback add-to-cart explicite.

## Landing de conversion (mono-objectif)
- **Above-the-fold** doit répondre en 3 s : *quoi ? pourquoi ? et après ?* → value prop + subhead + **CTA unique** + visuel + signaux de confiance.
- **Ordre persuasif** : hero → preuve (logos/stats) → bénéfices orientés résultat → preuve sociale/témoignages → FAQ/objections → CTA final (+ urgence si justifiée).
- **Un seul objectif** : CTA unique répétée (hero + mi-page + fin). Navigation minimale. Plusieurs CTA concurrents = chute de conversion.
- **Copy** : promesses chiffrées > bénéfices vagues.

## Éditorial / portfolio
- **Portfolio** : grille de projets (bento, hauteurs variées) → page projet/étude de cas.
- **Éditorial** : home magazine → article long-form → archive.
- **Article long-form** : mesure de ligne 65-75 car., **line-height 1.6-1.8**, sous-titres tous les 2-3 paragraphes, images insérées dans le flux (pas seulement en tête), pull-quotes (retrait, italique, couleur atténuée).
- **Codes** : typographie expressive (le cœur du registre), whitespace radical, audace maîtrisée.

## Docs / admin / dashboard
- **Docs** : 3 colonnes (nav arborescente + contenu + sommaire ancré), recherche proéminente, fil d'Ariane, prev/next, blocs de code copiables et colorisés.
- **Admin/dashboard** : sidebar + topbar, grille de cartes KPI, tables denses (tri/filtre/pagination, sticky header), états gérés (loading/empty/error), data-viz.
- **Codes** : **densité haute** (power users), lisibilité avant esthétique, neutralité (la donnée prime), monospace pour code/chiffres, dark mode quasi attendu, motion minimal et fonctionnel.
