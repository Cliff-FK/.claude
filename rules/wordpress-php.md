---
paths:
  - "**/wp-content/**/*.php"
  - "**/wp-config.php"
---

# Invariants WordPress (natif, rien en dur)

- **Avant toute feature WP : invoquer le skill `wp-native`** (vérif API via Context7, natif-first, sécurité).
- Garde-fous immédiats : valeurs réelles du **thème activé** via `wp_get_global_settings()` (slugs réels, jamais inventés ni les défauts WP) ; blocs via `WP_Block_Type_Registry`, jamais en dur.
