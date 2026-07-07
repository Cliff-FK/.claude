<#
.SYNOPSIS
  Provisionne les serveurs MCP globaux (scope user) de Claude Code.

.DESCRIPTION
  Installe/repare les serveurs MCP au scope "user" (dispo sur tous les projets),
  dans ~/.claude.json. Auto-adaptatif :
    - si le CLI 'claude' est disponible -> utilise la commande officielle `claude mcp add` ;
    - sinon -> edite directement ~/.claude.json (avec backup horodate).
  Idempotent : relançable a volonte, reinjecte la meme definition.

  Source de verite unique = le bloc $servers ci-dessous (modifier ICI uniquement).

  A lancer une fois par PC (setup nouveau poste), ou pour reparer apres une
  suppression/reinitialisation de ~/.claude.json.

  IMPORTANT (voie edition directe) : ferme entierement Claude Code AVANT de lancer,
  sinon l'instance en cours reecrira le fichier et ecrasera l'ajout.

.EXAMPLE
  pwsh ~/.claude/scripts/setup-mcp.ps1
#>

$ErrorActionPreference = 'Stop'

# --- Source de verite unique des serveurs MCP ---
$servers = [ordered]@{
    playwright = [ordered]@{ type = 'stdio'; command = 'npx'; args = @('-y', '@playwright/mcp@latest', '--headless') }
    context7   = [ordered]@{ type = 'stdio'; command = 'npx'; args = @('-y', '@upstash/context7-mcp@latest') }
}

$claude = Get-Command claude -ErrorAction SilentlyContinue

if ($claude) {
    # --- Voie propre : CLI officiel, scope user ---
    foreach ($name in $servers.Keys) {
        claude mcp remove --scope user $name 2>$null | Out-Null
        $cmd = @($servers[$name].command) + $servers[$name].args
        claude mcp add --scope user $name -- @($cmd)
        if ($LASTEXITCODE -ne 0) { Write-Error "Echec de l'ajout du serveur MCP '$name'."; exit 1 }
        Write-Host "MCP '$name' configure via CLI (scope user)."
    }
    Write-Host ""
    Write-Host "Termine. Verifie avec : claude mcp list"
    exit 0
}

# --- Voie de repli : edition directe de ~/.claude.json ---
Write-Warning "CLI 'claude' absent du PATH : passage en edition directe de ~/.claude.json."
Write-Warning "Assure-toi que Claude Code est ENTIEREMENT ferme, sinon l'ecriture sera ecrasee."

$path = Join-Path $HOME '.claude.json'
if (-not (Test-Path $path)) { '{}' | Set-Content -Path $path -Encoding utf8 }

# Backup horodate avant toute modification.
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$path.bak-$stamp"
Copy-Item -Path $path -Destination $backup -Force

$json = Get-Content -Path $path -Raw | ConvertFrom-Json

# Construire la cle racine mcpServers a partir de la source de verite.
$mcp = [ordered]@{}
foreach ($name in $servers.Keys) { $mcp[$name] = [pscustomobject]$servers[$name] }
$json | Add-Member -NotePropertyName 'mcpServers' -NotePropertyValue ([pscustomobject]$mcp) -Force

# Ecriture UTF-8 sans BOM (evite tout souci de parsing).
$out = $json | ConvertTo-Json -Depth 100
[System.IO.File]::WriteAllText($path, $out, (New-Object System.Text.UTF8Encoding($false)))

Write-Host "mcpServers injecte dans $path"
Write-Host "Backup : $backup"
Write-Host "Relance Claude Code pour charger les serveurs, puis verifie via /mcp."
