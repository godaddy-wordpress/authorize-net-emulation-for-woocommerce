<?php
/**
 * Authorize.Net Emulation for WooCommerce
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Authorize.Net Emulation for WooCommerce to newer
 * versions in the future. If you wish to customize Authorize.Net Emulation for WooCommerce for your
 * needs please refer to https://docs.woocommerce.com/document/authorize-net/#emulation-mode for more information.
 *
 * @author      SkyVerge
 * @copyright   Copyright (c) 2021, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Authorize_Net\Emulation;

defined( 'ABSPATH' ) or exit;


/**
 * WordPress plugin readme.txt parser.
 *
 * Loosely based on https://github.com/markjaquith/WordPress-Plugin-Readme-Parser
 *
 * @since 1.0.0
 */
class ReadmeParser {


	/** @var string */
	private $contents;


	/** @var array */
	private $results = [];


	/** @var Parsedown markdown parser instance */
	private $parsedown;


	/**
	 * Construct the class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contents
	 */
	public function __construct( string $contents ) {

		$this->contents  = $contents;
		$this->parsedown = new Parsedown();
	}


	/**
	 * Parses the readme file.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function parse(): array {

		$this->trim_file_contents();
		$this->extract_plugin_details();
		$this->parse_sections();

		return $this->results;
	}


	/**
	 * Trims readme file contents.
	 *
	 * @since 1.0.0
	 */
	private function trim_file_contents() {

		$this->contents = trim( str_replace( [ "\r\n", "\r" ], "\n", $this->contents ) );

		if ( 0 === strpos( $this->contents, "\xEF\xBB\xBF" ) ) {
			$this->contents = substr( $this->contents, 3 );
		}
	}


	/**
	 * Extracts plugin details from the readme file.
	 *
	 * @since 1.0.0
	 */
	private function extract_plugin_details() {

		if ( preg_match( '|Requires at least:(.*)|i', $this->contents, $requires ) ) {
			$this->results['requires'] = $this->sanitize_text( $requires[1] );
		}

		if ( preg_match( '|Tested up to:(.*)|i', $this->contents, $tested ) ) {
			$this->results['tested'] = $this->sanitize_text( $tested[1] );
		}
	}


	/**
	 * Parses readme sections.
	 *
	 * @since 1.0.0
	 */
	private function parse_sections() {

		$this->results['sections'] = [];

		$parts = preg_split( '/^[\s]*==[\s]*(.+?)[\s]*==/m', $this->contents, - 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$count = count( $parts );

		// if there are less than 3 parts, it means the readme contains no sections at all or only the main plugin section
		if ( $count < 3 ) {
			return;
		}

		// start at 3 - skip the first 2 results, as these will be the plugin name and general details which we've already extracted
		for ( $i = 3; $i <= $count; $i += 2 ) {
			$title = $this->sanitize_text( $parts[ $i - 1 ] );
			$key   = str_replace( ' ', '_', strtolower( $title ) );

			if ( 'frequently_asked_questions' === $key ) {
				$key = 'faq';
			}

			$this->results['sections'][ $key ] = $this->parse_section_content( $parts[ $i ] );
		}
	}


	/**
	 * Parses section content into HTML
	 *
	 * @since 1.0.0
	 *
	 * @param string $text
	 * @return string
	 */
	private function parse_section_content( string $text ): string {

		// replace =Title= with <h4>Title</h4> (not part of markdown)
		$text = preg_replace( '/^[\s]*=[\s]+(.+?)[\s]+=/m', '<h4>$1</h4>', $text );

		return $this->parsedown->text( $text );
	}


	/**
	 * Performs basic sanitation on given text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text
	 * @return string
	 */
	private function sanitize_text( string $text ): string {

		return trim( esc_html( strip_tags( $text ) ) );
	}


}
