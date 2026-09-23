<?php
/*
 * drop/actions.php (skill wp-incident-response) : actions de remédiation de l'outil déposé.
 * Chaque action naît d'un constat du détecteur et n'agit que sur les éléments de ce constat :
 * le navigateur ne transmet que des clés choisies dans le plan établi côté serveur, jamais un
 * chemin, une table ou une requête. Avant d'agir : empreinte de l'état analysé (refus si la cible a
 * changé depuis), sauvegarde, puis entrée de journal qui permet l'annulation.
 * Embarqué par build-drop.php avec detect.php (fonctions wd_*) ; aucun code exécuté au chargement.
 */

// Options dont la suppression casse le site : jamais proposées, à retoucher à la main.
const WDA_KEEP_OPTIONS = [ 'siteurl', 'home', 'active_plugins', 'template', 'stylesheet', 'cron', 'user_roles', 'db_version', 'initial_db_version', 'admin_email', 'blogname', 'permalink_structure' ];
const WDA_SALTS = [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ];
const WDA_FILE_CODES = [ 'code.capacite', 'integrite.orphelin', 'integrite.differe', 'emplacement.inattendu', 'code.obfusque', 'code.processus_detache', 'fichier.oublie', 'emplacement.binaire' ];
// Fichiers que la quarantaine ne déplace jamais : le site ou le serveur en dépend, on les retouche.
const WDA_NO_MOVE = '#(^|/)(wp-config\.php|\.htaccess|\.user\.ini|php\.ini|web\.config)$#i';

/* ============================================================ Environnement */

/** Chemin absolu d'une cible relative, seulement si c'est un fichier régulier de la racine hors outil. */
function wda_target( array $env, string $rel ): ?string {
	if ( $rel === '' || strpos( $rel, "\0" ) !== false || preg_match( '#(^|/)\.\.(/|$)#', $rel ) ) {
		return null;
	}
	$abs  = $env['root'] . '/' . ltrim( $rel, '/' );
	$real = realpath( $abs );
	if ( $real === false || is_link( $abs ) || ! is_file( $real ) ) {
		return null;
	}
	$real = wd_norm( $real );
	if ( ! wd_starts( $real, $env['root'] . '/' ) || $real === $env['drop'] || wd_starts( $real, $env['work'] . '/' ) ) {
		return null;
	}
	return $real;
}
function wda_is_core( string $rel ): bool {
	return in_array( $rel, WD_CORE_ROOT_FILES, true ) || wd_starts( $rel, 'wp-admin/' ) || wd_starts( $rel, 'wp-includes/' );
}
function wda_id( string ...$parts ): string {
	return substr( hash( 'sha256', implode( '|', $parts ) ), 0, 12 );
}
/** Écriture atomique (fichier temporaire du même dossier puis renommage). */
function wda_put( string $path, string $data ): bool {
	$tmp = $path . '.wda-' . bin2hex( random_bytes( 4 ) );
	if ( file_put_contents( $tmp, $data, LOCK_EX ) !== strlen( $data ) ) {
		@unlink( $tmp );
		return false;
	}
	if ( is_file( $path ) ) {
		@chmod( $tmp, fileperms( $path ) & 0777 );
	}
	if ( ! rename( $tmp, $path ) ) {
		@unlink( $tmp );
		return false;
	}
	return true;
}
/** Déplacement qui tient entre deux volumes, vérifié par empreinte. */
function wda_move( string $from, string $to ): bool {
	$h = hash_file( 'sha256', $from );
	if ( ! is_dir( dirname( $to ) ) && ! mkdir( dirname( $to ), 0700, true ) ) {
		return false;
	}
	if ( ! @rename( $from, $to ) ) {
		if ( ! copy( $from, $to ) || hash_file( 'sha256', $to ) !== $h || ! unlink( $from ) ) {
			return false;
		}
	}
	return hash_file( 'sha256', $to ) === $h;
}
function wda_backup_dir( array $env, string $exec ): string {
	$d = $env['work'] . '/sauvegardes/' . $exec;
	if ( ! is_dir( $d ) ) {
		mkdir( $d, 0700, true );
	}
	return $d;
}
/** Connexion à la base à partir de wp-config.php, lu par jetons comme le fait le détecteur. */
function wda_db( array $env ): array {
	$R   = new WdReport();
	$cfg = wd_parse_config( $R, $env['root'] );
	if ( $cfg['path'] === '' || $cfg['prefix'] === null ) {
		return [ null, '', 'wp-config.php ou préfixe de table illisible' ];
	}
	[ $my, $why ] = wd_db_connect( [ 'config' => $cfg ] );
	return [ $my, (string) $cfg['prefix'], $why ];
}
/* Valeurs liées par échappement du pilote (i = entier, s = chaîne, null = NULL) : fonctionne sans
 * mysqlnd, absent de certains hébergements PHP 7.4 (pas de get_result). Les noms passent par wd_sql_name(). */
