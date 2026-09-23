<?php
/*
 * Tests unitaires de la logique du détecteur (sans site ni base) :
 * - le décodage multi-schémas atteint le point fixe,
 * - le parseur ps tolère un utilisateur de plus de 8 caractères et l'entête,
 * - le kill ne se propose que sur signaux forts, jamais sur l'âge (etimes),
 * - detect.php n'inclut/require AUCUN chemin du site (analyse statique du script lui-même),
 * - les emplacements et statuts de contenu du cœur sont reconnus.
 */
require __DIR__ . '/lib.php';
require_once tl_detect_path();

/* --- Décodage jusqu'au point fixe, plusieurs schémas empilés --- */
[ $sch, $final ] = wd_decode_layers( base64_encode( gzdeflate( str_rot13( 'echo 42;' ) ) ) );
T::ok( 'U1 décodage base64>gzinflate>rot13', strpos( $final, 'echo 42;' ) !== false || strpos( str_rot13( $final ), 'echo 42;' ) !== false, implode( '>', $sch ) . ' => ' . $final );

[ , $hexOut ] = wd_decode_layers( bin2hex( 'uninstall_plugins ok' ) );
T::ok( 'U2 décodage hex', strpos( $hexOut, 'uninstall_plugins' ) !== false, $hexOut );

$P = wd_php_analyze( "<?php \$a='ev'.'al'; \$a(\$_GET['x']);" );
$hit = array_filter( $P->flows, fn( $f ) => $f['niveau'] === 'CONFIRMÉ' );
T::ok( 'U3 concat d\'un nom de fonction + entrée HTTP', (bool) $hit, json_encode( array_column( $P->flows, 'detail' ) ) );

$P2 = wd_php_analyze( "<?php echo esc_html(\$_GET['x']);" );
T::ok( 'U4 pas de faux positif sur une sortie échappée', ! array_filter( $P2->flows, fn( $f ) => $f['niveau'] === 'CONFIRMÉ' ) );

/* --- Parseur ps : sortie réelle « pid ppid etimes lstart args », utilisateur > 8 caractères --- */
$sample = "  PID  PPID ELAPSED                  STARTED COMMAND\n"
	. "10318     1 5380819 Thu Jul 23 05:02:14 2026 /opt/php/bin/php /var/www/vhosts/site/httpdocs/l.php\n"
	. "23796   827      21 Wed Sep 23 11:42:12 2026 php-fpm: pool site.example\n"
	. "23876 23796       1 Wed Sep 23 11:42:32 2026 sh -c ps -u 'webuser_longname12345' -o pid,ppid,etimes,lstart,args\n";
$rows = wd_parse_ps( $sample );
T::ok( 'U5 ps : 3 lignes de données lues (entête ignoré)', count( $rows ) === 3, json_encode( array_keys( $rows ) ) );
T::ok( 'U5b ps : lstart retiré, args propre', ( $rows[10318]['args'] ?? '' ) === '/opt/php/bin/php /var/www/vhosts/site/httpdocs/l.php', $rows[10318]['args'] ?? '(absent)' );
T::ok( 'U5c ps : ppid et etimes corrects', ( $rows[10318]['ppid'] ?? -1 ) === 1 && ( $rows[10318]['age'] ?? 0 ) === 5380819 );

/* La commande cible l'utilisateur (long) par -u, jamais par un regex sur la colonne tronquée. */
// escapeshellarg entoure de guillemets simples (POSIX) ou doubles (Windows) : les deux conviennent.
$cmd = wd_ps_command( 'webuser_longname12345' );
T::ok( 'U6 commande ps par -u (nom complet, non tronqué)', preg_match( '/-u [\'"]webuser_longname12345[\'"]/', $cmd ) === 1 && strpos( $cmd, 'pid=,ppid=,etimes=,args=' ) !== false, $cmd );

/* Kill proposé sur signaux forts uniquement ; l'âge n'est jamais un critère. */
$info = [ 10318 => [ 'cwd' => '/var/www/vhosts/site/httpdocs.old/wp-content/plugins/x (deleted)', 'exe' => '' ] ];
$sus  = wd_ps_suspects( $rows, $info, 23876, [ '/var/www/vhosts/site/httpdocs' ] );
T::ok( 'U7 processus détaché (PPID 1 + cwd supprimé) retenu', isset( $sus[10318] ) && count( $sus[10318]['forts'] ) >= 2, json_encode( $sus[10318]['forts'] ?? [] ) );
T::ok( 'U7b pool php-fpm et arbre du script ignorés', ! isset( $sus[23796] ) && ! isset( $sus[23876] ) );

