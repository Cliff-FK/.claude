# guard-git-destructive.ps1 — Barrière PreToolUse contre les commandes git DESTRUCTIVES
# pour le travail non commité. Force Claude à demander l'autorisation explicite de l'user
# avant tout revert (leçon : un `git checkout --` a effacé du travail non commité sans accord).
#
# Bloque (exit 2 → le tool est refusé, le message stderr revient à Claude) :
#   - git checkout -- <path> / git checkout . / git checkout -f / git checkout <path>  (restaure, écrase les modifs)
#   - git restore <path>                                                                (idem, syntaxe moderne)
#   - git reset --hard                                                                  (jette working tree + index)
#   - git clean -f / -d / -x / -fd ...                                                  (supprime les fichiers non suivis)
#   - git stash drop / git stash clear                                                  (perte de stash)
#   - git checkout/switch -f vers une autre branche                                     (perte de modifs non commitées)
#
# Laisse passer (non destructif) :
#   status, diff, log, add, commit, fetch, pull, push, branch, checkout -b (création),
#   switch <branche> sans -f, stash (push), stash list/show/pop/apply.
#
# Lit le payload JSON sur stdin (tool_input.command). Silencieux et robuste : en cas de
# doute de parsing il NE bloque PAS (évite les faux positifs qui figeraient le travail légitime),
# mais matche largement les motifs destructifs connus.

$ErrorActionPreference = 'SilentlyContinue'

try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }

    $payload = $raw | ConvertFrom-Json
    $cmd = [string]$payload.tool_input.command
    if (-not $cmd) { exit 0 }

    # Retire les portions CITÉES (messages de commit -m "...", heredocs) AVANT analyse :
    # sinon un message qui mentionne "reset --hard" serait bloqué à tort (faux positif).
    $stripped = $cmd
    $stripped = $stripped -replace "(?s)<<'?EOF'?.*?EOF", ' '   # heredocs (<<EOF ... EOF)
    $stripped = $stripped -replace '"[^"]*"', ' '               # chaînes double-quote
    $stripped = $stripped -replace "'[^']*'", ' '               # chaînes simple-quote

    # Normalise : espaces multiples → simple, pour des regex stables
    $c = $stripped -replace '\s+', ' '

    # Un même appel peut chaîner plusieurs commandes (&&, ;, |). On teste chaque segment.
    $segments = $c -split '(?:&&|\|\||;|\|)'

    $blockedReason = $null

    foreach ($seg in $segments) {
        $s = $seg.Trim()
        if ($s -notmatch '(?i)\bgit\b') { continue }

        # --- git reset --hard ---
        if ($s -match '(?i)\bgit\b.*\breset\b.*--hard') {
            $blockedReason = 'git reset --hard (jette le working tree et l''index)'
            break
        }
        # --- git clean -f/-d/-x (toute forme : -f, -fd, -xdf, --force) ---
        if ($s -match '(?i)\bgit\b.*\bclean\b.*(?:-[a-z]*f|--force)') {
            $blockedReason = 'git clean -f/-d/-x (supprime des fichiers non suivis)'
            break
        }
        # --- git stash drop / clear ---
        if ($s -match '(?i)\bgit\b.*\bstash\b.*\b(drop|clear)\b') {
            $blockedReason = 'git stash drop/clear (perte de stash)'
            break
        }
        # --- git restore <path> (syntaxe moderne, écrase les modifs) ---
        if ($s -match '(?i)\bgit\b.*\brestore\b') {
            $blockedReason = 'git restore (écrase des modifications non commitées)'
            break
        }
        # --- git checkout DESTRUCTIF ---
        # On bloque checkout quand il restaure des fichiers ou force, mais PAS la création de branche (-b)
        # ni un simple switch de branche sans perte.
        if ($s -match '(?i)\bgit\b.*\bcheckout\b') {
            # Autoriser explicitement la création de branche : git checkout -b <name>
            if ($s -match '(?i)\bcheckout\b\s+-b\b') { continue }
            # Bloquer : checkout -- , checkout . , checkout -f , checkout <path/fichier>
            if ($s -match '(?i)\bcheckout\b\s+(--|\.|-f\b|--force\b)') {
                $blockedReason = 'git checkout -- / . / -f (restaure et écrase des modifications non commitées)'
                break
            }
            # checkout suivi d'un chemin/fichier explicite (contient un / ou un . ou une extension) → restauration de fichier
            if ($s -match '(?i)\bcheckout\b\s+[^ ]*[\\/][^ ]*' -or $s -match '(?i)\bcheckout\b\s+[^ ]+\.[a-z0-9]+\b') {
                $blockedReason = 'git checkout <fichier> (restaure et écrase une version non commitée)'
                break
            }
        }
    }

    if ($blockedReason) {
        # exit 2 = blocage PreToolUse ; le texte stderr est renvoyé à Claude comme raison.
        [Console]::Error.WriteLine("BLOQUE par guard-git-destructive : $blockedReason.")
        [Console]::Error.WriteLine("Operation git DESTRUCTIVE pour du travail non commite. Tu DOIS demander une autorisation EXPLICITE a l'utilisateur (via AskUserQuestion) AVANT de lancer un revert git. Ne contourne pas ce garde.")
        exit 2
    }
} catch {
    # En cas d'erreur de parsing : ne pas bloquer (faux positif pire qu'un blocage manqué ici).
    exit 0
}
exit 0
