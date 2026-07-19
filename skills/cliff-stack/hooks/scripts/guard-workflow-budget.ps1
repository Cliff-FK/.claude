# guard-workflow-budget.ps1 — Barrière PreToolUse sur le tool Workflow (plafond d'agents).
# Leçon du 19/07/2026 : un deep-research lancé par `name:` a déclenché 106 agents (~4,5M tokens)
# alors que le plafond user est ~10 — une consigne d'échelle EN PROSE dans args ne borne rien,
# le fan-out d'un workflow est câblé dans son script JS. Ce guard rend le calcul OBLIGATOIRE.
#
# Bloque (exit 2 → le tool est refusé, le message stderr revient à Claude) :
#   - Workflow lancé par `name:` seul (script jamais lu, fan-out inconnu)
#   - Workflow par `script:`/`scriptPath:` SANS déclaration `// agents-max: N` en tête
#   - Déclaration N > plafond ($MAX_AGENTS) sans marqueur `// agents-max-user-ok`
#     (marqueur à n'ajouter QU'APRÈS validation explicite de l'user via AskUserQuestion)
#
# Laisse passer :
#   - resumeFromRunId (reprise sur cache, pas de nouveau fan-out)
#   - script déclarant `// agents-max: N` avec N <= plafond
#   - N > plafond AVEC `// agents-max-user-ok` (validation user tracée dans le script persisté)
#   - scriptPath introuvable (le tool échouera proprement de lui-même)
#
# La déclaration doit être le fan-out MÉCANIQUE calculé en lisant le script :
# somme des agent() par phase (ex. recherches × fetch × claims × votes), pas un vœu.
#
# Lit le payload JSON sur stdin (tool_input). Robuste : en cas d'erreur de parsing,
# NE bloque PAS (un faux positif figerait tout usage légitime de Workflow).

$ErrorActionPreference = 'SilentlyContinue'

# Plafond user (cf. mémoire workflow-max-10-agents ; ~10 le 27/06/2026, monté à 12 le 19/07/2026)
$MAX_AGENTS = 12

try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }

    $payload = $raw | ConvertFrom-Json
    $ti = $payload.tool_input
    if (-not $ti) { exit 0 }

    # Reprise d'un run existant : les agents inchangés rejouent depuis le cache.
    if ($ti.resumeFromRunId) { exit 0 }

    # Récupère le texte du script : inline, ou lu depuis scriptPath.
    $scriptText = $null
    if ($ti.scriptPath) {
        if (Test-Path -LiteralPath $ti.scriptPath) {
            $scriptText = [System.IO.File]::ReadAllText($ti.scriptPath)
        } else {
            exit 0
        }
    } elseif ($ti.script) {
        $scriptText = [string]$ti.script
    }

    if (-not $scriptText) {
        if ($ti.name) {
            [Console]::Error.WriteLine("BLOQUE par guard-workflow-budget : workflow nomme '$($ti.name)' lance a l'aveugle (script jamais lu, fan-out inconnu).")
            [Console]::Error.WriteLine("Procedure obligatoire : (1) recupere le script du workflow (fichier .claude/workflows/, ou re-auteur un script equivalent d'apres les phases du skill) ; (2) CALCULE son fan-out mecanique (somme des agent() par phase, ex. recherches x fetch x claims x votes) ; (3) si > $MAX_AGENTS, EDITE le script pour reduire (moins de sources, moins de claims verifiees, moins de votes) ; (4) declare '// agents-max: N' en tete de script et relance via script:/scriptPath:. Un plafond en prose dans args ne borne RIEN. Ne contourne pas ce garde.")
            exit 2
        }
        exit 0
    }

    if ($scriptText -match '(?im)^\s*//\s*agents-max\s*:\s*(\d+)') {
        $declared = [int]$Matches[1]
        if ($declared -le $MAX_AGENTS) { exit 0 }
        if ($scriptText -match '(?im)^\s*//\s*agents-max-user-ok\b') { exit 0 }
        [Console]::Error.WriteLine("BLOQUE par guard-workflow-budget : fan-out declare agents-max: $declared > plafond user $MAX_AGENTS.")
        [Console]::Error.WriteLine("Deux issues : (a) reduis le fan-out du script sous $MAX_AGENTS (moins de sources/claims/votes, ou plusieurs runs sequentiels cibles) ; (b) si l'echelle est vraiment necessaire, demande une validation EXPLICITE a l'utilisateur via AskUserQuestion (cout estime inclus), puis ajoute '// agents-max-user-ok' sous la declaration. Ne contourne pas ce garde.")
        exit 2
    }

    [Console]::Error.WriteLine("BLOQUE par guard-workflow-budget : aucune declaration '// agents-max: N' en tete de script.")
    [Console]::Error.WriteLine("Avant de lancer un Workflow : LIS le script, CALCULE son fan-out mecanique (somme des agent() par phase, multiplicateurs inclus : items x stages x votes), puis declare '// agents-max: N' en tete de script. Plafond user : $MAX_AGENTS (au-dela, validation explicite requise + '// agents-max-user-ok'). Ne contourne pas ce garde.")
    exit 2
} catch {
    # Erreur de parsing : ne pas bloquer (faux positif pire qu'un blocage manque ici).
    exit 0
}
exit 0
