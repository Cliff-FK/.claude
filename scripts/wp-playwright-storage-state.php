<?php
/**
 * Rend wp-admin accessible aux sessions Playwright lancées en `--isolated` (profil en mémoire :
 * aucun cookie n'est persisté, donc chaque session démarrerait déconnectée). Écrit les cookies
 * d'authentification WordPress du site courant dans le storage-state partagé, que le MCP charge
 * au démarrage.
 *
 * Usage, depuis la racine de N'IMPORTE QUEL projet WordPress :
 *   wp eval-file <chemin>/wp-playwright-storage-state.php [--force]
 *
 * Universel et idempotent, par conception :
 *   - tout est découvert au runtime (URL, domaine, chemins de cookies, COOKIEHASH viennent du
 *     site lui-même) — rien n'est propre à un projet ;
 *   - le compte de service `claude_dev` est créé s'il manque, donc arriver sur un projet neuf
 *     suffit à tout établir en un passage ;
 *   - le fichier est MERGÉ sur le triplet (name, domain, path) : les projets locaux coexistent,
 *     chacun ayant ses propres COOKIEHASH et chemins ;
 *   - relançable à volonté : sort en no-op tant que les cookies du site sont encore valides,
 *     sauf --force.
 *
 * Aucun mot de passe n'est requis, lu ni stocké : les cookies sont produits par
 * wp_set_auth_cookie() elle-même, dont on capture les valeurs via ses actions natives
 * `set_auth_cookie` / `set_logged_in_cookie`. En SAPI CLI les setcookie() du core sont des no-op,
 * seules les actions se déclenchent — on obtient donc exactement ce que WordPress aurait envoyé.
 *
 * Sortie : $PW_STORAGE_STATE, sinon <home>/.claude/playwright-storage-state.json
 * Le fichier porte des cookies de session valides : c'est un secret (ni plus ni moins que le
 * profil Chrome persistant qu'il remplace). Créé en 0600, à garder hors de tout dépôt.
 */

// Compte de service par défaut, valable pour tout projet. Un local.json peut l'outrepasser.
const CLAUDE_SERVICE_LOGIN = 'claude_dev';

$force = in_array('--force', (array) ($args ?? []), true);

// ==========================================
//   Cible : le compte de service
// ==========================================

// local.json, s'il existe, fait foi (convention maison) ; sinon le compte de service par défaut.
$login = null;
$local = getcwd() . '/.claude/local.json';
if (is_readable($local)) {
    $cfg   = json_decode(file_get_contents($local), true);
    $login = $cfg['wp_admin_user'] ?? null;
}
$login = $login ?: CLAUDE_SERVICE_LOGIN;

$user = get_user_by('login', $login);

if (! $user) {
    // On ne crée QUE le compte de service, jamais un compte déclaré à la main : si local.json
    // pointe vers un login humain absent, c'est une erreur de config à voir, pas à contourner.
    // Et jamais de repli sur "le premier administrateur" — cela ferait signer les sessions
    // Playwright par un compte humain (révisions et auteurs lui seraient attribués).
    if (CLAUDE_SERVICE_LOGIN !== $login) {
        WP_CLI::error(sprintf(
            "local.json déclare wp_admin_user=\"%s\", introuvable sur ce site.\n"
            . "Corriger local.json ou créer le compte — aucun repli automatique.",
            $login
        ));
    }

    $user_id = wp_insert_user([
        'user_login'   => $login,
        'user_pass'    => wp_generate_password(32, true, true), // jamais relu : l'auth passe par cookie
        'user_email'   => $login . '@localhost.local',
        'display_name' => 'Claude (dev)',
        'role'         => 'administrator',
    ]);
    if (is_wp_error($user_id)) {
        WP_CLI::error('Création de ' . $login . ' impossible : ' . $user_id->get_error_message());
    }
    $user = get_user_by('id', $user_id);
    WP_CLI::log(sprintf('Compte de service créé : %s (#%d).', $login, $user_id));
}

if (! user_can($user, 'edit_posts')) {
    WP_CLI::error(sprintf('%s existe mais n\'a pas les droits d\'édition sur ce site.', $login));
}

