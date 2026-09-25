<?php
/**
 * Snippet file headers.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Reads and updates the docblock header on a snippet file.
 *
 * @since 1.1.0
 */
class Mc_Functionality_Snippet_Meta {

	/**
	 * Parse header fields. Missing keys keep the historical defaults.
	 *
	 * @param string $content File contents.
	 * @return array{run_context: string, priority: int}
	 */
	public static function parse( $content ) {
		$metadata = array(
			'run_context'  => 'everywhere',
			'priority'     => 10,
			'type'         => 'php',
			'hook'         => '',
			'tags'         => '',
			'group'        => '',
			'logged_in'    => '',
			'role'         => '',
			'post_type'    => '',
			'url_contains' => '',
			'date_before'  => '',
			'date_after'   => '',
		);
		if ( ! is_string( $content ) ) {
			return $metadata;
		}
		if ( preg_match( '/Run-Context:\s*([a-zA-Z0-9\-]+)/', $content, $matches ) ) {
			$metadata['run_context'] = $matches[1];
		} elseif ( preg_match( '/run_context:\s*([a-zA-Z0-9\-]+)/', $content, $matches ) ) {
			$metadata['run_context'] = $matches[1];
		}
		if ( preg_match( '/Priority:\s*(\d+)/', $content, $matches ) ) {
			$metadata['priority'] = (int) $matches[1];
		} elseif ( preg_match( '/priority:\s*(\d+)/', $content, $matches ) ) {
			$metadata['priority'] = (int) $matches[1];
		}
		$lines = array(
			'type'         => '/Type:\s*([a-z]+)/',
			'hook'         => '/Hook:\s*([a-z_]+)/',
			'tags'         => '/Tags:\s*(.+)/',
			'group'        => '/Group:\s*(.+)/',
			'logged_in'    => '/Logged-In:\s*(yes|no)/',
			'role'         => '/Role:\s*([a-zA-Z0-9_\-,]+)/',
			'post_type'    => '/Post-Type:\s*([a-zA-Z0-9_\-]+)/',
			'url_contains' => '/URL-Contains:\s*(\S+)/',
			'date_before'  => '/Date-Before:\s*([0-9\-]+)/',
			'date_after'   => '/Date-After:\s*([0-9\-]+)/',
		);
		foreach ( $lines as $key => $pattern ) {
			if ( preg_match( $pattern, $content, $matches ) ) {
				$metadata[ $key ] = trim( $matches[1] );
			}
		}
		return $metadata;
	}

	/**
	 * @param array<string, mixed> $meta Parsed header.
	 * @return bool
	 */
	public static function has_condition( $meta ) {
		foreach ( array( 'logged_in', 'role', 'post_type', 'url_contains', 'date_before', 'date_after' ) as $key ) {
			if ( ! empty( $meta[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $meta    Parsed header.
	 * @param array<string, mixed> $context Request context.
	 * @return bool
	 */
	public static function matches( $meta, $context ) {
		if ( ! empty( $meta['logged_in'] ) ) {
			$want = 'yes' === $meta['logged_in'];
			$have = ! empty( $context['logged_in'] );
			if ( $want !== $have ) {
				return false;
			}
		}
		if ( ! empty( $meta['role'] ) ) {
			$roles = isset( $context['roles'] ) && is_array( $context['roles'] ) ? $context['roles'] : array();
			if ( ! in_array( $meta['role'], $roles, true ) ) {
				return false;
			}
		}
		if ( ! empty( $meta['post_type'] ) ) {
			$post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
			if ( $post_type !== $meta['post_type'] ) {
				return false;
			}
		}
		if ( ! empty( $meta['url_contains'] ) ) {
			$url = isset( $context['url'] ) ? (string) $context['url'] : '';
			if ( false === strpos( $url, $meta['url_contains'] ) ) {
				return false;
			}
		}
		$now = isset( $context['timestamp'] ) ? (int) $context['timestamp'] : 0;
		if ( ! empty( $meta['date_before'] ) && $now >= strtotime( $meta['date_before'] ) ) {
			return false;
		}
		if ( ! empty( $meta['date_after'] ) && $now <= strtotime( $meta['date_after'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function is_allowed_hook( $hook ) {
		return in_array( $hook, array( 'plugins_loaded', 'init', 'wp_head', 'wp_footer', 'admin_head', 'admin_footer', 'shortcode' ), true );
	}

	/**
	 * Set Run-Context and Priority without dropping other docblock lines.
	 *
	 * @param string $content     File contents.
	 * @param string $run_context Context value.
	 * @param int    $priority    Priority from 1 to 100.
	 * @return string
	 */
	public static function update( $content, $run_context, $priority ) {
		$run_context = preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $run_context );
		if ( '' === $run_context ) {
			$run_context = 'everywhere';
		}
		$priority = (int) $priority;
		if ( $priority < 1 ) {
			$priority = 1;
		}
		if ( $priority > 100 ) {
			$priority = 100;
		}
		if ( ! is_string( $content ) ) {
			$content = '';
		}

		if ( preg_match( '/Run-Context:\s*[a-zA-Z0-9\-]+/', $content ) ) {
			$content = preg_replace( '/Run-Context:\s*[a-zA-Z0-9\-]+/', 'Run-Context: ' . $run_context, $content, 1 );
		} elseif ( preg_match( '/run_context:\s*[a-zA-Z0-9\-]+/', $content ) ) {
			$content = preg_replace( '/run_context:\s*[a-zA-Z0-9\-]+/', 'Run-Context: ' . $run_context, $content, 1 );
		} else {
			$content = self::insert_header_line( $content, ' * Run-Context: ' . $run_context );
		}

		if ( preg_match( '/Priority:\s*\d+/', $content ) ) {
			$content = preg_replace( '/Priority:\s*\d+/', 'Priority: ' . $priority, $content, 1 );
		} elseif ( preg_match( '/priority:\s*\d+/', $content ) ) {
			$content = preg_replace( '/priority:\s*\d+/', 'Priority: ' . $priority, $content, 1 );
		} else {
			$content = self::insert_header_line( $content, ' * Priority: ' . $priority );
		}

		return $content;
	}

	/**
	 * Prepend the direct-access guard when it is missing.
	 *
	 * @param string $content File contents.
	 * @return string
	 */
	public static function ensure_guard( $content ) {
		if ( ! is_string( $content ) ) {
			$content = '';
		}
		if ( false !== strpos( $content, "defined( 'ABSPATH' )" ) || false !== strpos( $content, 'defined( "ABSPATH" )' ) ) {
			return $content;
		}
		$guard = "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n";
		if ( 0 === strpos( $content, '<?php' ) ) {
			return "<?php\n" . $guard . substr( $content, 5 );
		}
		return "<?php\n" . $guard . $content;
	}

	/**
	 * Insert a docblock line before the first closing comment, or create a header.
	 *
	 * @param string $content File contents.
	 * @param string $line    Line including the leading asterisk.
	 * @return string
	 */
	private static function insert_header_line( $content, $line ) {
		if ( preg_match( '/\/\*\*/', $content ) ) {
			return preg_replace( '/\*\//', $line . "\n */", $content, 1 );
		}
		$header = "/**\n" . $line . "\n */\n";
		if ( 0 === strpos( $content, '<?php' ) ) {
			return "<?php\n" . $header . substr( $content, 5 );
		}
		return $header . $content;
	}
}
