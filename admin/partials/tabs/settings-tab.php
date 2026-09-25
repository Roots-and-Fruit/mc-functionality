<?php
/**
 * Settings Tab Component
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/admin/partials/tabs
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<!-- Settings Tab Content -->
<div class="mc-functionality-settings">
	<h3>Plugin Settings</h3>
	
	<form method="post" action="options.php">
		<?php settings_fields( 'mc_functionality_settings' ); ?>
		<?php do_settings_sections( 'mc_functionality_settings' ); ?>
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="mc_codemirror_theme">CodeMirror Theme</label>
				</th>
				<td>
					<select name="mc_codemirror_theme" id="mc_codemirror_theme">
						<option value="default" <?php selected( get_option( 'mc_codemirror_theme', 'default' ), 'default' ); ?>>Default</option>
						<option value="darcula" <?php selected( get_option( 'mc_codemirror_theme', 'default' ), 'darcula' ); ?>>Darcula</option>
						<option value="eclipse" <?php selected( get_option( 'mc_codemirror_theme', 'default' ), 'eclipse' ); ?>>Eclipse</option>
						<option value="material" <?php selected( get_option( 'mc_codemirror_theme', 'default' ), 'material' ); ?>>Material</option>
						<option value="mdn-like" <?php selected( get_option( 'mc_codemirror_theme', 'default' ), 'mdn-like' ); ?>>MDN-like</option>
						<option value="monokai" <?php selected( get_option( 'mc_codemirror_theme', 'default' ), 'monokai' ); ?>>Monokai</option>
					</select>
					<p class="description">Choose the theme for the code editor. Changes will apply to new editing sessions.</p>
				</td>
			</tr>
		</table>
		
		<?php submit_button( 'Save Settings' ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mc_functionality_clear_flag" />
		<?php wp_nonce_field( 'mc_functionality_clear_flag' ); ?>
		<p class="description">If safe mode turned every snippet off, this turns them back on.</p>
		<?php submit_button( 'Turn snippets back on' ); ?>
	</form>
	<?php
	require_once dirname( __DIR__, 3 ) . '/includes/class-mc-functionality-standalone.php';
	$mc_mu_file = WP_CONTENT_DIR . '/mu-plugins/mc-functionality-loader.php';
	$mc_notice  = Mc_Functionality_Standalone::notice( $mc_mu_file );
	if ( '' !== $mc_notice ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $mc_notice ) . '</p></div>';
	}
	?>
	
	<!-- Environment Information -->
	<div class="mc-environment-info">
		<h3>PHP Environment</h3>
		<p>Current PHP configuration for snippet execution:</p>
		
		<table class="form-table">
			<tr>
				<th scope="row">Memory Limit</th>
				<td>
					<code><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></code>
					<?php 
					$memory_limit = ini_get( 'memory_limit' );
					$memory_bytes = 0;
					
					// Parse memory limit to bytes for comparison
					$unit = strtolower( substr( $memory_limit, -1 ) );
					$value = intval( $memory_limit );
					
					switch ( $unit ) {
						case 'g':
							$memory_bytes = $value * 1024 * 1024 * 1024;
							break;
						case 'm':
							$memory_bytes = $value * 1024 * 1024;
							break;
						case 'k':
							$memory_bytes = $value * 1024;
							break;
						default:
							$memory_bytes = $value;
					}
					
					if ( $memory_bytes < 64 * 1024 * 1024 ) {
						echo '<p class="description mc-rf-settings__status mc-rf-settings__status--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html__( 'Consider increasing for better snippet performance', 'mc-functionality' ) . '</p>';
					} else {
						echo '<p class="description mc-rf-settings__status mc-rf-settings__status--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Good for snippet execution', 'mc-functionality' ) . '</p>';
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">Max Execution Time</th>
				<td>
					<code><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?> seconds</code>
					<?php 
					$max_execution_time = ini_get( 'max_execution_time' );
					if ( $max_execution_time > 0 && $max_execution_time < 30 ) {
						echo '<p class="description mc-rf-settings__status mc-rf-settings__status--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html__( 'Consider increasing for complex snippets', 'mc-functionality' ) . '</p>';
					} else {
						echo '<p class="description mc-rf-settings__status mc-rf-settings__status--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Good for snippet execution', 'mc-functionality' ) . '</p>';
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">PHP Version</th>
				<td>
					<code><?php echo esc_html( PHP_VERSION ); ?></code>
					<?php 
					if ( version_compare( PHP_VERSION, '7.4', '>=' ) ) {
						echo '<p class="description mc-rf-settings__status mc-rf-settings__status--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Good for modern PHP features', 'mc-functionality' ) . '</p>';
					} else {
						echo '<p class="description mc-rf-settings__status mc-rf-settings__status--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html__( 'Consider upgrading for better performance', 'mc-functionality' ) . '</p>';
					}
					?>
				</td>
			</tr>
		</table>
		
		<p class="description"><?php esc_html_e( 'These limits are what PHP will allow a snippet to use. The editor warns you when a snippet looks like it would exceed them.', 'mc-functionality' ); ?></p>
	</div>
</div>