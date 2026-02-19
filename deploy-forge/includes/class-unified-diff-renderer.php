<?php
/**
 * Minimal unified diff renderer.
 *
 * WordPress ships Text_Diff_Renderer_inline and WP_Text_Diff_Renderer_Table
 * but has never included a unified-format renderer. This provides standard
 * unified diff output (@@ hunks, +/- line prefixes) using the same API as
 * the PEAR Text_Diff_Renderer base class.
 *
 * @package Deploy_Forge
 * @since   1.0.62
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified diff renderer extending the WP-bundled Text_Diff_Renderer.
 *
 * Produces standard unified diff format with @@ hunk headers and +/- prefixed lines.
 *
 * @since 1.0.62
 */
class Deploy_Forge_Unified_Diff_Renderer extends Text_Diff_Renderer {

	/**
	 * Number of leading context lines.
	 *
	 * @var int
	 */
	public $_leading_context_lines = 3; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase, PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Number of trailing context lines.
	 *
	 * @var int
	 */
	public $_trailing_context_lines = 3; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase, PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Format a block header in unified diff style.
	 *
	 * @param int $xbeg Start line in original.
	 * @param int $xlen Length in original.
	 * @param int $ybeg Start line in modified.
	 * @param int $ylen Length in modified.
	 * @return string The @@ header line.
	 */
	public function _blockHeader( $xbeg, $xlen, $ybeg, $ylen ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, PSR2.Methods.MethodDeclaration.Underscore
		if ( 1 !== $xlen ) {
			$xbeg .= ',' . $xlen;
		}
		if ( 1 !== $ylen ) {
			$ybeg .= ',' . $ylen;
		}
		return "@@ -$xbeg +$ybeg @@";
	}

	/**
	 * Format context (unchanged) lines.
	 *
	 * @param array $lines Lines to format.
	 * @return string Space-prefixed lines.
	 */
	public function _context( $lines ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, PSR2.Methods.MethodDeclaration.Underscore
		return $this->_lines( $lines, ' ' );
	}

	/**
	 * Format added lines.
	 *
	 * @param array $lines Lines to format.
	 * @return string Plus-prefixed lines.
	 */
	public function _added( $lines ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, PSR2.Methods.MethodDeclaration.Underscore
		return $this->_lines( $lines, '+' );
	}

	/**
	 * Format deleted lines.
	 *
	 * @param array $lines Lines to format.
	 * @return string Minus-prefixed lines.
	 */
	public function _deleted( $lines ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, PSR2.Methods.MethodDeclaration.Underscore
		return $this->_lines( $lines, '-' );
	}

	/**
	 * Format changed lines (deletions followed by additions).
	 *
	 * @param array $orig  Original lines.
	 * @param array $final Modified lines.
	 * @return string Combined deleted + added output.
	 */
	public function _changed( $orig, $final ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, PSR2.Methods.MethodDeclaration.Underscore, Universal.NamingConventions.NoReservedKeywordParameterNames.finalFound
		return $this->_deleted( $orig ) . $this->_added( $final );
	}
}
