# log-critic.ps1 — Journalise la fin des sous-agents critiques (hook SubagentStop).
# Une ligne JSONL par critique terminé dans ~\.claude\.critic-log\critics.jsonl
# (dossier hors versionnement : .gitignore deny-by-default). Sert à mesurer ce que
# les critiques trouvent réellement. Critique = agent_type finissant par
# « independent-critic » (nom nu ou préfixé par un plugin, ex. « cliff-stack:independent-critic »).
# Champs d'entrée utilisés : session_id, cwd, agent_type, last_assistant_message.
# Non bloquant, n'échoue jamais le hook (toujours exit 0).

$ErrorActionPreference = 'SilentlyContinue'
try {
    # Le harness envoie de l'UTF-8 ; sans ceci PowerShell 5.1 lit en page OEM et mutile les accents
    [Console]::InputEncoding = New-Object System.Text.UTF8Encoding($false)
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $type = [string]$payload.agent_type
    if ($type -notmatch 'independent-critic$') { exit 0 }

    $msg = [string]$payload.last_assistant_message
    if ($msg.Length -gt 400) { $msg = $msg.Substring(0, 400) }

    $entry = [ordered]@{
        ts         = [System.DateTime]::Now.ToString('o')
        session_id = [string]$payload.session_id
        cwd        = [string]$payload.cwd
        agent_type = $type
        excerpt    = $msg
    }
    $line = ($entry | ConvertTo-Json -Compress) + "`n"

    $dir = Join-Path $HOME '.claude\.critic-log'
    [System.IO.Directory]::CreateDirectory($dir) | Out-Null
    $file = Join-Path $dir 'critics.jsonl'
    # UTF-8 sans BOM : une ligne JSONL lisible par tout parseur
    [System.IO.File]::AppendAllText($file, $line, (New-Object System.Text.UTF8Encoding($false)))
} catch { }
exit 0
