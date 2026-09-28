<?php
/**
 * Phase test runner. Exit 1 when any assertion fails.
 *
 * Usage: php tests/run.php 1
 *
 * @package Mc_Functionality
 */

$phase      = isset( $argv[1] ) ? (string) $argv[1] : '';
$root       = dirname( __DIR__ );
$failed     = 0;
$plugin_dir = $root;

require_once $plugin_dir . '/includes/class-mc-functionality-snippet-store.php';
require_once $plugin_dir . '/includes/class-mc-functionality-snippet-loader.php';
require_once $plugin_dir . '/includes/class-mc-functionality-snippet-meta.php';
require_once $plugin_dir . '/includes/class-mc-functionality-snippet-migration.php';
require_once $plugin_dir . '/includes/class-mc-functionality-snippet-zip.php';
require_once $plugin_dir . '/includes/class-mc-functionality-standalone.php';

/**
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function mc_assert( $condition, $message ) {
	global $failed;
	if ( $condition ) {
		fwrite( STDOUT, "PASS {$message}\n" );
		return;
	}
	$failed++;
	fwrite( STDERR, "FAIL {$message}\n" );
}

/**
 * @return void
 */
function mc_run_phase1() {
	global $failed, $plugin_dir;

$temp = sys_get_temp_dir() . '/mc-functionality-phase1-' . getmypid();
if ( is_dir( $temp ) ) {
	mc_rrmdir( $temp );
}
mkdir( $temp, 0755, true );
file_put_contents( $temp . '/index.php', "<?php\n// silence\n" );
file_put_contents( $temp . '/enabled.php', "<?php\nfunction mc_phase1_enabled_marker() { return true; }\n" );
file_put_contents( $temp . '/skipped.php.disabled', "<?php\nfunction mc_phase1_disabled_marker() { return true; }\n" );

$outside = sys_get_temp_dir() . '/mc-functionality-outside-' . getmypid() . '.php';
file_put_contents( $outside, "<?php\nfunction mc_phase1_outside_marker() { return true; }\n" );

$loader = new Mc_Functionality_Snippet_Loader( $temp );
$loader->load_snippets();

mc_assert( function_exists( 'mc_phase1_enabled_marker' ), 'enabled php file is included' );
mc_assert( ! function_exists( 'mc_phase1_disabled_marker' ), 'disabled php file is not included' );
mc_assert( ! $loader->store()->is_inside( $outside ), 'path outside the temp dir is rejected' );
mc_assert( ! function_exists( 'mc_phase1_outside_marker' ), 'outside file is not included' );

$child_fatal = mc_phase1_child( 'fatal', $plugin_dir );

mc_assert( 0 !== $child_fatal['exit'], 'fatal child exits non-zero' );
mc_assert( ! is_file( $child_fatal['dir'] . '/boom.php' ), 'fatal snippet is renamed off' );
mc_assert( is_file( $child_fatal['dir'] . '/boom.php.disabled' ), 'fatal snippet has .disabled name' );
$error_note = is_file( $child_fatal['dir'] . '/boom.php.error' ) ? file_get_contents( $child_fatal['dir'] . '/boom.php.error' ) : '';
mc_assert( false !== strpos( $error_note, 'on line' ), 'error sidecar records a line' );
mc_assert( false !== strpos( $error_note, (string) $child_fatal['helper_line'] ), 'error sidecar line is the helper line, not only the snippet file' );

$child_notice = mc_phase1_child( 'notice', $plugin_dir );
mc_assert( 0 === $child_notice['exit'], 'notice child exits 0' );
mc_assert( is_file( $child_notice['dir'] . '/note.php' ), 'notice does not rename the snippet' );

$lint = mc_phase1_lint( $plugin_dir );
mc_assert( 0 === $lint, 'php lint accepts get_bloginfo and rejects eval' );

mc_rrmdir( $temp );
mc_rrmdir( $child_fatal['dir'] );
mc_rrmdir( $child_notice['dir'] );
if ( is_file( $outside ) ) {
	unlink( $outside );
}

if ( $failed > 0 ) {
	fwrite( STDERR, "{$failed} assertion(s) failed\n" );
	exit( 1 );
}
fwrite( STDOUT, "phase 1 ok\n" );
}

