# Cas mixtes : composer plusieurs typologies sans incohérence

Beaucoup de projets réels mélangent des typologies (ex. **landing de vente d'un plugin** = vitrine + landing + pricing + réassurance + checkout + espace client). La règle : **un seul design system, plusieurs registres**.

## Ce qui reste CONSTANT partout
Ne jamais faire varier d'une zone à l'autre :
- **Tokens** : palette couleur, échelle typographique, grille d'espacement, élévations.
- **Identité** : logo, navigation principale, footer.
- **Composants de base** : styles de boutons (primaire/secondaire), cartes, champs de formulaire.
→ C'est ce qui donne la cohérence « même produit » à travers tout le parcours.

## Ce qui CHANGE par zone
- **Densité** : landing/hero = spacieux (sections 32-64px) ; pricing = modéré ; checkout = dense (réduire la charge cognitive, focus transaction) ; dashboard post-achat = dense (interface power-user).
- **Ton** :
  - *Zone persuasion (hero/landing)* : émotionnel, orienté bénéfice, narratif.
  - *Zone réassurance (FAQ/trust)* : factuel, clair, traite les objections.
  - *Zone transactionnelle (checkout)* : **la confiance prime sur la persuasion**. Sobriété, signaux de sécurité proéminents, CTA « Finaliser l'achat » (pas « C'est parti ! »).
  - *Zone app (dashboard/espace client)* : utilitaire, dense, orienté donnée.

## Arbitrage quand les codes s'opposent
La logique de la **zone et du moment du parcours** prime sur la typologie globale :
- densité faible (vitrine) **vs** forte (admin) → appliquer celle de la zone affichée.
- persuasion (landing) **vs** sobriété (checkout) → au moment de payer, la sobriété/confiance gagne toujours.
- esthétique expressive (éditorial) **vs** neutre (docs) → selon que l'utilisateur lit ou opère.

## Détection « projet mixte » et registre dominant
1. Repérer les signaux multiples dans la demande (checkout + espace connecté + contenu long…).
2. Choisir le registre **dominant** (l'objectif business principal) pour l'identité visuelle globale.
3. Traiter les autres comme **zones secondaires** héritant des tokens, avec densité/ton adaptés.

## Cas d'école : landing de vente d'un plugin/SaaS
Parcours cohérent en une page (ou un site) :
`hero vitrine (persuasion) → preuve/bénéfices → pricing (clair, plan recommandé) → réassurance (FAQ, garantie, trust badges) → checkout (sobre, sécurité) → espace client (app, dense)`.
- Tokens/nav/footer identiques du hero au checkout.
- Le hero séduit ; le checkout rassure. Ne pas inverser (un checkout « survendeur » fait fuir).