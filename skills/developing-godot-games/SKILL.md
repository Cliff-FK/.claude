---
name: developing-godot-games
description: Développe des jeux Godot 4.x via pipeline CLI/headless piloté par agents sous Windows (alias godot-gamedev). Couvre l'écriture directe de .tscn/.tres/.gd en texte, la validation headless (--import, --check-only, tests SceneTree/GUT), l'export Steam, les ragdolls (PhysicalBoneSimulator3D, Jolt), le multijoueur high-level API (MultiplayerSynchronizer, patterns ragdoll réseau, GodotSteam) et la génération procédurale déterministe. Use when : créer/modifier une scène ou un script Godot, valider un projet Godot en CI/headless, godot --headless, ragdoll, netcode/multiplayer Godot, GodotSteam/Steam export, renommer des fichiers .gd, bugs d'import ou .uid, prototype gamedev Godot. Ne s'applique pas aux autres moteurs (Unity/Unreal) ni au web.
---

# developing-godot-games — Godot 4.x piloté par agents en CLI/headless

Sonnet+ sait déjà écrire un `.tscn`/`.gd` correct et déplacer un `.gd` avec son `.uid`. Ce skill encode ce qui a été MESURÉ comme manquant : la boucle de validation CI qui ne ment pas, les pièges d'export, l'API ragdoll exacte, et les patterns multijoueur physique cohérents. Faits volatils vérifiés sur Godot 4.7-stable (2026-07) : re-vérifier via `--doctool` si version différente.

## Localisation du binaire (jamais en dur)

Chercher dans cet ordre : `Get-Command godot` → `tools/godot/*console.exe` à la racine du projet → demander. Sous Windows, préférer le binaire `*_console.exe` (stdout visible) au `*.exe`.

## Boucle de validation obligatoire (après CHAQUE lot de modifications)

1. `godot --headless --path <projet> --import` — OBLIGATOIRE après tout ajout/déplacement d'asset ou de script, AVANT tout export/test. Au premier setup d'un projet, lancer l'import DEUX fois (erreurs "Unrecognized UID" au premier passage, issues #115205/#101962).
2. Syntaxe d'un script isolé : `godot --headless --check-only --script res://x.gd`.
3. Exécution réelle : `godot --headless --path <projet> res://scenes/x.tscn --quit-after 2` (piège : `--quit` seul peut échouer là où `--quit-after 2` réussit, issue #77508).
4. **Ne JAMAIS conclure au succès sur le seul exit code** (exit 1 trompeur documenté en CI, issue #83449 ; exit 0 possible avec erreurs de parse imprimées). Vérifier un signal sémantique direct : la ligne de log attendue dans stdout, l'artefact produit, ou un test `SceneTree` qui `quit(0/1)` explicitement.
5. Tests : script `extends SceneTree` + `quit(code)` (léger, zéro dépendance) ou GUT (`godot --headless -s res://addons/gut/gut_cmdln.gd -gdir=res://tests -gexit`). Toujours inclure une contre-épreuve (le test doit pouvoir échouer).

## 5 pièges qui coûtent cher

1. **`export_presets.cfg` SE COMMIT** (l'export headless en CI le requiert ; ne pas le gitignorer — seuls les secrets de signing en sortent via `export_credentials.cfg`, lui ignoré). `.godot/` ne se commit jamais ; les `.import` et `.uid` se committent toujours.
2. **`PhysicalBone3D` n'est PAS un `RigidBody3D`** : pas de propriété `freeze`. Il a `apply_impulse(impulse, position)` (position RELATIVE à l'origine du bone), `apply_central_impulse`, `linear_velocity`, `mass`, `joint_type`. On (dés)active la simulation via `PhysicalBoneSimulator3D.physical_bones_start_simulation(bones: StringName[] = [])` / `physical_bones_stop_simulation()` — jamais bone par bone.
3. Chemin de sortie d'un `--export-release` relatif à `project.godot`, PAS au cwd. Templates d'export requis dans `%APPDATA%/Godot/export_templates/<version>/` (télécharger le `.tpz` de la même release).
4. Renommer/déplacer un `.gd` ou `.gdshader` = déplacer AUSSI son sidecar `.uid` + mettre à jour les `path=` des `ext_resource` dans les `.tscn`. Puis ré-importer et ré-exécuter.
5. Les commentaires `;` écrits dans un `.tscn` disparaissent si l'éditeur resauvegarde — ne jamais y stocker d'information.

## Physique du projet

Jolt : `physics/3d/physics_engine` dans `[physics]` de project.godot — `"DEFAULT"` = Jolt sur les nouveaux projets 4.6+, mais le fixer explicitement à `"Jolt Physics"` pour éviter toute ambiguïté (vérifier au premier run : une valeur invalide log un warning ; re-vérifier le nom exact via ProjectSettings si autre version).

## Références (lire à la demande)

- `reference/headless-ci.md` — commandes complètes, export Steam, matrice versionner/ignorer, tests, doctool vérité terrain.
- `reference/ragdoll-physics.md` — pattern anim→ragdoll→anim exact, impulsions spectaculaires, vitres pré-fracturées, perf multi-ragdolls.
- `reference/multiplayer.md` — high-level API, LES DEUX patterns ragdoll réseau (cosmétique local vs root-sync) et quand choisir, GodotSteam sans GUI, procgen déterministe par seed, late join, anti-triche minimal.

## Anti-patterns

Répliquer les bones d'un ragdoll via MultiplayerSynchronizer • `freeze` sur PhysicalBone3D • conclure "ça marche" sans exécution réelle + signal direct • gitignorer export_presets.cfg • RNG global (`randi()`) dans de la génération répliquée • éditer un `.tscn` par regex aveugle (relire la section node visée d'abord).