// ==========================================
//   Storage-state : lecture + court-circuit
// ==========================================

$outfile = getenv('PW_STORAGE_STATE')
    ?: rtrim(getenv('HOME') ?: getenv('USERPROFILE'), '/\\') . '/.claude/playwright-storage-state.json';
$domain = parse_url(home_url(), PHP_URL_HOST);

$state = ['cookies' => [], 'origins' => []];
if (is_readable($outfile)) {
    $existing = json_decode(file_get_contents($outfile), true);
    if (is_array($existing) && isset($existing['cookies'])) {
        $state = ['cookies' => $existing['cookies'], 'origins' => $existing['origins'] ?? []];
    }
}

// Idempotence : ce site a-t-il déjà un cookie d'admin valide ? On garde une marge d'un jour pour
// ne pas laisser une session mourir en plein test.
$still_valid = false;
foreach ($state['cookies'] as $c) {
    if ($c['domain'] === $domain
        && $c['path']  === ADMIN_COOKIE_PATH
        && ($c['expires'] ?? 0) > time() + DAY_IN_SECONDS) {
        $still_valid = true;
        break;
    }
}
if ($still_valid && ! $force) {
    WP_CLI::success(sprintf('Cookies déjà valides pour %s (%s) — rien à faire. --force pour régénérer.', $domain, $login));
    return;
}

// ==========================================
//   Capture des cookies via le core
// ==========================================

$captured = [];

// Les paths viennent des constantes posées par WP (cf. pluggable.php:1193-1197) : rien n'est
// deviné, on reflète les setcookie() que le core aurait émis.
add_action('set_auth_cookie', function ($cookie, $expire, $expiration, $user_id, $scheme) use (&$captured) {
    $name = ('secure_auth' === $scheme) ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
    $captured[] = [$name, $cookie, ADMIN_COOKIE_PATH,   $expire, is_ssl()];
    $captured[] = [$name, $cookie, PLUGINS_COOKIE_PATH, $expire, is_ssl()];
}, 10, 5);

add_action('set_logged_in_cookie', function ($cookie, $expire, $expiration, $user_id) use (&$captured) {
    $secure = is_ssl() && 'https' === parse_url(get_option('home'), PHP_URL_SCHEME);
    $captured[] = [LOGGED_IN_COOKIE, $cookie, COOKIEPATH, $expire, $secure];
    if (COOKIEPATH !== SITECOOKIEPATH) {
        $captured[] = [LOGGED_IN_COOKIE, $cookie, SITECOOKIEPATH, $expire, $secure];
    }
}, 10, 4);

// `true` = "se souvenir de moi" → 14 jours au lieu de 2, pour espacer les régénérations.
wp_set_auth_cookie($user->ID, true);

if (! $captured) {
    WP_CLI::error('Aucun cookie capturé : wp_set_auth_cookie() a-t-elle été court-circuitée par un plugin ?');
}

// ==========================================
//   Merge et écriture
// ==========================================

$key   = fn($c) => $c['name'] . '|' . $c['domain'] . '|' . $c['path'];
$index = [];
foreach ($state['cookies'] as $c) { $index[$key($c)] = $c; }

foreach ($captured as [$name, $value, $path, $expire, $secure]) {
    $cookie = [
        'name'     => $name,
        'value'    => $value,
        'domain'   => $domain,
        'path'     => $path,
        'expires'  => (int) $expire,
        'httpOnly' => true,
        'secure'   => (bool) $secure,
        'sameSite' => 'Lax',
    ];
    $index[$key($cookie)] = $cookie;   // remplace la version périmée du même cookie
}

$state['cookies'] = array_values($index);

if (! is_dir(dirname($outfile))) { mkdir(dirname($outfile), 0700, true); }
file_put_contents($outfile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
@chmod($outfile, 0600);

WP_CLI::success(sprintf(
    "storage-state à jour : %s\n  site      : %s (%s)\n  compte    : %s (#%d)\n  cookies   : %d pour ce site, %d au total (tous projets)\n  expire le : %s",
    $outfile, home_url(), $domain, $user->user_login, $user->ID,
    count($captured), count($state['cookies']),
    date('Y-m-d H:i', (int) $captured[0][3])
));
