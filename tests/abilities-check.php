<?php
/**
 * Ability round-trip against a throwaway directory.
 *
 * Run with:
 * studio wp eval-file wp-content/plugins/mc-functionality/tests/abilities-check.php
 *
 * @package Mc_Functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Load this file with studio wp eval-file.\n" );
	exit( 1 );
}

$GLOBALS['mc_ability_failed'] = 0;

/**
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 */
function mc_ability_assert( $condition, $message ) {
	if ( $condition ) {
		fwrite( STDOUT, "PASS {$message}\n" );
		return;
	}
	$GLOBALS['mc_ability_failed']++;
	fwrite( STDERR, "FAIL {$message}\n" );
}

/**
 * @param string $dir Directory.
 * @return void
 */
function mc_ability_rrmdir( $dir ) {
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
			mc_ability_rrmdir( $path );
		} else {
			unlink( $path );
		}
	}
	rmdir( $dir );
}

$names = array(
	'mc-functionality/list-snippets',
	'mc-functionality/get-snippet',
	'mc-functionality/enable-snippet',
	'mc-functionality/disable-snippet',
	'mc-functionality/create-snippet',
	'mc-functionality/update-snippet',
	'mc-functionality/delete-snippet',
);

$dir      = sys_get_temp_dir() . '/mc-functionality-abilities-' . getmypid();
$sentinel = sys_get_temp_dir() . '/mc-functionality-abilities-sentinel-' . getmypid() . '.php';
$role     = 'mc_fn_ability_tester';
$user_id  = 0;

mc_ability_rrmdir( $dir );
mkdir( $dir, 0755, true );
file_put_contents( $sentinel, 'original-config' );
$other = "<?php\necho 'keep';\n";
file_put_contents( $dir . '/other.php', $other );

