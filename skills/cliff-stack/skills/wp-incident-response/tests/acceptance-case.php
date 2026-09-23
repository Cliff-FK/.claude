<?php
/*
 * Recette sur le cas réel « valream » (données du client : les noms propres n'existent QUE dans ce fichier).
 * Lecture seule : les données sont référencées par chemin, copiées fraîchement hors de ~/.claude,
 * vérifiées par sha256, puis re-hachées après chaque passage du détecteur.
 *
 *   php acceptance-valream.php --site=DOCROOT --sql=DUMP.sql --work=DOSSIER_TEMP [--ps-sample=tr2.txt] [--reuse] [--no-network]
 * Médias binaires d'uploads et wp-content/cache exclus de la copie (couverts par les mutations synthétiques).
 */
require __DIR__ . '/lib.php';

$opt = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([a-z-]+)(?:=(.*))?$/', $a, $m ) ) {
		$opt[ $m[1] ] = $m[2] ?? true;
	}
}
foreach ( [ 'site', 'sql', 'work' ] as $k ) {
	if ( empty( $opt[ $k ] ) ) {
		T::skip( 'acceptance', 'argument --' . $k . ' manquant : recette NON exécutée' );
		exit( T::end() );
	}
}
// Copie tractable sur un poste où l'antivirus scanne chaque fichier lu et écrit : on exclut ce
// qu'aucune assertion de ce cas ne vise. uploads/ et cache/ (code dans uploads couvert par M7/M9),
// et le cœur wp-admin/wp-includes (l'intégrité du cœur par manifeste est couverte ailleurs ;
// on garde version.php pour le contexte). Tout wp-content/plugins|themes|mu-plugins reste copié,
// donc les tables restent expliquées (ou non) par le vrai code installé — cœur de E22.
$exclude = function ( string $rel ): bool {
	$l = strtolower( $rel );
	if ( strpos( $l, 'wp-content/uploads/' ) === 0 || strpos( $l, 'wp-content/cache/' ) === 0 ) {
		return true;
	}
	if ( $l === 'wp-includes/version.php' ) {
		return false;
	}
	return strpos( $l, 'wp-admin/' ) === 0 || strpos( $l, 'wp-includes/' ) === 0;
};

$work = rtrim( str_replace( '\\', '/', $opt['work'] ), '/' );
$copy = $work . '/valream-copie';
$manF = $work . '/valream-manifeste.json';
$t0   = microtime( true );
if ( ! empty( $opt['reuse'] ) && is_file( $manF ) && is_dir( $copy ) ) {
	$manifest = json_decode( (string) file_get_contents( $manF ), true );
	echo "Copie existante réutilisée, re-vérification par hash…\n";
} else {
	tl_tmpdir( $work, 'valream-copie' );
	echo "Copie fraîche en cours (hors médias binaires et cache)…\n";
	$manifest = tl_fresh_copy( $opt['site'], $copy, $exclude );
	file_put_contents( $manF, json_encode( $manifest ) );
}
$now = tl_manifest( $copy );
T::ok( 'E0 copie vérifiée par sha256', $now === $manifest && count( $now ) > 1000, count( $now ) . ' fichiers, ' . round( microtime( true ) - $t0 ) . ' s' );
$sqlHash = hash_file( 'sha256', $opt['sql'] );