// Un processus vieux mais rattaché à un pool php-fpm normal n'est PAS un suspect (l'âge ne compte pas).
$rows2 = wd_parse_ps( "50 1 9999999 Mon Jan  1 00:00:00 2020 php-fpm: pool site.example\n" );
$sus2  = wd_ps_suspects( $rows2, [], 999, [] );
T::ok( 'U8 l\'âge (etimes) seul ne déclenche jamais un kill', ! isset( $sus2[50] ), json_encode( array_keys( $sus2 ) ) );

/* --- Analyse statique : detect.php n'inclut/require aucun chemin (invariant B1) --- */
$src    = file_get_contents( tl_detect_path() );
$tokens = token_get_all( $src );
$bad    = [];
foreach ( $tokens as $i => $t ) {
	if ( is_array( $t ) && in_array( $t[0], [ T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE ], true ) ) {
		$ctx = '';
		for ( $j = $i + 1; $j < min( $i + 6, count( $tokens ) ); $j++ ) {
			$ctx .= is_array( $tokens[ $j ] ) ? $tokens[ $j ][1] : $tokens[ $j ];
		}
		$bad[] = 'ligne ' . $t[2] . ' : ' . trim( $ctx );
	}
}
T::ok( 'U9 detect.php ne contient aucun include/require (jetons)', ! $bad, implode( ' ; ', $bad ) );
// Aucune API de chargement WordPress, quelle que soit la casse.
T::ok( 'U9b detect.php ne charge pas WordPress (eval-file/WP_CLI)', ! preg_match( '/eval-file|wp[_-]?cli/i', $src ) );
// Les seuls hôtes externes appelés (dans un contexte réseau) sont les API officielles ; le reste (UA/referer du test de cloaking, exemples de l\'aide) n\'est pas une cible d\'appel.
preg_match_all( '#https?://([a-z0-9.-]+)#i', $src, $mm );
$hosts = array_values( array_unique( array_map( 'strtolower', $mm[1] ) ) );
$allowed = [ 'api.wordpress.org', 'downloads.wordpress.org', 'www.google.com', 'site' ];
$foreign = array_values( array_diff( $hosts, $allowed ) );
T::ok( 'U9c aucun hôte externe hors API officielles et UA/referer du test', ! $foreign, implode( ',', $foreign ) );

/* --- Emplacements et statuts du cœur --- */
$ctx = [ 'core_root_files' => WD_CORE_ROOT_FILES ];
T::ok( 'U10 uploads = emplacement inattendu', wd_location( 'wp-content/uploads/x.php', $ctx ) === 'uploads' );
T::ok( 'U10b wp-includes = cœur', wd_location( 'wp-includes/pluggable.php', $ctx ) === 'coeur' );
T::ok( 'U10c drop-in reconnu', wd_location( 'wp-content/object-cache.php', $ctx ) === 'dropin' );
T::ok( 'U10d racine non-wp', wd_location( 'cloner.php', $ctx ) === 'racine_non_wp' );

/* --- Domaines réservés (RFC) reconnus, domaines réels non --- */
$db = new ReflectionClass( 'WdDb' );
T::ok( 'U11 domaine réservé .invalid reconnu', preg_match( WD_RESERVED_DOMAIN, 'attacker.invalid' ) === 1 );
T::ok( 'U11b .local reconnu, gmail.com non', preg_match( WD_RESERVED_DOMAIN, 'x.local' ) === 1 && preg_match( WD_RESERVED_DOMAIN, 'gmail.com' ) === 0 );

/* --- Entropie : littéral aléatoire vs texte --- */
T::ok( 'U12 entropie discrimine aléatoire vs texte', wd_entropy( 'aZ9kQ2mB7xR4pL6vT8wN3cF5dH1jY0sG' ) > wd_entropy( 'the quick brown fox jumps over' ) );

exit( T::end() );
