# play-sound.ps1 — Joue le son de notification Claude Code (cloche notify.wav).
#
# Modes (1er argument) :
#   always  (défaut) — joue toujours. Utilisé par le hook Notification (validation/inactivité).
#   stop            — ne joue QUE si le tour a duré plus de $thresholdSec secondes (12s).
#                     Utilisé par le hook Stop : inutile de sonner sur une réponse instantanée.
#
# La durée est mesurée via le fichier écrit par turn-timer.ps1 (hook UserPromptSubmit),
# indexé par session_id. Lit le payload JSON sur stdin pour récupérer session_id.
#
# PlaySync : garde le process vivant jusqu'à la fin du son (le hook tue sinon la lecture).
# Le non-blocage de Claude est assuré par "async": true sur le hook (settings.json).
# Silencieux et non bloquant : n'échoue jamais le hook (toujours exit 0).

$ErrorActionPreference = 'SilentlyContinue'
$thresholdSec = 12

try {
    $mode = $args[0]
    if (-not $mode) { $mode = 'always' }

    # Lire le payload (pour session_id) — disponible sur stdin
    $sid = 'default'
    $raw = [Console]::In.ReadToEnd()
    if ($raw) {
        $payload = $raw | ConvertFrom-Json
        if ($payload.session_id) { $sid = $payload.session_id }
    }

    if ($mode -eq 'stop') {
        # Ne sonner que si le tour a duré > seuil
        $timingFile = Join-Path $HOME (".claude\.turn-timing\$sid.txt")
        $play = $false
        if (Test-Path $timingFile) {
            $startTicks = [long][System.IO.File]::ReadAllText($timingFile)
            $elapsedSec = ([System.DateTime]::UtcNow.Ticks - $startTicks) / 10000000.0
            if ($elapsedSec -gt $thresholdSec) { $play = $true }
            [System.IO.File]::Delete($timingFile)   # consommé : un tour = un marqueur
        }
        # Si pas de fichier (timer non posé) : ne pas sonner (évite faux positifs).
        if (-not $play) { exit 0 }
    }

    $path = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..\sounds\notify.wav'))
    if (Test-Path $path) {
        # Lecture dans un process DÉTACHÉ : survit même si le harness tue le hook parent.
        # (Un PlaySync inline était coupé par "async": true quand le tour se terminait.)
        $psCmd = "(New-Object System.Media.SoundPlayer '$path').PlaySync()"
        Start-Process -FilePath 'powershell.exe' `
            -ArgumentList @('-NoProfile','-WindowStyle','Hidden','-Command',$psCmd) `
            -WindowStyle Hidden
    }
} catch { }
exit 0
