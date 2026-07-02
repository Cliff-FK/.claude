# context7-rotate-key.ps1
# Bascule la cle API Context7 active (variable env User CONTEXT7_API_KEY)
# parmi un trousseau de cles fourni par lutilisateur.
# Le serveur context7-mcp lit nativement CONTEXT7_API_KEY (cf --help :
#   --api-key key ... or set CONTEXT7_API_KEY env var). On ne touche donc
#   JAMAIS ~/.claude.json : on fait juste tourner la variable.
# Apres bascule : RELANCER Claude Code (le serveur relit la variable au demarrage).
#
# Trousseau : %USERPROFILE%\.claude\secrets\context7-keys.txt (une cle par ligne).
# Ce dossier ne doit PAS etre commite.
#
# STRATEGIE : anonyme par defaut (gratuit, quota mensuel par IP). Les cles du
# trousseau sont un SECOURS quand lanonyme est sature. Quand lanonyme se recharge
# (reset mensuel), repasser dessus avec off. Compteurs anonyme / cle SEPARES.
#
# Actions :
#   status : montre le mode actif (anonyme ou cle) et le trousseau. [defaut]
#   off    : repasse en mode ANONYME (vide CONTEXT7_API_KEY). Le trousseau est conserve.
#   on     : repasse sur la cle [1] du trousseau (secours quand anonyme sature).
#   next   : passe a la cle suivante (rotation circulaire) si une cle est saturee.
#   set    : active la cle dindex -Index (1-based).
#   add    : ajoute la cle -Key au trousseau.
#
# Apres toute bascule : RELANCER Claude Code.
#
# Exemples :
#   .\context7-rotate-key.ps1                 (status)
#   .\context7-rotate-key.ps1 -Action off     (revenir gratuit/anonyme)
#   .\context7-rotate-key.ps1 -Action on      (secours : activer cle [1])
#   .\context7-rotate-key.ps1 -Action next    (cle suivante si saturee)
#   .\context7-rotate-key.ps1 -Action add -Key ctx7sk-xxxx

[CmdletBinding()]
param(
  [ValidateSet('next','set','status','add','off','on')]
  [string]$Action = 'status',
  [int]$Index,
  [string]$Key
)

$ErrorActionPreference = 'Stop'

$SecretsDir = Join-Path $env:USERPROFILE '.claude\secrets'
$KeysFile   = Join-Path $SecretsDir 'context7-keys.txt'

function Mask([string]$k) {
  if ([string]::IsNullOrWhiteSpace($k)) { return '(vide)' }
  $k = $k.Trim()
  if ($k.Length -le 8)  { return ('*' * $k.Length) }
  if ($k.Length -le 14) { return $k.Substring(0,4) + '...' }
  return $k.Substring(0,10) + '...' + $k.Substring($k.Length-4)
}

function Get-Keys {
  if (-not (Test-Path $KeysFile)) { return ,@() }
  $raw = @(Get-Content -Path $KeysFile)
  $out = New-Object System.Collections.Generic.List[string]
  foreach ($line in $raw) {
    $t = "$line".Trim()
    if ($t -and -not $t.StartsWith('#')) { $out.Add($t) }
  }
  # ,(...) empeche PowerShell de deballer un tableau a 0/1 element en scalaire
  # (sinon $keys[$i] parcourt les CARACTERES de la cle, pas les lignes).
  return ,($out.ToArray())
}

function Get-Active { [Environment]::GetEnvironmentVariable('CONTEXT7_API_KEY','User') }
function Set-Active([string]$k) { [Environment]::SetEnvironmentVariable('CONTEXT7_API_KEY', $k, 'User') }

