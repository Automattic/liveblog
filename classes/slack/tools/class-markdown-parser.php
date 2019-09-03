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
class WPCOM_Liveblog_Markdown_Parser {
	public static $liner_rules = [
		'/\[([^\[]+)\]\(([^\)]+)\)/'   => '<a href=\'\2\'>\1</a>',  // links
		'/(\*\*|__)(.*?)\1/'           => '<strong>\2</strong>', // bold
		'/(\*|_)(.*?)\1/'              => '<em>\2</em>', // emphasis
		'/\~(.*?)\~/'                  => '<del>\1</del>', // del
		'/\:\"(.*?)\"\:/'              => '<q>\1</q>', // quote
		'/<\/ul><ul>/'                 => '', // fix extra ul
		'/<\/ol><ol>/'                 => '', // fix extra ol
		'/<\/blockquote><blockquote>/' => "\n",  // fix extra blockquote
	];

	public static $block_rules = [
		'/(#+)(.*)/'       => 'header', // headers
		'/\n\*(.*)/'       => 'ul_list', // ul lists
		'/\n[0-9]+\.(.*)/' => 'ol_list', // ol lists
		'/\n&gt;(.*)/'     => 'blockquote', // blockquotes
		'/\n([^\n]+)\n/'   => 'paragraph', // add paragraphs
	];

	private static function paragraph( $line ) {
		$trimmed = trim( $line );
		if ( strpos( $trimmed, '<' ) === 0 ) {
			return $line;
		}
		return sprintf( "\n<p>%s</p>\n", $trimmed );
	}
	private static function ul_list( $item ) {
		return sprintf( "\n<ul>\n\t<li>%s</li>\n</ul>", trim( $item ) );
	}
	private static function ol_list( $item ) {
		return sprintf( "\n<ol>\n\t<li>%s</li>\n</ol>", trim( $item ) );
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
						return call_user_func( [ __CLASS__, Markdown_Parser::$block_rules[ $regex ] ], $matches[1], $matches[2] );
					} else {
						return call_user_func( [ __CLASS__, Markdown_Parser::$block_rules[ $regex ] ], $matches[1] );
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
