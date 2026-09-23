<?php
/*
 * drop/app.php (skill wp-incident-response) : interface web de l'outil déposé, assemblé par
 * build-drop.php avec detect.php et drop/actions.php en UN fichier à poser à la racine du projet.
 *
 * Sécurité (le fichier tourne sur un site compromis) :
 * - jeton de 256 bits dont seule l'empreinte sha256 est embarquée ; saisi en POST, jamais en URL ;
 * - expiration écrite dans le fichier : passé ce délai, il se supprime à la première requête ;
 * - 10 jetons faux : suppression immédiate ;
 * - session propre (pas de session PHP partagée) : cookie HttpOnly, SameSite=Strict, Secure en HTTPS,
 *   inactivité 30 min ; un CSRF par session sur chaque POST, contrôle d'origine ;
 * - aucune exécution de commande, aucune lecture ni écriture de chemin fourni par le navigateur :
 *   les actions ne s'adressent que par identifiant dans le plan établi côté serveur ;
 * - n'inclut aucun fichier ; tout affichage passe par wdd_e().
 * Constantes posées par le build : WDD_ID, WDD_TOKEN_HASH, WDD_EXPIRES, WDD_BUILT.
 */

const WDD_MAX_FAILS = 10;
const WDD_IDLE      = 1800;

