# Pipeline headless/CI Godot 4.x — référence complète

Vérifié sur Godot 4.7-stable win64 (2026-07). Binaire : voir SKILL.md (jamais en dur).

## Commandes

```
godot --headless --version
godot --headless --path <projet> --import                      # (ré)importe ; 2x au premier setup
godot --headless --check-only --script res://foo.gd            # syntaxe seule
godot --headless --path <projet> res://scenes/x.tscn --quit-after 2   # exécute une scène ~2 frames
godot --headless --path <projet> --script res://tests/t.gd     # script extends SceneTree
godot --headless --path <projet> --export-release "Windows Desktop" build/jeu.exe
godot --headless --path <projet> --export-debug "Windows Desktop" build/jeu-debug.exe
godot --headless --doctool <dossier>                           # dump XML de TOUTES les classes de CETTE version (vérité terrain)
```

- `--headless` = display driver headless + audio Dummy. `--import` démarre l'éditeur sans fenêtre, importe, quitte.
- `-s`/`--script` : le script doit `extends SceneTree` (ou MainLoop) ; entrée = `_init()` ; terminer par `quit(code)`. Sans `quit()`, le process ne rend pas la main.
- `--quit-after N` : quitte après N frames. Piège #77508 : `--quit` peut échouer sur l'import là où `--quit-after 2` passe.

## Exit codes : ne jamais s'y fier seuls

- Exit 1 trompeur documenté en CI même quand l'opération a réussi (#83449) ; inversement, des erreurs de parse peuvent s'imprimer avec exit 0 selon le chemin.
- Signal fiable = sémantique directe : ligne stdout attendue (`print` sentinelle), existence/fraîcheur de l'artefact (`build/jeu.exe`, `.godot/imported/*`), ou test SceneTree avec `quit(0/1)` explicite.
- Toujours faire la contre-épreuve du harnais de test une fois (le casser volontairement → il doit échouer).

## Export Steam/Windows

1. Templates : télécharger `Godot_v<version>_export_templates.tpz` de la MÊME release, extraire le contenu de `templates/` vers `%APPDATA%/Godot/export_templates/<version>.stable/`.
2. `export_presets.cfg` à la racine du projet : fichier INI texte, créable/éditable à la main (sections `[preset.0]` name="Windows Desktop", platform="Windows Desktop", `[preset.0.options]`). **À COMMITTER** (la CI en a besoin). Les credentials vivent dans `export_credentials.cfg` (ignoré par défaut, le laisser ignoré).
3. `--headless --import` AVANT l'export (sans `.godot/` régénéré, l'export échoue — #71521, #95287, comportement variable selon versions).
4. Vérifier l'artefact produit (taille > 0, date), pas le seul exit code.

## Matrice de versioning git

| Fichier | Git |
|---|---|
| `project.godot`, `*.tscn`, `*.tres`, `*.gd` | oui |
| `*.gd.uid`, `*.gdshader.uid` | **oui** (lockfile de références ; déplacer avec le fichier maître) |
| `*.import` (sidecars d'assets) | **oui** (config d'import ; sinon reset au clone) |
| `export_presets.cfg` | **oui** |
| `export_credentials.cfg` | non (ignoré par défaut) |
| `.godot/` | **non** (cache régénéré ; l'absence exige juste un `--import` en CI) |
| `build/`, `*.exe`, `*.pck` | non |

## Tests

- **Léger (zéro dépendance)** : script `extends SceneTree`, assertions manuelles, `push_error` + `quit(1)` en échec, `quit(0)` en succès. Pattern éprouvé : `ResourceLoader.exists()` → `load()` → `instantiate()` → `get_root().add_child()` (détecte les erreurs d'intégration) → parcours de hiérarchie pour vérifier le contenu réel (ex. présence d'un MeshInstance3D) → `free()`.
- **GUT 9.x** : `godot --headless -s res://addons/gut/gut_cmdln.gd -gdir=res://tests -gprefix=test_ -gexit` (exit non-nul si échec).
- **GdUnit4** : `addons/gdUnit4/runtest.cmd` (Windows natif, exige env `GODOT_BIN`), exit 0/100/101, rapports HTML+JUnit XML.

## Vérité terrain de la version installée

`godot --headless --doctool c:/tmp/godot-doc` → `doc/classes/*.xml` + `modules/*/doc_classes/*.xml` (~1076 classes en 4.7). C'est la source d'autorité pour toute signature/propriété douteuse — supérieure au web et à la mémoire. Exemples de vérifications utiles : `PhysicalBone3D.xml` (pas de freeze), `MultiplayerSynchronizer.xml` (`public_visibility`, `replication_interval`, `delta_interval`).

## Divers

- Sortie stdout Godot : contient des séquences ANSI ; filtrer si parsing.
- Après échec d'import mystérieux : supprimer `.godot/` et réimporter 2x.
- Un projet dont `config/features` déclare une version supérieure au binaire refuse de s'ouvrir : garder features et binaire alignés.
- **Un `.blend` présent dans le projet CASSE l'import headless de TOUT le lot restant** (« Blender path is invalid », l'import avorte, les autres assets n'obtiennent jamais leur `.import`). Ne jamais laisser de `.blend` dans le projet sans Blender configuré — les sortir du dossier projet (mesuré : leur retrait a débloqué l'import de tous les .glb/.jpg).
- `push_error` dans un script `--script` peut faire retourner un exit non-zéro même après `quit(0)` : dans un harnais de test, réserver `push_error` aux vrais échecs (la « contre-épreuve » d'un test se fait en cassant temporairement le code testé, PAS en laissant une assertion fausse permanente dans le test).
- **Tests flaky vs logique de stabilisation** : ne jamais tester « l'état est encore X après N frames » quand l'entité peut légitimement transitionner (ragdoll → KO/relevé) ; tester l'invariant physique direct (déplacement mesuré) + un ensemble d'états post-transition admis.
- Échelle des assets : les kits Kenney sont ~55 % de l'échelle humaine (desk ~0,38 m de haut) — mesurer l'AABB réelle du .glb chargé et appliquer un facteur uniforme, ne jamais supposer 1 unité = 1 m.
