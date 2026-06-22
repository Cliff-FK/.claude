---
paths:
  - "**/wp-content/plugins/morph-blocks/**"
---

# morph-blocks — pipeline multi-zones (une modif locale = risque cross-zone)

- **Source vs build** : éditer la source (`includes/`, `admin/`, `licensing/` + JS source), **jamais `build/dist/**`** (sortie générée).
- **Constantes = source de vérité** : `includes/constants.php` (SCHEMA_VER, meta keys, markers) — jamais de mémoire. Parité **PHP↔JS byte-for-byte** ; tout changement de payload/sig/meta-key → bump `MORPH_BLOCKS_SCHEMA_VER`.
- **Zones** (editor / build+cache / serve / front / licensing / signature) reliées par la signature `pos_<hex>` + cache 1-ligne/post : **signaler les contrats cross-zone (seams) AVANT de changer**.
- **Selon le besoin** : tâche cross-zone ou « pourquoi X end-to-end » → agent `morph-orchestrator` ; investigation d'une zone localisée → `morph-blocks-auditor` / l'agent de zone. **Pas pour une retouche triviale mono-fichier.**
- **Jamais « résolu » sans chaîne admin→cache→front validée (vrai save UI, clic réel)** ; tout diagnostic = hypothèse jusqu'à mesure directe.
