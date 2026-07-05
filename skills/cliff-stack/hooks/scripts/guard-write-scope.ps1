# guard-write-scope.ps1 — Firewall sortant Claude Code (GLOBAL, multiprojets)
#
# Rôle : seule vraie barrière de sécurité quand allow = Bash(*)/PowerShell(*).
# Empêche toute commande Bash/PowerShell d'écrire/modifier/détruire :
#   - en dehors du projet courant ($env:CLAUDE_PROJECT_DIR) + c:\tmp
#   - sur des chemins SYSTÈME Windows critiques (même via commande sans path)
#   - via des contournements (sous-shells, cd hors-projet, registre, format…)
#
# Lectures (cat, ls, head, grep, Get-Content) toujours autorisées partout
# (l'exfiltration de secrets est gérée par guard-secrets-read.ps1).
#
# MySQL : seuls les statements modificateurs ciblant la DB du wp-config.php du
# projet sont autorisés. SELECT/SHOW/DESCRIBE/EXPLAIN restent libres partout.
#
# S'auto-adapte à CHAQUE projet via $env:CLAUDE_PROJECT_DIR — aucune réécriture
# par projet nécessaire.
#
# Behavior : exit 2 = block (stderr montrée à Claude). exit 0 = allow.

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}

# Lire JSON d'entrée
$raw = [Console]::In.ReadToEnd()
if (-not $raw) { exit 0 }
try { $payload = $raw | ConvertFrom-Json } catch { exit 0 }

$tool = $payload.tool_name
if ($tool -ne 'Bash' -and $tool -ne 'PowerShell') { exit 0 }

$cmd = $payload.tool_input.command
if (-not $cmd) { exit 0 }

