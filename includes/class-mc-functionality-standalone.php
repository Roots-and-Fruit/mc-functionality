<?php
/**
 * Optional mu-plugin bootstrap.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Writes a copy of the runtime loader into mu-plugins.
 *
 * @since 1.1.0
 */
class Mc_Functionality_Standalone {

	/**
	 * Runtime class files. The admin class is not one of them.
	 *
	 * @return string[]
	 */
	public static function runtime_files() {
		return array(
			'class-mc-functionality-snippet-store.php',
			'class-mc-functionality-snippet-meta.php',
			'class-mc-functionality-snippet-loader.php',
		);
	}

	/**
	 * @param string $mu_dir      mu-plugins directory.
	 * @param string $plugin_dir  Plugin directory.
	 * @param string $storage_dir Snippet storage directory.
	 * @return bool
	 */
	public static function install( $mu_dir, $plugin_dir, $storage_dir ) {
		$copy_dir = rtrim( $mu_dir, '/\\' ) . '/mc-functionality';
		if ( ! is_dir( $copy_dir ) && ! mkdir( $copy_dir, 0755, true ) ) {
			return false;
		}
		foreach ( self::runtime_files() as $file ) {
			$source = rtrim( $plugin_dir, '/\\' ) . '/includes/' . $file;
			if ( ! is_file( $source ) ) {
				return false;
			}
			if ( ! copy( $source, $copy_dir . '/' . $file ) ) {
				return false;
			}
		}
		$bootstrap  = "<?php\n";
		$bootstrap .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n";
		$bootstrap .= 'if ( ! defined( \'MC_FUNCTIONALITY_SNIPPETS_DIR\' ) ) {' . "\n";
		$bootstrap .= "\tdefine( 'MC_FUNCTIONALITY_SNIPPETS_DIR', " . var_export( $storage_dir, true ) . " );\n";
		$bootstrap .= "}\n";
		$bootstrap .= "require_once __DIR__ . '/mc-functionality/class-mc-functionality-snippet-loader.php';\n";
		$bootstrap .= "( new Mc_Functionality_Snippet_Loader() )->load_snippets();\n";
		return false !== file_put_contents( rtrim( $mu_dir, '/\\' ) . '/mc-functionality-loader.php', $bootstrap );
	}

	/**
	 * @param string $mu_file Bootstrap path.
	 * @return string
	 */
	public static function notice( $mu_file ) {
		if ( ! is_file( $mu_file ) ) {
			return '';
		}
		return 'Snippets keep running if you delete this plugin. Delete ' . $mu_file . ' to stop them.';
	}
}
