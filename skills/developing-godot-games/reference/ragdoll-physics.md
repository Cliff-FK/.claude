# Ragdoll et physique spectaculaire — Godot 4.x (Jolt)

API vérifiées sur 4.7-stable via doctool (2026-07).

## Architecture d'un PNJ frappable

```
Npc (CharacterBody3D)            # déplacement/IA à l'état vivant
├── CollisionShape3D             # capsule de déplacement
├── Modele (Node3D, mesh glTF)
│   └── Skeleton3D
│       ├── PhysicalBoneSimulator3D
│       │   ├── PhysicalBone3D (Hips)      # un par os majeur : hips, spine, head,
│       │   ├── PhysicalBone3D (Spine)     # upper/lower arm x2, upper/lower leg x2
│       │   └── ...                        # ~11-15 bones suffisent, pas un par vertèbre
│       └── (mesh skinné)
├── AnimationPlayer / AnimationTree
└── NavigationAgent3D
```

- `PhysicalBoneSimulator3D` hérite de `SkeletonModifier3D` : il DOIT être enfant du `Skeleton3D` qu'il pilote.
- Chaque `PhysicalBone3D` : `bone_name` implicite via le nom du nœud, `joint_type` (PIN/CONE/HINGE/SLIDER/6DOF), `joint_offset`, `mass`, `friction`, `bounce`, `linear/angular_damp`, `can_sleep`. Créables en `.tscn` texte (pas besoin de l'assistant de l'éditeur : positionner `body_offset`/`joint_offset` d'après les transforms de repos du squelette).

## Pattern anim → ragdoll → (anim)

```gdscript
# état vivant : AnimationTree actif, simulator inactif (pose suit l'animation)
# à l'impact :
func enter_ragdoll(impulse: Vector3, hit_bone: StringName, hit_point: Vector3) -> void:
    anim_tree.active = false
    collision_shape.disabled = true            # la capsule ne doit plus bloquer les bones
    set_physics_process(false)                 # coupe déplacement/IA
    simulator.physical_bones_start_simulation()  # tous les bones ([] = tous)
    var bone := simulator.get_node_or_null(NodePath(hit_bone)) as PhysicalBone3D
    if bone:
        # position RELATIVE à l'origine du bone (pas un point monde !)
        bone.apply_impulse(impulse, hit_point - bone.global_position)
    else:
        # fallback : impulsion centrale sur les hanches
        (simulator.get_node("Hips") as PhysicalBone3D).apply_central_impulse(impulse)
```

- **PAS de `freeze`** sur PhysicalBone3D (n'existe pas — c'est un PhysicsBody3D, pas un RigidBody3D). L'activation/désactivation passe UNIQUEMENT par `physical_bones_start_simulation(bones)` / `physical_bones_stop_simulation()` sur le simulateur.
- Retour à l'animation (PNJ qui se relève) : attendre la quasi-immobilité des hanches (`linear_velocity.length() < 0.1` pendant ~0.5 s), `physical_bones_stop_simulation()`, téléporter la racine du Npc sur la position des hanches, rejouer une animation get_up. Le blend parfait est du polish, pas un bloquant.

## Impulsions « défouloir » (game-feel)

- Ordre de grandeur de départ : masse totale du ragdoll ~70 (kg), coup de pied Sparta = impulsion 400-900 sur les hanches/torse orientée `direction_horizontale + Vector3.UP * 0.3~0.5` (la composante verticale fait décoller — c'est elle qui rend le launch drôle). Claque = 80-150 sur la tête. Tester et ajuster : le nombre exact se règle au feel, pas au calcul.
- Le juice porte 80 % du fun : hit-stop (Engine.time_scale 0.05→1.0 sur ~80 ms ou pause de frame), screen shake court, SFX percussif, particules au point d'impact. Implémenter dans un autoload dédié réutilisable.
- Multi-ragdolls : mesurer AVANT le contenu de masse (gate ADR-001). Leviers si ça rame : moins de bones par PNJ, `can_sleep=true`, despawn/gel des ragdolls hors vue, remplacer le ragdoll stabilisé par une pose figée (stop_simulation).

## Vitres pré-fracturées (pattern universel indé)

1. Deux états dans la scène de la vitre : `Intact` (MeshInstance3D + StaticBody3D + Area3D de détection) et `Fragments` (8-20 shards RigidBody3D pré-découpés, masqués/désactivés au départ, ou spawnés à la volée depuis une PackedScene).
2. Rupture quand un corps traverse l'Area3D avec `linear_velocity.length() * masse > seuil` : cacher l'intact, désactiver sa collision, activer les fragments, appliquer à chacun une impulsion héritée de la vitesse de l'impacteur + bruit aléatoire.
3. Despawn agressif : timer 2-4 s puis fade/free ; jamais plus de ~2 vitres en fragments simultanés par zone (budget).
4. Le PNJ qui traverse continue sa trajectoire : la vitre ne doit opposer qu'une décélération symbolique (les fragments n'ont pas de collision contre le ragdoll, layers séparés).
5. Découpe des shards : générables en code (grille de quads irréguliers dans le plan de la vitre) — pas besoin d'outil de fracture externe pour du verre plat.

## Jolt

- `[physics] physics/3d/physics_engine="Jolt Physics"` dans project.godot (explicite plutôt que DEFAULT). Jolt respecte mieux les limites de joints et la stabilité des corps articulés que GodotPhysics — c'est le choix pour les ragdolls.
- **Jolt écrête la vitesse linéaire à ~500 u/s par défaut** (mesuré empiriquement 4.7 : impulsion 680 sur masse 1 → vitesse ~499, pas 680). Un launch « raté » silencieusement peut venir de là : donner des masses réalistes aux bones (v = I/m reste alors loin du plafond) ou relever la limite via les ProjectSettings Jolt (`physics/jolt_physics_3d/limits/...` — vérifier le nom exact au runtime).
- Un RigidBody3D créé par script a besoin d'UNE frame physique après `add_child()` avant que Jolt enregistre son espace : `apply_impulse`/lecture de `global_position` juste après l'ajout échouent — attendre `physics_frame` d'abord (vaut aussi dans les tests).
- On ne peut PAS surcharger une méthode native (ex. `apply_impulse`) sur un corps physique en GDScript (refus au chargement) : pour espionner la physique dans un test, mesurer l'effet réel (vitesse résultante), pas un stub.
- **PIÈGE AUTO-CATAPULTAGE (grave, mesuré) : les PhysicalBone3D d'un ragdoll, même simulation ÉTEINTE, restent des corps de collision sur leur couche.** Si le CharacterBody porteur (ou d'autres PNJ) masque cette couche, le personnage vivant entre en collision avec ses PROPRES os → `move_and_slide` le catapulte à des dizaines de m/s (symptôme : PNJ qui fuit à 150+ m). Fix : mettre les os sur une couche dédiée (ex. `pb.collision_layer = 1 << 4`) masquant SEULEMENT le monde (`pb.collision_mask = 1`), jamais la couche du CharacterBody vivant. Conséquence à ne pas oublier : les Area3D qui doivent détecter le ragdoll projeté (vitres cassables !) doivent alors inclure cette couche dédiée dans leur `collision_mask` — sinon plus aucune détection d'impact.
- Squelette glb importé : `bone_name` OBLIGATOIRE sur chaque PhysicalBone3D construit en code ; positionner via `skeleton.get_bone_global_rest(idx)`. Attention à la TOPOLOGIE réelle (ex. Quaternius : `Hips` et les jambes sont tous enfants de `Body` → la racine physique doit être sur `Body`, sinon les jambes se détachent car le joint auto remonte au premier ancêtre ayant un PhysicalBone3D).
- La physique N'EST PAS déterministe cross-machine même avec Jolt (l'intégration Godot casse la garantie amont) : ne jamais fonder une logique répliquée sur « la même simulation donnera le même résultat ».
