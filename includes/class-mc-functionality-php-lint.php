<?php
/**
 * Syntax check for PHP snippets.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Rejects empty snippets, a short denylist of calls, and parse errors.
 *
 * @since 1.1.0
 */
class Mc_Functionality_Php_Lint {

	/**
	 * @param string $php_code Snippet source.
	 * @return true|WP_Error
	 */
	public static function check( $php_code ) {
		if ( ! is_string( $php_code ) || '' === trim( $php_code ) ) {
			return new WP_Error( 'empty_content', 'Snippet content cannot be empty.' );
		}

		$code = trim( $php_code );
		if ( 0 !== strpos( $code, '<?php' ) ) {
			$code = "<?php\n" . $php_code;
		}

		try {
			$tokens = token_get_all( $code, TOKEN_PARSE );
		} catch ( ParseError $error ) {
			return new WP_Error( 'syntax_error', $error->getMessage() );
		}

		$calls = array( 'eval', 'exec', 'system', 'shell_exec', 'passthru', 'assert' );
		$count = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( '`' === $token ) {
				return new WP_Error( 'dangerous_function', 'Backtick execution is not allowed.' );
			}
			if ( ! is_array( $token ) ) {
				continue;
			}
			if ( T_EVAL === $token[0] ) {
				return self::rejected( 'eval' );
			}
			if ( in_array( $token[0], array( T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE ), true ) ) {
				return self::rejected( strtolower( $token[1] ) );
			}
			if ( T_STRING !== $token[0] || ! in_array( strtolower( $token[1] ), $calls, true ) ) {
				continue;
			}
			$next = self::significant( $tokens, $i + 1, 1 );
			if ( '(' !== $next ) {
				continue;
			}
			$prev = self::significant( $tokens, $i - 1, -1 );
			if ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true ) ) {
				continue;
			}
			return self::rejected( strtolower( $token[1] ) );
		}

		return true;
	}

	/**
	 * @param string $name Call name.
	 * @return WP_Error
	 */
	private static function rejected( $name ) {
		return new WP_Error(
			'dangerous_function',
			"The use of '{$name}' is not allowed for security reasons."
		);
	}

	/**
	 * Next or previous token that is not whitespace or a comment.
	 *
	 * @param array<int, array<int, mixed>|string> $tokens Token list.
	 * @param int                                   $index  Start index.
	 * @param int                                   $step   1 or -1.
	 * @return array<int, mixed>|string|null
	 */
	private static function significant( $tokens, $index, $step ) {
		$count = count( $tokens );
		while ( $index >= 0 && $index < $count ) {
			$token = $tokens[ $index ];
			$index += $step;
			if ( ! is_array( $token ) ) {
				return $token;
			}
			if ( in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			return $token;
		}
		return null;
	}
}
