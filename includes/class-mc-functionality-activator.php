<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.mattcromwell.com
 * @since      1.0.0
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 * @author     Matt Cromwell <info@mattcromwell.com>
 */
class Mc_Functionality_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-mc-functionality-snippet-store.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-mc-functionality-snippet-migration.php';
		$store = new Mc_Functionality_Snippet_Store();
		$store->ensure_dir();
		require_once __DIR__ . '/class-mc-functionality-abilities.php';
		Mc_Functionality_Abilities::grant_caps();
		if ( function_exists( 'mc_functionality_legacy_snippets_dir' ) ) {
			Mc_Functionality_Snippet_Migration::copy_once( mc_functionality_legacy_snippets_dir(), $store->get_dir() );
		}
	}

}
