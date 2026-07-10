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
