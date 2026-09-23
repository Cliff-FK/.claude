# Dépréciations de bloc : changer le save() sans casser l'existant

Source : developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/

- **Quand** : toute modification du markup produit par `save()` ou de la forme des attributs d'un bloc statique déjà publié. Sans dépréciation, le contenu existant échoue à la validation de bloc.
- **Ordre** : le tableau `deprecated` va **du plus récent au plus ancien** (« reverse chronological order ») : l'éditeur essaie d'abord les versions les plus probables.
- **Rien n'est hérité** : `attributes`, `supports` et `save` ne sont pas repris de la version courante. Chaque entrée recopie la définition **telle qu'elle était** à cette version (figer une copie, ne pas importer la constante courante qui évoluera).
- **`save`** : le rendu exact de l'ancienne version, qui sert à valider le markup stocké.
- **`migrate( attributes, innerBlocks )`** : transforme les anciens attributs vers la forme actuelle ; retourne les attributs, ou `[ attributes, innerBlocks ]` si les inner blocks changent.
- **`isEligible`** : force la migration d'un bloc pourtant valide (cas où l'ancien markup reste valide mais les attributs doivent migrer).
- **Fixtures** : conserver le markup sérialisé de chaque version publiée et tester que chacune se charge et migre (recommandation de la doc). Une dépréciation non testée sur un vrai contenu d'époque n'est pas prouvée.
- **Pas de chaînage** : la première entrée dont le `save` valide le markup stocké gagne, son `migrate` produit le résultat final, les entrées suivantes sont sautées (sauf `isEligible`) (`applyBlockDeprecatedVersions`, `wp-includes/js/dist/blocks.js:5633-5686`, 7.1.2). Chaque `migrate` doit donc sortir la forme **actuelle** : à un nouveau changement de schéma, ajouter une entrée en tête ET mettre à jour la sortie des `migrate` existants.