/**
 * @param string $mode   fatal or notice.
 * @param string $plugin Plugin directory.
 * @return array{exit: int, dir: string, helper_line: int}
 */
function mc_phase1_child( $mode, $plugin ) {
	$dir = sys_get_temp_dir() . '/mc-functionality-phase1-' . $mode . '-' . getmypid();
	if ( is_dir( $dir ) ) {
		mc_rrmdir( $dir );
	}
	mkdir( $dir, 0755, true );
	$helper_line = 0;
	$script      = mc_phase1_child_script( $mode, $plugin, $dir, $helper_line );
	$path        = $dir . '/child.php';
	file_put_contents( $path, $script );
	$php = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
	$spec = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$proc = proc_open( escapeshellarg( $php ) . ' ' . escapeshellarg( $path ), $spec, $pipes );
	if ( ! is_resource( $proc ) ) {
		return array(
			'exit'        => 1,
			'dir'         => $dir,
			'helper_line' => $helper_line,
		);
	}
	fclose( $pipes[0] );
	stream_get_contents( $pipes[1] );
	stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exit = proc_close( $proc );
	return array(
		'exit'        => $exit,
		'dir'         => $dir,
		'helper_line' => $helper_line,
	);
}

/**
 * @param string $mode        fatal or notice.
 * @param string $plugin      Plugin directory.
 * @param string $dir         Temp dir.
 * @param int    $helper_line Filled with the fatal helper line.
 * @return string
 */
function mc_phase1_child_script( $mode, $plugin, $dir, &$helper_line ) {
	$helper_line = 5;
	$snippet     = 'fatal' === $mode
		? "<?php\nmc_phase1_fatal_helper();\n"
		: "<?php\ntrigger_error( 'mc phase1 notice', E_USER_NOTICE );\n";
	$name        = 'fatal' === $mode ? 'boom.php' : 'note.php';
	file_put_contents( $dir . '/' . $name, $snippet );
	$plugin_export = var_export( $plugin, true );
	$dir_export    = var_export( $dir, true );
	$body          = 'fatal' === $mode
		? "function mc_phase1_fatal_helper() {\n\tmc_phase1_missing_function_xyz();\n}\n"
		: '';
	return "<?php\nrequire_once {$plugin_export} . '/includes/class-mc-functionality-snippet-store.php';\nrequire_once {$plugin_export} . '/includes/class-mc-functionality-snippet-loader.php';\n{$body}\n\$loader = new Mc_Functionality_Snippet_Loader( {$dir_export} );\n\$loader->load_snippets();\nexit( 0 );\n";
}

/**
 * @param string $plugin Plugin directory.
 * @return int
 */
function mc_phase1_lint( $plugin ) {
	$script = $plugin . '/tests/lint-check.php';
	$cmd    = 'studio wp eval-file ' . escapeshellarg( $script );
	$spec   = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$proc = proc_open( $cmd, $spec, $pipes, dirname( $plugin, 3 ) );
	if ( ! is_resource( $proc ) ) {
		return 1;
	}
	fclose( $pipes[0] );
	stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$code = proc_close( $proc );
	if ( 0 !== $code && '' !== $err ) {
		fwrite( STDERR, $err );
	}
	return $code;
}

/**
 * @param string $dir Directory.
 * @return void
 */
function mc_rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			mc_rrmdir( $path );
		} else {
			unlink( $path );
		}
	}
	rmdir( $dir );
}

/**
 * @return void
 */
