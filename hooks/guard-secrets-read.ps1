# guard-secrets-read.ps1 — Anti-exfiltration de secrets (GLOBAL)
#
# Comme Read(*) et Bash(*) sont ouverts, ce hook empêche la LECTURE via shell
# (cat, type, Get-Content, head, tail…) de fichiers sensibles, où qu'ils soient.
# La lecture de code reste libre ; seuls les fichiers de secrets sont bloqués.
#
# Couvre le shell uniquement. Pour l'outil Read natif, voir la deny-list dans
# settings.json (Read(**/.env), etc.).
#
# exit 2 = block, exit 0 = allow.

$ErrorActionPreference = 'Stop'
$raw = [Console]::In.ReadToEnd()
if (-not $raw) { exit 0 }
try { $payload = $raw | ConvertFrom-Json } catch { exit 0 }

$tool = $payload.tool_name
if ($tool -ne 'Bash' -and $tool -ne 'PowerShell') { exit 0 }
$cmd = $payload.tool_input.command
if (-not $cmd) { exit 0 }

# Deux familles de commandes de lecture :
# - lecteurs PURS : la cible est le fichier (cat .env => lit le secret).
# - CHERCHEURS : le 1er arg est un PATTERN, pas un fichier (grep "tmp_pass" code.sh
#   cherche le MOT, ne lit pas un secret). On ne bloque un chercheur que si un motif
#   secret apparaît comme CIBLE FICHIER (avec séparateur de chemin, ou en dernier arg).
$pureReaders = '(?i)\b(cat|type|more|less|head|tail|Get-Content|gc|nl|tac|xxd|od|strings)\b'
$searchers   = '(?i)\b(grep|Select-String|sls|rg|findstr)\b'

if ($cmd -notmatch $pureReaders -and $cmd -notmatch $searchers) { exit 0 }

# Motifs de fichiers sensibles
$secretPatterns = @(
    '(?i)(^|[\\/\s"''])\.env($|[\.\s"''])',          # .env, .env.local, .env.production
    '(?i)\.credentials\.json',
    '(?i)id_rsa\b', '(?i)id_ed25519\b', '(?i)id_ecdsa\b', '(?i)id_dsa\b',
    '(?i)[\\/]\.ssh[\\/]',
    '(?i)\.pem($|["''\s])', '(?i)\.pfx($|["''\s])', '(?i)\.p12($|["''\s])',
    '(?i)\.aws[\\/]credentials',
    '(?i)\.npmrc\b', '(?i)\.pypirc\b',
    '(?i)\.git-credentials\b',
    '(?i)\.tmp_pass\b'
)

$hasSecretToken = $false
foreach ($pat in $secretPatterns) { if ($cmd -match $pat) { $hasSecretToken = $true; break } }

if ($hasSecretToken) {
    $block = $false
    if ($cmd -match $pureReaders) {
        # lecteur pur : la cible EST un fichier → un motif secret = lecture de secret
        $block = $true
    } elseif ($cmd -match $searchers) {
        # chercheur : bloquer SEULEMENT si le secret est une cible fichier
        # (précédé d'un séparateur de chemin, ou fichier secret en dernier argument)
        if ($cmd -match '(?i)[\\/][^\s"'']*(\.env|\.pem|\.pfx|\.p12|\.tmp_pass|id_rsa|id_ed25519|credentials\.json|\.ssh[\\/]|\.git-credentials|\.npmrc|\.pypirc)' -or
            $cmd -match '(?i)\s(\.?env|[^\s]*\.pem|[^\s]*\.tmp_pass|id_rsa|[^\s]*credentials\.json)\s*$') {
            $block = $true
        }
    }
    if ($block) {
        $msg = @{
            decision = 'block'
            reason   = "BLOQUE (lecture d'un fichier de secrets via shell). Si c'est legitime, demande a l'utilisateur de confirmer explicitement."
        } | ConvertTo-Json -Compress
        [Console]::Error.WriteLine($msg)
        exit 2
    }
}

exit 0
