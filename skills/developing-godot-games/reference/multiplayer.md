# Multijoueur Godot 4.x — high-level API, ragdolls en réseau, GodotSteam, procgen déterministe

Patterns issus du consensus industrie (Gaffer On Games, docs Unity/Epic, GDC Halo « don't network ragdolls ») croisé avec l'API Godot 4.7 vérifiée. Aucun moteur n'est physiquement déterministe cross-machine : on réplique l'ÉTAT ou l'ÉVÉNEMENT, jamais « la même simulation ».

## Socle

- Hôte autoritaire (un joueur héberge, peer id 1). Joueurs = `CharacterBody3D` + `MultiplayerSynchronizer` (position, rotation, état d'anim). Spawn dynamique = `MultiplayerSpawner`.
- `MultiplayerSynchronizer` : `replication_config` (propriétés watchées), `replication_interval`/`delta_interval` (cadence ; 0 = chaque frame réseau), `public_visibility` (couper la réplication d'un nœud inactif = économie majeure). Les propriétés watchées sont envoyées en full-sync au late-joiner — un état persistant ne doit JAMAIS vivre uniquement dans un RPC one-shot.
- RPC : `@rpc("authority"|"any_peer", "call_local"|"call_remote", "reliable"|"unreliable")`. Tout RPC `any_peer` reçu par l'hôte se valide (distance plausible, cooldown, raycast) avant effet : n'importe quel pair peut l'appeler.
- **La réplication continue de `RigidBody3D` par MultiplayerSynchronizer est boguée/fragile** (flicker documenté, issues #76299/#108910) : ne jamais synchroniser un corps en simulation active ; côté récepteur le corps doit être kinématique/piloté, jamais simulé en concurrence avec les données réseau.

## Ragdolls en réseau : DEUX patterns, choisir par enjeu

**Pattern A — cosmétique local (défaut, ~90 % des cas de défouloir)**
Pour les PNJ dont la position finale n'a PAS d'enjeu gameplay dur.
- L'hôte décide de l'impact et broadcast UN événement : `@rpc("authority", "call_local", "reliable") func npc_hit(npc_id, impulse: Vector3, hit_bone: StringName, hit_point: Vector3)`.
- CHAQUE pair (hôte inclus) exécute localement `enter_ragdoll(...)` (voir ragdoll-physics.md) : simulation ragdoll complète, locale, divergente entre clients — accepté, indétectable en coop.
- Si le corps doit rester saisissable/cohérent : l'hôte re-broadcast la transform des hanches à basse fréquence (1-5 Hz, unreliable) et chaque client corrige DOUCEMENT (lerp sur ~0.5 s, jamais de snap) uniquement si l'écart dépasse ~1 m.
- Fin de ragdoll : RPC fiable de l'hôte avec transform finale (cadavre figé ou PNJ qui se relève) ; tous alignent.

**Pattern B — root-sync hôte (si la position du corps a un enjeu dur : objectif, loot, physique de porte)**
- SEUL l'hôte simule le ragdoll. Clients : `physical_bones_start_simulation()` quand même localement (le mesh doit ragdoller visuellement — NE PAS tenter de geler les bones : pas de freeze sur PhysicalBone3D), mais un contrôleur force les hanches vers l'état reçu (synchronizer dédié activé pendant le ragdoll seulement : transform + vélocités des hanches, 10-15 Hz) ; les membres suivent par contraintes locales.
- Coût et complexité réels : réserver aux rares entités qui le justifient.

**Interdits** : répliquer tous les bones (40 kB/s par ragdoll, injouable à 2-3 ragdolls) ; laisser deux simulations se battre (source du flicker) ; RPC par frame physique (la réplication de propriétés fait ça mieux).

## Vitres/destructibles en réseau

- État `is_broken: bool` watché par un MultiplayerSynchronizer (late join automatique). Décision de rupture : hôte uniquement.
- Fragments : 100 % locaux, spawnés par chaque pair depuis l'événement (point, normale, impulsion). Jamais répliqués.

## Grab / prises de catch

- L'autorité du corps saisi reste l'hôte. Le grab client = RPC de requête → l'hôte valide → crée le joint (Generic6DOFJoint3D) côté simulation d'autorité → broadcast l'état "saisi par X" ; les clients attachent visuellement (reparent/follow), sans joint physique local.
- Le lancer : RPC hôte avec l'impulsion → retour au pattern A.

## GodotSteam (sans GUI, vérifié 2026-07 — volatile, re-vérifier les versions)

1. Récupérer GodotSteam GDExtension (projet migré sur Codeberg depuis 2026-06 : codeberg.org/godotsteam/godotsteam ; releases GitHub archivées utilisables). Extraire `addons/godotsteam/` (binaires par plateforme + `godotsteam.gdextension`) dans le projet. Aucune activation d'éditeur : singleton `Steam` détecté au démarrage.
2. `steam_appid.txt` à la racine du projet contenant `480` (Spacewar) tant qu'on n'a pas son AppID. Steam doit tourner sur la machine pour les tests réels.
3. Transport multijoueur : préférer **expressobits/steam-multiplayer-peer** (Asset Library #2258, plus actif que le module officiel archivé) : `SteamMultiplayerPeer` s'utilise comme `ENetMultiplayerPeer` (lobbies + relay Steam, zéro port à ouvrir).
4. **Développer transport-agnostique** : toute la logique passe par la high-level API ; `ENetMultiplayerPeer` en local/CI (2 instances headless possibles), Steam peer en prod. Le swap = 3 lignes à la création du peer.

## Procgen déterministe par seed (multijoueur)

- L'hôte tire la seed maître, la réplique (propriété watchée → late join OK). Chaque client génère localement — seule la seed transite.
- **Un `RandomNumberGenerator` DÉDIÉ par sous-système** (layout, mobilier, thème), seedé par dérivation stable de la seed maître (`hash(seed, "furniture")`). JAMAIS `randi()`/`randf()` globaux dans du code de génération.
- Même seed ≠ même résultat si le NOMBRE d'appels RNG diffère entre clients : toute branche conditionnelle dépendant d'un état local (options graphiques, plateforme) est interdite dans le chemin de génération.
- Ordre d'itération de `Dictionary` non contractuel : trier les clés avant toute itération qui influence la génération. Préférer les grilles/indices entiers aux floats.
- Filet : checksum de l'état généré (ex. hash de la liste triée des placements) comparé hôte/clients à la fin de la génération — une divergence se diagnostique à la génération, jamais après coup.
