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
			if ( 'index.php' === $name || 'safe-mode.key' === $name || '.htaccess' === $name ) {
				continue;
			}
			$target = rtrim( $dest, '/\\' ) . '/' . Mc_Functionality_Snippet_Store::storage_basename( $name );
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
			if ( 'index.php' === $name || 'safe-mode.key' === $name || '.htaccess' === $name ) {
				continue;
			}
			$target = rtrim( $dest, '/\\' ) . '/' . Mc_Functionality_Snippet_Store::storage_basename( $name );
			if ( ! is_file( $target ) ) {
				continue;
			}
			if ( unlink( $path ) ) {
				$removed[] = $name;
			}
		}
		return $removed;
	}

	/**
	 * Move the public wp-content/mc-snippets folder out of the web root.
	 *
	 * Copies snippet bodies to .mcphp names, checks the size, then deletes the
	 * public files. The safe-mode key is not copied.
	 *
	 * @since 1.2.1
	 * @param string $public  Old directory inside the web root.
	 * @param string $private New directory outside the web root.
	 * @return bool Whether the public directory is gone or was already absent.
	 */
	public static function relocate_public_snippets( $public, $private ) {
		$public  = rtrim( (string) $public, '/\\' );
		$private = rtrim( (string) $private, '/\\' );
		if ( '' === $public || ! is_dir( $public ) ) {
			return true;
		}
		$public_real = realpath( $public );
		if ( is_dir( $private ) ) {
			$private_real = realpath( $private );
			if ( false !== $public_real && false !== $private_real && $public_real === $private_real ) {
				return false;
			}
		} elseif ( ! mkdir( $private, 0755, true ) ) {
			return false;
		}
		$names = scandir( $public );
		if ( ! is_array( $names ) ) {
			return false;
		}
		$copied = array();
		foreach ( $names as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$path = $public . '/' . $name;
			if ( ! is_file( $path ) ) {
				continue;
			}
			if ( in_array( $name, array( 'safe-mode.key', '.htaccess', 'index.php', 'README.md' ), true ) ) {
				continue;
			}
			$target = $private . '/' . Mc_Functionality_Snippet_Store::storage_basename( $name );
			if ( is_file( $target ) && filesize( $target ) === filesize( $path ) ) {
				$copied[] = $name;
				continue;
			}
			if ( is_file( $target ) ) {
				return false;
			}
			if ( ! copy( $path, $target ) || ! is_file( $target ) || filesize( $target ) !== filesize( $path ) ) {
				if ( is_file( $target ) ) {
					unlink( $target );
				}
				return false;
			}
			$copied[] = $name;
		}
		foreach ( $names as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$path = $public . '/' . $name;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$drop = in_array( $name, array( 'safe-mode.key', '.htaccess', 'index.php', 'README.md' ), true );
			if ( ! $drop && ! in_array( $name, $copied, true ) ) {
				continue;
			}
			unlink( $path );
		}
		$left = scandir( $public );
		if ( is_array( $left ) && 2 === count( $left ) ) {
			rmdir( $public );
		}
		return ! is_dir( $public );
	}
}
