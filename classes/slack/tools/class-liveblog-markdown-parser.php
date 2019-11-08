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
		'/\[([^\[]+)\]\(([^\)]+)\)/'     => '<a href=\'\2\'>\1</a>',  // links
		'/[*]{1,2}((?:\\\\\*|[^*]|[*][^*]*+[*])+?)[*]{1,2}(?![*])/' => '<strong>\1</strong>', // bold
		'/(?<!¯.)_{1,2}((?:\\\\_|[^_]|__[^_]*__)+?)_{1,2}(?!_)\b/' => '<em>\1</em>', // emphasis
		'/\~(.*?)\~/'                    => '<del>\1</del>', // del
		'/\:\"(.*?)\"\:/'                => '<q>\1</q>', // quote
		'/<\/ul><ul>/'                   => '', // fix extra ul
		'/<\/ul>\n<ul>/'                 => "\n", // fix extra ul
		'/<\/ol><ol>/'                   => '', // fix extra ol
		'/<\/ol>\n<ol>/'                 => "\n", // fix extra ol
		'/<\/blockquote><blockquote>/'   => '', // fix extra blockquote
		'/<\/blockquote>\n<blockquote>/' => "\n", // fix extra blockquote
		'/<em>\(ツ\)<\/em>/'              => '_(ツ)_', // fix shrug
	];

	/**
	 * Block level rules
	 *
	 * @var array
	 */
	public static $block_rules = [
		'/\n(#+)(.*)/'                           => 'header', // headers
		'/\n\* (.*)/'                            => 'ul_list', // ul lists
		'/\n\• (.*)/'                            => 'ul_list', // ul lists
		'/\n[0-9]+\. (.*)/'                      => 'ol_list', // ol lists
		'/&gt;&gt;&gt;(.*\n[\s\S]*?\n[^\n]+)\n/' => 'blockquote', // blockquotes
		'/\n&gt;(.*)/'                           => 'blockquote', // blockquotes
		'/([^\n]+)\n/'                           => 'paragraph', // add paragraphs
	];

	/**
	 * Generate paragraph tag
	 *
	 * @param $line
	 *
	 * @return string
	 */
	private static function paragraph( $line ) {
		$trimmed = trim( $line );
		if ( strpos( $trimmed, '<' ) === 0 ) {
			return $line;
		}
		return sprintf( "<p>%s</p>\n", $trimmed );
	}

	/**
	 * Generate unordered list tags
	 *
	 * @param $item
	 *
	 * @return string
	 */
	private static function ul_list( $item ) {
		return sprintf( "\n<ul>\n\t<li>%s</li>\n</ul>", trim( $item ) );
	}

	/**
	 * Generate ordered list tags
	 *
	 * @param $item
	 *
	 * @return string
	 */
	private static function ol_list( $item ) {
		return sprintf( "\n<ol>\n\t<li>%s</li>\n</ol>", trim( $item ) );
	}

	/**
	 * Generate blockquote tag
	 *
	 * @param $item
	 *
	 * @return string
	 */
	private static function blockquote( $item ) {
		return sprintf( "<blockquote>\n\n%s\n</blockquote>\n", trim( $item ) );
	}

	/**
	 * Generate header tags
	 *
	 * @param $chars
	 * @param $header
	 *
	 * @return string
	 */
	private static function header( $chars, $header ) {
		$level = strlen( $chars );
		return sprintf( '<h%d>%s</h%d>', $level, trim( $header ), $level );
	}

	/**
	 * Add custom rule.
	 *
	 * @param $regex
	 * @param $replacement
	 */
	public static function add_rule( $regex, $replacement ) {
		self::$rules[ $regex ] = $replacement;
	}

	/**
	 * Render some Markdown into HTML
	 *
	 * @param $text
	 *
	 * @return string
	 */
	public static function render( $text ) {
		$text = "\n" . $text . "\n";
		foreach ( self::$block_rules as $regex => $replacement ) {
			$text = preg_replace_callback(
				$regex,
				function ( $matches ) use ( $regex ) {
					if ( '/\n(#+)(.*)/' === $regex ) {
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
