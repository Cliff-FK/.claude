# Catalogue de micro-patterns à convention forte

Pour chaque composant : **forme PRO** (ce qu'il faut générer) + **❌ erreur amateur**. Ce sont des conventions visuelles fortes : s'en écarter « fait amateur » même si le reste est propre.

## PRIX & PROMO (le plus discriminant)
**Forme PRO** (vertical, hiérarchisé) :
```
-30%            ← badge promo (petit, coloré)
12,99 €         ← prix barré PETIT (~12px), gris/atténué, line-through
8,99 €          ← prix réel GROS (18-24px), bold, couleur d'accent
```
- Les 3 éléments **proches** (<20px), empilés, prix réel **dominant visuellement**.
- Le barré sert seulement d'ancre de comparaison → il doit être **discret**.
- **❌ Erreur amateur** : `12,99 €  8,99 €` côte à côte, **même taille**, barré à gauche / réel à droite, désalignés → l'œil ne sait pas en 1 s quel prix il paie.
- Source : Baymard Institute (product page price discounts) : barré + badge + réel hiérarchisé, proximité immédiate, couleur distinctive sur le prix final.

## Pricing table
**Forme PRO** : toggle mensuel/annuel **centré au-dessus** → 3 colonnes côte à côte → colonne du milieu = **« recommended »** (fond légèrement teinté OU badge « Most Popular » OU légèrement plus grande) → CTA primaire (filled) sur le plan recommandé, secondaires sur les autres.
- 3 plans = point d'équilibre (choix sans paralysie).
- Le toggle met à jour les chiffres, pas la structure.
- **❌ Amateur** : aucun plan mis en avant, tarifs noyés dans des listes égales, pas de toggle.

## Carte produit
**Forme PRO** : image (aspect ratio verrouillé) → titre 1 ligne max (14-16px) → meta optionnelle (12px gris) → note/étoiles → prix (cf. ci-dessus) → CTA filled « Ajouter ». Padding intérieur 16px.
- **❌ Amateur** : ratios d'images hétérogènes, titres qui débordent sur 3 lignes, texte rétréci <14px pour faire tenir l'info (→ déplacer en fiche).

## CTA primaire vs secondaire
**Forme PRO** : primaire = bouton **plein**, couleur de marque, fort contraste, **verbe d'action** (« Commencer », « Télécharger ») ; secondaire = **ghost/outline** ou lien souligné, moins proéminent. Hiérarchie : **taille > remplissage > couleur**.
- **❌ Amateur** : deux boutons de même poids visuel (l'utilisateur ne sait pas lequel est l'action principale), CTA générique (« Cliquez ici »).

## Hero
**Forme PRO** : headline (proposition de valeur, ~10-15 mots, clarté > malice) + subhead (~20 mots, le « pourquoi ») + CTA primaire (+ secondaire optionnel) + visuel net (screenshot produit réel ou illustration sur-mesure).
- **❌ Amateur** : headline vague (« Bienvenue »), pas de CTA above-the-fold, stock photo générique non justifiée.

## Preuve sociale
**Forme PRO** : logos clients **above-the-fold** (grayscale, 5-8, discrets) ; témoignages = citation + **nom + rôle + photo** ; au checkout, badges sécurité **près du champ de paiement** (2-3× plus efficaces que dans le header/footer) ; stats chiffrées (« 10k+ utilisateurs »).
- **❌ Amateur** : témoignages anonymes/vagues, badges entassés (effet spam), faux avis, badges génériques au lieu d'officiels (Norton/McAfee).

## FAQ accordéon
**Forme PRO** : question = en-tête cliquable (état hover visible), réponse en expand/collapse animé, titre ≥16px, ordre par fréquence ou par flux d'objections. Placement : après les features, avant le CTA.

## Trust badges
**Forme PRO** : minimal, discret (bas-droite ou près du formulaire), couleur alignée à la marque : sécurité/confidentialité, retours (« satisfait ou remboursé 30j »), garantie.

## Formulaire (4 états + a11y)
**Forme PRO** : **label toujours visible** (jamais placeholder seul) → input → helper optionnel (12px gris) → message d'erreur explicite + actionnable.
- **default** : bordure 1px neutre. **focus** : bordure 2px accent + focus ring clavier visible. **error** : bordure rouge + icône + message texte (pas la couleur seule) + `aria-invalid="true"` + `aria-describedby`. **disabled** : fond grisé, pas d'interaction.
- Validation au **blur** (pas à chaque frappe), focus auto sur la 1re erreur à la soumission.
- **❌ Amateur** : placeholder-only (le label disparaît à la saisie), erreur « Invalide » sans dire quoi corriger, erreur signalée par la couleur seule (inaccessible).
- Détail complet des états → `design-auditor/references/states.md`.
