<?php
/*
 * Outils communs aux tests : assertions qui échouent visiblement, lancement du détecteur
 * dans un processus séparé (jamais inclus pour être exécuté), copie fraîche vérifiée par hash.
 */

final class T {
	public static $pass = 0;
	public static $fail = 0;
	public static $skip = 0;
	public static $log  = [];

	public static function ok( string $id, bool $cond, string $detail = '' ): bool {
		$cond ? self::$pass++ : self::$fail++;
		$line = ( $cond ? 'PASS ' : 'FAIL ' ) . $id . ( $detail !== '' ? ' : ' . $detail : '' );
		self::$log[] = $line;
		echo $line, "\n";
		return $cond;
	}
	public static function skip( string $id, string $why ): void {
		self::$skip++;
		$line = 'SKIP ' . $id . ' : ' . $why;
		self::$log[] = $line;
		echo $line, "\n";
	}
	public static function end(): int {
		echo sprintf( "\n== %d PASS, %d FAIL, %d SKIP (PHP %s) ==\n", self::$pass, self::$fail, self::$skip, PHP_VERSION );
		return self::$fail > 0 ? 1 : 0;
	}
}

function tl_detect_path(): string {
	return dirname( __DIR__ ) . '/scripts/detect.php';
}

/** Lance detect.php avec le PHP courant ; retourne [code retour, JSON décodé, texte]. */
function tl_run_detect( array $args, string $jsonOut ): array {
	$cmd  = array_merge( [ PHP_BINARY, '-d', 'memory_limit=3G', tl_detect_path() ], $args, [ '--json=' . $jsonOut ] );
	$desc = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
	$p    = proc_open( $cmd, $desc, $pipes );
	if ( ! is_resource( $p ) ) {
		throw new RuntimeException( 'proc_open impossible' );
	}
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$rc = proc_close( $p );
	if ( $err !== '' ) {
		echo "stderr du détecteur :\n", substr( $err, 0, 2000 ), "\n";
	}
	$json = is_file( $jsonOut ) ? json_decode( (string) file_get_contents( $jsonOut ), true ) : null;
	return [ $rc, $json, (string) $out ];
}

function tl_findings( array $doc, string $code, ?string $cible = null, ?string $statut = null ): array {
	return array_values(
		array_filter(
			$doc['constats'] ?? [],
			fn( $c ) => $c['code'] === $code && ( $cible === null || $c['cible'] === $cible || @preg_match( $cible, $c['cible'] ) === 1 ) && ( $statut === null || $c['statut'] === $statut )
		)
	);
}
function tl_any( array $doc, callable $fn ): array {
	return array_values( array_filter( $doc['constats'] ?? [], $fn ) );
}
function tl_text( array $findings ): string {
	return implode( "\n", array_map( fn( $c ) => $c['titre'] . "\n" . implode( "\n", $c['preuves'] ), $findings ) );
}

function tl_rrmdir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() && ! $f->isLink() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $dir );
}

/** Inventaire sha256 d'une arborescence (chemins relatifs), avec filtre d'exclusion. */
function tl_manifest( string $root, ?callable $exclude = null ): array {
	$root = rtrim( str_replace( '\\', '/', $root ), '/' );
	$out  = [];
	$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$rel = ltrim( substr( str_replace( '\\', '/', $f->getPathname() ), strlen( $root ) ), '/' );
		if ( $exclude && $exclude( $rel ) ) {
			continue;
		}
		$h = hash_file( 'sha256', $f->getPathname() );
		if ( $h === false ) {
			throw new RuntimeException( 'fichier illisible : ' . $rel );
		}
		$out[ $rel ] = $h;
	}
	ksort( $out );
	return $out;
}

/** Copie fraîche vérifiée : chaque fichier copié doit avoir le même sha256 que sa source. */
function tl_fresh_copy( string $src, string $dst, callable $exclude ): array {
	if ( is_dir( $dst ) ) {
		tl_rrmdir( $dst );
	}
	$src = rtrim( str_replace( '\\', '/', $src ), '/' );
	$man = [];
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $it as $f ) {
		$rel = ltrim( substr( str_replace( '\\', '/', $f->getPathname() ), strlen( $src ) ), '/' );
		if ( $exclude( $rel ) ) {
			continue;
		}
		$target = $dst . '/' . $rel;
		if ( $f->isDir() ) {
			if ( ! is_dir( $target ) && ! mkdir( $target, 0700, true ) ) {
				throw new RuntimeException( 'mkdir impossible : ' . $target );
			}
			continue;
		}
		if ( ! is_dir( dirname( $target ) ) ) {
			mkdir( dirname( $target ), 0700, true );
		}
		if ( ! copy( $f->getPathname(), $target ) ) {
			throw new RuntimeException( 'copie impossible (antivirus ?) : ' . $rel );
		}
		$man[ $rel ] = hash_file( 'sha256', $f->getPathname() );
	}
	ksort( $man );
	return $man;
}

function tl_tmpdir( string $base, string $name ): string {
	$base = rtrim( str_replace( '\\', '/', $base ), '/' );
	$home = str_replace( '\\', '/', (string) ( getenv( 'USERPROFILE' ) ?: getenv( 'HOME' ) ) );
	if ( $home !== '' && stripos( $base, $home . '/.claude' ) === 0 ) {
		throw new RuntimeException( 'Refus : les fixtures ne vont jamais sous ~/.claude (' . $base . ')' );
	}
	$d = $base . '/' . $name;
	if ( is_dir( $d ) ) {
		tl_rrmdir( $d );
	}
	if ( ! mkdir( $d, 0700, true ) ) {
		throw new RuntimeException( 'mkdir impossible : ' . $d );
	}
	return $d;
}
