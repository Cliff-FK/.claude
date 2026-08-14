---
paths:
  - "**/*.php"
  - "**/*.js"
  - "**/*.jsx"
  - "**/*.ts"
  - "**/*.tsx"
  - "**/*.py"
  - "**/*.astro"
  - "**/*.vue"
---

# Point d'extension : invariants de mise en œuvre

Complète la règle « séparer la feature de ses consommateurs » (CLAUDE.md). Le pattern est nommé : Dependency Inversion + Open/Closed → *plugin architecture* (Martin).

- **Nom namespacé.** Un point d'extension est une API publique dès qu'un tiers s'y branche : préfixe unique, sinon collision et bugs introuvables.
- **Lu le plus tard possible, à l'usage.** Un consommateur qui se déclare après la lecture est ignoré en silence. L'ordre d'initialisation n'est jamais symétrique : celui qui démarre en dernier peut se brancher sur l'autre, pas l'inverse.
- **Appliqué après tout cache.** Un registre mis en cache avant l'appel du point d'extension fige les déclarations faites en code jusqu'à expiration.
- **Renommer ou déprécier : la portée des appelants décide, pas l'ancienneté.** « Puis-je mettre à jour TOUS les appelants dans le même déploiement ? » Oui (pas encore livré, appelants du même dépôt) → renommer franchement, router les appelants dans le même commit, aucun alias. Non (dépôt déployé séparément, code déjà livré, code tiers) → déprécier avec période de grâce.
- **Deux entrées quand elles ont du sens** : déclarer une source à scanner, et inscrire une entrée à la main.