function mc_run_phase2() {
	global $failed, $plugin_dir, $phase;

	$legacy = sys_get_temp_dir() . '/mc-functionality-legacy-' . getmypid();
	$dest   = sys_get_temp_dir() . '/mc-functionality-dest-' . getmypid();
	mc_rrmdir( $legacy );
	mc_rrmdir( $dest );
	mkdir( $legacy, 0755, true );
	mkdir( $dest, 0755, true );
	file_put_contents( $legacy . '/index.php', "<?php\n// silence\n" );
	file_put_contents( $legacy . '/a.php', "<?php\nfunction mc_phase2_source_body() { return 'source'; }\n" );
	file_put_contents( $legacy . '/b.php', "<?php\nfunction mc_phase2_copied() { return true; }\n" );
	file_put_contents( $legacy . '/only-legacy.php', "<?php\nfunction mc_phase2_legacy_only() { return true; }\n" );
	file_put_contents( $dest . '/a.php', "<?php\nfunction mc_phase2_edited() { return 'edited'; }\n" );
	file_put_contents( $dest . '/only-dest.php', "<?php\nfunction mc_phase2_dest_only() { return true; }\n" );

	if ( ! defined( 'MC_FUNCTIONALITY_SNIPPETS_DIR' ) ) {
		define( 'MC_FUNCTIONALITY_SNIPPETS_DIR', $dest );
	}
	$loader = new Mc_Functionality_Snippet_Loader();
	mc_assert( $loader->store()->get_dir() === $dest, 'default loader uses the storage constant' );
	$loader->load_snippets();
	mc_assert( function_exists( 'mc_phase2_dest_only' ), 'storage file is included' );
	mc_assert( ! function_exists( 'mc_phase2_legacy_only' ), 'legacy folder is not the default load path' );

	Mc_Functionality_Snippet_Migration::copy_once( $legacy, $dest );
	$edited = file_get_contents( $dest . '/a.php' );
	mc_assert( false !== strpos( $edited, 'mc_phase2_edited' ), 'second copy leaves the destination edit in place' );
	mc_assert( is_file( $legacy . '/a.php' ), 'source file still exists before verification' );
	mc_assert( is_file( $dest . '/b.php' ), 'missing destination file is copied' );

	Mc_Functionality_Snippet_Migration::delete_verified_sources( $legacy, $dest );
	mc_assert( ! is_file( $legacy . '/a.php' ), 'verified source snippet is removed' );
	mc_assert( is_file( $legacy . '/index.php' ), 'source index.php is kept' );
	mc_assert( false !== strpos( file_get_contents( $dest . '/a.php' ), 'mc_phase2_edited' ), 'destination edit survives verification' );

	$guarded = Mc_Functionality_Snippet_Meta::ensure_guard( "<?php\necho 'ran-after-guard';\n" );
	$guard_file = $dest . '/guarded.php';
	file_put_contents( $guard_file, $guarded );
	$php  = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
	$spec = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$proc = proc_open( escapeshellarg( $php ) . ' ' . escapeshellarg( $guard_file ), $spec, $pipes );
	$out  = '';
	if ( is_resource( $proc ) ) {
		fclose( $pipes[0] );
		$out = stream_get_contents( $pipes[1] );
		stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $proc );
	}
	mc_assert( false === strpos( $out, 'ran-after-guard' ), 'direct request does not run code after the guard' );

	$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
	$rerun   = escapeshellarg( $php_bin ) . ' ' . escapeshellarg( $plugin_dir . '/tests/run.php' ) . ' 1';
	$proc1   = proc_open( $rerun, $spec, $pipes1 );
	$code1   = 1;
	if ( is_resource( $proc1 ) ) {
		fclose( $pipes1[0] );
		stream_get_contents( $pipes1[1] );
		stream_get_contents( $pipes1[2] );
		fclose( $pipes1[1] );
		fclose( $pipes1[2] );
		$code1 = proc_close( $proc1 );
	}
	mc_assert( 0 === $code1, 'phase 1 still exits 0' );

	mc_rrmdir( $legacy );
	mc_rrmdir( $dest );

	if ( $failed > 0 ) {
		fwrite( STDERR, "{$failed} assertion(s) failed\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "phase 2 ok\n" );
}

/**
 * @return void
 */
function mc_run_phase3() {
	global $failed, $plugin_dir;

	$dir = sys_get_temp_dir() . '/mc-functionality-safe-' . getmypid();
	mc_rrmdir( $dir );
	mkdir( $dir, 0755, true );
	file_put_contents( $dir . '/live.php', "<?php\nfunction mc_phase3_live() { return true; }\n" );

	$store = new Mc_Functionality_Snippet_Store( $dir );
	$key   = $store->ensure_key();

	$store->maybe_disable( 'wrong-secret' );
	$loader = new Mc_Functionality_Snippet_Loader( $dir );
	$loader->load_snippets();
	mc_assert( function_exists( 'mc_phase3_live' ), 'wrong secret still loads the snippet' );
	mc_assert( ! is_file( $dir . '/snippets-disabled' ), 'wrong secret does not write the flag' );

	$store->maybe_disable( $key );
	mc_assert( is_file( $dir . '/snippets-disabled' ), 'correct secret writes the flag' );
	$loader2 = new Mc_Functionality_Snippet_Loader( $dir );
	$loader2->load_snippets();
	mc_assert( ! function_exists( 'mc_phase3_blocked' ), 'flagged loader does not define a new snippet function' );
	file_put_contents( $dir . '/blocked.php', "<?php\nfunction mc_phase3_blocked() { return true; }\n" );
	$loader3 = new Mc_Functionality_Snippet_Loader( $dir );
	$loader3->load_snippets();
	mc_assert( ! function_exists( 'mc_phase3_blocked' ), 'flag present skips snippets' );

	mc_assert( false === $store->clear_flag( array() ), 'user without manage_options cannot clear the flag' );
	mc_assert( is_file( $dir . '/snippets-disabled' ), 'failed clear leaves the flag' );
	mc_assert( true === $store->clear_flag( array( 'manage_options' => true ) ), 'manage_options can clear the flag' );
	mc_assert( ! is_file( $dir . '/snippets-disabled' ), 'flag file is gone after a permitted clear' );

	$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
	$spec    = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	foreach ( array( '1', '2' ) as $earlier ) {
		$rerun = escapeshellarg( $php_bin ) . ' ' . escapeshellarg( $plugin_dir . '/tests/run.php' ) . ' ' . $earlier;
		$proc  = proc_open( $rerun, $spec, $pipes );
		$code  = 1;
		if ( is_resource( $proc ) ) {
			fclose( $pipes[0] );
			stream_get_contents( $pipes[1] );
			stream_get_contents( $pipes[2] );
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			$code = proc_close( $proc );
		}
		mc_assert( 0 === $code, 'phase ' . $earlier . ' still exits 0' );
	}

	mc_rrmdir( $dir );
	if ( $failed > 0 ) {
		fwrite( STDERR, "{$failed} assertion(s) failed\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "phase 3 ok\n" );
}

/**
 * @return void
 */
function mc_run_phase4() {
	global $failed, $plugin_dir;

	$dir = sys_get_temp_dir() . '/mc-functionality-headers-' . getmypid();
	mc_rrmdir( $dir );
	mkdir( $dir, 0755, true );
	file_put_contents( $dir . '/plain.php', "<?php\nfunction mc_phase4_plain() { return true; }\n" );
	file_put_contents( $dir . '/late.php', "<?php\n/**\n * Priority: 20\n */\nfile_put_contents( " . var_export( $dir . '/order.log', true ) . ", \"20\\n\", FILE_APPEND );\n" );
	file_put_contents( $dir . '/early.php', "<?php\n/**\n * Priority: 5\n */\nfile_put_contents( " . var_export( $dir . '/order.log', true ) . ", \"5\\n\", FILE_APPEND );\n" );
	file_put_contents( $dir . '/adminish.php', "<?php\n/**\n * Run-Context: frontend-only\n */\nfunction mc_phase4_front() { return true; }\n" );
	file_put_contents( $dir . '/url.php', "<?php\n/**\n * URL-Contains: /hello\n */\nfunction mc_phase4_url() { return true; }\n" );

	$loader = new Mc_Functionality_Snippet_Loader( $dir );
	$loader->load_snippets( array( 'is_admin' => false ) );
	mc_assert( function_exists( 'mc_phase4_plain' ), 'headerless php loads immediately' );
	mc_assert( function_exists( 'mc_phase4_front' ), 'frontend-only loads on the front' );
	$order = is_file( $dir . '/order.log' ) ? file_get_contents( $dir . '/order.log' ) : '';
	mc_assert( "5\n20\n" === $order, 'lower priority number runs first' );
	mc_assert( ! function_exists( 'mc_phase4_url' ), 'conditional snippet is not included in the early batch' );

	$loader->run_scheduled( array( 'url' => '/other', 'is_admin' => false ) );
	mc_assert( ! function_exists( 'mc_phase4_url' ), 'unmatched url condition does not load' );
	$loader->run_scheduled( array( 'url' => '/hello-world', 'is_admin' => false ) );
	mc_assert( function_exists( 'mc_phase4_url' ), 'matched url condition loads on the scheduled callback' );

	$admin_dir = $dir . '-admin';
	mkdir( $admin_dir, 0755, true );
	file_put_contents( $admin_dir . '/front.php', "<?php\n/**\n * Run-Context: frontend-only\n */\nfunction mc_phase4_not_in_admin() { return true; }\n" );
	$admin_loader = new Mc_Functionality_Snippet_Loader( $admin_dir );
	$admin_loader->load_snippets( array( 'is_admin' => true ) );
	mc_assert( ! function_exists( 'mc_phase4_not_in_admin' ), 'frontend-only stays out of admin' );

	$round = "<?php\n/**\n * Description stays here\n * Run-Context: everywhere\n * Priority: 10\n */\n";
	$updated = Mc_Functionality_Snippet_Meta::update( $round, 'admin-only', 5 );
	mc_assert( false !== strpos( $updated, 'Description stays here' ), 'description line survives a header update' );
	mc_assert( false !== strpos( $updated, 'Run-Context: admin-only' ), 'run context is updated' );
	mc_assert( false !== strpos( $updated, 'Priority: 5' ), 'priority is updated' );

	$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
	$spec    = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	foreach ( array( '1', '2', '3' ) as $earlier ) {
		$rerun = escapeshellarg( $php_bin ) . ' ' . escapeshellarg( $plugin_dir . '/tests/run.php' ) . ' ' . $earlier;
		$proc  = proc_open( $rerun, $spec, $pipes );
		$code  = 1;
		if ( is_resource( $proc ) ) {
			fclose( $pipes[0] );
			stream_get_contents( $pipes[1] );
			stream_get_contents( $pipes[2] );
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			$code = proc_close( $proc );
		}
		mc_assert( 0 === $code, 'phase ' . $earlier . ' still exits 0' );
	}

	mc_rrmdir( $dir );
	mc_rrmdir( $admin_dir );
	if ( $failed > 0 ) {
		fwrite( STDERR, "{$failed} assertion(s) failed\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "phase 4 ok\n" );
}

/**
 * @return void
 */
function mc_run_phase5() {
	global $failed, $plugin_dir;

	$dir = sys_get_temp_dir() . '/mc-functionality-assets-' . getmypid();
	mc_rrmdir( $dir );
	mkdir( $dir, 0755, true );
	file_put_contents( $dir . '/look.css', "<?php echo \"ran\";\n" );
	file_put_contents( $dir . '/look.js', "console.log('ran');\n" );
	file_put_contents( $dir . '/block.php', "<?php\n/**\n * Type: content\n * Hook: shortcode\n */\necho 'from-content';\n" );
	file_put_contents( $dir . '/real.php', "<?php\nfunction mc_phase5_php() { return true; }\n" );

	$loader = new Mc_Functionality_Snippet_Loader( $dir );
	ob_start();
	$loader->load_snippets( array( 'is_admin' => false ) );
	$noise = ob_get_clean();
	mc_assert( false === strpos( (string) $noise, 'ran' ), 'css file is not executed' );
	mc_assert( ! function_exists( 'mc_phase5_from_css' ), 'css file is not included' );
	$styles = implode( ' ', $loader->enqueued_styles );
	$scripts = implode( ' ', $loader->enqueued_scripts );
	mc_assert( false !== strpos( $styles, 'look.css' ), 'css file is enqueued' );
	mc_assert( false !== strpos( $scripts, 'look.js' ), 'js file is enqueued' );
	mc_assert( false === strpos( $styles, 'real.php' ), 'php snippet is not on the style queue' );
	mc_assert( 'from-content' === $loader->render_shortcode( array( 'id' => 'block' ) ), 'shortcode prints the content snippet' );
	mc_assert( '' === $loader->render_shortcode( array( 'id' => 'missing' ) ), 'unknown shortcode id prints nothing' );

	mc_rrmdir( $dir );
	mc_rerun_phases( array( '1', '2', '3', '4' ), $plugin_dir );
	if ( $failed > 0 ) {
		fwrite( STDERR, "{$failed} assertion(s) failed\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "phase 5 ok\n" );
}

/**
 * @param string[] $phases Phase numbers.
 * @param string   $plugin_dir Plugin directory.
 * @return void
 */
function mc_rerun_phases( $phases, $plugin_dir ) {
	global $failed;
	$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
	$spec    = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	foreach ( $phases as $earlier ) {
		$rerun = escapeshellarg( $php_bin ) . ' ' . escapeshellarg( $plugin_dir . '/tests/run.php' ) . ' ' . $earlier;
		$proc  = proc_open( $rerun, $spec, $pipes );
		$code  = 1;
		if ( is_resource( $proc ) ) {
			fclose( $pipes[0] );
			stream_get_contents( $pipes[1] );
			stream_get_contents( $pipes[2] );
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			$code = proc_close( $proc );
		}
		mc_assert( 0 === $code, 'phase ' . $earlier . ' still exits 0' );
	}
}

/**
 * @return void
 */
function mc_run_phase6() {
	global $failed, $plugin_dir;

	$dir = sys_get_temp_dir() . '/mc-functionality-zip-' . getmypid();
	mc_rrmdir( $dir );
	mkdir( $dir, 0755, true );
	$php_body = "<?php\necho 'php-body';\n";
	$css_body = "body { color: #111; }\n";
	file_put_contents( $dir . '/round.php', $php_body );
	file_put_contents( $dir . '/round.css', $css_body );
	file_put_contents( $dir . '/safe-mode.key', 'secret-key' );
	$zip_path = $dir . '/out.zip';
	mc_assert( true === Mc_Functionality_Snippet_Zip::export( $dir, $zip_path ), 'export writes a zip' );

	$zip = new ZipArchive();
	$zip->open( $zip_path );
	mc_assert( false === $zip->locateName( 'safe-mode.key' ), 'zip does not contain the safe-mode key' );
	$zip->close();

	$restore = $dir . '-restore';
	mkdir( $restore, 0755, true );
	mc_assert( true === Mc_Functionality_Snippet_Zip::import( $zip_path, $restore, true ), 'import restores files' );
	mc_assert( file_get_contents( $restore . '/round.php' ) === $php_body, 'php file round-trips' );
	mc_assert( file_get_contents( $restore . '/round.css' ) === $css_body, 'css file round-trips' );

	file_put_contents( $restore . '/round.php', "<?php\necho 'edited';\n" );
	mc_assert( true === Mc_Functionality_Snippet_Zip::import( $zip_path, $restore, false ), 'import without confirm returns' );
	mc_assert( false !== strpos( file_get_contents( $restore . '/round.php' ), 'edited' ), 'import without confirm leaves the existing file' );

	$sentinel_dir = sys_get_temp_dir() . '/mc-functionality-sentinel-' . getmypid();
	mkdir( $sentinel_dir, 0755, true );
	$sentinel = $sentinel_dir . '/wp-config.php';
	file_put_contents( $sentinel, 'original-config' );
	$slip = $dir . '/slip.zip';
	$slip_zip = new ZipArchive();
	$slip_zip->open( $slip, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$slip_zip->addFromString( '../wp-config.php', 'pwned' );
	$slip_zip->close();
	mc_assert( 'path' === Mc_Functionality_Snippet_Zip::import( $slip, $dir, true ), 'zip slip is rejected' );
	mc_assert( 'original-config' === file_get_contents( $sentinel ), 'wp-config.php is unchanged' );

	mc_rrmdir( $dir );
	mc_rrmdir( $restore );
	mc_rrmdir( $sentinel_dir );
	mc_rerun_phases( array( '1', '2', '3', '4', '5' ), $plugin_dir );
	if ( $failed > 0 ) {
		fwrite( STDERR, "{$failed} assertion(s) failed\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "phase 6 ok\n" );
}

/**
 * @return void
 */
function mc_run_phase7() {
	global $failed, $plugin_dir;

	$storage = sys_get_temp_dir() . '/mc-functionality-mu-storage-' . getmypid();
	$mu      = sys_get_temp_dir() . '/mc-functionality-mu-' . getmypid();
	mc_rrmdir( $storage );
	mc_rrmdir( $mu );
	mkdir( $storage, 0755, true );
	mkdir( $mu, 0755, true );
	file_put_contents( $storage . '/mu-snippet.php', "<?php\nfunction mc_phase7_mu() { return true; }\n" );

	$mu_file = $mu . '/mc-functionality-loader.php';
	mc_assert( '' === Mc_Functionality_Standalone::notice( $mu_file ), 'notice is empty when the mu file is absent' );
	mc_assert( ! is_file( $mu_file ), 'standalone off leaves the mu file absent' );
	mc_assert( true === Mc_Functionality_Standalone::install( $mu, $plugin_dir, $storage ), 'standalone install writes the bootstrap' );
	mc_assert( '' !== Mc_Functionality_Standalone::notice( $mu_file ), 'notice is set when the mu file exists' );
	$bootstrap = file_get_contents( $mu_file );
	mc_assert( false === strpos( $bootstrap, 'class-mc-functionality-admin.php' ), 'mu-plugin does not reference the admin class' );

	$child = $mu . '/child.php';
	file_put_contents(
		$child,
		"<?php\ndefine( 'ABSPATH', " . var_export( $mu, true ) . " );\nrequire " . var_export( $mu_file, true ) . ";\necho function_exists( 'mc_phase7_mu' ) ? 'yes' : 'no';\n"
	);
	$php  = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
	$spec = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$proc = proc_open( escapeshellarg( $php ) . ' ' . escapeshellarg( $child ), $spec, $pipes );
	$out  = '';
	if ( is_resource( $proc ) ) {
		fclose( $pipes[0] );
		$out = stream_get_contents( $pipes[1] );
		stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $proc );
	}
	mc_assert( false !== strpos( $out, 'yes' ), 'mu-plugin alone loads the snippet' );

	$uninstall = $plugin_dir . '/uninstall.php';
	$child_un  = $mu . '/uninstall-child.php';
	file_put_contents(
		$child_un,
		"<?php\ndefine( 'WP_UNINSTALL_PLUGIN', 'mc-functionality/mc-functionality.php' );\nrequire " . var_export( $uninstall, true ) . ";\necho mc_functionality_uninstall_keep_files( " . var_export( $storage, true ) . ", " . var_export( $mu_file, true ) . " ) ? 'kept' : 'lost';\necho is_dir( " . var_export( $storage, true ) . " ) ? 'dir' : 'nodir';\n"
	);
	$proc2 = proc_open( escapeshellarg( $php ) . ' ' . escapeshellarg( $child_un ), $spec, $pipes2 );
	$out2  = '';
	if ( is_resource( $proc2 ) ) {
		fclose( $pipes2[0] );
		$out2 = stream_get_contents( $pipes2[1] );
		stream_get_contents( $pipes2[2] );
		fclose( $pipes2[1] );
		fclose( $pipes2[2] );
		proc_close( $proc2 );
	}
	mc_assert( false !== strpos( $out2, 'kept' ) && false !== strpos( $out2, 'dir' ), 'uninstall leaves the storage directory' );

	mc_rrmdir( $storage );
	mc_rrmdir( $mu );
	mc_rerun_phases( array( '1', '2', '3', '4', '5', '6' ), $plugin_dir );
	if ( $failed > 0 ) {
		fwrite( STDERR, "{$failed} assertion(s) failed\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "phase 7 ok\n" );
}

/**
 * @return void
 */
function mc_run_phase8() {
	global $failed;

	if ( ! class_exists( 'WP_Error' ) ) {
		/**
		 * Minimal error stub for store tests outside WordPress.
		 */
		class WP_Error {
			/**
			 * @var string
			 */
			private $code;

			/**
			 * @var string
			 */
			private $message;

			/**
			 * @param string $code    Error code.
			 * @param string $message Error message.
			 * @param mixed  $data    Unused data.
			 */
			public function __construct( $code = '', $message = '', $data = '' ) {
				$this->code    = $code;
				$this->message = $message;
				unset( $data );
			}

			/**
			 * @return string
			 */
			public function get_error_code() {
				return $this->code;
			}
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		/**
		 * @param mixed $thing Value to test.
		 * @return bool
		 */
		function is_wp_error( $thing ) {
			return $thing instanceof WP_Error;
		}
	}

	$dir = sys_get_temp_dir() . '/mc-functionality-phase8-' . getmypid();
	mc_rrmdir( $dir );
	mkdir( $dir, 0755, true );
	$other = "<?php\necho 'keep';\n";
	file_put_contents( $dir . '/other.php', $other );
	$sentinel = sys_get_temp_dir() . '/mc-functionality-phase8-sentinel-' . getmypid() . '.php';
	file_put_contents( $sentinel, 'original-config' );

	$store  = new Mc_Functionality_Snippet_Store( $dir );
	$source = "<?php\n/**\n * Round\n * Run-Context: admin-only\n */\necho 'one';\n";
	$bad    = $store->create( '../wp-config.php', $source );
	mc_assert( is_wp_error( $bad ), 'traversal filename is refused' );
	mc_assert( 'original-config' === file_get_contents( $sentinel ), 'sentinel file is unchanged' );
	mc_assert( $other === file_get_contents( $dir . '/other.php' ), 'other snippet is unchanged after a refused create' );

	$created = $store->create( 'round.php', $source );
	mc_assert( is_array( $created ) && 'disabled' === $created['status'], 'create returns disabled' );
	mc_assert( is_file( $dir . '/round.php.disabled' ), 'create writes .php.disabled' );
	mc_assert( ! is_file( $dir . '/round.php' ), 'create does not enable the file' );
	mc_assert( false !== strpos( file_get_contents( $dir . '/round.php.disabled' ), "defined( 'ABSPATH' )" ), 'create adds the direct-access guard' );

	$eval = $store->create( 'evil.php', "<?php\neval('echo 1;');\n" );
	mc_assert( is_wp_error( $eval ), 'eval source is refused' );
	mc_assert( ! is_file( $dir . '/evil.php' ) && ! is_file( $dir . '/evil.php.disabled' ), 'refused eval writes nothing' );

	$huge = $store->create( 'huge.php', str_repeat( 'a', Mc_Functionality_Snippet_Store::MAX_SOURCE_BYTES + 1 ) );
	mc_assert( is_wp_error( $huge ), 'oversized source is refused' );
	mc_assert( ! is_file( $dir . '/huge.php.disabled' ), 'refused oversized write leaves no file' );

	$updated = $store->update( 'round.php', "<?php\n/**\n * Round\n * Run-Context: frontend-only\n */\necho 'two';\n" );
	mc_assert( is_array( $updated ) && 'disabled' === $updated['status'], 'update rewrites a disabled snippet' );
	mc_assert( false !== strpos( file_get_contents( $dir . '/round.php.disabled' ), 'two' ), 'updated source is on disk' );

	mc_assert( true === $store->enable( 'round.php' ), 'enable renames the file on' );
	mc_assert( is_file( $dir . '/round.php' ), 'enabled file is round.php' );
	$blocked = $store->update( 'round.php', "<?php\necho 'three';\n" );
	mc_assert( is_wp_error( $blocked ), 'update refuses an enabled snippet' );
	mc_assert( false === strpos( file_get_contents( $dir . '/round.php' ), 'three' ), 'refused update leaves the enabled source' );

	mc_assert( true === $store->disable( 'round.php' ), 'disable renames the file off' );
	file_put_contents( $dir . '/round.php.error', 'boom on line 1' );
	$deleted = $store->delete( 'round.php' );
	mc_assert( is_array( $deleted ) && 'deleted' === $deleted['status'], 'delete returns deleted' );
	mc_assert( ! is_file( $dir . '/round.php' ) && ! is_file( $dir . '/round.php.disabled' ) && ! is_file( $dir . '/round.php.error' ), 'delete removes the snippet and the error note' );
	mc_assert( $other === file_get_contents( $dir . '/other.php' ), 'other snippet is unchanged after the round trip' );

	$read = $store->read( 'other.php' );
	mc_assert( is_array( $read ) && 'other.php' === $read['filename'] && 'enabled' === $read['status'], 'read returns the other snippet' );
	mc_assert( ! isset( $read['path'] ), 'read does not return a path' );

	mc_rrmdir( $dir );
	if ( is_file( $sentinel ) ) {
		unlink( $sentinel );
	}
	if ( $failed > 0 ) {
		fwrite( STDERR, "{$failed} assertion(s) failed\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "phase 8 ok\n" );
}

if ( '1' === $phase ) {
	mc_run_phase1();
} elseif ( '2' === $phase ) {
	mc_run_phase2();
} elseif ( '3' === $phase ) {
	mc_run_phase3();
} elseif ( '4' === $phase ) {
	mc_run_phase4();
} elseif ( '5' === $phase ) {
	mc_run_phase5();
} elseif ( '6' === $phase ) {
	mc_run_phase6();
} elseif ( '7' === $phase ) {
	mc_run_phase7();
} elseif ( '8' === $phase ) {
	mc_run_phase8();
} else {
	fwrite( STDERR, "Unknown phase: {$phase}\n" );
	exit( 1 );
}

