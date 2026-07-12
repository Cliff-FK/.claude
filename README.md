# claude-config — mon ~/.claude versionné

Configuration personnelle complète de Claude Code, portable d'un poste à l'autre : un
clone de ce repo dans `~/.claude` et le poste est prêt (skills, agents, hooks, réglages,
règles et workflows inclus).

## Logique de versionnement : deny-by-default

Le dossier `~/.claude` vivant est un mélange de config réutilisable et de données
sensibles (secrets, credentials, transcripts de sessions, mémoires par projet). Le
`.gitignore` applique donc une politique inverse de l'habitude :

1. **Tout est ignoré** (`/*` puis `/**`).
2. **Seules les zones approuvées sont whitelistées** : `CLAUDE.md`, `settings.json`,
   `rules/`, `skills/`, `agents/`, `hooks/`, `scripts/`, `workflows/`, `plugins/` (index).
3. **Patterns de secrets re-bloqués partout** (`**/*secret*`, `**/*password*`, `**/*token*`,
   clés SSH), avec une exception documentée : `guard-secrets-read.ps1` est du code de config
   dont le nom contient « secret », il est ré-inclus explicitement (sans ça, le clone d'un
   nouveau poste perdait le hook, régression déjà vécue).
4. **Zones sensibles re-exclues nommément** en défense en profondeur (`projects/`,
   `sessions/`, `playwright-output/`, `shell-snapshots/`, `.credentials.json`, etc.).

Conséquence pratique : `git status` propre = rien de sensible ne peut partir par accident ;
une nouvelle zone n'est versionnée que par ajout explicite à la whitelist.

## Architecture : un repo, un plugin in-place

Toute la valeur vit dans **`skills/cliff-stack/`**, qui est un plugin Claude Code complet
développé in-place (pas de repo séparé, pas de copie) :

```
~/.claude/
├── CLAUDE.md                  # Instructions globales (langue, sécurité, style, rigueur)
├── settings.json              # Réglages harness (permissions, modèle, hooks du plugin)
├── README.md                  # Ce fichier
├── rules/                     # Règles par sujet, auto-chargées selon le contexte
│   ├── morph-blocks.md        #   pipeline multi-zones du plugin morph-blocks
│   ├── wordpress-php.md       #   invariants WordPress natif
│   └── astro.md               #   invariants Astro
├── workflows/                 # Scripts d'orchestration multi-agents réutilisables
│   ├── generator-critic-verifier.js
│   └── research-arbitrate.js
├── scripts/
│   └── setup-mcp.ps1          # Installation des serveurs MCP sur un poste neuf
├── plugins/                   # Index des plugins installés (catalogue, pas de code)
└── skills/
    ├── developing-godot-games/  # Skill autonome hors plugin
    └── cliff-stack/             # LE plugin maison, tout se crée ICI
        ├── .claude-plugin/plugin.json
        ├── skills/              # ~25 skills : wp-native, freemius, seo-launch,
        │                        #   pricing-strategist, design-auditor, impeccable,
        │                        #   agent-builder, skill-builder, asana-triage, etc.
        ├── agents/              # 17 agents : flotte morph-blocks (6 zones +
        │                        #   orchestrateur + auditeur + regression-tester),
        │                        #   framing-critic, analystes marché, etc.
        ├── hooks/
        │   ├── hooks.json       # Câblage des hooks (source de vérité)
        │   └── scripts/         # guard-write-scope, guard-secrets-read,
        │                        #   guard-git-destructive, phpcs-wp-native,
        │                        #   play-sound, turn-timer
        ├── scripts/             # Utilitaires (context7-rotate-key, wp-user-create...)
        ├── sounds/              # Sons des notifications
        └── phpcs-wp-native.xml  # Ruleset PHPCS WordPress
```

**Convention cardinale** : tout nouveau skill, agent ou hook se crée DANS
`skills/cliff-stack/` (jamais à la racine de `~/.claude`), pour rester dans le périmètre
du plugin et donc du versionnement.

## Modèle de sécurité

Le poste tourne en liberté shell maximale (allow global sur shell, web et fichiers),
compensée par une barrière de hooks `PreToolUse` câblés dans le plugin :

| Hook | Rôle |
|---|---|
| `guard-write-scope` | Bloque toute écriture ou destruction hors zones autorisées (projet courant, `c:\tmp`, `~/.claude`, plus les racines listées dans `write-scope-extra-roots.txt` local) |
| `guard-secrets-read` | Bloque la lecture shell des fichiers de secrets (`.env`, credentials, clés) |
| `guard-git-destructive` | Intercepte les commandes git destructrices (reset --hard, push --force, clean) pour confirmation |

En complément : `permissions.deny` dans `settings.json` en filet, PHPCS WordPress
automatique après chaque écriture PHP (`PostToolUse`), rappel des règles mémoire à
chaque prompt (`UserPromptSubmit`), sons sur les notifications et fins de tour.

Deux principes de maintenance : ne jamais réintroduire de règles `allow` par projet
(inutile, génère des prompts) ; si un hook bloque à tort, corriger le **script** dans
`skills/cliff-stack/hooks/scripts/`, jamais le câblage.

## Installation sur un poste neuf

```bash
git clone https://github.com/Cliff-FK/claude-config.git "%USERPROFILE%\.claude"
```

Puis :
1. `scripts/setup-mcp.ps1` pour les serveurs MCP (playwright, context7).
2. Recréer les fichiers locaux non versionnés : `write-scope-extra-roots.txt`
   (racines d'écriture supplémentaires propres au poste) et les credentials.
3. Les dossiers runtime (`projects/` avec les mémoires par projet, `sessions/`,
   `playwright-output/`...) se recréent seuls à l'usage : ils sont volontairement
   locaux au poste et hors versionnement.

## Sauvegarde

Le repo se sauvegarde par commit et push classiques. Les pièges connus sont documentés
dans le `.gitignore` lui-même (patterns `*secret*`, zones re-exclues) : le lire avant
d'ajouter une zone à la whitelist.
