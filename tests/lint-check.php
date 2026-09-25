<?php
/**
 * Lint assertions. Run with: studio wp eval-file tests/lint-check.php
 *
 * @package Mc_Functionality
 */

require_once dirname( __DIR__ ) . '/includes/class-mc-functionality-php-lint.php';

$ok = Mc_Functionality_Php_Lint::check( "<?php\nget_bloginfo( 'name' );\n" );
$bad = Mc_Functionality_Php_Lint::check( "<?php\neval( '1' );\n" );

if ( true !== $ok ) {
	fwrite( STDERR, "get_bloginfo was rejected\n" );
	exit( 1 );
}
if ( ! is_wp_error( $bad ) ) {
	fwrite( STDERR, "eval was accepted\n" );
	exit( 1 );
}
exit( 0 );