function wda_bind( mysqli $my, string $sql, string $types, array $args ): string {
	$i = 0;
	return preg_replace_callback(
		'/\?/',
		function () use ( $my, $types, $args, &$i ) {
			$v = $args[ $i ] ?? null;
			$t = $types[ $i++ ] ?? 's';
			return $v === null ? 'NULL' : ( $t === 'i' ? (string) (int) $v : "'" . $my->real_escape_string( (string) $v ) . "'" );
		},
		$sql
	);
}
function wda_rows( mysqli $my, string $sql, string $types = '', array $args = [] ): ?array {
	$res = $my->query( wda_bind( $my, $sql, $types, $args ) );
	if ( ! $res instanceof mysqli_result ) {
		return null;
	}
	$out = [];
	while ( $r = $res->fetch_assoc() ) {
		$out[] = $r;
	}
	return $out;
}
function wda_exec( mysqli $my, string $sql, string $types = '', array $args = [] ): int {
	if ( $my->query( wda_bind( $my, $sql, $types, $args ) ) !== true ) {
		throw new RuntimeException( 'requête échouée : ' . $my->error );
	}
	return $my->affected_rows;
}
/** Réinsère des lignes sauvegardées telles quelles (annulation). */
function wda_reinsert( mysqli $my, string $table, array $rows ): void {
	foreach ( $rows as $row ) {
		$cols = array_keys( $row );
		$sql  = 'INSERT INTO ' . wd_sql_name( $table ) . ' (' . implode( ',', array_map( 'wd_sql_name', $cols ) ) . ') VALUES (' . implode( ',', array_fill( 0, count( $cols ), '?' ) ) . ')';
		wda_exec( $my, $sql, str_repeat( 's', count( $cols ) ), array_map( fn( $v ) => $v === null ? null : (string) $v, array_values( $row ) ) );
	}
}
/** Hash inutilisable : aucun mot de passe ne le produit, la connexion exige « mot de passe oublié ». */
function wda_unusable_hash(): string {
	return '$wda$verrouille$' . bin2hex( random_bytes( 24 ) );
}
function wda_salt(): string {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#%^&*()-_=+[]{}<>~;:,.|/?';
	$out   = '';
	for ( $i = 0; $i < 64; $i++ ) {
		$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}
/** Positions des littéraux de sels dans wp-config.php : [nom => [octet de début, longueur]]. */
function wda_salt_literals( string $code ): array {
	$T   = token_get_all( $code );
	$pos = 0;
	$off = [];
	foreach ( $T as $i => $t ) {
		$off[ $i ] = $pos;
		$pos      += strlen( is_array( $t ) ? $t[1] : $t );
	}
	$sig = [];
	foreach ( $T as $i => $t ) {
		if ( is_array( $t ) && $t[0] !== T_WHITESPACE && $t[0] !== T_COMMENT && $t[0] !== T_DOC_COMMENT ) {
			$sig[] = $i;
		} elseif ( ! is_array( $t ) ) {
			$sig[] = $i;
		}
	}
	$out = [];
	$n   = count( $sig );
	for ( $k = 0; $k + 5 < $n; $k++ ) {
		$a = $T[ $sig[ $k ] ];
		if ( ! is_array( $a ) || $a[0] !== T_STRING || strtolower( $a[1] ) !== 'define' || $T[ $sig[ $k + 1 ] ] !== '(' ) {
			continue;
		}
		$name = $T[ $sig[ $k + 2 ] ];
		$val  = $T[ $sig[ $k + 4 ] ];
		if ( ! is_array( $name ) || $name[0] !== T_CONSTANT_ENCAPSED_STRING || $T[ $sig[ $k + 3 ] ] !== ',' || ! is_array( $val ) || $val[0] !== T_CONSTANT_ENCAPSED_STRING || $T[ $sig[ $k + 5 ] ] !== ')' ) {
			continue;
		}
		$n2 = substr( $name[1], 1, -1 );
		if ( in_array( $n2, WDA_SALTS, true ) ) {
			$out[ $n2 ] = [ $off[ $sig[ $k + 4 ] ], strlen( $val[1] ) ];
		}
	}
	return $out;
}

/* ============================================================ Plan : actions pertinentes pour ce rapport */

/**
 * Établit les actions à partir des constats. $env : root, drop, work (chemins normalisés).
 * Rend [id => action] ; chaque action porte ses éléments cochables (items) et rien d'autre.
 */
function wda_plan( array $doc, array $env ): array {
	$root  = $env['root'];
	$info  = $doc['contexte']['racines'][ $root ] ?? [];
	$isWp  = ( $info['type'] ?? '' ) === 'wordpress';
	$plan  = [];
	$add   = function ( string $type, string $key, string $titre, array $base ) use ( &$plan ) {
		$id = wda_id( $type, $key );
		if ( ! isset( $plan[ $id ] ) ) {
			$plan[ $id ] = $base + [ 'id' => $id, 'type' => $type, 'titre' => $titre, 'items' => [] ];
		}
		return $id;
	};
	$anyConf  = false;
	$accounts = false;
	foreach ( $doc['constats'] as $c ) {
		$anyConf  = $anyConf || $c['statut'] === WD_CONF;
		$accounts = $accounts || wd_starts( $c['code'], 'comptes.' );
		$item     = [ 'statut' => $c['statut'], 'code' => $c['code'], 'titre' => $c['titre'], 'preuves' => array_slice( $c['preuves'], 0, 4 ) ];

		if ( in_array( $c['code'], WDA_FILE_CODES, true ) && ! preg_match( WDA_NO_MOVE, $c['cible'] ) ) {
			$abs = wda_target( $env, $c['cible'] );
			if ( $abs === null ) {
				continue;
			}
			if ( $isWp && wda_is_core( $c['cible'] ) ) {
				// Un fichier du cœur ne part pas en quarantaine (le site tomberait) : il se remplace par l'officiel.
				if ( $c['code'] === 'integrite.differe' || $c['code'] === 'code.capacite' ) {
					$id = $add( 'restaurer_coeur', '', 'Remplacer des fichiers du cœur par la version officielle', [ 'version' => (string) ( $info['wordpress'] ?? '' ), 'locale' => preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z]+)?$/', (string) ( $info['langue'] ?? '' ) ) ? (string) $info['langue'] : 'en_US' ] );
					$plan[ $id ]['items'][ $c['cible'] ] = ( $plan[ $id ]['items'][ $c['cible'] ] ?? [ 'sha256' => hash_file( 'sha256', $abs ), 'constats' => [] ] );
					$plan[ $id ]['items'][ $c['cible'] ]['constats'][] = $item;
				}
				continue;
			}
			$id = $add( 'quarantaine', '', 'Mettre des fichiers en quarantaine (hors d\'atteinte, restaurables)', [] );
			$plan[ $id ]['items'][ $c['cible'] ] = ( $plan[ $id ]['items'][ $c['cible'] ] ?? [ 'sha256' => hash_file( 'sha256', $abs ), 'constats' => [], 'composant' => wd_component_of( $c['cible'] ) ] );
			$plan[ $id ]['items'][ $c['cible'] ]['constats'][] = $item;
			continue;
		}

		if ( ! empty( $c['lignes'] ) && preg_match( '#(^|/)(\.htaccess|\.user\.ini|php\.ini)$#i', $c['cible'] ) ) {
			$abs = wda_target( $env, $c['cible'] );
			if ( $abs === null ) {
				continue;
			}
			$id = $add( 'regles_serveur', $c['cible'], 'Retirer des règles de ' . $c['cible'], [ 'fichier' => $c['cible'], 'sha256' => hash_file( 'sha256', $abs ) ] );
			foreach ( $c['lignes'] as $r ) {
				$a = (int) $r[0];
				$b = max( $a, (int) $r[1] );
				if ( $a < 1 ) {
					continue;
				}
				$k = $a . '-' . $b;
				$plan[ $id ]['items'][ $k ] = ( $plan[ $id ]['items'][ $k ] ?? [ 'de' => $a, 'a' => $b, 'constats' => [] ] );
				$plan[ $id ]['items'][ $k ]['constats'][] = $item;
			}
			continue;
		}

		if ( ! empty( $c['ligne']['table'] ) && $isWp ) {
			$l      = $c['ligne'];
			$prefix = (string) ( $info['prefixe'] ?? '' );
			// Une option se juge par son nom : sans nom (autre clé), on ne sait pas si elle est vitale, on ne propose rien.
			if ( $l['table'] === $prefix . 'options' && ( $l['cle'] !== 'option_name' || in_array( preg_replace( '/^' . preg_quote( $prefix, '/' ) . '/', '', (string) $l['valeur'] ), WDA_KEEP_OPTIONS, true ) ) ) {
				continue;
			}
			$id = $add( 'lignes_base', '', 'Supprimer des lignes de la base (sauvegardées, réinsérables)', [] );
			$k  = $l['table'] . '|' . $l['cle'] . '|' . $l['valeur'];
			$plan[ $id ]['items'][ $k ] = ( $plan[ $id ]['items'][ $k ] ?? [ 'table' => (string) $l['table'], 'cle' => (string) $l['cle'], 'valeur' => (string) $l['valeur'], 'constats' => [] ] );
			$plan[ $id ]['items'][ $k ]['constats'][] = $item;
			continue;
		}

		if ( in_array( $c['code'], [ 'comptes.suspects', 'comptes.fenetre' ], true ) && ! empty( $c['ids'] ) && $isWp ) {
			$id    = $add( 'comptes', '', 'Neutraliser des comptes (plus de rôle, plus de connexion, restaurable)', [] );
			$label = [];
			foreach ( $c['preuves'] as $p ) {
				if ( preg_match( '/^#(\d+) (.*)$/', $p, $m ) ) {
					$label[ (int) $m[1] ] = $m[2];
				}
			}
			foreach ( $c['ids'] as $uid ) {
				$plan[ $id ]['items'][ (string) (int) $uid ] = [ 'statut' => $c['statut'], 'libelle' => $label[ (int) $uid ] ?? ( 'compte #' . (int) $uid ), 'constats' => [ $item ] ];
			}
			continue;
		}

		if ( $c['code'] === 'comptes.mot_de_passe_application' && ! empty( $c['ids'] ) && $isWp ) {
			$id = $add( 'mots_de_passe_application', '', 'Révoquer des mots de passe d\'application', [] );
			foreach ( $c['ids'] as $uid ) {
				$plan[ $id ]['items'][ (string) (int) $uid ] = [ 'statut' => $c['statut'], 'libelle' => implode( ', ', array_slice( $c['preuves'], 0, 1 ) ), 'constats' => [ $item ] ];
			}
		}
	}

	// Actions globales : seulement quand un piratage est avéré ou que des comptes sont en cause.
	if ( $isWp && ( $anyConf || $accounts ) ) {
		$cfg = (string) ( $info['wp_config'] ?? '' );
		$add( 'sessions', '', 'Déconnecter tous les comptes (sessions ouvertes supprimées)', [ 'items' => [ 'tout' => [ 'libelle' => 'toutes les sessions de tous les comptes' ] ] ] );
		$add( 'mots_de_passe', '', 'Invalider les mots de passe (réinitialisation obligatoire par e-mail)', [ 'items' => [ 'tout' => [ 'libelle' => 'tous les comptes, sauf ceux listés à conserver' ] ] ] );
		if ( $cfg !== '' && is_file( $cfg ) && count( wda_salt_literals( (string) file_get_contents( $cfg ) ) ) === count( WDA_SALTS ) ) {
			$add( 'cles', '', 'Régénérer les clés et sels de wp-config.php (invalide tous les cookies)', [ 'fichier' => $cfg, 'items' => [ 'tout' => [ 'libelle' => 'les ' . count( WDA_SALTS ) . ' clés et sels' ] ] ] );
		}
	}
	foreach ( $plan as $id => $a ) {
		if ( $a['type'] === 'regles_serveur' ) {
			// Une plage contenue dans une plage plus large du même fichier ferait un doublon à cocher.
			foreach ( $a['items'] as $k => $it ) {
				foreach ( $a['items'] as $k2 => $o ) {
					if ( $k !== $k2 && $o['de'] <= $it['de'] && $o['a'] >= $it['a'] && ( $o['a'] - $o['de'] ) > ( $it['a'] - $it['de'] ) ) {
						$plan[ $id ]['items'][ $k2 ]['constats'] = array_merge( $o['constats'], $it['constats'] );
						unset( $plan[ $id ]['items'][ $k ] );
						break;
					}
				}
			}
			$a = $plan[ $id ];
		}
		if ( ! $a['items'] ) {
			unset( $plan[ $id ] );
		}
	}
	return $plan;
}

/* ============================================================ Aperçu : ce qui va changer, lu maintenant */

function wda_preview( array $a, array $env ): array {
	$out = [ 'avertissements' => [], 'details' => [] ];
	switch ( $a['type'] ) {
		case 'quarantaine':
			foreach ( $a['items'] as $rel => $it ) {
				$abs = wda_target( $env, $rel );
				$out['details'][ $rel ] = $abs === null ? 'absent ou déjà déplacé' : ( hash_file( 'sha256', $abs ) === $it['sha256'] ? filesize( $abs ) . ' octets, inchangé depuis l\'analyse' : 'MODIFIÉ depuis l\'analyse : relancer l\'analyse' );
				if ( ( $it['composant'] ?? '' ) !== '' && ( $it['composant'] ?? '' ) !== 'racine' ) {
					$out['details'][ $rel ] .= ' ; composant ' . $it['composant'] . ' : il peut cesser de fonctionner, le réinstaller depuis sa source officielle';
				}
			}
			$out['avertissements'][] = 'Destination : ' . $env['work'] . '/quarantaine (extension .quarantaine, jamais exécutée).';
			break;
		case 'restaurer_coeur':
			$out['avertissements'][] = 'Source : archive officielle WordPress ' . $a['version'] . ' (' . $a['locale'] . '), chaque fichier vérifié contre le manifeste officiel avant écriture.';
			foreach ( $a['items'] as $rel => $it ) {
				$abs = wda_target( $env, $rel );
				$out['details'][ $rel ] = $abs === null ? 'absent' : ( hash_file( 'sha256', $abs ) === $it['sha256'] ? 'inchangé depuis l\'analyse' : 'MODIFIÉ depuis l\'analyse : relancer l\'analyse' );
			}
			break;
		case 'regles_serveur':
			$abs = wda_target( $env, $a['fichier'] );
			if ( $abs === null || hash_file( 'sha256', $abs ) !== $a['sha256'] ) {
				$out['avertissements'][] = 'Le fichier a changé depuis l\'analyse (réécriture ?) : relancer l\'analyse avant de retirer quoi que ce soit.';
				break;
			}
			$lines = preg_split( '/\r\n|\n|\r/', (string) file_get_contents( $abs ) );
			foreach ( $a['items'] as $k => $it ) {
				$out['details'][ $k ] = implode( "\n", array_map( fn( $n ) => 'L' . $n . '  ' . wd_redact( $lines[ $n - 1 ] ?? '' ), range( $it['de'], min( $it['a'], $it['de'] + 40 ) ) ) );
			}
			break;
		case 'lignes_base':
			[ $my, , $why ] = wda_db( $env );
			if ( ! $my ) {
				$out['avertissements'][] = 'Base inaccessible : ' . $why;
				break;
			}
			foreach ( $a['items'] as $k => $it ) {
				$rows = wda_rows( $my, 'SELECT * FROM ' . wd_sql_name( $it['table'] ) . ' WHERE ' . wd_sql_name( $it['cle'] ) . ' = ? LIMIT 2', 's', [ $it['valeur'] ] );
				$out['details'][ $k ] = $rows === null ? 'lecture impossible' : ( ! $rows ? 'ligne absente (déjà supprimée ?)' : ( count( $rows ) > 1 ? 'clé non unique : refusé' : wd_clean( json_encode( $rows[0], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE ) ) ) );
			}
			$out['avertissements'][] = 'La ligne entière est supprimée. Si elle mêle du contenu légitime (réglage de widget, texte), faire la retouche à la main.';
			break;
		case 'comptes':
			$out['avertissements'][] = 'Chaque compte coché perd son rôle sur tous les sites, ses sessions et ses mots de passe d\'application ; son mot de passe devient inutilisable. Ses contenus restent. Supprimer ensuite le compte depuis l\'administration, avec réattribution des contenus.';
			break;
		case 'mots_de_passe':
			$out['avertissements'][] = 'Chaque compte non conservé devra passer par « Mot de passe oublié » : vérifier d\'abord que le site envoie bien les e-mails. Garder au moins un administrateur légitime.';
			break;
		case 'cles':
			$out['avertissements'][] = 'wp-config.php est sauvegardé ; seules les ' . count( WDA_SALTS ) . ' valeurs de clés changent. Toutes les sessions et tous les cookies deviennent invalides.';
			break;
	}
	return $out;
}

/* ============================================================ Exécution et annulation */

/**
 * Exécute les éléments choisis d'une action. $keys est filtré contre le plan : une clé absente du
 * plan est refusée, jamais interprétée. Rend [messages, entrées de journal].
 */
function wda_execute( array $a, array $keys, array $input, array $env ): array {
	$keys = array_values( array_intersect( array_map( 'strval', $keys ), array_map( 'strval', array_keys( $a['items'] ) ) ) );
	if ( ! $keys ) {
		return [ [ 'REFUS : aucun élément valide coché, rien n\'a été fait.' ], [] ];
	}
	$exec = gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 3 ) );
	$msgs = [];
	$log  = [];
	switch ( $a['type'] ) {
		case 'quarantaine':
			foreach ( $keys as $rel ) {
				$abs = wda_target( $env, $rel );
				if ( $abs === null || hash_file( 'sha256', $abs ) !== $a['items'][ $rel ]['sha256'] ) {
					$msgs[] = 'REFUS ' . $rel . ' : absent ou modifié depuis l\'analyse.';
					continue;
				}
				$dest = $env['work'] . '/quarantaine/' . $exec . '/' . $rel . '.quarantaine';
				if ( ! wda_move( $abs, $dest ) ) {
					$msgs[] = 'ÉCHEC ' . $rel . ' : déplacement impossible (droits ?).';
					continue;
				}
				$msgs[] = 'Quarantaine : ' . $rel;
				$log[]  = [ 'type' => 'quarantaine', 'cible' => $rel, 'dest' => $dest, 'sha256' => $a['items'][ $rel ]['sha256'] ];
			}
			break;

		case 'restaurer_coeur':
			[ $zip, $sums, $why ] = wda_core_source( $a['version'], $a['locale'], $env );
			if ( $zip === null ) {
				return [ [ 'ÉCHEC : ' . $why ], [] ];
			}
			$bak = wda_backup_dir( $env, $exec );
			foreach ( $keys as $rel ) {
				$abs = wda_target( $env, $rel );
				$new = $zip->getFromName( 'wordpress/' . $rel );
				if ( $abs === null || hash_file( 'sha256', $abs ) !== $a['items'][ $rel ]['sha256'] ) {
					$msgs[] = 'REFUS ' . $rel . ' : absent ou modifié depuis l\'analyse.';
				} elseif ( $new === false || ! isset( $sums[ $rel ] ) || ! in_array( md5( $new ), (array) $sums[ $rel ], true ) ) {
					$msgs[] = 'REFUS ' . $rel . ' : la version officielle ne correspond pas au manifeste, rien n\'est écrit.';
				} elseif ( ! copy( $abs, $bak . '/' . md5( $rel ) . '.bak' ) ) {
					$msgs[] = 'ÉCHEC ' . $rel . ' : sauvegarde impossible, rien n\'est écrit.';
				} elseif ( ! wda_put( $abs, $new ) ) {
					$msgs[] = 'ÉCHEC ' . $rel . ' : écriture impossible (droits ?).';
				} else {
					$msgs[] = 'Restauré depuis l\'officiel : ' . $rel;
					$log[]  = [ 'type' => 'fichier_remplace', 'cible' => $rel, 'sauvegarde' => $bak . '/' . md5( $rel ) . '.bak', 'sha256_apres' => hash( 'sha256', $new ) ];
				}
			}
			$zip->close();
			break;

		case 'regles_serveur':
			$abs = wda_target( $env, $a['fichier'] );
			if ( $abs === null || hash_file( 'sha256', $abs ) !== $a['sha256'] ) {
				return [ [ 'REFUS : ' . $a['fichier'] . ' a changé depuis l\'analyse. Relancer l\'analyse (une réécriture est un signe de persistance).' ], [] ];
			}
			$raw   = (string) file_get_contents( $abs );
			$lines = preg_split( '/(?<=\n)/', $raw );
			$drop  = [];
			foreach ( $keys as $k ) {
				for ( $n = $a['items'][ $k ]['de']; $n <= $a['items'][ $k ]['a']; $n++ ) {
					$drop[ $n - 1 ] = true;
				}
			}
			$kept = [];
			foreach ( $lines as $i => $l ) {
				if ( ! isset( $drop[ $i ] ) ) {
					$kept[] = $l;
				}
			}
			$bak = wda_backup_dir( $env, $exec ) . '/' . md5( $a['fichier'] ) . '.bak';
			if ( file_put_contents( $bak, $raw ) !== strlen( $raw ) ) {
				return [ [ 'ÉCHEC : sauvegarde impossible, rien n\'est modifié.' ], [] ];
			}
			$new = implode( '', $kept );
			if ( ! wda_put( $abs, $new ) ) {
				return [ [ 'ÉCHEC : écriture de ' . $a['fichier'] . ' impossible.' ], [] ];
			}
			$msgs[] = count( $drop ) . ' ligne(s) retirée(s) de ' . $a['fichier'] . ' (' . implode( ', ', $keys ) . ').';
			$log[]  = [ 'type' => 'fichier_remplace', 'cible' => $a['fichier'], 'sauvegarde' => $bak, 'sha256_apres' => hash( 'sha256', $new ) ];
			break;

		case 'lignes_base':
			[ $my, , $why ] = wda_db( $env );
			if ( ! $my ) {
				return [ [ 'ÉCHEC : base inaccessible (' . $why . ').' ], [] ];
			}
			foreach ( $keys as $k ) {
				$it   = $a['items'][ $k ];
				$rows = wda_rows( $my, 'SELECT * FROM ' . wd_sql_name( $it['table'] ) . ' WHERE ' . wd_sql_name( $it['cle'] ) . ' = ? LIMIT 2', 's', [ $it['valeur'] ] );
				if ( $rows === null || count( $rows ) !== 1 ) {
					$msgs[] = 'REFUS ' . $k . ' : ligne absente, illisible ou clé non unique.';
					continue;
				}
				try {
					wda_exec( $my, 'DELETE FROM ' . wd_sql_name( $it['table'] ) . ' WHERE ' . wd_sql_name( $it['cle'] ) . ' = ? LIMIT 1', 's', [ $it['valeur'] ] );
				} catch ( Throwable $e ) {
					$msgs[] = 'ÉCHEC ' . $k . ' : ' . $e->getMessage();
					continue;
				}
				$msgs[] = 'Ligne supprimée : ' . $it['table'] . ' ' . $it['cle'] . '=' . $it['valeur'];
				$log[]  = [ 'type' => 'lignes', 'cible' => $k, 'lignes' => [ $it['table'] => $rows ] ];
			}
			break;

		case 'comptes':
		case 'mots_de_passe_application':
			[ $my, $prefix, $why ] = wda_db( $env );
			if ( ! $my ) {
				return [ [ 'ÉCHEC : base inaccessible (' . $why . ').' ], [] ];
			}
			$metaRe = $a['type'] === 'comptes'
				? '^' . preg_quote( $prefix, '/' ) . '([0-9]+_)?(capabilities|user_level)$|^session_tokens$|^_application_passwords$'
				: '^_application_passwords$';
			foreach ( $keys as $uid ) {
				$u    = (int) $uid;
				$user = wda_rows( $my, 'SELECT * FROM ' . wd_sql_name( $prefix . 'users' ) . ' WHERE ID = ?', 'i', [ $u ] );
				$meta = wda_rows( $my, 'SELECT * FROM ' . wd_sql_name( $prefix . 'usermeta' ) . ' WHERE user_id = ? AND meta_key REGEXP ?', 'is', [ $u, $metaRe ] );
				if ( ! $user || $meta === null ) {
					$msgs[] = 'REFUS compte #' . $u . ' : introuvable.';
					continue;
				}
				try {
					$my->begin_transaction();
					wda_exec( $my, 'DELETE FROM ' . wd_sql_name( $prefix . 'usermeta' ) . ' WHERE user_id = ? AND meta_key REGEXP ?', 'is', [ $u, $metaRe ] );
					if ( $a['type'] === 'comptes' ) {
						wda_exec( $my, 'UPDATE ' . wd_sql_name( $prefix . 'users' ) . ' SET user_pass = ?, user_activation_key = \'\' WHERE ID = ?', 'si', [ wda_unusable_hash(), $u ] );
					}
					$my->commit();
				} catch ( Throwable $e ) {
					$my->rollback();
					$msgs[] = 'ÉCHEC compte #' . $u . ' : ' . $e->getMessage();
					continue;
				}
				$msgs[] = ( $a['type'] === 'comptes' ? 'Compte neutralisé : #' : 'Mots de passe d\'application révoqués : #' ) . $u . ' ' . $user[0]['user_login'];
				$log[]  = [ 'type' => 'compte', 'cible' => '#' . $u, 'user_id' => $u, 'user_pass' => $a['type'] === 'comptes' ? $user[0]['user_pass'] : null, 'activation' => $user[0]['user_activation_key'], 'lignes' => [ $prefix . 'usermeta' => $meta ] ];
			}
			break;

		case 'sessions':
		case 'mots_de_passe':
			[ $my, $prefix, $why ] = wda_db( $env );
			if ( ! $my ) {
				return [ [ 'ÉCHEC : base inaccessible (' . $why . ').' ], [] ];
			}
			$keep = array_filter( array_map( 'trim', explode( ',', (string) ( $input['conserver'] ?? '' ) ) ), 'strlen' );
			if ( $a['type'] === 'mots_de_passe' && ! $keep ) {
				return [ [ 'REFUS : indiquer au moins un identifiant à conserver (un administrateur légitime), sinon plus personne ne peut se connecter si les e-mails ne partent pas.' ], [] ];
			}
			try {
				if ( $a['type'] === 'sessions' ) {
					$rows = wda_rows( $my, 'SELECT * FROM ' . wd_sql_name( $prefix . 'usermeta' ) . ' WHERE meta_key = \'session_tokens\'' );
					wda_exec( $my, 'DELETE FROM ' . wd_sql_name( $prefix . 'usermeta' ) . ' WHERE meta_key = \'session_tokens\'' );
					$msgs[] = count( (array) $rows ) . ' compte(s) déconnecté(s).';
					$log[]  = [ 'type' => 'lignes', 'cible' => 'sessions', 'lignes' => [ $prefix . 'usermeta' => (array) $rows ] ];
				} else {
					$users = (array) wda_rows( $my, 'SELECT ID, user_login, user_pass, user_activation_key FROM ' . wd_sql_name( $prefix . 'users' ) );
					$known = array_column( $users, 'user_login' );
					$miss  = array_diff( $keep, $known );
					if ( $miss ) {
						return [ [ 'REFUS : identifiant(s) à conserver inconnu(s) : ' . implode( ', ', $miss ) . '.' ], [] ];
					}
					$saved = [];
					$my->begin_transaction();
					foreach ( $users as $u ) {
						if ( in_array( $u['user_login'], $keep, true ) ) {
							continue;
						}
						wda_exec( $my, 'UPDATE ' . wd_sql_name( $prefix . 'users' ) . ' SET user_pass = ?, user_activation_key = \'\' WHERE ID = ?', 'si', [ wda_unusable_hash(), (int) $u['ID'] ] );
						$saved[] = $u;
					}
					$my->commit();
					$msgs[] = count( $saved ) . ' mot(s) de passe invalidé(s) ; conservé(s) : ' . implode( ', ', $keep ) . '.';
					$log[]  = [ 'type' => 'mots_de_passe', 'cible' => 'tous sauf ' . implode( ', ', $keep ), 'comptes' => $saved ];
				}
			} catch ( Throwable $e ) {
				$my->rollback();
				return [ [ 'ÉCHEC : ' . $e->getMessage() . ' (rien n\'est modifié pour les mots de passe, transaction annulée).' ], [] ];
			}
			break;

		case 'cles':
			$cfg = $a['fichier'];
			$raw = is_file( $cfg ) ? (string) file_get_contents( $cfg ) : '';
			$pos = wda_salt_literals( $raw );
			if ( count( $pos ) !== count( WDA_SALTS ) ) {
				return [ [ 'REFUS : les clés de wp-config.php ne sont plus des littéraux simples, retouche manuelle.' ], [] ];
			}
			uasort( $pos, fn( $x, $y ) => $y[0] <=> $x[0] );
			$new = $raw;
			foreach ( $pos as [ $at, $len ] ) {
				$new = substr_replace( $new, "'" . wda_salt() . "'", $at, $len );
			}
			if ( count( wda_salt_literals( $new ) ) !== count( WDA_SALTS ) || count( token_get_all( $new ) ) !== count( token_get_all( $raw ) ) ) {
				return [ [ 'REFUS : contrôle de structure du nouveau wp-config.php échoué, rien n\'est écrit.' ], [] ];
			}
			$bak = wda_backup_dir( $env, $exec ) . '/wp-config.php.bak';
			if ( file_put_contents( $bak, $raw ) !== strlen( $raw ) || ! wda_put( $cfg, $new ) ) {
				return [ [ 'ÉCHEC : sauvegarde ou écriture de wp-config.php impossible.' ], [] ];
			}
			$msgs[] = 'Clés et sels régénérés dans wp-config.php.';
			$log[]  = [ 'type' => 'fichier_remplace', 'cible' => 'wp-config.php', 'chemin' => $cfg, 'sauvegarde' => $bak, 'sha256_apres' => hash( 'sha256', $new ) ];
			break;
	}
	foreach ( $log as &$e ) {
		$e += [ 'action' => $a['id'], 'titre' => $a['titre'], 'execution' => $exec, 'date' => gmdate( 'c' ), 'annule' => false ];
	}
	unset( $e );
	return [ $msgs, $log ];
}