if ($Action -eq 'add') {
  if (-not $Key) { throw 'Fournis -Key ctx7sk-xxxx' }
  if (-not (Test-Path $SecretsDir)) { New-Item -ItemType Directory -Path $SecretsDir -Force | Out-Null }
  if (-not (Test-Path $KeysFile))   { New-Item -ItemType File -Path $KeysFile | Out-Null }
  $existing = Get-Keys
  if ($existing -contains $Key.Trim()) {
    Write-Host 'Cle deja presente dans le trousseau.' -ForegroundColor Yellow
  } else {
    # append en UTF-8 sans BOM (evite le EF BB BF de Add-Content -Encoding utf8 en PS 5.1)
    $line = $Key.Trim() + [Environment]::NewLine
    [System.IO.File]::AppendAllText($KeysFile, $line, (New-Object System.Text.UTF8Encoding($false)))
    Write-Host ('Cle ajoutee : ' + (Mask $Key)) -ForegroundColor Green
  }
  return
}

$keys   = Get-Keys
$active = Get-Active

if ($Action -eq 'off') {
  [Environment]::SetEnvironmentVariable('CONTEXT7_API_KEY', $null, 'User')
  Write-Host 'Mode ANONYME (gratuit) : variable CONTEXT7_API_KEY videe.' -ForegroundColor Green
  Write-Host 'Le trousseau est conserve (reactivable avec -Action on).'
  Write-Host ''
  Write-Host '=== RELANCE Claude Code maintenant ===' -ForegroundColor Magenta
  return
}

if ($Action -eq 'on') {
  if (-not $keys -or $keys.Count -eq 0) {
    throw 'Trousseau vide : aucune cle de secours. Ajoute : -Action add -Key ctx7sk-xxxx'
  }
  Set-Active $keys[0]
  Write-Host ('Mode CLE (secours) : [1] ' + (Mask $keys[0]) + ' activee.') -ForegroundColor Green
  Write-Host ''
  Write-Host '=== RELANCE Claude Code maintenant ===' -ForegroundColor Magenta
  return
}

if ($Action -eq 'status') {
  Write-Host '=== Context7 - etat ===' -ForegroundColor Cyan
  if ([string]::IsNullOrWhiteSpace($active)) {
    Write-Host 'Mode actif : ANONYME (gratuit, quota mensuel par IP)' -ForegroundColor Green
  } else {
    Write-Host ('Mode actif : CLE ' + (Mask $active)) -ForegroundColor Yellow
  }
  if (-not $keys -or $keys.Count -eq 0) {
    Write-Host 'Trousseau VIDE. Ajoute : -Action add -Key ctx7sk-xxxx' -ForegroundColor Yellow
    Write-Host ('Fichier attendu : ' + $KeysFile)
    return
  }
  Write-Host ('Trousseau (' + $KeysFile + ') :')
  for ($i = 0; $i -lt $keys.Count; $i++) {
    $marker = ''
    if ($keys[$i] -eq $active) { $marker = '  <= ACTIVE' }
    Write-Host ('  [' + ($i + 1) + '] ' + (Mask $keys[$i]) + $marker)
  }
  return
}

if (-not $keys -or $keys.Count -eq 0) {
  throw 'Trousseau vide. Ajoute dabord tes cles : -Action add -Key ctx7sk-xxxx'
}

if ($Action -eq 'set') {
  if (-not $Index -or $Index -lt 1 -or $Index -gt $keys.Count) {
    throw ('-Index invalide. Entier entre 1 et ' + $keys.Count + '.')
  }
  $target = $keys[$Index - 1]
  Set-Active $target
  Write-Host ('Cle active : [' + $Index + '] ' + (Mask $target)) -ForegroundColor Green
}

if ($Action -eq 'next') {
  $curIdx  = [Array]::IndexOf($keys, $active)
  $nextIdx = 0
  if ($curIdx -ge 0) { $nextIdx = ($curIdx + 1) % $keys.Count }
  $target = $keys[$nextIdx]
  Set-Active $target
  Write-Host ('Cle active : [' + ($nextIdx + 1) + '/' + $keys.Count + '] ' + (Mask $target)) -ForegroundColor Green
}

Write-Host ''
Write-Host '=== RELANCE Claude Code maintenant ===' -ForegroundColor Magenta
Write-Host '    (context7-mcp ne relit CONTEXT7_API_KEY quau demarrage)' -ForegroundColor DarkGray
