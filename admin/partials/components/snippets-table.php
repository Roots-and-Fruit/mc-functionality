<?php
/**
 * Snippets Table Component
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/admin/partials/components
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Render the snippets table
 *
 * @param array $all_snippets Array of snippet data
 */
function mc_render_snippets_table( $all_snippets ) {
	if ( empty( $all_snippets ) ) : ?>
		<div class="notice notice-info">
			<p><strong>No snippets found.</strong> Create PHP files in the <code><?php echo esc_html( MC_FUNCTIONALITY_SNIPPETS_DIR ); ?></code> directory to get started.</p>
		</div>
	<?php else : ?>
		<div class="mc-snippets-list table-wrap">
			<table class="rf-table">
				<thead>
					<tr>
						<th>File Name</th>
						<th>Path</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_snippets as $snippet ) : ?>
						<tr>
							<td>
								<a href="#" class="mc-edit-snippet" data-filename="<?php echo esc_attr( $snippet['filename'] ); ?>">
									<strong><?php echo esc_html( $snippet['filename'] ); ?></strong>
								</a>
							</td>
							<td class="mc-snippet-path"><?php echo wp_kses( str_replace( array( '/', '\\' ), array( '/<wbr>', '\\<wbr>' ), esc_html( $snippet['path'] ) ), array( 'wbr' => array() ) ); ?></td>
							<td>
								<?php if ( $snippet['enabled'] ) : ?>
									<span class="snippet-status enabled"><?php esc_html_e( 'Enabled', 'mc-functionality' ); ?></span>
								<?php else : ?>
									<span class="snippet-status disabled"><?php esc_html_e( 'Disabled', 'mc-functionality' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button button-small mc-toggle-snippet" 
										data-filename="<?php echo esc_attr( $snippet['filename'] ); ?>"
										data-current-status="<?php echo esc_attr( $snippet['status'] ); ?>">
									<?php if ( $snippet['enabled'] ) : ?>
										Disable
									<?php else : ?>
										Enable
									<?php endif; ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif;
}