# --- Zones autorisées ----------------------------------------------------------
$projectRoot = $env:CLAUDE_PROJECT_DIR
$hasProject  = [bool]$projectRoot
if ($hasProject) {
    $projectRoot = ($projectRoot -replace '/','\').TrimEnd('\').ToLower()
}
$allowedRoots = @()
if ($hasProject) { $allowedRoots += $projectRoot }
$allowedRoots += 'c:\tmp'
# Dossier de config Claude (déjà dans additionalDirectories) : skills, hooks,
# memory, sorties MCP (ex. playwright-output). Écriture shell autorisée ici.
if ($env:USERPROFILE) { $allowedRoots += (Join-Path $env:USERPROFILE '.claude').ToLower() }

# Détection DB MySQL projet (lecture wp-config.php)
$projectDb = $null
if ($hasProject) {
    $wpConfig = Join-Path $env:CLAUDE_PROJECT_DIR 'wp-config.php'
    if (Test-Path $wpConfig) {
        $cfg = Get-Content $wpConfig -Raw
        if ($cfg -match "define\(\s*['""]DB_NAME['""]\s*,\s*['""]([^'""]+)['""]") {
            $projectDb = $matches[1].ToLower()
        }
    }
    # Projets NON-WordPress (Astro/Node avec DB) : opt-in EXPLICITE par projet via
    # .claude/allowed-db (1 nom de DB par ligne, commentaires # ignorés). Même garantie
    # que wp-config : seule LA DB déclarée du projet est modifiable, rien d'autre.
    if (-not $projectDb) {
        $allowedDbFile = Join-Path $env:CLAUDE_PROJECT_DIR '.claude\allowed-db'
        if (Test-Path $allowedDbFile) {
            $line = (Get-Content $allowedDbFile | Where-Object { $_ -match '\S' -and $_ -notmatch '^\s*#' } | Select-Object -First 1)
            if ($line) { $projectDb = $line.Trim().ToLower() }
        }
    }
}

# --- Helpers -------------------------------------------------------------------
function Reject([string]$reason) {
    $msg = @{
        decision = 'block'
        reason   = $reason
    } | ConvertTo-Json -Compress
    [Console]::Error.WriteLine($msg)
    exit 2
}

function Normalize-Path([string]$path) {
    $p = $path.Trim('"',"'")
    if ($p -match '^/([a-z])/(.*)$') { $p = "$($matches[1]):\$($matches[2] -replace '/','\')" }
    $p = $p -replace '/','\'
    $p = $p -replace '\\{2,}','\'
    return $p.ToLower()
}

function Is-PathInProject([string]$path) {
    if (-not $path) { return $true }
    $p = Normalize-Path $path
    # path relatif sans drive → relatif au cwd Claude = projet par défaut
    if ($p -notmatch '^[a-z]:' -and -not $p.StartsWith('\\')) { return $true }
    foreach ($root in $allowedRoots) {
        if ($p.StartsWith($root)) { return $true }
    }
    return $false
}

# ==============================================================================
# 0. INTERDITS ABSOLUS SYSTÈME — bloqués partout, même dans un projet, même
#    sans path explicite. Ce sont des opérations qui ne devraient JAMAIS tourner.
# ==============================================================================
$systemKillPatterns = @(
    # Effacement disque / partition / boot
    @{ rx = '(?i)\bformat(\.com)?\s+[a-z]:';                 why = 'format d''un volume' },
    @{ rx = '(?i)\bdiskpart\b';                              why = 'diskpart (gestion partitions)' },
    @{ rx = '(?i)\bbcdedit\b';                               why = 'bcdedit (boot)' },
    @{ rx = '(?i)\bcipher\s+/w';                             why = 'cipher /w (wipe disque)' },
    @{ rx = '(?i)\bvssadmin\s+delete';                       why = 'suppression shadow copies' },
    @{ rx = '(?i)\bwmic\b.*\bshadowcopy\b.*\bdelete\b';      why = 'suppression shadow copies (wmic)' },
    # rm -rf racine (toutes formes)
    @{ rx = '\brm\s+(-[a-z]*\s+)*-?[a-z]*r[a-z]*f?[a-z]*\s+/\s*($|[;&|])';  why = 'rm -rf /' },
    @{ rx = '\brm\s+(-[a-z]*\s+)*-?[a-z]*[rf][a-z]*\s+/[a-z]/\s*($|[;&|])'; why = 'rm -rf d''une racine disque' },
    @{ rx = '\brm\s+(-[a-z]*\s+)*-?[a-z]*[rf][a-z]*\s+~\s*($|[;&|])';       why = 'rm -rf du home' },
    # Fork bomb
    @{ rx = ':\(\)\s*\{\s*:\|:&\s*\}\s*;:';                  why = 'fork bomb' },
    # Pipe-to-shell d'un téléchargement distant (exécution de code arbitraire)
    @{ rx = '(?i)\b(curl|wget|iwr|invoke-webrequest)\b[^\n|]*\|\s*(bash|sh|powershell|pwsh|iex|invoke-expression)\b'; why = 'pipe download->shell (RCE)' },
    @{ rx = '(?i)\b(iex|invoke-expression)\b.*\b(downloadstring|invoke-webrequest|iwr)\b'; why = 'IEX download (RCE)' }
)
foreach ($k in $systemKillPatterns) {
    if ($cmd -match $k.rx) { Reject "BLOQUÉ (interdit système absolu : $($k.why)) — $($cmd.Substring(0,[Math]::Min(160,$cmd.Length)))" }
}

# Chemins système Windows protégés (écriture/suppression interdite partout)
$protectedSystemPaths = @(
    'c:\windows', 'c:\program files', 'c:\program files (x86)',
    'c:\programdata', 'c:\boot', 'c:\system volume information',
    'c:\users\default', 'c:\$recycle.bin'
)

# Registre destructeur
if ($cmd -match '(?i)\breg(\.exe)?\s+(delete|add)\s+' -or
    $cmd -match '(?i)\b(Remove-Item|Set-ItemProperty|New-ItemProperty|Remove-ItemProperty)\b.*\bHK(LM|CU|CR|U|CC):') {
    if ($cmd -match '(?i)HK(LM|CR):\\?SYSTEM' -or $cmd -match '(?i)HKEY_LOCAL_MACHINE' -or $cmd -match '(?i)reg(\.exe)?\s+delete') {
        Reject "BLOQUÉ (modification du registre Windows) — $($cmd.Substring(0,[Math]::Min(160,$cmd.Length)))"
    }
}

# git config --global / npm config set -g : altèrent l'environnement global
if ($cmd -match '(?i)\bgit\s+config\s+(--global|--system)\b' -and $cmd -notmatch '(?i)\bgit\s+config\s+(--global|--system)\s+(--get|--list|-l)\b') {
    Reject "BLOQUÉ (git config global/system en écriture — affecte tous les repos). Utilise --local."
}

# Désactivation de défenses Windows
if ($cmd -match '(?i)\b(Set-MpPreference|Disable-WindowsOptionalFeature)\b' -or
    $cmd -match '(?i)netsh\s+advfirewall\s+set.*\boff\b' -or
    $cmd -match '(?i)\bSet-ExecutionPolicy\b.*\b(Unrestricted|Bypass)\b.*\b(-Scope\s+)?(LocalMachine|CurrentUser)\b') {
    Reject "BLOQUÉ (désactivation d'une protection système) — $($cmd.Substring(0,[Math]::Min(160,$cmd.Length)))"
}

# --- 1. Détection MySQL (avant scan paths) -------------------------------------
if ($cmd -match '\b(mysql|mysqldump|mariadb)(\.exe)?\b') {
    $targetDb = $null
    if ($cmd -match '--database[=\s]+(\S+)')                 { $targetDb = $matches[1] }
    elseif ($cmd -match '\s-D\s*(\S+)')                      { $targetDb = $matches[1] }
    elseif ($cmd -match "USE\s+``?([a-zA-Z0-9_\-]+)``?")     { $targetDb = $matches[1] }
    # Forme POSITIONNELLE (la plus courante : `mysql -u root -proot NOMDB -e "..."`) :
    # le nom de DB nu juste avant -e/--execute (jamais précédé de -u/-h/-P, dont les
    # valeurs collent à l'option ou sont capturées par le lookbehind négatif).
    elseif ($cmd -match "(?<!-[a-zA-Z]|--[a-z\-]{2,20})\s([a-zA-Z0-9_$\-]+)\s+(?:-[NB]\s+)*(-e|--execute)\b") { $targetDb = $matches[1] }
    $sqlPayload = ''
    if ($cmd -match "(-e|--execute)\s+[`"']([^`"']+)[`"']") { $sqlPayload = $matches[2] }
    if ($cmd -match "(?i)\b(DROP|CREATE|INSERT|UPDATE|DELETE|TRUNCATE|ALTER|GRANT|REVOKE|RENAME)\b") {
        if ($targetDb) { $targetDb = $targetDb.ToLower().Trim('`','"',"'") }
        if (-not $projectDb) {
            Reject "MySQL write bloqué : pas de DB projet déclarée (wp-config.php DB_NAME ou .claude/allowed-db). cmd: $($cmd.Substring(0,[Math]::Min(160,$cmd.Length)))"
        }
        if ($targetDb -and $targetDb -ne $projectDb) {
            Reject "MySQL write bloqué : DB cible '$targetDb' != DB projet '$projectDb'"
        }
        # FAIL-CLOSED : un write dont la DB cible est indéterminable (ni option, ni USE,
        # ni positionnelle) peut viser n'importe quelle base (ex. table qualifiée
        # `autre_db.table`) → bloqué plutôt que présumé sain.
        if (-not $targetDb) {
            Reject "MySQL write bloqué : DB cible indéterminable (utilise `mysql <db> -e ...`, -D ou USE). cmd: $($cmd.Substring(0,[Math]::Min(160,$cmd.Length)))"
        }
    }
}

