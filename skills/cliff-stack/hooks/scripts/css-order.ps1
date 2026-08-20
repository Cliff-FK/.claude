# css-order.ps1 - hook PostToolUse (Write|Edit|MultiEdit) global.
# Relaie le payload a css-order.mjs, qui verifie l'ordre canonique des declarations
# CSS/SCSS (« sablier inverse ») sur les seuls blocs touches par l'edition.
# Skip silencieux si : node absent, moteur absent, ou fichier hors perimetre.
# Findings -> stderr + exit 2 (remontes a Claude comme feedback). Sinon exit 0.
# Ne reecrit rien : mode --check. Passer le moteur en --write pour la reecriture auto.

$ErrorActionPreference = 'Stop'
try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }

    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) { exit 0 }

    $engine = Join-Path $PSScriptRoot 'css-order.mjs'
    if (-not (Test-Path -LiteralPath $engine)) { exit 0 }

    # stderr de node se propage tel quel : pas de 2>&1 (il fausserait $? en PS 5.1).
    $raw | & $node.Source $engine --hook
    if ($LASTEXITCODE -eq 2) { exit 2 }
    exit 0
}
catch {
    # Un hook ne doit jamais casser le flux.
    exit 0
}
