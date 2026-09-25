<?php
/**
 * Filesystem access for snippet files.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Owns the snippet directory, the path jail, and enable/disable renames.
 *
 * @since 1.1.0
 */
class Mc_Functionality_Snippet_Store {

	/**
	 * Snippet directory, without a trailing slash.
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * @param string|null $dir Directory override. Null uses MC_FUNCTIONALITY_SNIPPETS_DIR.
	 */
	public function __construct( $dir = null ) {
		if ( null === $dir ) {
			$dir = defined( 'MC_FUNCTIONALITY_SNIPPETS_DIR' ) ? MC_FUNCTIONALITY_SNIPPETS_DIR : '';
		}
		$this->dir = rtrim( (string) $dir, '/\\' );
	}

	/**
	 * @return string
	 */
	public function get_dir() {
		return $this->dir;
	}

	/**
	 * Create the directory if it is missing.
	 *
	 * @return bool
	 */
	public function ensure_dir() {
		if ( '' === $this->dir ) {
			return false;
		}
		if ( ! is_dir( $this->dir ) && ! mkdir( $this->dir, 0755, true ) ) {
			return false;
		}
		$index = $this->dir . '/index.php';
		if ( ! is_file( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		$htaccess = $this->dir . '/.htaccess';
		if ( ! is_file( $htaccess ) ) {
			file_put_contents( $htaccess, "<Files \"*\">\nRequire all denied\n</Files>\n" );
		}
		return true;
	}

	/**
	 * @return string
	 */
	public function key_path() {
		return $this->dir . '/safe-mode.key';
	}

	/**
	 * @return string
	 */
	public function flag_path() {
		return $this->dir . '/snippets-disabled';
	}

	/**
	 * @return string
	 */
	public function ensure_key() {
		$this->ensure_dir();
		$path = $this->key_path();
		if ( is_file( $path ) ) {
			$key = trim( (string) file_get_contents( $path ) );
			if ( '' !== $key ) {
				return $key;
			}
		}
		$key = bin2hex( random_bytes( 16 ) );
		file_put_contents( $path, $key );
		return $key;
	}

	/**
	 * @return bool
	 */
	public function is_disabled() {
		return is_file( $this->flag_path() );
	}

	/**
	 * Write the disabled flag when the secret matches the key file.
	 *
	 * @param string $secret Request value.
	 * @return bool Whether the flag was written.
	 */
	public function maybe_disable( $secret ) {
		$key_path = $this->key_path();
		if ( ! is_file( $key_path ) ) {
			return false;
		}
		$key = trim( (string) file_get_contents( $key_path ) );
		if ( '' === $key || ! is_string( $secret ) || ! hash_equals( $key, $secret ) ) {
			return false;
		}
		file_put_contents( $this->flag_path(), '1' );
		return true;
	}

	/**
	 * @param array<string, bool> $capabilities Capability map for the current user.
	 * @return bool
	 */
	public static function can_clear_flag( $capabilities ) {
		return is_array( $capabilities ) && ! empty( $capabilities['manage_options'] );
	}

	/**
	 * @param array<string, bool> $capabilities Capability map for the current user.
	 * @return bool
	 */
	public function clear_flag( $capabilities ) {
		if ( ! self::can_clear_flag( $capabilities ) ) {
			return false;
		}
		$path = $this->flag_path();
		if ( ! is_file( $path ) ) {
			return true;
		}
		return unlink( $path );
	}

	/**
	 * Whether a path is the directory itself or a file inside it.
	 *
	 * @param string $path Absolute or relative path.
	 * @return bool
	 */
	public function is_inside( $path ) {
		$real_dir = realpath( $this->dir );
		if ( false === $real_dir ) {
			return false;
		}
		$real_path = realpath( $path );
		if ( false === $real_path ) {
			return false;
		}
		if ( $real_path === $real_dir ) {
			return true;
		}
		$prefix = $real_dir . DIRECTORY_SEPARATOR;
		return 0 === strpos( $real_path, $prefix );
	}

	/**
	 * Basename is a single snippet file name with no directories.
	 *
	 * @param string $filename File name.
	 * @return bool
	 */
	public function is_safe_filename( $filename ) {
		if ( ! is_string( $filename ) || '' === $filename ) {
			return false;
		}
		if ( basename( $filename ) !== $filename ) {
			return false;
		}
		if ( 'index.php' === $filename ) {
			return false;
		}
		return 1 === preg_match( '/^[a-zA-Z0-9\-_\.]+\.php(\.disabled)?$/', $filename );
	}

	/**
	 * Enabled PHP snippets. Skips index.php and files that have a .disabled sibling.
	 *
	 * @return string[] Absolute paths.
	 */
	public function enabled_php_files() {
		if ( ! is_dir( $this->dir ) ) {
			return array();
		}
		$paths = glob( $this->dir . '/*.php' );
		if ( ! is_array( $paths ) ) {
			return array();
		}
		$files = array();
		foreach ( $paths as $path ) {
			if ( ! $this->is_loadable_php( $path ) ) {
				continue;
			}
			$files[] = $path;
		}
		return $files;
	}

	/**
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public function is_loadable_php( $path ) {
		if ( 'index.php' === basename( $path ) ) {
			return false;
		}
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}
		if ( 'php' !== pathinfo( $path, PATHINFO_EXTENSION ) ) {
			return false;
		}
		if ( is_file( $path . '.disabled' ) ) {
			return false;
		}
		return $this->is_inside( $path );
	}

	/**
	 * @param string $filename Basename ending in .php.
	 * @return bool
	 */
	public function is_enabled( $filename ) {
		if ( ! $this->is_safe_filename( $filename ) || substr( $filename, -10 ) === '.disabled' ) {
			return false;
		}
		$path = $this->dir . '/' . $filename;
		return $this->is_loadable_php( $path );
	}

	/**
	 * @param string $filename Basename ending in .php.
	 * @return bool
	 */
	public function enable( $filename ) {
		if ( ! $this->is_safe_filename( $filename ) ) {
			return false;
		}
		$filename = preg_replace( '/\.disabled$/', '', $filename );
		$path     = $this->dir . '/' . $filename;
		$disabled = $path . '.disabled';
		if ( ! is_file( $disabled ) ) {
			return is_file( $path );
		}
		if ( ! $this->is_inside( $disabled ) ) {
			return false;
		}
		return rename( $disabled, $path );
	}

	/**
	 * @param string $filename Basename ending in .php.
	 * @return bool
	 */
	public function disable( $filename ) {
		$filename = preg_replace( '/\.disabled$/', '', (string) $filename );
		if ( ! $this->is_safe_filename( $filename ) ) {
			return false;
		}
		$path     = $this->dir . '/' . $filename;
		$disabled = $path . '.disabled';
		if ( is_file( $disabled ) ) {
			return true;
		}
		if ( ! $this->is_loadable_php( $path ) ) {
			return false;
		}
		return rename( $path, $disabled );
	}

	/**
	 * Rename a loaded snippet off and write a sidecar with the fatal message.
	 *
	 * @param string $path    Absolute path to the .php file.
	 * @param string $message Fatal message.
	 * @param int    $line    Line number.
	 * @return bool
	 */
	public function quarantine( $path, $message, $line ) {
		if ( ! $this->is_inside( $path ) || ! is_file( $path ) ) {
			return false;
		}
		if ( 'php' !== pathinfo( $path, PATHINFO_EXTENSION ) ) {
			return false;
		}
		$note = $path . '.error';
		file_put_contents( $note, $message . ' on line ' . (int) $line );
		$disabled = $path . '.disabled';
		if ( is_file( $disabled ) ) {
			return true;
		}
		return rename( $path, $disabled );
	}

	/**
	 * Enabled and disabled snippets for the admin table.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all_snippets() {
		if ( ! is_dir( $this->dir ) ) {
			return array();
		}
		$snippets = array();
		$php      = glob( $this->dir . '/*.php' );
		if ( is_array( $php ) ) {
			foreach ( $php as $path ) {
				if ( ! $this->is_loadable_php( $path ) ) {
					continue;
				}
				$snippets[] = array(
					'filename' => basename( $path ),
					'path'     => $path,
					'enabled'  => true,
					'status'   => 'enabled',
				);
			}
		}
		$disabled = glob( $this->dir . '/*.php.disabled' );
		if ( is_array( $disabled ) ) {
			foreach ( $disabled as $path ) {
				if ( ! $this->is_inside( $path ) ) {
					continue;
				}
				$name = basename( $path, '.disabled' );
				if ( 'index.php' === $name ) {
					continue;
				}
				$snippets[] = array(
					'filename' => $name,
					'path'     => $path,
					'enabled'  => false,
					'status'   => 'disabled',
				);
			}
		}
		return $snippets;
	}

	/**
	 * CSS and JS files that are not disabled.
	 *
	 * @return string[]
	 */
	public function asset_files() {
		if ( ! is_dir( $this->dir ) ) {
			return array();
		}
		$files = array();
		foreach ( array( 'css', 'js' ) as $ext ) {
			$paths = glob( $this->dir . '/*.' . $ext );
			if ( ! is_array( $paths ) ) {
				continue;
			}
			foreach ( $paths as $path ) {
				if ( is_file( $path . '.disabled' ) || ! $this->is_inside( $path ) ) {
					continue;
				}
				$files[] = $path;
			}
		}
		return $files;
	}
}
