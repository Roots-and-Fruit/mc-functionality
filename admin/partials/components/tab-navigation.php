<?php
/**
 * Tab Navigation Component
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/admin/partials/components
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get the current tab from URL parameters
 *
 * @return string Current tab name
 */
if ( ! function_exists( 'mc_get_current_tab' ) ) {
	function mc_get_current_tab() {
		return isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'snippets';
	}
}

$current_tab = mc_get_current_tab();
?>

<nav class="nav-tab-wrapper">
	<a href="<?php echo esc_url( admin_url( 'plugins.php?page=mc-functionality&tab=snippets' ) ); ?>"
	   class="nav-tab <?php echo ( 'snippets' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
		Snippets
	</a>
	<a href="<?php echo esc_url( admin_url( 'plugins.php?page=mc-functionality&tab=overview' ) ); ?>"
	   class="nav-tab <?php echo ( 'overview' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
		Overview
	</a>
	<a href="<?php echo esc_url( admin_url( 'plugins.php?page=mc-functionality&tab=settings' ) ); ?>"
	   class="nav-tab <?php echo ( 'settings' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
		Settings
	</a>
</nav>