/** Archive officielle du cœur (mise en cache dans le dossier de travail) et son manifeste md5. */
function wda_core_source( string $version, string $locale, array $env ): array {
	if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $version ) ) {
		return [ null, [], 'version de WordPress illisible' ];
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		return [ null, [], 'extension zip absente : remplacer ces fichiers à la main depuis l\'archive officielle' ];
	}
	$R = new WdReport();
	[ $st, $j ] = wd_json_get( $R, 'https://api.wordpress.org/core/checksums/1.0/?version=' . rawurlencode( $version ) . '&locale=' . rawurlencode( $locale ) );
	if ( $st !== 'ok' || empty( $j['checksums'] ) ) {
		$locale = 'en_US';
		[ $st, $j ] = wd_json_get( $R, 'https://api.wordpress.org/core/checksums/1.0/?version=' . rawurlencode( $version ) . '&locale=en_US' );
	}
	if ( $st !== 'ok' || empty( $j['checksums'] ) ) {
		return [ null, [], 'manifeste officiel injoignable ou absent' ];
	}
	$cache = $env['work'] . '/cache/wordpress-' . $version . '-' . $locale . '.zip';
	if ( ! is_file( $cache ) ) {
		$url = 'https://downloads.wordpress.org/release/' . ( $locale === 'en_US' ? '' : rawurlencode( $locale ) . '/' ) . 'wordpress-' . rawurlencode( $version ) . '.zip';
		[ $code, $body ] = wd_http( $R, $url, [], 120 );
		if ( $code !== 200 || $body === '' ) {
			return [ null, [], 'archive officielle non téléchargée (' . $url . ', HTTP ' . $code . ')' ];
		}
		if ( ! is_dir( dirname( $cache ) ) ) {
			mkdir( dirname( $cache ), 0700, true );
		}
		file_put_contents( $cache, $body );
	}
	$zip = new ZipArchive();
	if ( $zip->open( $cache ) !== true ) {
		@unlink( $cache );
		return [ null, [], 'archive officielle illisible' ];
	}
	return [ $zip, $j['checksums'], '' ];
}

