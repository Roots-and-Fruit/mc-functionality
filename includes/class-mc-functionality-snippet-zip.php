<?php
/**
 * Zip import and export for the snippet directory.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Packs and unpacks snippet files. Skips the safe-mode key and the disabled flag.
 *
 * @since 1.1.0
 */
class Mc_Functionality_Snippet_Zip {

	const MAX_BYTES = 2097152;

	/**
	 * @param string $dir     Storage directory.
	 * @param string $zip_path Destination zip path.
	 * @return bool
	 */
	public static function export( $dir, $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) || ! is_dir( $dir ) ) {
			return false;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$paths = glob( rtrim( $dir, '/\\' ) . '/*' );
		if ( ! is_array( $paths ) ) {
			$zip->close();
			return false;
		}
		foreach ( $paths as $path ) {
			if ( ! is_file( $path ) ) {
				continue;
			}
			$name = basename( $path );
			if ( 'safe-mode.key' === $name || 'snippets-disabled' === $name || 'index.php' === $name || '.htaccess' === $name ) {
				continue;
			}
			if ( ! self::allowed_name( $name ) ) {
				continue;
			}
			$zip->addFile( $path, $name );
		}
		return $zip->close();
	}

	/**
	 * @param string $zip_path Zip path.
	 * @param string $dir      Storage directory.
	 * @param bool   $confirm  Whether existing files may be replaced.
	 * @return true|string True, or an error code.
	 */
	public static function import( $zip_path, $dir, $confirm ) {
		if ( ! class_exists( 'ZipArchive' ) || ! is_file( $zip_path ) ) {
			return 'missing';
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return 'open';
		}
		$total = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$stat = $zip->statIndex( $i );
			if ( ! is_array( $stat ) || empty( $stat['name'] ) ) {
				$zip->close();
				return 'stat';
			}
			$name = (string) $stat['name'];
			if ( self::is_unsafe_name( $name ) || ! self::allowed_name( basename( $name ) ) ) {
				$zip->close();
				return 'path';
			}
			$total += isset( $stat['size'] ) ? (int) $stat['size'] : 0;
			if ( $total > self::MAX_BYTES ) {
				$zip->close();
				return 'size';
			}
		}
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) ) {
			$zip->close();
			return 'dir';
		}
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$stat = $zip->statIndex( $i );
			$name = basename( (string) $stat['name'] );
			$target = rtrim( $dir, '/\\' ) . '/' . $name;
			if ( is_file( $target ) && ! $confirm ) {
				continue;
			}
			$contents = $zip->getFromIndex( $i );
			if ( false === $contents ) {
				$zip->close();
				return 'read';
			}
			file_put_contents( $target, $contents );
		}
		$zip->close();
		return true;
	}

	/**
	 * @param string $name File name.
	 * @return bool
	 */
	public static function is_unsafe_name( $name ) {
		if ( '' === $name || false !== strpos( $name, '..' ) ) {
			return true;
		}
		if ( false !== strpos( $name, '\\' ) || false !== strpos( $name, '/' ) ) {
			return true;
		}
		if ( ':' === substr( $name, 1, 1 ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $name Basename.
	 * @return bool
	 */
	public static function allowed_name( $name ) {
		if ( 'safe-mode.key' === $name || 'snippets-disabled' === $name ) {
			return false;
		}
		return 1 === preg_match( '/^[a-zA-Z0-9\-_\.]+\.(php|css|js)(\.disabled|\.error)?$/', $name );
	}
}
