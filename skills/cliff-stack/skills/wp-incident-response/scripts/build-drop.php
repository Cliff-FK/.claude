<?php
/*
 * build-drop.php (skill wp-incident-response) : génère l'outil d'intervention à déposer par FTP à la
 * racine d'un projet. Assemble detect.php + drop/actions.php + drop/app.php en UN fichier, avec un
 * jeton neuf (seule son empreinte sha256 est écrite dans le fichier) et une expiration codée.
 *
 *   php build-drop.php --out=/hors/depot/nom.php [--hours=4] [--token-out=FICHIER]
 *
 * Le nom du fichier doit passer un éventuel verrou .htaccess par liste blanche ; sinon, retirer le
 * verrou d'abord. Le jeton s'affiche une seule fois (ou va dans --token-out quand un agent lance le
 * build : un secret ne passe pas par le chat) ; il n'est stocké nulle part ailleurs.
 */

function bd_fail( string $msg ): void {
	fwrite( STDERR, $msg . "\n" );
	exit( 1 );
}
/** Corps d'une source PHP sans sa balise ouvrante ; refuse toute balise fermante ou inclusion. */
function bd_body( string $path ): string {
	$code = file_get_contents( $path );
	if ( $code === false || strncmp( $code, "<?php", 5 ) !== 0 ) {
		bd_fail( 'Source illisible ou sans balise <?php en tête : ' . $path );
	}
	foreach ( token_get_all( $code ) as $t ) {
		if ( is_array( $t ) && in_array( $t[0], [ T_CLOSE_TAG, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_INLINE_HTML ], true ) ) {
			bd_fail( 'Refus : ' . basename( $path ) . ' contient ' . token_name( $t[0] ) . ' ligne ' . $t[2] . ' (le fichier assemblé n\'inclut rien).' );
		}
	}
	return substr( $code, 5 );
}

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}
$opt = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( ! preg_match( '/^--([a-z-]+)(?:=(.*))?$/s', $a, $m ) ) {
		bd_fail( 'Argument inconnu : ' . $a );
	}
	$opt[ $m[1] ] = $m[2] ?? true;
}
if ( empty( $opt['out'] ) || isset( $opt['help'] ) ) {
	echo "php build-drop.php --out=/hors/depot/nom.php [--hours=4] [--token-out=FICHIER]\n  --hours      durée de vie, 1 à 72 h (défaut 4)\n  --token-out  écrit le jeton dans ce fichier au lieu de l'afficher\n";
	exit( empty( $opt['out'] ) && ! isset( $opt['help'] ) ? 1 : 0 );
}
$hours = (int) ( $opt['hours'] ?? 4 );
if ( $hours < 1 || $hours > 72 ) {
	bd_fail( '--hours doit être compris entre 1 et 72.' );
}
$out = str_replace( '\\', '/', (string) $opt['out'] );
if ( ! preg_match( '/\.php$/', $out ) || ! is_dir( dirname( $out ) ) ) {
	bd_fail( '--out doit désigner un fichier .php dans un dossier existant.' );
}
$home = str_replace( '\\', '/', (string) ( getenv( 'USERPROFILE' ) ?: getenv( 'HOME' ) ) );
$outDir = str_replace( '\\', '/', (string) realpath( dirname( $out ) ) );
if ( $home !== '' && stripos( $outDir . '/', $home . '/.claude/' ) === 0 ) {
	bd_fail( 'Refus : le fichier généré porte une empreinte de jeton, il ne va pas sous ~/.claude.' );
}

$token   = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
$id      = bin2hex( random_bytes( 8 ) );
$expires = time() + $hours * 3600;
$dir     = __DIR__;
$header  = "<?php\n/*\n * Outil d'intervention wp-incident-response, généré le " . gmdate( 'Y-m-d H:i' ) . ' UTC.'
	. "\n * Expire le " . gmdate( 'Y-m-d H:i', $expires ) . " UTC ; il se supprime alors à la première requête."
	. "\n * Accès par jeton (non stocké ici). SUPPRIMER CE FICHIER à la fin de l'intervention.\n */\n"
	. "const WDD_ID = '" . $id . "';\nconst WDD_TOKEN_HASH = '" . hash( 'sha256', $token ) . "';\nconst WDD_EXPIRES = " . $expires . ";\nconst WDD_BUILT = '" . gmdate( 'c' ) . "';\n"
	. "define( 'WD_EMBEDDED', true );\n";
$code = $header
	. "\n// ======================================== detect.php\n" . bd_body( $dir . '/detect.php' )
	. "\n// ======================================== drop/actions.php\n" . bd_body( $dir . '/drop/actions.php' )
	. "\n// ======================================== drop/app.php\n" . bd_body( $dir . '/drop/app.php' )
	. "\n// ======================================== point d'entrée\n"
	. "if ( isset( \$_SERVER['REQUEST_METHOD'] ) ) {\n\twdd_main();\n} else {\n\tfwrite( STDERR, \"Outil web : l'ouvrir dans un navigateur. En ligne de commande, utiliser detect.php.\\n\" );\n\texit( 1 );\n}\n";

if ( file_put_contents( $out, $code ) !== strlen( $code ) ) {
	bd_fail( 'Écriture impossible : ' . $out );
}
// Contrôle de syntaxe du fichier assemblé par le PHP courant (lint, sans l'exécuter).
$lint = [];
exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $out ) . ' 2>&1', $lint, $rc );
if ( $rc !== 0 ) {
	unlink( $out );
	bd_fail( "Fichier assemblé invalide, supprimé :\n" . implode( "\n", $lint ) );
}
echo "Outil généré : $out (" . round( strlen( $code ) / 1024 ) . " Ko)\n";
echo 'Expire le ' . date( 'Y-m-d H:i', $expires ) . " (heure locale de cette machine)\n";
if ( ! empty( $opt['token-out'] ) ) {
	if ( file_put_contents( (string) $opt['token-out'], $token . "\n" ) === false ) {
		unlink( $out );
		bd_fail( 'Jeton non écrit dans ' . $opt['token-out'] . ' : outil supprimé.' );
	}
	@chmod( (string) $opt['token-out'], 0600 );
	echo 'Jeton écrit dans ' . $opt['token-out'] . " (à ouvrir soi-même, jamais à coller dans le chat).\n";
} else {
	echo "Jeton (affiché une seule fois) :\n$token\n";
}
echo "1. Déposer le fichier par FTP à la racine du projet.\n2. L'ouvrir dans le navigateur, saisir le jeton.\n3. Terminer par « Terminer et supprimer l'outil », puis vérifier par FTP qu'il a disparu.\n";