# --- 2. Détection commandes destructives + extraction paths --------------------
# Paths NON quotés (s'arrêtent au 1er espace/séparateur)
$pathRegex = '(?:[A-Za-z]:[\\/][^\s"''<>|;`&]+|/[a-z]/[^\s"''<>|;`&]+|//[a-z]/[^\s"''<>|;`&]+)'
# Paths QUOTÉS (peuvent contenir des espaces) : "C:\a b\c", '/c/a b/c'. Capture l'intérieur.
$quotedPathRegex = '["'']((?:[A-Za-z]:[\\/]|//?[a-z]/)[^"'']*)["'']'

$destructivePatterns = @(
    # Bash
    '\brm\s+', '\brmdir\s+', '\bmv\s+', '\bcp\s+', '\btee\s+',
    '\bmkdir\s+', '\btouch\s+', '\bdd\s+', '\bchmod\s+', '\bchown\s+', '\bln\s+',
    '\bshred\s+', '\btruncate\s+',
    # Redirection shell vers FICHIER (pas stdout/stderr/dev-null) — accepte path quoté
    '(?<![0-9&])>{1,2}\s+(?!/dev/null|&\d|/dev/stderr|/dev/stdout)(?=["''A-Za-z\./])',
    # PowerShell
    '\bRemove-Item\b', '\bDelete-Item\b', '\bOut-File\b', '\bSet-Content\b',
    '\bAdd-Content\b', '\bClear-Content\b', '\bNew-Item\b', '\bCopy-Item\b',
    '\bMove-Item\b', '\bRename-Item\b', '\bWrite-Host\b\s+.*\s+>'
)

$isDestructive = $false
foreach ($pat in $destructivePatterns) {
    if ($cmd -match $pat) { $isDestructive = $true; break }
}

if (-not $isDestructive) { exit 0 }

