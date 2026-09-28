<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.mattcromwell.com
 * @since             1.0.0
 * @package           Mc_Functionality
 *
 * @wordpress-plugin
 * Plugin Name:       MC Functionality
 * Plugin URI:        https://www.mattcromwell.com/mc-functionality
 * Description:       A file-based code snippet system for WordPress. Load and execute PHP files from the /code-snippets/ directory with better performance and security than database-stored snippets.
 * Version:           1.2.0
 * Author:            Matt Cromwell
 * Author URI:        https://www.mattcromwell.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mc-functionality
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'MC_FUNCTIONALITY_VERSION', '1.2.0' );

/**
 * Plugin snippets directory path.
 * This is where PHP code snippets are stored and loaded from.
 */
if ( ! defined( 'MC_FUNCTIONALITY_SNIPPETS_DIR' ) ) {
	define( 'MC_FUNCTIONALITY_SNIPPETS_DIR', WP_CONTENT_DIR . '/mc-snippets' );
}

/**
 * Legacy folder. Another process may still add files here. Copy them forward. Do not delete them.
 *
 * @return string
 */
function mc_functionality_legacy_snippets_dir() {
	return plugin_dir_path( __FILE__ ) . 'code-snippets';
}

/**
 * Load code snippets early in the WordPress lifecycle.
 * This ensures snippets are available before other plugins and themes load.
 */
function load_mc_functionality_snippets() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-mc-functionality-snippet-store.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-mc-functionality-snippet-migration.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-mc-functionality-snippet-loader.php';

	$store = new Mc_Functionality_Snippet_Store();
	$store->ensure_dir();
	$store->ensure_key();
	Mc_Functionality_Snippet_Migration::copy_once( mc_functionality_legacy_snippets_dir(), $store->get_dir() );

	$snippet_loader = new Mc_Functionality_Snippet_Loader();
	if ( isset( $_GET['mc-snippets-safe-mode'] ) ) {
		$snippet_loader->store()->maybe_disable( wp_unslash( $_GET['mc-snippets-safe-mode'] ) );
	}
	$snippet_loader->load_snippets();
}

// Hook to load snippets early in the WordPress lifecycle
add_action( 'plugins_loaded', 'load_mc_functionality_snippets', 5 );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mc-functionality-activator.php
 */
function activate_mc_functionality() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-mc-functionality-activator.php';
	Mc_Functionality_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mc-functionality-deactivator.php
 */
function deactivate_mc_functionality() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-mc-functionality-deactivator.php';
	Mc_Functionality_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_mc_functionality' );
register_deactivation_hook( __FILE__, 'deactivate_mc_functionality' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-mc-functionality.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_mc_functionality() {

	$plugin = new Mc_Functionality();
	$plugin->run();
}
run_mc_functionality();
