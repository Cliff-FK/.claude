# guard-root-hygiene.ps1 — Hygiène de la racine du projet (GLOBAL, multiprojets)
#
# Rôle : empêcher la pollution de la racine du projet par des artefacts de
# session (captures d'écran, .md de travail, fichiers de sortie, package.json
# accidentel). Règle unique et vérifiable :
#   AUCUN NOUVEAU FICHIER À LA RACINE DU PROJET, quel qu'il soit.
# Exceptions : CLAUDE.md + noms listés dans <projet>\.claude\root-allow.txt
# (1 nom par ligne, # = commentaire). Éditer un fichier racine EXISTANT reste
# libre (wp-config.php, .htaccess…) ; écrire dans un sous-dossier reste libre.
#
# Couvre 3 canaux :
#   1. Write/Edit/MultiEdit/NotebookEdit → file_path à la racine
#   2. Captures Playwright MCP (take_screenshot / pdf_save) → filename OBLIGATOIRE
#      et NU : un chemin relatif se résout depuis le cwd du serveur, soit la
#      racine du projet ; un nom nu part dans son --output-dir, hors dépôt
#   3. Bash/PowerShell → cibles de dépôt (redirections, tee/touch, Set-Content…,
#      cp/mv) résolvant à la racine + npm/pnpm/yarn install créant un package.json
#
# S'auto-adapte via $env:CLAUDE_PROJECT_DIR — rien en dur par projet.
# Behavior : exit 2 = block (stderr montrée à Claude). exit 0 = allow.

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}

$raw = [Console]::In.ReadToEnd()
if (-not $raw) { exit 0 }
try { $payload = $raw | ConvertFrom-Json } catch { exit 0 }