# --- Collecte unifiée des paths (quotés + non quotés) -------------------------
# IMPORTANT : on capture d'abord les chemins QUOTÉS (qui peuvent contenir des
# espaces, ex. "C:\Users\Cliff Rob\..."), puis on NEUTRALISE ces portions quotées
# avant d'appliquer la regex non quotée. Sinon $pathRegex s'arrête au 1er espace
# et extrait un fragment tronqué ("C:\Users\Cliff") qui échoue à tort la
# vérification de zone. Fix universel pour tout chemin contenant une espace.
$collectedPaths = @()
$cmdNoQuoted = $cmd
foreach ($m in [regex]::Matches($cmd, $quotedPathRegex)) {
    $collectedPaths += $m.Groups[1].Value
    $cmdNoQuoted = $cmdNoQuoted.Replace($m.Value, ' ')
}
foreach ($m in [regex]::Matches($cmdNoQuoted, $pathRegex)) { $collectedPaths += $m.Value }

# --- 2b. Protection chemins système (sur destructeurs) -------------------------
foreach ($pv in $collectedPaths) {
    $np = Normalize-Path $pv
    foreach ($sys in $protectedSystemPaths) {
        if ($np.StartsWith($sys)) {
            Reject "BLOQUÉ (écriture/suppression sur chemin système protégé : '$pv')"
        }
    }
}

# --- 2c. Anti-contournement : cd hors-projet, sous-shells dangereux -----------
# `cd <ailleurs> && <destructif>` : si on change de répertoire vers un path
# absolu hors-projet juste avant une commande destructrice, on bloque.
if ($cmd -match '(?i)\b(cd|Set-Location|chdir|pushd)\s+(?:"([^"]+)"|''([^'']+)''|([^\s"''&|;]+))') {
    # Cible quote-aware : entre guillemets doubles, simples, ou nue — un chemin
    # avec espaces type "C:\Users\Cliff Rob\..." se capture en entier.
    $cdTarget = if ($matches[2]) { $matches[2] } elseif ($matches[3]) { $matches[3] } else { $matches[4] }
    # Ne juger que les chemins ABSOLUS (C:\... ou /c/...), comme avant : un cd
    # relatif reste dans le répertoire courant déjà contrôlé.
    if ($cdTarget -match '^(?:[A-Za-z]:[\\/]|/[a-z]/)') {
        if (-not (Is-PathInProject $cdTarget)) {
            Reject "BLOQUÉ (cd vers '$cdTarget' hors projet avant une commande destructrice). Lance ce dossier comme projet Claude."
        }
    }
}
# rm récursif/forcé dont la CIBLE est une substitution non résolue (cible non vérifiable).
# On isole chaque invocation `rm` et on ne cherche la substitution que DANS SES PROPRES
# arguments (jusqu'au prochain séparateur ; && || |). Évite le faux positif d'un `$(...)`
# situé sur une autre commande de la même ligne (ex: PID=$(...) ; ... ; rm -f /chemin/explicite).
foreach ($rmMatch in [regex]::Matches($cmd, '\brm\b(?<args>[^;&|\r\n]*)')) {
    $rmArgs = $rmMatch.Groups['args'].Value
    if ($rmArgs -match '(^|\s)-[a-zA-Z]*[rf]' -and $rmArgs -match '\$\(|`[^`]+`|\$\{') {
        Reject "BLOQUÉ (rm récursif/forcé avec substitution comme cible — non vérifiable). Explicite le chemin."
    }
}

# --- 3. Vérifier chaque path absolu trouvé (quotés + non quotés) --------------
foreach ($p in $collectedPaths) {
    if ($p -match '\.(exe|bat|cmd|sh|ps1|py|pl|rb|jar|phar)$') { continue }  # exécutables (read+exec)
    if ($p -match '/dev/(null|stdout|stderr|tty)') { continue }
    if ($p -match '^[a-z]:/+(localhost|[a-z0-9.-]+\.[a-z]{2,})/') { continue }  # fragments d'URL
    if (-not (Is-PathInProject $p)) {
        Reject "BLOQUÉ (écriture/destruction hors projet : '$p'). Zones autorisées : $($allowedRoots -join ', ')"
    }
}

# --- 4. Destructeurs sans path lisible mais clairement racine -----------------
if ($cmd -match '\brm\s+-rf?\s+/\s*$' -or
    $cmd -match '\brm\s+-rf?\s+/c/\s*$' -or
    $cmd -match '\bRemove-Item\s+.*-Recurse.*[Cc]:\\?\s*$') {
    Reject "BLOQUÉ (opération destructrice sur racine) : $cmd"
}

exit 0
