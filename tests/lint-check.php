<?php
/**
 * Lint assertions. Run with: studio wp eval-file tests/lint-check.php
 *
 * @package Mc_Functionality
 */

require_once dirname( __DIR__ ) . '/includes/class-mc-functionality-php-lint.php';

$ok = Mc_Functionality_Php_Lint::check( "<?php\nget_bloginfo( 'name' );\n" );
$samples = array(
	"<?php\neval( '1' );\n",
	"<?php\neval ( '1' );\n",
	"<?php\nassert( 1 );\n",
	"<?php\ninclude 'a.php';\n",
	"<?php\necho `ls`;\n",
);

if ( true !== $ok ) {
	fwrite( STDERR, "get_bloginfo was rejected\n" );
	exit( 1 );
}
foreach ( $samples as $sample ) {
	$bad = Mc_Functionality_Php_Lint::check( $sample );
	if ( ! is_wp_error( $bad ) ) {
		fwrite( STDERR, "accepted: {$sample}\n" );
		exit( 1 );
	}
}
exit( 0 );