$tool = $payload.tool_name
$projectRoot = $env:CLAUDE_PROJECT_DIR
if (-not $projectRoot) { exit 0 }
$projectRootNorm = ($projectRoot -replace '/','\').TrimEnd('\')
$projectRootLower = $projectRootNorm.ToLower()

function Reject([string]$reason) {
    $msg = @{ decision = 'block'; reason = $reason } | ConvertTo-Json -Compress
    [Console]::Error.WriteLine($msg)
    exit 2
}

# Whitelist des NOUVEAUX fichiers autorisés à la racine
$rootAllow = @('claude.md')
$allowFile = Join-Path $projectRootNorm '.claude\root-allow.txt'
if (Test-Path $allowFile) {
    foreach ($line in (Get-Content $allowFile -ErrorAction SilentlyContinue)) {
        $line = "$line".Trim()
        if ($line -and -not $line.StartsWith('#')) { $rootAllow += $line.ToLower() }
    }
}

# Un chemin (absolu ou relatif au projet) désigne-t-il un fichier DIRECTEMENT à
# la racine du projet ? Retourne le chemin résolu, ou $null si hors sujet.
function Resolve-IfRootLevel([string]$path) {
    if (-not $path) { return $null }
    $p = $path.Trim('"',"'")
    if ($p -match '^/([a-z])/(.*)$') { $p = "$($matches[1]):\$($matches[2])" }
    $p = $p -replace '/','\'
    if ($p -notmatch '^[a-zA-Z]:' -and -not $p.StartsWith('\\')) {
        # relatif → relatif au cwd Claude = racine projet par défaut
        $p = Join-Path $projectRootNorm $p
    }
    # Normalisation réelle (résout \.\ et \..\) — un chemin invalide (wildcards…)
    # n'est pas jugeable : on laisse guard-write-scope faire son travail de zone.
    try { $p = [System.IO.Path]::GetFullPath($p) } catch { return $null }
    $parent = Split-Path $p -Parent
    if ($parent -and $parent.TrimEnd('\').ToLower() -eq $projectRootLower) { return $p }
    return $null
}

function Assert-RootTarget([string]$path, [string]$channel) {
    $resolved = Resolve-IfRootLevel $path
    if (-not $resolved) { return }
    if (Test-Path $resolved) { return }   # édition/écrasement d'un fichier racine existant : libre
    $base = (Split-Path $resolved -Leaf).ToLower()
    if ($rootAllow -contains $base) { return }
    Reject ("BLOQUÉ (hygiène racine, $channel) : création de '" + (Split-Path $resolved -Leaf) + "' à la racine du projet interdite. " +
        "Captures navigateur : filename nu, le serveur MCP les écrit hors dépôt ; fichiers temporaires → scratchpad de session ; docs → un sous-dossier du projet. " +
        "Si ce fichier racine est vraiment légitime (demande explicite de l'utilisateur), ajouter son nom dans .claude\root-allow.txt du projet.")
}

# ==============================================================================
# 1. Write / Edit / MultiEdit / NotebookEdit
# ==============================================================================
if ($tool -in @('Write','Edit','MultiEdit','NotebookEdit')) {
    $fp = $payload.tool_input.file_path
    if (-not $fp) { $fp = $payload.tool_input.notebook_path }
    if ($fp) { Assert-RootTarget $fp $tool }
    exit 0
}

# ==============================================================================
# 2. Captures Playwright MCP — filename NU, aucun dossier devant
#    Le serveur résout un filename RELATIF depuis son cwd, soit la racine du
#    projet : un nom préfixé d'un dossier y recrée ce dossier. Un nom nu, lui,
#    tombe dans le dossier déclaré au serveur par --output-dir, hors dépôt.
# ==============================================================================
if ($tool -match '^mcp__playwright__browser_(take_screenshot|pdf_save)$') {
    $fn = $payload.tool_input.filename
    if (-not $fn) {
        Reject "BLOQUÉ (hygiène racine) : capture Playwright sans filename. Repasse l'appel avec filename: '<nom-parlant>.png' (ou .jpeg/.pdf), sans dossier devant."
    }
    if ($fn -match '[\\/]') {
        $nu = ($fn -replace '\\','/' -split '/')[-1]
        Reject ("BLOQUÉ (hygiène racine) : filename de capture avec un chemin ('$fn') — il se résout depuis la racine du projet et l'y écrit. " +
            "Repasse l'appel avec filename: '$nu', sans dossier devant : le serveur le pose dans son --output-dir, hors dépôt.")
    }
    exit 0
}

# ==============================================================================
# 3. Bash / PowerShell — cibles de dépôt résolvant à la racine du projet
# ==============================================================================
if ($tool -ne 'Bash' -and $tool -ne 'PowerShell') { exit 0 }
$cmd = $payload.tool_input.command
if (-not $cmd) { exit 0 }

# Ce qui n'est pas du shell est retiré AVANT d'y chercher des redirections : corps des
# heredocs et code passé en ligne (python -c, php -r, node -e…). Sinon un `=>`, un `<>`
# ou une balise `<style …>` DANS le contenu écrit passe pour une redirection et bloque
# une commande légitime.
$scan = $cmd
$scan = [regex]::Replace($scan, '(?s)<<-?\s*[\x27"]?(\w+)[\x27"]?\r?\n.*?\r?\n\s*\1\b', ' ')
$scan = [regex]::Replace($scan, '(?is)\b(?:python3?|php|node|perl|ruby)\s+(?:-\w+\s+)*-(?:c|r|e)\s+(?:"(?:[^"\\]|\\.)*"|\x27(?:[^\x27\\]|\\.)*\x27)', ' ')

# npm/pnpm/yarn/bun install|init SANS package.json racine existant → en créerait un
if (-not (Test-Path (Join-Path $projectRootNorm 'package.json'))) {
    if ($scan -match '(?i)(^|[;&|]\s*)(npm|pnpm|yarn|bun)\s+(install|i|add|init)\b' -and
        $scan -notmatch '(?i)(^|[;&|]\s*)(cd|pushd|set-location|chdir)\s') {
        Reject "BLOQUÉ (hygiène racine) : ce '$($matches[2]) $($matches[3])' créerait un package.json à la racine du projet (aucun n'y existe). Lance-le dans le sous-dossier outillé (ex. cd .vite) ou dans le scratchpad."
    }
}

# Un cd/pushd dans la commande rend le cwd incertain → on ne juge alors que les
# chemins qui désignent EXPLICITEMENT la racine (absolus) ; les noms nus sont skippés.
$hasCd = $scan -match '(?i)(^|[;&|]\s*)(cd|pushd|set-location|chdir)\s'

$targets = @()
# Redirections > / >> (cible quotée ou nue), hors flux (&1, /dev/null…) et hors opérateurs
# de langage qui contiennent un chevron (`=>`, `->`, `<>`, `>=`) : les prendre pour une
# redirection bloquait des commandes légitimes.
foreach ($m in [regex]::Matches($scan, '(?<![0-9&=<>\-])>{1,2}(?!=)\s*(?:"([^"]+)"|''([^'']+)''|([^\s;&|<>]+))')) {
    $t = if ($m.Groups[1].Value) { $m.Groups[1].Value } elseif ($m.Groups[2].Value) { $m.Groups[2].Value } else { $m.Groups[3].Value }
    $targets += $t
}
# tee / touch
foreach ($m in [regex]::Matches($scan, '(?i)\b(tee|touch)\s+(?:-a\s+)?(?:"([^"]+)"|''([^'']+)''|([^\s;&|<>-][^\s;&|<>]*))')) {
    $t = if ($m.Groups[2].Value) { $m.Groups[2].Value } elseif ($m.Groups[3].Value) { $m.Groups[3].Value } else { $m.Groups[4].Value }
    $targets += $t
}
# Cmdlets PowerShell écrivains : -Path/-FilePath/-LiteralPath ou 1er argument positionnel
foreach ($m in [regex]::Matches($scan, '(?i)\b(Out-File|Set-Content|Add-Content|New-Item)\b[^;&|\r\n]*')) {
    $seg = $m.Value
    if ($seg -match '(?i)-(File|Literal)?Path\s+(?:"([^"]+)"|''([^'']+)''|([^\s;&|<>]+))') {
        $t = if ($matches[2]) { $matches[2] } elseif ($matches[3]) { $matches[3] } else { $matches[4] }
        $targets += $t
    } elseif ($seg -match '(?i)\b(Out-File|Set-Content|Add-Content|New-Item)\s+(?:"([^"]+)"|''([^'']+)''|([^\s;&|<>-][^\s;&|<>]*))') {
        $t = if ($matches[2]) { $matches[2] } elseif ($matches[3]) { $matches[3] } else { $matches[4] }
        $targets += $t
    }
}
# cp/mv & équivalents : dernière cible du segment = dépôt
foreach ($m in [regex]::Matches($scan, '(?i)\b(cp|mv|copy|move|xcopy|robocopy|Copy-Item|Move-Item)\b(?<args>[^;&|\r\n]*)')) {
    $seg = $m.Groups['args'].Value
    $segT = @()
    foreach ($pm in [regex]::Matches($seg, '(?:"([^"]+)"|''([^'']+)''|([^\s;&|<>-][^\s;&|<>]*))')) {
        $v = if ($pm.Groups[1].Value) { $pm.Groups[1].Value } elseif ($pm.Groups[2].Value) { $pm.Groups[2].Value } else { $pm.Groups[3].Value }
        $segT += $v
    }
    if ($segT.Count -ge 2) { $targets += $segT[-1] }
}

foreach ($t in $targets) {
    if (-not $t) { continue }
    if ($t -match '^(/dev/(null|stdout|stderr|tty)|\$null|nul)$') { continue }
    if ($t.StartsWith('-') -or $t.StartsWith('$')) { continue }
    $isBare = ($t -notmatch '[\\/]')
    if ($hasCd -and $isBare) { continue }   # cwd incertain, nom nu injugeable
    Assert-RootTarget $t $tool
}

exit 0
