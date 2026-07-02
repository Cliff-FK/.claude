# turn-timer.ps1 — Horodate le début d'un tour (hook UserPromptSubmit).
# Écrit le timestamp de départ dans un fichier indexé par session_id, lu ensuite
# par play-sound.ps1 (mode Stop conditionnel : ne sonne que si le tour a duré > seuil).
# Non bloquant, n'échoue jamais le hook.

$ErrorActionPreference = 'SilentlyContinue'
try {
    $raw = [Console]::In.ReadToEnd()
    if ($raw) {
        $payload = $raw | ConvertFrom-Json
        $sid = $payload.session_id
    }
    if (-not $sid) { $sid = 'default' }
    $dir = Join-Path $HOME '.claude\.turn-timing'
    [System.IO.Directory]::CreateDirectory($dir) | Out-Null
    $file = Join-Path $dir ("$sid.txt")
    # Ticks .NET (100 ns) — précis et comparables sans dépendre du fuseau
    [System.IO.File]::WriteAllText($file, [string]([System.DateTime]::UtcNow.Ticks))
} catch { }
exit 0
