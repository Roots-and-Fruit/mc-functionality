<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://www.mattcromwell.com
 * @since      1.0.0
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get the snippet loader to show loaded snippets
$snippet_loader = new Mc_Functionality_Snippet_Loader();
$all_snippets = $snippet_loader->get_all_snippets();
?>

<div class="wrap mc-rf-settings">
	<header class="mc-rf-settings__header">
		<h1 class="mc-rf-settings__title">
			<img class="mc-rf-settings__mark" src="<?php echo esc_url( plugins_url( 'images/rf-logo.svg', dirname( __FILE__, 2 ) . '/index.php' ) ); ?>" alt="" width="88" height="36" />
			<?php esc_html_e( 'Site Functions', 'mc-functionality' ); ?>
		</h1>
		<p class="mc-rf-settings__lede"><?php esc_html_e( 'Snippets live as files. Turn one off by renaming it, or let a fatal error do that for you.', 'mc-functionality' ); ?></p>
	</header>

	<div class="mc-rf-settings__body">
	<?php require_once plugin_dir_path( __FILE__ ) . 'components/tab-navigation.php'; ?>

	<div class="mc-functionality-admin-content">
		<?php
		// Get current tab
		$current_tab = mc_get_current_tab();
		
		// Load appropriate tab content
		switch ( $current_tab ) {
			case 'snippets':
				require_once plugin_dir_path( __FILE__ ) . 'tabs/snippets-tab.php';
				break;
			case 'overview':
				require_once plugin_dir_path( __FILE__ ) . 'tabs/overview-tab.php';
				break;
			case 'settings':
				require_once plugin_dir_path( __FILE__ ) . 'tabs/settings-tab.php';
				break;
			default:
				require_once plugin_dir_path( __FILE__ ) . 'tabs/snippets-tab.php';
				break;
		}
		?>
	</div>
	</div>
</div>

<?php 
// Include modal only on snippets tab
if ( $current_tab === 'snippets' ) {
	require_once plugin_dir_path( __FILE__ ) . 'components/snippet-editor-modal.php';
}
?>