try {
	mc_ability_assert( function_exists( 'wp_get_ability' ), 'abilities API is available' );
	foreach ( $names as $name ) {
		$ability = wp_get_ability( $name );
		mc_ability_assert( $ability instanceof WP_Ability, $name . ' is registered' );
		if ( $ability instanceof WP_Ability ) {
			$meta = $ability->get_meta();
			mc_ability_assert( isset( $meta['show_in_rest'] ) && false === $meta['show_in_rest'], $name . ' stays off the REST API' );
			mc_ability_assert( ! empty( $meta['mcp']['public'] ), $name . ' is visible to MCP' );
		}
	}

	remove_role( $role );
	add_role( $role, 'MC Ability Tester', array( 'read' => true ) );
	$user_id = wp_insert_user(
		array(
			'user_login' => 'mc_fn_ability_' . wp_generate_password( 6, false ),
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => 'mc-fn-ability-' . wp_rand( 1000, 9999 ) . '@example.com',
			'role'       => $role,
		)
	);
	mc_ability_assert( ! is_wp_error( $user_id ), 'tester user exists' );
	if ( is_wp_error( $user_id ) ) {
		throw new RuntimeException( 'user' );
	}

	$store = new Mc_Functionality_Snippet_Store( $dir );
	add_filter(
		'mc_functionality_snippet_store',
		static function () use ( $store ) {
			return $store;
		}
	);

	wp_set_current_user( $user_id );
	$refused = wp_get_ability( 'mc-functionality/create-snippet' )->execute(
		array(
			'filename' => 'round.php',
			'content'  => "<?php\necho 'round';\n",
		)
	);
	mc_ability_assert( is_wp_error( $refused ), 'user without the caps cannot create' );
	mc_ability_assert( ! is_file( $dir . '/round.php' ) && ! is_file( $dir . '/round.php.disabled' ), 'refused create writes nothing' );

	$user = new WP_User( $user_id );
	$user->add_cap( 'read_mc_snippets' );
	$user->add_cap( 'edit_mc_snippets' );
	$user->add_cap( 'delete_mc_snippets' );
	clean_user_cache( $user_id );
	wp_set_current_user( 0 );
	wp_set_current_user( $user_id );

	$traversal = wp_get_ability( 'mc-functionality/create-snippet' )->execute(
		array(
			'filename' => '../wp-config.php',
			'content'  => "<?php\necho 'no';\n",
		)
	);
	mc_ability_assert( is_wp_error( $traversal ) && 'mc_functionality_invalid_filename' === $traversal->get_error_code(), 'traversal filename is refused' );
	mc_ability_assert( 'original-config' === file_get_contents( $sentinel ), 'sentinel file is unchanged' );

	$seen = array();
	add_action(
		'mc_functionality_snippet_changed',
		static function ( $change ) use ( &$seen ) {
			$seen[] = $change;
		}
	);

	$created = wp_get_ability( 'mc-functionality/create-snippet' )->execute(
		array(
			'filename' => 'round.php',
			'content'  => "<?php\n/**\n * Round\n * Run-Context: admin-only\n */\necho 'one';\n",
		)
	);
	if ( is_wp_error( $created ) ) {
		fwrite( STDERR, 'create error: ' . $created->get_error_code() . ' ' . $created->get_error_message() . "\n" );
	}
	mc_ability_assert( is_array( $created ) && 'disabled' === $created['status'], 'create returns disabled' );
	mc_ability_assert( is_file( $dir . '/round.php.disabled' ) && ! is_file( $dir . '/round.php' ), 'create lands on .php.disabled' );

	$listed = wp_get_ability( 'mc-functionality/list-snippets' )->execute( array( 'status' => 'disabled' ) );
	if ( is_wp_error( $listed ) ) {
		fwrite( STDERR, 'list error: ' . $listed->get_error_code() . ' ' . $listed->get_error_message() . "\n" );
	}
	mc_ability_assert( is_array( $listed ), 'list returns rows' );
	$found = false;
	if ( is_array( $listed ) ) {
		foreach ( $listed as $row ) {
			if ( isset( $row['filename'] ) && 'round.php' === $row['filename'] ) {
				$found = 'disabled' === $row['status'] && 'admin-only' === $row['run_context'] && ! isset( $row['path'] );
			}
		}
	}
	mc_ability_assert( $found, 'list includes the disabled snippet without a path' );

	$got = wp_get_ability( 'mc-functionality/get-snippet' )->execute( array( 'filename' => 'round.php' ) );
	mc_ability_assert( is_array( $got ) && isset( $got['content'] ) && false !== strpos( $got['content'], 'one' ), 'get returns the source' );
	mc_ability_assert( is_array( $got ) && ! isset( $got['path'] ), 'get does not return a path' );
	mc_ability_assert( $other === file_get_contents( $dir . '/other.php' ), 'list and get leave the other snippet alone' );

	$updated = wp_get_ability( 'mc-functionality/update-snippet' )->execute(
		array(
			'filename' => 'round.php',
			'content'  => "<?php\necho 'two';\n",
		)
	);
	mc_ability_assert( is_array( $updated ) && 'disabled' === $updated['status'], 'update rewrites the disabled snippet' );

	file_put_contents( $dir . '/evil.php.disabled', "<?php\neval('echo 1;');\n" );
	$evil = wp_get_ability( 'mc-functionality/enable-snippet' )->execute( array( 'filename' => 'evil.php' ) );
	mc_ability_assert( is_wp_error( $evil ) && 'dangerous_function' === $evil->get_error_code(), 'enable refuses eval' );
	mc_ability_assert( is_file( $dir . '/evil.php.disabled' ) && ! is_file( $dir . '/evil.php' ), 'refused enable leaves the file disabled' );

	$enabled = wp_get_ability( 'mc-functionality/enable-snippet' )->execute( array( 'filename' => 'round.php' ) );
	mc_ability_assert( is_array( $enabled ) && 'enabled' === $enabled['status'] && is_file( $dir . '/round.php' ), 'enable renames the file on' );

	$live = wp_get_ability( 'mc-functionality/update-snippet' )->execute(
		array(
			'filename' => 'round.php',
			'content'  => "<?php\necho 'three';\n",
		)
	);
	mc_ability_assert( is_wp_error( $live ), 'update refuses an enabled snippet' );
	mc_ability_assert( false === strpos( file_get_contents( $dir . '/round.php' ), 'three' ), 'refused update leaves the enabled source' );

	$disabled = wp_get_ability( 'mc-functionality/disable-snippet' )->execute( array( 'filename' => 'round.php' ) );
	mc_ability_assert( is_array( $disabled ) && 'disabled' === $disabled['status'], 'disable renames the file off' );

	file_put_contents( $dir . '/round.php.error', 'boom on line 1' );
	$deleted = wp_get_ability( 'mc-functionality/delete-snippet' )->execute( array( 'filename' => 'round.php' ) );
	mc_ability_assert( is_array( $deleted ) && 'deleted' === $deleted['status'], 'delete returns deleted' );
	mc_ability_assert( ! is_file( $dir . '/round.php' ) && ! is_file( $dir . '/round.php.disabled' ) && ! is_file( $dir . '/round.php.error' ), 'delete removes the snippet and the error note' );
	mc_ability_assert( $other === file_get_contents( $dir . '/other.php' ), 'other snippet is unchanged after the round trip' );
	mc_ability_assert( 'original-config' === file_get_contents( $sentinel ), 'sentinel file is unchanged after the round trip' );

	$abilities = array();
	foreach ( $seen as $change ) {
		if ( isset( $change['ability'] ) ) {
			$abilities[] = $change['ability'];
		}
		mc_ability_assert( isset( $change['user_id'] ) && (int) $change['user_id'] === (int) $user_id, 'change record has the tester user' );
	}
	mc_ability_assert( in_array( 'mc-functionality/create-snippet', $abilities, true ), 'create fires the change action' );
	mc_ability_assert( in_array( 'mc-functionality/delete-snippet', $abilities, true ), 'delete fires the change action' );
} finally {
	if ( $user_id && ! is_wp_error( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
	remove_role( $role );
	mc_ability_rrmdir( $dir );
	if ( is_file( $sentinel ) ) {
		unlink( $sentinel );
	}
}

if ( $GLOBALS['mc_ability_failed'] > 0 ) {
	fwrite( STDERR, "{$GLOBALS['mc_ability_failed']} assertion(s) failed\n" );
	exit( 1 );
}
fwrite( STDOUT, "abilities ok\n" );
