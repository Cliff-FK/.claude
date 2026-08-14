---
paths:
  - "**/wp-content/**/*.php"
  - "**/wp-config.php"
  - "**/wp-content/**/*.js"
  - "**/wp-content/**/*.jsx"
  - "**/wp-content/**/*.ts"
  - "**/wp-content/**/*.tsx"
  - "**/wp-content/**/*.scss"
  - "**/wp-content/**/*.css"
  - "**/block.json"
---

# Invariants WordPress (natif, rien en dur)

- **Avant toute feature WP : invoquer le skill `cliff-stack:wp-native`** (nom qualifié obligatoire, le nom court échoue) (vérif API via Context7, natif-first, sécurité).
- Garde-fous immédiats : valeurs réelles du **thème activé** via `wp_get_global_settings()` (slugs réels, jamais inventés ni les défauts WP) ; blocs via `WP_Block_Type_Registry`, jamais en dur.
- **Points d'extension** (déclinaison WP de la règle « séparer la feature de ses consommateurs ») : `apply_filters`/`do_action` préfixés — le Plugin Handbook en fait une consigne, les collisions donnent des bugs introuvables. Ordre d'init : `plugins_loaded` → `functions.php` du thème → `after_setup_theme` → `init` : **le thème peut se brancher sur un plugin, l'inverse n'est pas garanti** ; un registre alimenté par filtre se lit donc à l'usage (requête REST, `wp_enqueue_scripts`, `render_block`), jamais au chargement du fichier. Dépréciation d'un hook déjà consommé hors du dépôt : `apply_filters_deprecated()` / `do_action_deprecated()`.
- **Thème et plugin sont déployés séparément** : un thème peut tourner face à une version de plugin antérieure. Toute fonction du plugin appelée par le thème passe par un `function_exists()` ou un stub, et un garde global « plugin actif → tout va bien » est faux (actif ≠ à jour).
