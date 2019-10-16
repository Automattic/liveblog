<?php

/**
 * A very basic regex-based Markdown parser. Supports the
 * following elements (and can be extended via MarkdownParser::add_rule()):
 *
 * Forked from Slimedown
 *
 * - Headers
 * - Links
 * - Bold
 * - Emphasis
 * - Deletions
 * - Quotes
 * - Blockquotes
 * - Ordered/unordered lists
 */
class Liveblog_Markdown_Parser {
	/**
	 * Regex rules.
	 *
	 * Because of how slack builds out markdown bold will always use (*) while
	 * italic will always use (_)
	 *
	 * @var array
	 */
	public static $liner_rules = [
		'/\[([^\[]+)\]\(([^\)]+)\)/'   => '<a href=\'\2\'>\1</a>',  // links
		'/(\*\*|\*)(.*?)\1/'           => '<strong>\2</strong>', // bold
		'/(\_|__)(.*?)\1/'             => '<em>\2</em>', // emphasis
		'/\~(.*?)\~/'                  => '<del>\1</del>', // del
		'/\:\"(.*?)\"\:/'              => '<q>\1</q>', // quote
		'/<\/blockquote><blockquote>/' => "\n",  // fix extra blockquote
	];

	public static $block_rules = [
		'/(#+)(.*)/'     => 'header', // headers
		'/\n&gt;(.*)/'   => 'blockquote', // blockquotes
		'/\n([^\n]+)\n/' => 'paragraph', // add paragraphs
	];

	private static function paragraph( $line ) {
		$trimmed = trim( $line );
		if ( strpos( $trimmed, '<' ) === 0 ) {
			return $line;
		}
		return sprintf( "\n<p>%s</p>\n", $trimmed );
	}

	private static function blockquote( $item ) {
		return sprintf( "\n<blockquote>%s</blockquote>", trim( $item ) );
	}

	private static function header( $chars, $header ) {
		$level = strlen( $chars );
		return sprintf( '<h%d>%s</h%d>', $level, trim( $header ), $level );
	}
	/**
	 * Add a rule.
	 */
	public static function add_rule( $regex, $replacement ) {
		self::$rules[ $regex ] = $replacement;
	}
	/**
	 * Render some Markdown into HTML.
	 */
	public static function render( $text ) {
		$text = "\n" . $text . "\n";

		foreach ( self::$block_rules as $regex => $replacement ) {
			$text = preg_replace_callback(
				$regex,
				function ( $matches ) use ( $regex ) {
					if ( '/(#+)(.*)/' === $regex ) {
						return call_user_func( [ __CLASS__, Liveblog_Markdown_Parser::$block_rules[ $regex ] ], $matches[1], $matches[2] );
					} else {
						return call_user_func( [ __CLASS__, Liveblog_Markdown_Parser::$block_rules[ $regex ] ], $matches[1] );
					}
				},
				$text 
			);
		}

		foreach ( self::$liner_rules as $regex => $replacement ) {
			$text = preg_replace( $regex, $replacement, $text );
		}
		return trim( $text );
	}
}