/** Annule une entrée du journal, seulement si la cible est restée dans l'état laissé par l'action. */
function wda_undo( array $e, array $env ): string {
	switch ( $e['type'] ) {
		case 'quarantaine':
			$dst = $env['root'] . '/' . $e['cible'];
			if ( file_exists( $dst ) ) {
				return 'REFUS : un fichier occupe de nouveau ' . $e['cible'] . ' (réinfection ?). Rien n\'est restauré.';
			}
			if ( ! is_file( $e['dest'] ) || hash_file( 'sha256', $e['dest'] ) !== $e['sha256'] ) {
				return 'REFUS : fichier en quarantaine absent ou modifié.';
			}
			return wda_move( $e['dest'], $dst ) ? 'Restauré : ' . $e['cible'] : 'ÉCHEC : déplacement impossible.';
		case 'fichier_remplace':
			$abs = $e['chemin'] ?? ( $env['root'] . '/' . $e['cible'] );
			if ( ! is_file( $abs ) || hash_file( 'sha256', $abs ) !== $e['sha256_apres'] ) {
				return 'REFUS : ' . $e['cible'] . ' a changé depuis l\'action. Rien n\'est restauré.';
			}
			$old = is_file( $e['sauvegarde'] ) ? file_get_contents( $e['sauvegarde'] ) : false;
			return $old !== false && wda_put( $abs, $old ) ? 'Restauré : ' . $e['cible'] : 'ÉCHEC : sauvegarde illisible ou écriture impossible.';
		case 'lignes':
		case 'compte':
		case 'mots_de_passe':
			[ $my, $prefix, $why ] = wda_db( $env );
			if ( ! $my ) {
				return 'ÉCHEC : base inaccessible (' . $why . ').';
			}
			try {
				$my->begin_transaction();
				foreach ( $e['lignes'] ?? [] as $table => $rows ) {
					wda_reinsert( $my, $table, $rows );
				}
				if ( $e['type'] === 'compte' && $e['user_pass'] !== null ) {
					wda_exec( $my, 'UPDATE ' . wd_sql_name( $prefix . 'users' ) . ' SET user_pass = ?, user_activation_key = ? WHERE ID = ?', 'ssi', [ $e['user_pass'], (string) $e['activation'], (int) $e['user_id'] ] );
				}
				foreach ( $e['comptes'] ?? [] as $u ) {
					wda_exec( $my, 'UPDATE ' . wd_sql_name( $prefix . 'users' ) . ' SET user_pass = ?, user_activation_key = ? WHERE ID = ?', 'ssi', [ $u['user_pass'], (string) $u['user_activation_key'], (int) $u['ID'] ] );
				}
				$my->commit();
			} catch ( Throwable $x ) {
				$my->rollback();
				return 'ÉCHEC : ' . $x->getMessage() . ' (transaction annulée, rien n\'est restauré).';
			}
			return 'Restauré en base : ' . $e['cible'];
	}
	return 'REFUS : type d\'entrée inconnu.';
}
