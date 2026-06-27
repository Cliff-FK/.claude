# log-bell-events.ps1 — Loggeur d'audit de la cloche sonore (NE JOUE AUCUN SON).
#
# But : enregistrer dans un fichier .md partagé (toutes sessions Claude Code) chaque
# event de hook susceptible — ou non — de déclencher la cloche de notification, afin
# de diagnostiquer ensuite les demandes de confirmation qui N'ONT PAS sonné.
#
# Premier argument = étiquette de l'event source :
#   notification | pretooluse | stop
#
# La cloche elle-même est jouée par play-sound.ps1 (hook Notification matcher
# permission_prompt|idle_prompt). Ce loggeur tourne EN PARALLÈLE (2e commande du hook),
# reçoit sa propre copie du payload stdin, et n'altère donc jamais le son existant.
#
# Croisement attendu pour l'analyse finale :
#   - event Notification (type permission_prompt) => la cloche a été déclenchée.
#   - PreToolUse d'un outil "sensible" SANS Notification associé dans la même fenêtre
#     temporelle => demande exécutée sans cloche (auto-autorisée par une règle allow,
#     ou type d'interaction non couvert par le matcher Notification). C'est le cas cible.
#
# Silencieux et non bloquant : n'échoue jamais (toujours exit 0).

$ErrorActionPreference = 'SilentlyContinue'

try {
    $source = $args[0]
    if (-not $source) { $source = 'unknown' }

    $raw = [Console]::In.ReadToEnd()

    # Valeurs par défaut si le payload est illisible
    $sid       = 'default'
    $hookEvent = ''
    $toolName  = ''
    $message   = ''
    $cwd       = ''

    if ($raw) {
        $payload = $raw | ConvertFrom-Json
        if ($payload.session_id)        { $sid       = $payload.session_id }
        if ($payload.hook_event_name)   { $hookEvent = $payload.hook_event_name }
        if ($payload.tool_name)         { $toolName  = $payload.tool_name }
        if ($payload.message)           { $message   = $payload.message }
        if ($payload.cwd)               { $cwd       = $payload.cwd }
    }

    # Déduire si l'event est CENSÉ déclencher la cloche.
    # La cloche sonne sur Notification de type permission_prompt / idle_prompt.
    $bellExpected = 'non'
    if ($source -eq 'notification') {
        if ($message -match '(?i)permission|idle|approve|confirm|autoris|valid') {
            $bellExpected = 'oui'
        } else {
            $bellExpected = 'oui (notification)'
        }
    }

    # Aplatir le message sur une seule ligne (markdown table-safe)
    $msgFlat = ($message -replace '\r?\n', ' ' -replace '\|', '\')
    if ($msgFlat.Length -gt 200) { $msgFlat = $msgFlat.Substring(0, 200) + '…' }

    $ts = [System.DateTime]::Now.ToString('yyyy-MM-dd HH:mm:ss')

    $logFile = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\bell-audit-log.md'))

    # Initialiser le fichier avec un en-tête + table si absent
    if (-not (Test-Path $logFile)) {
        $header = @(
            '# Audit de la cloche sonore — log à la volée',
            '',
            'Une ligne par event de hook. Colonnes :',
            '',
            '- **horodatage** : heure locale du déclenchement.',
            '- **source** : event de hook ayant écrit la ligne (`notification`, `pretooluse`, `stop`).',
            '- **event** : `hook_event_name` rapporté par le harness.',
            '- **outil** : outil concerné (pour `pretooluse`).',
            '- **cloche_attendue** : ce hook est-il censé faire sonner la cloche ?',
            '- **session** : `session_id` (8 premiers car.) pour distinguer les fenêtres.',
            '- **détail** : message brut de la notification (tronqué).',
            '',
            '| horodatage | source | event | outil | cloche_attendue | session | détail |',
            '|---|---|---|---|---|---|---|'
        ) -join "`r`n"
        [System.IO.File]::WriteAllText($logFile, $header + "`r`n", [System.Text.UTF8Encoding]::new($true))
    }

    $sidShort = if ($sid.Length -ge 8) { $sid.Substring(0, 8) } else { $sid }

    $line = "| $ts | $source | $hookEvent | $toolName | $bellExpected | $sidShort | $msgFlat |"

    # Append atomique (StreamWriter en mode append, encodage UTF-8)
    $sw = [System.IO.StreamWriter]::new($logFile, $true, [System.Text.UTF8Encoding]::new($false))
    try { $sw.WriteLine($line) } finally { $sw.Dispose() }

} catch { }
exit 0
