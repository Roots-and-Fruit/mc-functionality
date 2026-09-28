<?php
/**
 * Loads snippet files from the store.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Includes enabled PHP snippets and quarantines the file that raised a fatal.
 *
 * @since 1.0.0
 */
class Mc_Functionality_Snippet_Loader {

	const FATAL_ERROR_TYPES = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );

	/**
	 * @var string[]
	 */
	public $enqueued_styles = array();

	/**
	 * @var string[]
	 */
	public $enqueued_scripts = array();

	/**
	 * @var Mc_Functionality_Snippet_Store
	 */
	private $store;

	/**
	 * Snippet paths currently inside require_once, innermost last.
	 *
	 * @var string[]
	 */
	private static $running = array();

	/**
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Directory the shutdown handler is allowed to quarantine.
	 *
	 * @var string
	 */
	private static $store_dir = '';

	/**
	 * @param string|null $dir Optional directory. Tests pass a temp dir.
	 */
	public function __construct( $dir = null ) {
		require_once __DIR__ . '/class-mc-functionality-snippet-store.php';
		$this->store = new Mc_Functionality_Snippet_Store( $dir );
		self::$store_dir = $this->store->get_dir();
		$this->register_shutdown();
	}

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private $scheduled = array();

	/**
	 * Record CSS and JS files. Do not include them.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		foreach ( $this->store->asset_files() as $path ) {
			$ext = pathinfo( $path, PATHINFO_EXTENSION );
			if ( 'css' === $ext ) {
				$this->enqueued_styles[] = $path;
				if ( function_exists( 'wp_enqueue_style' ) ) {
					wp_enqueue_style( 'mc-snippet-' . md5( $path ), $path, array(), (string) filemtime( $path ) );
				}
			}
			if ( 'js' === $ext ) {
				$this->enqueued_scripts[] = $path;
				if ( function_exists( 'wp_enqueue_script' ) ) {
					wp_enqueue_script( 'mc-snippet-' . md5( $path ), $path, array(), (string) filemtime( $path ), true );
				}
			}
		}
	}

	/**
	 * Render a content snippet for [mc_snippet id="basename"].
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		require_once __DIR__ . '/class-mc-functionality-snippet-meta.php';
		$id = '';
		if ( is_array( $atts ) && isset( $atts['id'] ) ) {
			$id = (string) $atts['id'];
		}
		$id = basename( $id );
		if ( '' === $id ) {
			return '';
		}
		$paths = $this->store->file_paths( $id );
		if ( is_object( $paths ) || ! is_file( $paths['enabled'] ) ) {
			return '';
		}
		$path = $paths['enabled'];
		$meta = Mc_Functionality_Snippet_Meta::parse( (string) file_get_contents( $path ) );
		if ( 'content' !== $meta['type'] ) {
			return '';
		}
		self::push( $path );
		ob_start();
		include $path;
		$output = ob_get_clean();
		self::pop();
		if ( ! is_string( $output ) ) {
			return '';
		}
		return $output;
	}

	/**
	 * Include every enabled PHP file that is allowed to run now.
	 *
	 * @param array<string, mixed>|null $context Optional request context for tests.
	 * @return void
	 */
	public function load_snippets( $context = null ) {
		if ( $this->store->is_disabled() ) {
			return;
		}
		require_once __DIR__ . '/class-mc-functionality-snippet-meta.php';
		if ( null === $context ) {
			$context = self::current_context();
		}
		$this->enqueue_assets();
		if ( function_exists( 'add_shortcode' ) ) {
			add_shortcode( 'mc_snippet', array( $this, 'render_shortcode' ) );
		}
		$files = $this->store->enabled_php_files();
		usort(
			$files,
			function ( $a, $b ) {
				$meta_a = Mc_Functionality_Snippet_Meta::parse( (string) file_get_contents( $a ) );
				$meta_b = Mc_Functionality_Snippet_Meta::parse( (string) file_get_contents( $b ) );
				return $meta_a['priority'] <=> $meta_b['priority'];
			}
		);
		foreach ( $files as $path ) {
			$meta = Mc_Functionality_Snippet_Meta::parse( (string) file_get_contents( $path ) );
			if ( 'php' !== $meta['type'] && '' !== $meta['type'] ) {
				continue;
			}
			if ( self::should_defer( $meta ) ) {
				$this->scheduled[] = array(
					'path' => $path,
					'meta' => $meta,
				);
				if ( function_exists( 'add_action' ) ) {
					$hook = isset( $meta['hook'] ) ? (string) $meta['hook'] : '';
					if ( ! Mc_Functionality_Snippet_Meta::is_allowed_hook( $hook ) || 'plugins_loaded' === $hook ) {
						$hook = 'init';
					}
					add_action(
						$hook,
						function () use ( $path ) {
							$this->include_if_matched( $path );
						},
						(int) $meta['priority']
					);
				}
				continue;
			}
			if ( ! self::context_allows( $meta, $context ) ) {
				continue;
			}
			$this->include_file( $path );
		}
	}

	/**
	 * Run snippets that were deferred, using the context at hook time.
	 *
	 * @param array<string, mixed> $context Request context.
	 * @return void
	 */
	public function run_scheduled( $context ) {
		require_once __DIR__ . '/class-mc-functionality-snippet-meta.php';
		foreach ( $this->scheduled as $item ) {
			if ( ! Mc_Functionality_Snippet_Meta::matches( $item['meta'], $context ) ) {
				continue;
			}
			if ( ! self::context_allows( $item['meta'], $context ) ) {
				continue;
			}
			$this->include_file( $item['path'] );
		}
	}

	/**
	 * @param array<string, mixed> $meta Parsed header.
	 * @return bool
	 */
	private static function should_defer( $meta ) {
		if ( Mc_Functionality_Snippet_Meta::has_condition( $meta ) ) {
			return true;
		}
		$hook = isset( $meta['hook'] ) ? (string) $meta['hook'] : '';
		if ( '' === $hook ) {
			return false;
		}
		if ( ! Mc_Functionality_Snippet_Meta::is_allowed_hook( $hook ) ) {
			return false;
		}
		return 'plugins_loaded' !== $hook;
	}

	/**
	 * @param array<string, mixed> $meta    Parsed header.
	 * @param array<string, mixed> $context Request context.
	 * @return bool
	 */
	private static function context_allows( $meta, $context ) {
		$where = isset( $meta['run_context'] ) ? (string) $meta['run_context'] : 'everywhere';
		$admin = ! empty( $context['is_admin'] );
		if ( 'admin-only' === $where && ! $admin ) {
			return false;
		}
		if ( 'frontend-only' === $where && $admin ) {
			return false;
		}
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function current_context() {
		$roles = array();
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( isset( $user->roles ) && is_array( $user->roles ) ) {
				$roles = $user->roles;
			}
		}
		$post_type = '';
		if ( function_exists( 'get_post_type' ) ) {
			$found = get_post_type();
			if ( is_string( $found ) ) {
				$post_type = $found;
			}
		}
		$url = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return array(
			'is_admin'  => function_exists( 'is_admin' ) && is_admin(),
			'logged_in' => function_exists( 'is_user_logged_in' ) && is_user_logged_in(),
			'roles'     => $roles,
			'post_type' => $post_type,
			'url'       => $url,
			'timestamp' => time(),
		);
	}

	/**
	 * Include one file when its header matches the current request.
	 *
	 * @param string $path Absolute path.
	 * @return void
	 */
	public function include_if_matched( $path ) {
		require_once __DIR__ . '/class-mc-functionality-snippet-meta.php';
		if ( ! is_file( $path ) ) {
			return;
		}
		$meta = Mc_Functionality_Snippet_Meta::parse( (string) file_get_contents( $path ) );
		$context = self::current_context();
		if ( ! Mc_Functionality_Snippet_Meta::matches( $meta, $context ) ) {
			return;
		}
		if ( ! self::context_allows( $meta, $context ) ) {
			return;
		}
		$this->include_file( $path );
	}

	/**
	 * @param string $path Absolute path.
	 * @return void
	 */
	public function include_file( $path ) {
		if ( ! $this->store->is_loadable_php( $path ) ) {
			return;
		}
		self::push( $path );
		require_once $path;
		self::pop();
	}

	/**
	 * @param string $path Absolute snippet path.
	 * @return void
	 */
	public static function push( $path ) {
		self::$running[] = $path;
	}

	/**
	 * @return void
	 */
	public static function pop() {
		array_pop( self::$running );
	}

	/**
	 * @return string
	 */
	public static function running_snippet() {
		if ( ! self::$running ) {
			return '';
		}
		return (string) end( self::$running );
	}

	/**
	 * @return void
	 */
	private function register_shutdown() {
		if ( self::$shutdown_registered ) {
			return;
		}
		self::$shutdown_registered = true;
		register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
	}

	/**
	 * Rename the running snippet when the request died on a fatal error.
	 *
	 * @return void
	 */
	public static function handle_shutdown() {
		$error = error_get_last();
		if ( ! is_array( $error ) || ! isset( $error['type'] ) ) {
			return;
		}
		if ( ! in_array( $error['type'], self::FATAL_ERROR_TYPES, true ) ) {
			return;
		}
		$path = self::running_snippet();
		if ( '' === $path || ! is_file( $path ) || '' === self::$store_dir ) {
			return;
		}
		$store = new Mc_Functionality_Snippet_Store( self::$store_dir );
		if ( ! $store->is_inside( $path ) ) {
			return;
		}
		$message = isset( $error['message'] ) ? $error['message'] : '';
		$line    = isset( $error['line'] ) ? (int) $error['line'] : 0;
		$store->quarantine( $path, $message, $line );
	}

	/**
	 * @param string $filename Basename.
	 * @return bool
	 */
	public function is_snippet_enabled( $filename ) {
		return $this->store->is_enabled( $filename );
	}

	/**
	 * @param string $filename Basename.
	 * @return bool
	 */
	public function enable_snippet( $filename ) {
		return $this->store->enable( $filename );
	}

	/**
	 * @param string $filename Basename.
	 * @return bool
	 */
	public function disable_snippet( $filename ) {
		return $this->store->disable( $filename );
	}

	/**
	 * @return string[]
	 */
	public function get_loaded_snippets() {
		return $this->store->enabled_php_files();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_snippets() {
		return $this->store->all_snippets();
	}

	/**
	 * @return Mc_Functionality_Snippet_Store
	 */
	public function store() {
		return $this->store;
	}
}