function wdd_e( $s ): string {
	return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/* ============================================================ Environnement et état */

function wdd_env(): array {
	static $env = null;
	if ( $env === null ) {
		$root = wd_norm( (string) realpath( __DIR__ ) );
		$env  = [ 'root' => $root, 'drop' => wd_norm( (string) realpath( __FILE__ ) ), 'work' => wdd_workdir( $root ) ];
	}
	return $env;
}
/**
 * Dossier de travail (état, sauvegardes, quarantaine) : hors de la racine servie quand le parent est
 * inscriptible, sinon dans la racine sous un nom imprévisible, fermé au web par .htaccess.
 */
function wdd_workdir( string $root ): string {
	$name = '.wd-' . WDD_ID;
	$out  = dirname( $root ) . '/' . $name;
	$in   = $root . '/' . $name;
	foreach ( [ $out, $in ] as $d ) {
		if ( @is_dir( $d ) ) {
			return $d;
		}
	}
	if ( @is_writable( dirname( $root ) ) && @mkdir( $out, 0700 ) ) {
		return $out;
	}
	if ( ! @mkdir( $in, 0700 ) && ! is_dir( $in ) ) {
		wdd_fail( 500, 'Dossier de travail impossible à créer : ni ' . $out . ', ni ' . $in . '.' );
	}
	file_put_contents( $in . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
	file_put_contents( $in . '/index.html', '' );
	return $in;
}
function wdd_state_path( string $name ): string {
	return wdd_env()['work'] . '/' . $name;
}
function wdd_load( string $name, $default = null ) {
	$p = wdd_state_path( $name );
	if ( ! is_file( $p ) ) {
		return $default;
	}
	$v = json_decode( (string) file_get_contents( $p ), true );
	return $v === null ? $default : $v;
}
function wdd_save( string $name, $value ): void {
	$json = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
	if ( $json === false || ! wda_put( wdd_state_path( $name ), $json ) ) {
		wdd_fail( 500, 'Écriture de l\'état impossible (' . $name . ').' );
	}
}

/* ============================================================ Autodestruction */

function wdd_self_delete(): bool {
	return @unlink( __FILE__ ) || ! is_file( __FILE__ );
}
function wdd_purge_work(): bool {
	$w = wdd_env()['work'];
	if ( ! is_dir( $w ) ) {
		return true;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $w, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() && ! $f->isLink() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() );
	}
	return @rmdir( $w );
}

/* ============================================================ Réponses */

function wdd_headers(): void {
	header( 'Cache-Control: no-store, max-age=0' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'X-Frame-Options: DENY' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: no-referrer' );
	header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'" );
	header( 'Content-Type: text/html; charset=utf-8' );
}
function wdd_fail( int $code, string $msg ): void {
	http_response_code( $code );
	wdd_page( 'Refusé', '<p class="err">' . wdd_e( $msg ) . '</p>' );
	exit;
}
function wdd_self_url( array $q = [] ): string {
	$path = strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '?' );
	return $path . ( $q ? '?' . http_build_query( $q ) : '' );
}
function wdd_redirect( array $q ): void {
	header( 'Location: ' . wdd_self_url( $q ), true, 303 );
	exit;
}
function wdd_page( string $title, string $body, string $head = '' ): void {
	$left = max( 0, WDD_EXPIRES - time() );
	echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">', $head,
		'<meta name="robots" content="noindex,nofollow"><title>', wdd_e( $title ), '</title><style>',
		':root{--bg:#f7f7f5;--fg:#1c1c1a;--mut:#5d5d58;--line:#d9d9d3;--card:#fff;--acc:#1f5fbf;--conf:#b3261e;--piste:#9a5b00;--ok:#1e6b36}',
		'@media (prefers-color-scheme:dark){:root{--bg:#161615;--fg:#ececea;--mut:#a3a39d;--line:#34342f;--card:#1f1f1d;--acc:#7fb0ff;--conf:#ff8a80;--piste:#f5b35c;--ok:#7ed69a}}',
		'body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.5 system-ui,sans-serif}main{max-width:980px;margin:0 auto;padding:16px}',
		'h1{font-size:1.3rem}h2{font-size:1.1rem;margin-top:2rem}.card{background:var(--card);border:1px solid var(--line);border-radius:8px;padding:12px 14px;margin:10px 0}',
		'.mut{color:var(--mut)}.err{color:var(--conf)}.ok{color:var(--ok)}.s-CONFIRMÉ{color:var(--conf);font-weight:600}.s-PISTE{color:var(--piste);font-weight:600}',
		'pre{white-space:pre-wrap;word-break:break-all;font-size:13px;background:var(--bg);padding:8px;border-radius:6px;margin:6px 0}',
		'button,input[type=submit]{font:inherit;padding:6px 14px;border-radius:6px;border:1px solid var(--acc);background:var(--acc);color:#fff;cursor:pointer}',
		'button.sec{background:transparent;color:var(--acc)}input[type=text],input[type=password],input[type=date]{font:inherit;padding:6px;width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:6px;background:var(--card);color:var(--fg)}',
		'label{display:block;margin:6px 0}nav{display:flex;gap:8px;flex-wrap:wrap;align-items:center}nav form{margin:0}.bar{height:8px;background:var(--line);border-radius:4px}.bar i{display:block;height:8px;background:var(--acc);border-radius:4px}',
		'</style></head><body><main><p class="mut">Outil d\'intervention déposé, expire dans ', (int) floor( $left / 60 ), ' min. Le supprimer dès la fin.</p>', $body, '</main></body></html>';
}

/* ============================================================ Session et jeton */

function wdd_session(): ?array {
	$st  = wdd_load( 'etat.json', [] );
	$sid = (string) ( $_COOKIE[ 'wdd_' . WDD_ID ] ?? '' );
	$s   = $st['session'] ?? null;
	if ( $sid === '' || ! $s || ! hash_equals( (string) $s['sid'], hash( 'sha256', $sid ) ) || time() - (int) $s['vu'] > WDD_IDLE ) {
		return null;
	}
	$st['session']['vu'] = time();
	wdd_save( 'etat.json', $st );
	return $st['session'];
}
function wdd_cookie( string $value, int $expires ): void {
	$https = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || ( $_SERVER['SERVER_PORT'] ?? '' ) === '443';
	setcookie( 'wdd_' . WDD_ID, $value, [ 'expires' => $expires, 'path' => (string) strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), '?' ), 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict' ] );
}
function wdd_login(): void {
	$st    = wdd_load( 'etat.json', [] );
	$token = (string) ( $_POST['jeton'] ?? '' );
	if ( $token === '' || ! hash_equals( WDD_TOKEN_HASH, hash( 'sha256', $token ) ) ) {
		$st['echecs'] = (int) ( $st['echecs'] ?? 0 ) + 1;
		wdd_save( 'etat.json', $st );
		sleep( 1 );
		if ( $st['echecs'] >= WDD_MAX_FAILS ) {
			wdd_self_delete();
			wdd_fail( 410, 'Trop de jetons faux : l\'outil s\'est supprimé.' );
		}
		wdd_fail( 403, 'Jeton refusé.' );
	}
	$sid           = bin2hex( random_bytes( 32 ) );
	$st['session'] = [ 'sid' => hash( 'sha256', $sid ), 'csrf' => bin2hex( random_bytes( 32 ) ), 'vu' => time() ];
	$st['echecs']  = 0;
	wdd_save( 'etat.json', $st );
	wdd_cookie( $sid, WDD_EXPIRES );
	wdd_redirect( [] );
}
function wdd_check_post( array $s ): void {
	$origin = (string) ( $_SERVER['HTTP_ORIGIN'] ?? '' );
	if ( $origin !== '' && $origin !== 'null' && strcasecmp( (string) parse_url( $origin, PHP_URL_HOST ), (string) preg_replace( '/:\d+$/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) ) !== 0 ) {
		wdd_fail( 403, 'Origine refusée.' );
	}
	if ( ! hash_equals( (string) $s['csrf'], (string) ( $_POST['csrf'] ?? '' ) ) ) {
		wdd_fail( 403, 'Jeton de formulaire invalide : recharger la page.' );
	}
}
function wdd_form( array $s, string $a, string $inner, string $button, string $cls = '' ): string {
	return '<form method="post" action="' . wdd_e( wdd_self_url() ) . '"><input type="hidden" name="csrf" value="' . wdd_e( $s['csrf'] ) . '"><input type="hidden" name="a" value="' . wdd_e( $a ) . '">' . $inner . '<button' . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . wdd_e( $button ) . '</button></form>';
}

/* ============================================================ Analyse par étapes */

function wdd_analysis_start( array $s ): void {
	$opt = [ 'root' => wdd_env()['root'], 'exclude' => [ wdd_env()['drop'] . ',' . wdd_env()['work'] ] ];
	$since = (string) ( $_POST['since'] ?? '' );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) ) {
		$opt['since'] = $since;
	}
	$admins = trim( (string) ( $_POST['admins'] ?? '' ) );
	if ( $admins !== '' ) {
		$opt['legit-admins'] = $admins;
	}
	$url = trim( (string) ( $_POST['url'] ?? '' ) );
	if ( $url !== '' && preg_match( '#^https?://[^/\s]+#i', $url ) ) {
		$opt['url'] = $url;
	}
	if ( empty( $_POST['reseau'] ) ) {
		$opt['no-network'] = true;
	}
	$S = wd_steps_init( $opt, 'http_depose' );
	file_put_contents( wdd_state_path( 'analyse.ser' ), serialize( $S ) );
	@unlink( wdd_state_path( 'rapport.json' ) );
	@unlink( wdd_state_path( 'plan.json' ) );
	wdd_redirect( [ 'v' => 'analyse' ] );
}
function wdd_analysis_step(): void {
	$p = wdd_state_path( 'analyse.ser' );
	if ( ! is_file( $p ) ) {
		wdd_redirect( [] );
	}
	$S = unserialize( (string) file_get_contents( $p ), [ 'allowed_classes' => [ 'WdReport', 'WdRefs' ] ] );
	if ( ! is_array( $S ) || ! isset( $S['R'] ) || ! $S['R'] instanceof WdReport ) {
		@unlink( $p );
		wdd_fail( 500, 'État d\'analyse illisible : relancer l\'analyse.' );
	}
	$met    = (int) ini_get( 'max_execution_time' );
	$budget = $met > 0 ? max( 3, min( 15, $met - 8 ) ) : 15;
	@set_time_limit( $met > 0 ? $met : 0 );
	$done = wd_step( $S, microtime( true ) + $budget );
	if ( ! $done ) {
		file_put_contents( $p, serialize( $S ) );
		$pr  = wd_steps_progress( $S );
		$pct = (int) round( 100 * $pr['etape'] / max( 1, $pr['etapes'] ) );
		$det = isset( $pr['fichiers'] ) ? 'fichiers ' . $pr['fichiers'][0] . ' / ' . $pr['fichiers'][1] : ( isset( $pr['tables'] ) ? 'tables ' . $pr['tables'][0] . ' / ' . $pr['tables'][1] : '' );
		wdd_page( 'Analyse en cours', '<h1>Analyse en cours</h1><p>Phase : ' . wdd_e( $pr['phase'] ) . ' ' . wdd_e( $det ) . '</p><div class="bar"><i style="width:' . $pct . '%"></i></div><p class="mut">La page se recharge seule. Lecture seule : rien n\'est modifié pendant l\'analyse.</p>', '<meta http-equiv="refresh" content="1;url=' . wdd_e( wdd_self_url( [ 'v' => 'analyse' ] ) ) . '">' );
		exit;
	}
	[ $doc ] = wd_steps_result( $S );
	@unlink( $p );
	wdd_save( 'rapport.json', $doc );
	wdd_save( 'plan.json', wda_plan( $doc, wdd_env() ) );
	wdd_redirect( [ 'v' => 'rapport' ] );
}

/* ============================================================ Vues */

function wdd_nav( array $s ): string {
	$links = [ '' => 'Accueil', 'rapport' => 'Rapport et actions', 'journal' => 'Journal' ];
	$h     = '<nav>';
	foreach ( $links as $v => $l ) {
		$h .= '<a href="' . wdd_e( wdd_self_url( $v === '' ? [] : [ 'v' => $v ] ) ) . '">' . wdd_e( $l ) . '</a>';
	}
	return $h . wdd_form( $s, 'deconnexion', '', 'Se déconnecter', 'sec' ) . '</nav>';
}
function wdd_view_home( array $s ): void {
	$scheme = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
	$site   = $scheme . '://' . preg_replace( '/[^A-Za-z0-9.:\[\]-]/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) . rtrim( dirname( (string) strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), '?' ) ), '/\\' ) . '/';
	$inner  = '<label>Début de fenêtre (date de la preuve la plus ancienne, si connue)<input type="date" name="since"></label>'
		. '<label>Administrateurs légitimes, confirmés par le client (identifiants séparés par des virgules)<input type="text" name="admins"></label>'
		. '<label>Adresse publique du site (test de cloaking ; vider pour l\'ignorer)<input type="text" name="url" value="' . wdd_e( $site ) . '"></label>'
		. '<label><input type="checkbox" name="reseau" value="1" checked> Consulter les manifestes officiels de WordPress (sans eux, rien ne peut être expliqué)</label>';
	$body = wdd_nav( $s ) . '<h1>Analyse du projet</h1><p class="mut">Racine : ' . wdd_e( wdd_env()['root'] ) . '<br>Dossier de travail : ' . wdd_e( wdd_env()['work'] ) . '</p>'
		. '<div class="card">' . wdd_form( $s, 'analyser', $inner, is_file( wdd_state_path( 'rapport.json' ) ) ? 'Relancer l\'analyse' : 'Lancer l\'analyse' ) . '</div>'
		. '<h2>Terminer</h2><div class="card"><p>Supprime cet outil du serveur. Le dossier de travail contient les sauvegardes, la quarantaine et le journal : le récupérer d\'abord (FTP), puis le supprimer.</p>'
		. wdd_form( $s, 'terminer', '<label><input type="checkbox" name="confirme" value="1"> Je termine l\'intervention</label><label><input type="checkbox" name="purge" value="1"> Supprimer aussi le dossier de travail (sauvegardes et quarantaine perdues)</label>', 'Terminer et supprimer l\'outil' ) . '</div>';
	wdd_page( 'Outil d\'intervention', $body );
}
function wdd_view_report( array $s ): void {
	$doc  = wdd_load( 'rapport.json' );
	$plan = wdd_load( 'plan.json', [] );
	if ( ! $doc ) {
		wdd_redirect( [] );
	}
	$done = [];
	foreach ( wdd_load( 'journal.json', [] ) as $e ) {
		if ( empty( $e['annule'] ) ) {
			$done[ $e['action'] ] = true;
		}
	}
	$h = wdd_nav( $s ) . '<h1>Rapport</h1><p class="mut">Généré le ' . wdd_e( $doc['genere_le'] ) . ' ; comptes par statut : ' . wdd_e( json_encode( $doc['comptes_par_statut'], JSON_UNESCAPED_UNICODE ) ) . '. ' . wdd_e( $doc['avertissement'] ) . '</p>';
	if ( $done ) {
		$h .= '<p class="card">Des actions ont été exécutées depuis cette analyse : relancer l\'analyse pour vérifier leur effet et repérer une réécriture.</p>';
	}
	$h .= '<h2>Actions proposées (' . count( $plan ) . ')</h2>';
	if ( ! $plan ) {
		$h .= '<p class="mut">Aucune action automatisable pour ces constats : les traiter à la main selon la colonne « action » du rapport.</p>';
	}
	foreach ( $plan as $a ) {
		$h .= '<div class="card"><strong>' . wdd_e( $a['titre'] ) . '</strong> <span class="mut">(' . count( $a['items'] ) . ' élément(s))</span>' . ( isset( $done[ $a['id'] ] ) ? ' <span class="ok">déjà exécutée</span>' : '' )
			. '<br><a href="' . wdd_e( wdd_self_url( [ 'v' => 'action', 'id' => $a['id'] ] ) ) . '">Aperçu et choix des éléments</a></div>';
	}
	$h .= '<h2>Constats (' . count( $doc['constats'] ) . ')</h2>';
	foreach ( $doc['constats'] as $c ) {
		$h .= '<div class="card"><span class="s-' . wdd_e( $c['statut'] ) . '">' . wdd_e( $c['statut'] ) . '</span> [' . wdd_e( $c['code'] ) . '] <strong>' . wdd_e( $c['cible'] ) . '</strong> : ' . wdd_e( $c['titre'] );
		if ( $c['preuves'] ) {
			$h .= '<pre>' . wdd_e( implode( "\n", array_slice( $c['preuves'], 0, 8 ) ) ) . '</pre>';
		}
		if ( $c['action'] !== '' ) {
			$h .= '<p class="mut">' . wdd_e( $c['action'] ) . '</p>';
		}
		$h .= '</div>';
	}
	wdd_page( 'Rapport', $h );
}
function wdd_view_action( array $s ): void {
	$plan = wdd_load( 'plan.json', [] );
	$id   = (string) ( $_GET['id'] ?? '' );
	if ( ! isset( $plan[ $id ] ) ) {
		wdd_fail( 404, 'Action inconnue : relancer l\'analyse.' );
	}
	$a   = $plan[ $id ];
	$pv  = wda_preview( $a, wdd_env() );
	$in  = '<input type="hidden" name="id" value="' . wdd_e( $id ) . '">';
	foreach ( $pv['avertissements'] as $w ) {
		$in .= '<p class="card">' . wdd_e( $w ) . '</p>';
	}
	foreach ( $a['items'] as $k => $it ) {
		$in .= '<div class="card"><label><input type="checkbox" name="items[]" value="' . wdd_e( $k ) . '"> <strong>' . wdd_e( $it['libelle'] ?? $k ) . '</strong>' . ( isset( $it['statut'] ) ? ' <span class="s-' . wdd_e( $it['statut'] ) . '">' . wdd_e( $it['statut'] ) . '</span>' : '' ) . '</label>';
		foreach ( $it['constats'] ?? [] as $c ) {
			$in .= '<div class="mut"><span class="s-' . wdd_e( $c['statut'] ) . '">' . wdd_e( $c['statut'] ) . '</span> [' . wdd_e( $c['code'] ) . '] ' . wdd_e( $c['titre'] ) . '</div>';
		}
		if ( isset( $pv['details'][ $k ] ) ) {
			$in .= '<pre>' . wdd_e( $pv['details'][ $k ] ) . '</pre>';
		}
		$in .= '</div>';
	}
	if ( $a['type'] === 'mots_de_passe' ) {
		$in .= '<label>Identifiants à conserver (au moins un administrateur légitime)<input type="text" name="conserver"></label>';
	}
	$in .= '<label><input type="checkbox" name="confirme" value="1"> Une copie de sauvegarde du site (fichiers et base) existe hors du serveur, et j\'ai relu chaque élément coché.</label>';
	wdd_page( 'Action', wdd_nav( $s ) . '<h1>' . wdd_e( $a['titre'] ) . '</h1><p class="mut">Rien n\'est coché par défaut : chaque élément se valide un par un. Chaque changement est sauvegardé et annulable depuis le journal.</p>' . wdd_form( $s, 'executer', $in, 'Exécuter les éléments cochés' ) );
}
function wdd_view_journal( array $s, array $msgs = [] ): void {
	$j = wdd_load( 'journal.json', [] );
	$h = wdd_nav( $s ) . '<h1>Journal</h1>';
	foreach ( $msgs as $m ) {
		$h .= '<p class="' . ( preg_match( '/^(REFUS|ÉCHEC)/u', $m ) ? 'err' : 'ok' ) . '">' . wdd_e( $m ) . '</p>';
	}
	if ( ! $j ) {
		$h .= '<p class="mut">Aucune action exécutée.</p>';
	}
	foreach ( array_reverse( $j, true ) as $n => $e ) {
		$h .= '<div class="card">' . wdd_e( $e['date'] ) . ' : <strong>' . wdd_e( $e['titre'] ) . '</strong> : ' . wdd_e( $e['cible'] ) . ( $e['annule'] ? ' <span class="mut">(annulée le ' . wdd_e( $e['annule'] ) . ')</span>' : wdd_form( $s, 'annuler', '<input type="hidden" name="n" value="' . (int) $n . '">', 'Annuler', 'sec' ) ) . '</div>';
	}
	wdd_page( 'Journal', $h );
}

/* ============================================================ Contrôleur */

function wdd_main(): void {
	umask( 0077 );
	wdd_headers();
	if ( time() > WDD_EXPIRES ) {
		wdd_self_delete();
		wdd_fail( 410, 'Outil expiré : il s\'est supprimé. En générer un nouveau si besoin.' );
	}
	$post = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
	$a    = $post ? (string) ( $_POST['a'] ?? '' ) : '';
	if ( $a === 'login' ) {
		wdd_login();
	}
	$s = wdd_session();
	if ( ! $s ) {
		if ( $post ) {
			wdd_fail( 403, 'Session absente ou expirée.' );
		}
		wdd_page( 'Accès', '<h1>Accès</h1><form method="post" action="' . wdd_e( wdd_self_url() ) . '"><input type="hidden" name="a" value="login"><label>Jeton<input type="password" name="jeton" autocomplete="off" autofocus></label><button>Entrer</button></form>' );
		return;
	}
	if ( $post ) {
		wdd_check_post( $s );
		switch ( $a ) {
			case 'deconnexion':
				$st = wdd_load( 'etat.json', [] );
				unset( $st['session'] );
				wdd_save( 'etat.json', $st );
				wdd_cookie( '', 1 );
				wdd_redirect( [] );
				break;
			case 'analyser':
				wdd_analysis_start( $s );
				break;
			case 'executer':
				$plan = wdd_load( 'plan.json', [] );
				$id   = (string) ( $_POST['id'] ?? '' );
				if ( ! isset( $plan[ $id ] ) ) {
					wdd_fail( 404, 'Action inconnue.' );
				}
				if ( empty( $_POST['confirme'] ) ) {
					wdd_view_journal( $s, [ 'REFUS : confirmation non cochée, rien n\'a été fait.' ] );
					return;
				}
				[ $msgs, $log ] = wda_execute( $plan[ $id ], (array) ( $_POST['items'] ?? [] ), [ 'conserver' => (string) ( $_POST['conserver'] ?? '' ) ], wdd_env() );
				if ( $log ) {
					wdd_save( 'journal.json', array_merge( wdd_load( 'journal.json', [] ), $log ) );
				}
				wdd_view_journal( $s, $msgs );
				return;
			case 'annuler':
				$j = wdd_load( 'journal.json', [] );
				$n = (int) ( $_POST['n'] ?? -1 );
				if ( ! isset( $j[ $n ] ) || $j[ $n ]['annule'] ) {
					wdd_view_journal( $s, [ 'REFUS : entrée inconnue ou déjà annulée.' ] );
					return;
				}
				$msg = wda_undo( $j[ $n ], wdd_env() );
				if ( ! preg_match( '/^(REFUS|ÉCHEC)/u', $msg ) ) {
					$j[ $n ]['annule'] = gmdate( 'c' );
					wdd_save( 'journal.json', $j );
				}
				wdd_view_journal( $s, [ $msg ] );
				return;
			case 'terminer':
				if ( empty( $_POST['confirme'] ) ) {
					wdd_redirect( [] );
				}
				$work   = wdd_env()['work'];
				$purged = ! empty( $_POST['purge'] ) && wdd_purge_work();
				$gone   = wdd_self_delete();
				wdd_cookie( '', 1 );
				wdd_page( 'Terminé', '<h1>' . ( $gone ? 'Outil supprimé' : 'Suppression impossible' ) . '</h1>'
					. ( $gone ? '<p class="ok">Le fichier de l\'outil n\'existe plus sur le serveur.</p>' : '<p class="err">Le fichier n\'a pas pu être supprimé : le retirer par FTP immédiatement.</p>' )
					. ( $purged ? '<p>Dossier de travail supprimé.</p>' : '<p>Dossier de travail conservé : ' . wdd_e( $work ) . '. Le récupérer par FTP puis le supprimer.</p>' ) );
				return;
		}
		wdd_fail( 400, 'Requête inconnue.' );
	}
	switch ( (string) ( $_GET['v'] ?? '' ) ) {
		case 'analyse':
			wdd_analysis_step();
			return;
		case 'rapport':
			wdd_view_report( $s );
			return;
		case 'action':
			wdd_view_action( $s );
			return;
		case 'journal':
			wdd_view_journal( $s );
			return;
	}
	wdd_view_home( $s );
}
