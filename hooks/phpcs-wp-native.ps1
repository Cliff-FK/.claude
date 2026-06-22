# phpcs-wp-native.ps1 — hook PostToolUse (Write|Edit) global.
# Lance phpcs (ruleset HAUT SIGNAL sécurité/correctness WP) sur le fichier .php édité.
# Skip silencieux si : pas un .php, hors projet WordPress, ou phpcs/php introuvable.
# Findings → stderr + exit 2 (remontés à Claude comme feedback). Sinon exit 0.
# Non bloquant pour l'édition (PostToolUse) : informe, ne casse rien.

$ErrorActionPreference = 'Stop'
try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $data = $raw | ConvertFrom-Json

    # Chemin du fichier édité (Write/Edit/MultiEdit)
    $file = $data.tool_input.file_path
    if (-not $file) { exit 0 }
    if ($file -notmatch '\.php$') { exit 0 }
    if (-not (Test-Path -LiteralPath $file)) { exit 0 }

    # Contexte WordPress : remonter l'arborescence à la recherche de wp-load.php / wp-settings.php
    # ou d'un segment wp-content. Sinon → ce n'est pas un projet WP → skip.
    $isWp = $false
    if ($file -match '[\\/]wp-content[\\/]') { $isWp = $true }
    if (-not $isWp) {
        $dir = Split-Path -Parent $file
        for ($i = 0; $i -lt 8 -and $dir; $i++) {
            if ((Test-Path (Join-Path $dir 'wp-load.php')) -or (Test-Path (Join-Path $dir 'wp-settings.php'))) { $isWp = $true; break }
            $parent = Split-Path -Parent $dir
            if ($parent -eq $dir) { break }
            $dir = $parent
        }
    }
    if (-not $isWp) { exit 0 }

    # Découverte de phpcs (composer global) + php (PATH puis MAMP le plus récent)
    $phpcs = Join-Path $env:APPDATA 'Composer\vendor\bin\phpcs'
    if (-not (Test-Path $phpcs)) { exit 0 }

    $php = $null
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { $php = $cmd.Source }
    if (-not $php) {
        # Racine du stack local : $env:MAMP_ROOT (accepte le style MSYS /c/MAMP) sinon C:\MAMP.
        $mampRoot = if ($env:MAMP_ROOT) { $env:MAMP_ROOT -replace '^/([a-zA-Z])/', '$1:\' -replace '/', '\' } else { 'C:\MAMP' }
        $mamp = Get-ChildItem (Join-Path $mampRoot 'bin\php') -Directory -Filter 'php*' -ErrorAction SilentlyContinue |
                Where-Object { Test-Path (Join-Path $_.FullName 'php.exe') } |
                Sort-Object Name -Descending | Select-Object -First 1
        if ($mamp) { $php = Join-Path $mamp.FullName 'php.exe' }
    }
    if (-not $php) { exit 0 }

    $rules = Join-Path $HOME '.claude\phpcs-wp-native.xml'
    if (-not (Test-Path $rules)) { exit 0 }

    # Exécution : rapport full sans couleur, sévérité erreurs (les warnings restent visibles).
    $out = & $php $phpcs "--standard=$rules" '--report=full' '--no-colors' '-q' $file 2>&1
    if ($LASTEXITCODE -ne 0 -and $out) {
        [Console]::Error.WriteLine("[wp-native/phpcs] WordPress security/correctness issues found in $file :")
        [Console]::Error.WriteLine(($out -join "`n"))
        exit 2
    }
    exit 0
}
catch {
    # Un hook ne doit jamais casser le flux : en cas d'erreur interne, on ne bloque pas.
    exit 0
}