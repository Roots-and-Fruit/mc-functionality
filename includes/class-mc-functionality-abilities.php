<?php
/**
 * Abilities API registration for snippet files.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Registers snippet abilities and the capabilities that gate them.
 *
 * @since 1.2.0
 */
class Mc_Functionality_Abilities {

	/**
	 * Hook registration, the REST lock, and the capability upgrade.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_grant_caps' ), 20 );
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'force_rest_off' ), 1000, 2 );
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Give Administrator the snippet capabilities once per upgrade.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function maybe_grant_caps() {
		if ( '1' === (string) get_option( 'mc_functionality_caps_version' ) ) {
			return;
		}
		self::grant_caps();
	}

	/**
	 * Add the snippet capabilities to the Administrator role.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function grant_caps() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'read_mc_snippets' );
			$role->add_cap( 'edit_mc_snippets' );
			$role->add_cap( 'delete_mc_snippets' );
		}
		update_option( 'mc_functionality_caps_version', '1' );
	}

	/**
	 * Keep these abilities off the Abilities REST API.
	 *
	 * @since 1.2.0
	 * @param array<string, mixed> $args Ability arguments.
	 * @param string               $name Ability ID.
	 * @return array<string, mixed>
	 */
	public static function force_rest_off( $args, $name ) {
		if ( ! is_string( $name ) || 0 !== strpos( $name, 'mc-functionality/' ) ) {
			return $args;
		}
		if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
			$args['meta'] = array();
		}
		$args['meta']['show_in_rest'] = false;
		return $args;
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_category() {
		wp_register_ability_category(
			'mc-functionality',
			array(
				'label'       => __( 'MC Functionality', 'mc-functionality' ),
				'description' => __( 'List, read, and change file-based snippets.', 'mc-functionality' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_abilities() {
		self::register_list();
		self::register_get();
		self::register_enable();
		self::register_disable();
		self::register_create();
		self::register_update();
		self::register_delete();
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public static function can_read( $input = null ) {
		unset( $input );
		return current_user_can( 'read_mc_snippets' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Added in grant_caps().
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public static function can_edit( $input = null ) {
		unset( $input );
		return current_user_can( 'edit_mc_snippets' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Added in grant_caps().
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public static function can_delete( $input = null ) {
		unset( $input );
		return current_user_can( 'delete_mc_snippets' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Added in grant_caps().
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array<int, array<string, string>>|WP_Error
	 */
	public static function execute_list( $input = null ) {
		$args   = self::args( $input );
		$status = '';
		if ( array_key_exists( 'status', $args ) && null !== $args['status'] && '' !== $args['status'] ) {
			$status = (string) $args['status'];
		}
		return self::store()->list_rows( $status );
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_get( $input = null ) {
		$filename = self::filename_from( $input );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}
		return self::present_read( self::store()->read( $filename ) );
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array{filename: string, status: string}|WP_Error
	 */
	public static function execute_enable( $input = null ) {
		$filename = self::filename_from( $input );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}
		$store = self::store();
		$read  = $store->read( $filename );
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		if ( 'enabled' !== $read['status'] ) {
			require_once __DIR__ . '/class-mc-functionality-php-lint.php';
			$lint = Mc_Functionality_Php_Lint::check( $read['content'] );
			if ( is_wp_error( $lint ) ) {
				return $lint;
			}
			if ( ! $store->enable( $read['filename'] ) ) {
				return new WP_Error( 'mc_functionality_snippet_data_unavailable', __( 'Unable to enable the snippet.', 'mc-functionality' ) );
			}
		}
		self::changed( 'mc-functionality/enable-snippet', $read['filename'] );
		return array(
			'filename' => $read['filename'],
			'status'   => 'enabled',
		);
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array{filename: string, status: string}|WP_Error
	 */
	public static function execute_disable( $input = null ) {
		$filename = self::filename_from( $input );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}
		$store = self::store();
		$read  = $store->read( $filename );
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		if ( ! $store->disable( $read['filename'] ) ) {
			return new WP_Error( 'mc_functionality_snippet_data_unavailable', __( 'Unable to disable the snippet.', 'mc-functionality' ) );
		}
		self::changed( 'mc-functionality/disable-snippet', $read['filename'] );
		return array(
			'filename' => $read['filename'],
			'status'   => 'disabled',
		);
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array{filename: string, status: string}|WP_Error
	 */
	public static function execute_create( $input = null ) {
		$filename = self::filename_from( $input );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}
		$content = self::content_from( $input );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$result = self::store()->create( $filename, $content );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::changed( 'mc-functionality/create-snippet', $result['filename'] );
		return $result;
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array{filename: string, status: string}|WP_Error
	 */
	public static function execute_update( $input = null ) {
		$filename = self::filename_from( $input );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}
		$content = self::content_from( $input );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$result = self::store()->update( $filename, $content );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::changed( 'mc-functionality/update-snippet', $result['filename'] );
		return $result;
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array{filename: string, status: string}|WP_Error
	 */
	public static function execute_delete( $input = null ) {
		$filename = self::filename_from( $input );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}
		$result = self::store()->delete( $filename );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::changed( 'mc-functionality/delete-snippet', $result['filename'] );
		return $result;
	}

	/**
	 * Snippet store for ability calls.
	 *
	 * @since 1.2.0
	 * @return Mc_Functionality_Snippet_Store
	 */
	public static function store() {
		/**
		 * Filters the snippet store used by abilities.
		 *
		 * @since 1.2.0
		 *
		 * @param Mc_Functionality_Snippet_Store|null $store Store instance. Null uses the default directory.
		 */
		$store = apply_filters( 'mc_functionality_snippet_store', null );
		if ( $store instanceof Mc_Functionality_Snippet_Store ) {
			return $store;
		}
		return new Mc_Functionality_Snippet_Store();
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	private static function register_list() {
		wp_register_ability(
			'mc-functionality/list-snippets',
			array(
				'label'               => __( 'List snippets', 'mc-functionality' ),
				'description'         => __( 'Lists snippet files with filename, status, type, and run context.', 'mc-functionality' ),
				'category'            => 'mc-functionality',
				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'enabled', 'disabled' ),
							'description' => __( 'Limit the list to enabled or disabled snippets.', 'mc-functionality' ),
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'filename'    => array( 'type' => 'string' ),
							'status'      => array(
								'type' => 'string',
								'enum' => array( 'enabled', 'disabled' ),
							),
							'type'        => array( 'type' => 'string' ),
							'run_context' => array( 'type' => 'string' ),
						),
					),
				),
				'meta'                => self::meta( true, false, true ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'execute_callback'    => array( __CLASS__, 'execute_list' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	private static function register_get() {
		wp_register_ability(
			'mc-functionality/get-snippet',
			array(
				'label'               => __( 'Read snippet', 'mc-functionality' ),
				'description'         => __( 'Returns one snippet source file and its parsed header.', 'mc-functionality' ),
				'category'            => 'mc-functionality',
				'input_schema'        => self::filename_schema(),
				'output_schema'       => self::read_schema(),
				'meta'                => self::meta( true, false, true ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'execute_callback'    => array( __CLASS__, 'execute_get' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	private static function register_enable() {
		wp_register_ability(
			'mc-functionality/enable-snippet',
			array(
				'label'               => __( 'Enable snippet', 'mc-functionality' ),
				'description'         => __( 'Enables a disabled snippet after the source passes the PHP lint check.', 'mc-functionality' ),
				'category'            => 'mc-functionality',
				'input_schema'        => self::filename_schema(),
				'output_schema'       => self::status_schema( array( 'enabled' ) ),
				'meta'                => self::meta( false, true, true ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'execute_callback'    => array( __CLASS__, 'execute_enable' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	private static function register_disable() {
		wp_register_ability(
			'mc-functionality/disable-snippet',
			array(
				'label'               => __( 'Disable snippet', 'mc-functionality' ),
				'description'         => __( 'Disables a snippet by renaming it so it does not run.', 'mc-functionality' ),
				'category'            => 'mc-functionality',
				'input_schema'        => self::filename_schema(),
				'output_schema'       => self::status_schema( array( 'disabled' ) ),
				'meta'                => self::meta( false, false, true ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'execute_callback'    => array( __CLASS__, 'execute_disable' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	private static function register_create() {
		wp_register_ability(
			'mc-functionality/create-snippet',
			array(
				'label'               => __( 'Create snippet', 'mc-functionality' ),
				'description'         => __( 'Creates a snippet file in the disabled state.', 'mc-functionality' ),
				'category'            => 'mc-functionality',
				'input_schema'        => self::write_schema(),
				'output_schema'       => self::status_schema( array( 'disabled' ) ),
				'meta'                => self::meta( false, false, false ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'execute_callback'    => array( __CLASS__, 'execute_create' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	private static function register_update() {
		wp_register_ability(
			'mc-functionality/update-snippet',
			array(
				'label'               => __( 'Update snippet', 'mc-functionality' ),
				'description'         => __( 'Replaces the source of a disabled snippet.', 'mc-functionality' ),
				'category'            => 'mc-functionality',
				'input_schema'        => self::write_schema(),
				'output_schema'       => self::status_schema( array( 'disabled' ) ),
				'meta'                => self::meta( false, true, true ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'execute_callback'    => array( __CLASS__, 'execute_update' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	private static function register_delete() {
		wp_register_ability(
			'mc-functionality/delete-snippet',
			array(
				'label'               => __( 'Delete snippet', 'mc-functionality' ),
				'description'         => __( 'Deletes one snippet file.', 'mc-functionality' ),
				'category'            => 'mc-functionality',
				'input_schema'        => self::filename_schema(),
				'output_schema'       => self::status_schema( array( 'deleted' ) ),
				'meta'                => self::meta( false, true, false ),
				'permission_callback' => array( __CLASS__, 'can_delete' ),
				'execute_callback'    => array( __CLASS__, 'execute_delete' ),
			)
		);
	}

	/**
	 * @since 1.2.0
	 * @param bool $reads_only  Whether the ability leaves files unchanged.
	 * @param bool $destructive Whether the ability can remove or replace running code.
	 * @param bool $idempotent  Whether repeating the same call has no further effect.
	 * @return array<string, mixed>
	 */
	private static function meta( $reads_only, $destructive, $idempotent ) {
		return array(
			'show_in_rest' => false,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly'    => $reads_only,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
		);
	}

	/**
	 * @since 1.2.0
	 * @return array<string, mixed>
	 */
	private static function filename_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'filename' ),
			'properties' => array(
				'filename' => array(
					'type'        => 'string',
					'description' => __( 'Snippet file name, such as example.php.', 'mc-functionality' ),
				),
			),
		);
	}

	/**
	 * @since 1.2.0
	 * @return array<string, mixed>
	 */
	private static function write_schema() {
		$schema                          = self::filename_schema();
		$schema['required'][]            = 'content';
		$schema['properties']['content'] = array(
			'type'        => 'string',
			'description' => __( 'PHP source to write.', 'mc-functionality' ),
		);
		return $schema;
	}

	/**
	 * @since 1.2.0
	 * @param string[] $statuses Allowed status values.
	 * @return array<string, mixed>
	 */
	private static function status_schema( $statuses ) {
		return array(
			'type'       => 'object',
			'properties' => array(
				'filename' => array( 'type' => 'string' ),
				'status'   => array(
					'type' => 'string',
					'enum' => $statuses,
				),
			),
		);
	}

	/**
	 * @since 1.2.0
	 * @return array<string, mixed>
	 */
	private static function read_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'filename' => array( 'type' => 'string' ),
				'status'   => array(
					'type' => 'string',
					'enum' => array( 'enabled', 'disabled' ),
				),
				'content'  => array( 'type' => 'string' ),
				'header'   => array(
					'type'       => 'object',
					'properties' => array(
						'run_context'  => array( 'type' => 'string' ),
						'priority'     => array( 'type' => 'integer' ),
						'type'         => array( 'type' => 'string' ),
						'hook'         => array( 'type' => 'string' ),
						'tags'         => array( 'type' => 'string' ),
						'group'        => array( 'type' => 'string' ),
						'logged_in'    => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string' ),
						'post_type'    => array( 'type' => 'string' ),
						'url_contains' => array( 'type' => 'string' ),
						'date_before'  => array( 'type' => 'string' ),
						'date_after'   => array( 'type' => 'string' ),
					),
				),
			),
		);
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>
	 */
	private static function args( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return $input;
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return string|WP_Error
	 */
	private static function filename_from( $input ) {
		$args = self::args( $input );
		if ( ! isset( $args['filename'] ) || ! is_string( $args['filename'] ) || '' === $args['filename'] ) {
			return new WP_Error( 'mc_functionality_missing_filename', __( 'A filename is required.', 'mc-functionality' ) );
		}
		return $args['filename'];
	}

	/**
	 * @since 1.2.0
	 * @param mixed $input Ability input.
	 * @return string|WP_Error
	 */
	private static function content_from( $input ) {
		$args = self::args( $input );
		if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) ) {
			return new WP_Error( 'mc_functionality_missing_content', __( 'Snippet content is required.', 'mc-functionality' ) );
		}
		return $args['content'];
	}

	/**
	 * Drop the absolute path from a store read.
	 *
	 * @since 1.2.0
	 * @param array<string, mixed>|WP_Error $read Store read result.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function present_read( $read ) {
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		unset( $read['path'] );
		return $read;
	}

	/**
	 * Record a successful file change.
	 *
	 * @since 1.2.0
	 * @param string $ability  Ability ID.
	 * @param string $filename Snippet basename.
	 * @return void
	 */
	private static function changed( $ability, $filename ) {
		/**
		 * Fires after an ability changes a snippet file.
		 *
		 * @since 1.2.0
		 *
		 * @param array{user_id: int, ability: string, filename: string} $change Change record.
		 */
		do_action(
			'mc_functionality_snippet_changed',
			array(
				'user_id'  => get_current_user_id(),
				'ability'  => $ability,
				'filename' => $filename,
			)
		);
	}
}