$args = [ '--root=' . $copy, '--sql=' . $opt['sql'] ];
if ( ! empty( $opt['no-network'] ) ) {
	$args[] = '--no-network';
}
$jsonOut = $work . '/valream-rapport-php' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.json';
$t1 = microtime( true );
[ $rc, $doc, $text ] = tl_run_detect( $args, $jsonOut );
file_put_contents( $work . '/valream-rapport-php' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.txt', $text );
echo 'Détecteur : ' . round( microtime( true ) - $t1 ) . " s\n";
if ( ! T::ok( 'E00 rapport JSON produit', is_array( $doc ) ) ) {
	exit( T::end() );
}

// E1 : index.php racine, chargeur de contenu distant, adresse décodée (IOC comparé sous forme encodée).
$idx = tl_findings( $doc, 'code.capacite', 'index.php', 'CONFIRMÉ' );
$c2  = false;
foreach ( $idx as $f ) {
	foreach ( $f['preuves'] as $p ) {
		if ( preg_match( '#adresse décodée : (\S+)#', $p, $m ) && strpos( base64_encode( $m[1] ), 'aHR0cDovL3VzNTEyLXYzNDkudXNhbWF6b24z' ) === 0 ) {
			$c2 = true;
		}
	}
}
T::ok( 'E1 index.php racine CONFIRMÉ + C2 décodé', $idx && $c2, tl_text( $idx ) );

// E2 : verrou .htaccess racine (liste blanche de noms).
$lock = tl_findings( $doc, 'serveur.verrou', '.htaccess' );
T::ok( 'E2 verrou .htaccess racine', $lock && $lock[0]['statut'] === 'CONFIRMÉ' && in_array( 'lock360.php', $lock[0]['noms_autorises'] ?? [], true ), $lock ? implode( ' | ', $lock[0]['preuves'] ) : 'absent' );

// E3 : webshells stockés en base sans eval (snippets 5 à 10), exemples 1 à 4 non signalés.
$sn = [];
foreach ( tl_findings( $doc, 'base.code_execute', null, 'CONFIRMÉ' ) as $f ) {
	if ( preg_match( '/snippets#(\d+):code$/', $f['cible'], $m ) ) {
		$sn[] = (int) $m[1];
	}
}
sort( $sn );
T::ok( 'E3 snippets webshell 5-10 CONFIRMÉ, 1-4 non', $sn === range( 5, 10 ), json_encode( $sn ) );
$noEval = true;
foreach ( tl_findings( $doc, 'base.code_execute', '/snippets#5:code$/' ) as $f ) {
	$noEval = $noEval && strpos( implode( ' ', $f['preuves'] ), 'eval' ) === false;
}
T::ok( 'E3b détection sans reposer sur eval', $noEval );

// E4 : 82 comptes isolés, exactement les ID 5 à 86.
$ids = [];
foreach ( [ 'comptes.suspects', 'comptes.fenetre' ] as $code ) {
	foreach ( tl_findings( $doc, $code ) as $f ) {
		$ids = array_merge( $ids, $f['ids'] ?? [] );
	}
}
sort( $ids );
T::ok( 'E4 82 comptes isolés (ID 5 à 86)', $ids === range( 5, 86 ), count( $ids ) . ' comptes' );
T::ok( 'E4b comptes 1 à 4 non signalés', ! array_intersect( $ids, [ 1, 2, 3, 4 ] ) );

// E5 : artefacts de contenu.
$anti = tl_text( tl_findings( $doc, 'contenus.antidates' ) );
$req  = tl_findings( $doc, 'contenus.type_non_explique' );
$reqT = tl_text( array_filter( $req, fn( $f ) => strpos( $f['titre'], ': request' ) !== false ) );
$pic  = tl_any( $doc, fn( $c ) => $c['code'] === 'contenus.pic_fenetre' && ( $c['type'] ?? '' ) === 'oembed_cache' );
T::ok( 'E5a customize_changeset antidatés', strpos( $anti, 'customize_changeset' ) !== false );
T::ok( 'E5b type « request » non expliqué, statut parse', strpos( $reqT, '"parse":92' ) !== false, $reqT );
T::ok( 'E5c pic oembed_cache dans la fenêtre', (bool) $pic, $pic ? $pic[0]['preuves'][0] : '' );

// E6 : défacement sans <script>.
$def = tl_text( tl_findings( $doc, 'contenus.defacement', null, 'CONFIRMÉ' ) );
T::ok( 'E6 défacement détecté par le contenu', substr_count( $def, 'Hacked by' ) >= 6, '' );

// E7 : métadonnées et relations orphelines.
$pm = tl_findings( $doc, 'base.postmeta_orphelines' );
T::ok( 'E7 postmeta orphelines', $pm && ( $pm[0]['nombre'] ?? 0 ) > 0, $pm ? $pm[0]['titre'] : '' );
$tr = tl_findings( $doc, 'base.relations_orphelines' );
T::ok( 'E7b relations de termes orphelines', $tr && ( $tr[0]['nombre'] ?? 0 ) > 0 );

// E8 : table SEO d'extension (orphelines + lignes d'options exfiltrées en hexadécimal).
$yo = tl_findings( $doc, 'base.table_extension_orphelines', 'wp63V743V_yoast_indexable' );
T::ok( 'E8 table SEO : lignes orphelines', $yo && ( $yo[0]['nombre'] ?? 0 ) > 0, $yo ? $yo[0]['titre'] : '' );
$hex = tl_any( $doc, fn( $c ) => $c['code'] === 'base.valeur_decodee' && strpos( $c['cible'], 'yoast_indexable#' ) !== false );
T::ok( 'E8b table SEO : valeurs hexadécimales décodées', count( $hex ) >= 3, count( $hex ) . ' ligne(s)' );

// E9 : journal d'agent distant décodé (tentative execute_php_code).
$mwp = tl_text( tl_any( $doc, fn( $c ) => $c['code'] === 'base.valeur_decodee' && strpos( $c['cible'], 'mwp_last_communication_error' ) !== false ) );
T::ok( 'E9 journal d\'agent distant décodé', strpos( $mwp, 'execute_php_code' ) !== false, substr( $mwp, 0, 200 ) );

// E10 : mises à jour automatiques coupées par le thème.
$mf = tl_text( tl_findings( $doc, 'maj.filtre', 'wp-content/themes/themezero/functions.php' ) );
T::ok( 'E10 auto_update désactivé par le thème', strpos( $mf, 'auto_update_core' ) !== false, $mf );

// E11 à E13 : aucun faux positif sur functions.php:93, cloner.php (eval sur jeton), ACF updates.php (base64).
$fp = function ( string $rel ) use ( $doc ): array {
	return tl_any( $doc, fn( $c ) => $c['cible'] === $rel && in_array( $c['code'], [ 'code.capacite', 'code.obfusque' ], true ) );
};
T::ok( 'E11 aucun faux positif functions.php (eval( en chaîne)', ! $fp( 'wp-content/themes/themezero/functions.php' ), tl_text( $fp( 'wp-content/themes/themezero/functions.php' ) ) );
// cloner.php est un outil déposé par l'attaquant : il est légitimement signalé (dropper + emplacement).
// Le faux positif à éviter est précis : son eval() sur un JETON de configuration (chaîne constante lue
// par token_get_all) ne doit jamais être pris pour un puits d'exécution de code.
$clonerEval = false;
foreach ( tl_findings( $doc, 'code.capacite', 'cloner.php' ) as $f ) {
	foreach ( $f['preuves'] as $p ) {
		if ( preg_match( '/\[(exec_code|include)\]/', $p ) || preg_match( '/eval\(\)/', $p ) ) {
			$clonerEval = true;
		}
	}
}
T::ok( 'E12 aucun faux positif sur le eval de jeton de cloner.php', ! $clonerEval, tl_text( $fp( 'cloner.php' ) ) );
T::ok( 'E12b cloner.php signalé comme PHP racine non expliqué', (bool) tl_findings( $doc, 'emplacement.inattendu', 'cloner.php', 'PISTE' ) );
T::ok( 'E13 aucun faux positif ACF pro/updates.php (base64)', ! $fp( 'wp-content/plugins/advanced-custom-fields-pro/pro/updates.php' ) );

// E14 : siteurl/home sains.
T::ok( 'E14 siteurl/home sains', ! tl_any( $doc, fn( $c ) => $c['code'] === 'base.option' && in_array( $c['cible'], [ 'siteurl', 'home' ], true ) ) );

// E15 : aucun secret affiché (mot de passe base lu par le parseur du détecteur, hash de mots de passe).
require_once tl_detect_path();
$R   = new WdReport();
$cfg = wd_parse_config( $R, $copy );
$pw  = (string) ( $cfg['db']['DB_PASSWORD'] ?? '' );
$all = (string) file_get_contents( $jsonOut ) . $text;
T::ok( 'E15 mot de passe de base jamais affiché', $pw !== '' && strpos( $all, $pw ) === false );
T::ok( 'E15b aucun hash de mot de passe affiché', ! preg_match( '/\$wp\$2y\$|\$P\$[A-Za-z0-9.\/]{20}/', $all ) );
$salts = 0;
foreach ( [ 'AUTH_KEY', 'NONCE_SALT' ] as $k ) {
	$v = (string) ( $cfg['consts'][ $k ]['valeur'] ?? '' );
	$salts += ( $v !== '' && strpos( $all, $v ) !== false ) ? 1 : 0;
}
T::ok( 'E15c clés et sels jamais affichés', $salts === 0 );

// E16 et E17 : corrélation temporelle et fenêtre fondée sur la preuve la plus ancienne.
$gr = tl_text( tl_findings( $doc, 'correlation.grappes' ) );
T::ok( 'E16 grappe compte + code en base', preg_match( '/compte w2s_\w+ \| [0-9: -]+ code_en_base/', $gr ) === 1 );
T::ok( 'E17 fenêtre = 2026-07-26 13:58:36', ( $doc['contexte']['fenetre']['debut'] ?? '' ) === '2026-07-26 13:58:36' );

// E18 : composants sans référence publique → NEEDS_HUMAN (ou vérification réseau signalée non faite).
$acf = tl_findings( $doc, 'integrite.sans_reference', 'wp-content/plugins/advanced-custom-fields-pro', 'NEEDS_HUMAN' );
$netDown = (bool) tl_any( $doc, fn( $c ) => $c['code'] === 'verif.non_faite' && strpos( $c['cible'], 'manifeste' ) !== false );
T::ok( 'E18 extension commerciale sans référence : NEEDS_HUMAN', (bool) $acf || $netDown );
T::ok( 'E18b mu-plugins à faire confirmer', (bool) tl_findings( $doc, 'composant.mu_plugins', null, 'NEEDS_HUMAN' ) );

// E19 : lecture seule prouvée (copie et dump inchangés).
T::ok( 'E19 copie inchangée après analyse', tl_manifest( $copy ) === $manifest );
T::ok( 'E19b dump inchangé', hash_file( 'sha256', $opt['sql'] ) === $sqlHash );

// E20 et E21 : code retour, jamais de statut « ok ».
T::ok( 'E20 code retour 2 (CONFIRMÉ présent)', $rc === 2, 'rc=' . $rc );
$st = array_unique( array_column( $doc['constats'], 'statut' ) );
T::ok( 'E21 statuts limités aux cinq statuts', ! array_diff( $st, [ 'CONFIRMÉ', 'PISTE', 'DURCISSEMENT', 'NEEDS_HUMAN', 'ILLISIBLE' ] ), implode( ',', $st ) );

// E22 : tables expliquées par le code installé, tables orphelines signalées.
$tb = tl_findings( $doc, 'base.table_non_expliquee' );
$tl = $tb ? implode( ' ', $tb[0]['tables'] ?? [] ) : '';
T::ok( 'E22 tables de code et SEO expliquées par le code', strpos( $tl, '_snippets ' ) === false && strpos( $tl, 'yoast_indexable ' ) === false, $tl );
T::ok( 'E22b tables sans code déclarant signalées', strpos( $tl, 'mclean_scan' ) !== false );

// E23 : parseur ps sur la sortie réelle (utilisateur > 8 caractères).
if ( ! empty( $opt['ps-sample'] ) && is_file( $opt['ps-sample'] ) ) {
	$lines = array_slice( file( $opt['ps-sample'] ), 2134, 11 );
	$rows  = wd_parse_ps( implode( '', $lines ) );
	T::ok( 'E23 ps réel : 4 processus lus', count( $rows ) === 4, json_encode( array_keys( $rows ) ) );
	$sus = wd_ps_suspects( $rows, [ 10318 => [ 'cwd' => '/var/www/vhosts/x/httpdocs.old-20260724/wp-content/plugins/x/src/ui (deleted)', 'exe' => '' ] ], 23796, [ '/var/www/vhosts/vibrant-ritchie.51-83-66-154.plesk.page/httpdocs' ] );
	T::ok( 'E23b processus persistant repéré, pool php-fpm et sonde ignorés', array_keys( $sus ) === [ 10318 ] && count( $sus[10318]['forts'] ) >= 2, json_encode( $sus ) );
	// La commande cible l'utilisateur complet par -u ; la sortie « ps -eo user » réelle le tronque en
	// « vibrant+ » (colonne user à 8 caractères), donc un filtre sur le nom complet ne trouverait rien.
	$byU  = preg_match( '/-u [\'"]vibrant-ritchie_xpnv8f1n2ya[\'"]/', wd_ps_command( 'vibrant-ritchie_xpnv8f1n2ya' ) ) === 1;
	$trunc = preg_match( '/(^|\s)vibrant\+\s+10318\b/m', implode( '', $lines ) ) === 1;
	T::ok( 'E23c colonne user tronquée : la commande filtre par -u', $byU && $trunc, 'byU=' . var_export( $byU, true ) . ' trunc=' . var_export( $trunc, true ) );
} else {
	T::skip( 'E23 ps réel', 'fournir --ps-sample=tr2.txt' );
}

// E24 : aucune liste nommée (produits, familles, services) hors de ce fichier.
$banned = [ 'managewp', 'code snippets', 'code-snippets', 'wpcode', 'yoast', 'lock360', 'imunify', 'plesk', 'cpanel', 'wordfence', 'sucuri', 'jetpack', 'mainwp', 'infinitewp', 'advanced-custom-fields', 'valream', 'usamazon', 'wp2shell' ];
$files  = array_merge( [ tl_detect_path(), dirname( __DIR__ ) . '/SKILL.md' ], glob( dirname( __DIR__ ) . '/references/*.md' ) ?: [] );
$hits   = [];
foreach ( $files as $f ) {
	$c = strtolower( (string) file_get_contents( $f ) );
	foreach ( $banned as $b ) {
		if ( strpos( $c, $b ) !== false ) {
			$hits[] = basename( $f ) . ':' . $b;
		}
	}
}
T::ok( 'E24 aucun nom propre ni liste nommée dans la skill et le détecteur', ! $hits, implode( ', ', $hits ) );

exit( T::end() );
