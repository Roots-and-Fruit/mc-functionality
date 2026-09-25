<?php
/**
 * Copy snippets into the storage directory once.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Copies legacy plugin snippets into storage without overwriting newer copies.
 *
 * @since 1.1.0
 */
class Mc_Functionality_Snippet_Migration {

	/**
	 * Copy files that are not already in the destination.
	 *
	 * @param string $source Legacy directory.
	 * @param string $dest   Storage directory.
	 * @return string[] Basenames copied.
	 */
	public static function copy_once( $source, $dest ) {
		$copied = array();
		if ( ! is_dir( $source ) ) {
			return $copied;
		}
		if ( ! is_dir( $dest ) && ! mkdir( $dest, 0755, true ) ) {
			return $copied;
		}
		$paths = glob( rtrim( $source, '/\\' ) . '/*' );
		if ( ! is_array( $paths ) ) {
			return $copied;
		}
		foreach ( $paths as $path ) {
			if ( ! is_file( $path ) ) {
				continue;
			}
			$name = basename( $path );
			if ( 'index.php' === $name ) {
				continue;
			}
			$target = rtrim( $dest, '/\\' ) . '/' . $name;
			if ( is_file( $target ) ) {
				continue;
			}
			if ( copy( $path, $target ) ) {
				$copied[] = $name;
			}
		}
		return $copied;
	}

	/**
	 * Remove source snippets whose basename is already in the destination.
	 *
	 * Leaves index.php alone. Live installs should not call this while another
	 * process is still adding files to the legacy folder.
	 *
	 * @param string $source Legacy directory.
	 * @param string $dest   Storage directory.
	 * @return string[] Basenames removed from the source.
	 */
	public static function delete_verified_sources( $source, $dest ) {
		$removed = array();
		if ( ! is_dir( $source ) || ! is_dir( $dest ) ) {
			return $removed;
		}
		$paths = glob( rtrim( $source, '/\\' ) . '/*' );
		if ( ! is_array( $paths ) ) {
			return $removed;
		}
		foreach ( $paths as $path ) {
			if ( ! is_file( $path ) ) {
				continue;
			}
			$name = basename( $path );
			if ( 'index.php' === $name ) {
				continue;
			}
			$target = rtrim( $dest, '/\\' ) . '/' . $name;
			if ( ! is_file( $target ) ) {
				continue;
			}
			if ( unlink( $path ) ) {
				$removed[] = $name;
			}
		}
		return $removed;
	}
}
