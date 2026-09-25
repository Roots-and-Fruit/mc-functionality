<?php
/**
 * Syntax check for PHP snippets.
 *
 * @package    Mc_Functionality
 * @subpackage Mc_Functionality/includes
 */

/**
 * Rejects empty snippets, a short denylist of process functions, and parse errors.
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

		$dangerous = array( 'eval', 'exec', 'system', 'shell_exec', 'passthru' );
		foreach ( $dangerous as $func ) {
			if ( false !== stripos( $php_code, $func . '(' ) ) {
				return new WP_Error(
					'dangerous_function',
					"The use of '{$func}()' function is not allowed for security reasons."
				);
			}
		}

		$code = trim( $php_code );
		if ( 0 !== strpos( $code, '<?php' ) ) {
			$code = "<?php\n" . $php_code;
		}

		try {
			token_get_all( $code, TOKEN_PARSE );
		} catch ( ParseError $error ) {
			return new WP_Error( 'syntax_error', $error->getMessage() );
		}

		return true;
	}
}
