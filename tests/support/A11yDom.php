<?php
/**
 * Server-side accessibility checks on rendered HTML (R1-19, R0-15 §2.2).
 *
 * A DOM-based subset of what axe-core checks in a browser: labels for form
 * controls, accessible names, positive tabindex, image alternatives, broken
 * ARIA id references, duplicate ids, table headers and document landmarks.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.

/**
 * Static checks; every method returns a list of human readable problems.
 */
final class A11yDom {

	/**
	 * Parse HTML (fragment or document) into an XPath.
	 *
	 * @param string $html HTML.
	 * @return \DOMXPath
	 */
	public static function xpath( string $html ): \DOMXPath {
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$wrapped  = false === stripos( $html, '<html' ) ? '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' : $html;
		$doc->loadHTML( $wrapped );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return new \DOMXPath( $doc );
	}

	/**
	 * Problems of a fragment (admin screen, block, panel).
	 *
	 * @param string $html HTML.
	 * @return list<string>
	 */
	public static function fragment_problems( string $html ): array {
		$xpath    = self::xpath( $html );
		$problems = array();
		$ids      = array();
		// Hidden inputs (WordPress nonce fields) are not exposed to assistive technology.
		foreach ( $xpath->query( '//*[@id][not(self::input[@type="hidden"])]' ) as $node ) {
			$id = $node instanceof \DOMElement ? $node->getAttribute( 'id' ) : '';
			if ( isset( $ids[ $id ] ) ) {
				$problems[] = 'duplicate id "' . $id . '"';
			}
			$ids[ $id ] = true;
		}
		foreach ( $xpath->query( '//input[not(@type="hidden") and not(@type="submit") and not(@type="button") and not(@type="reset") and not(@type="image")] | //select | //textarea' ) as $control ) {
			if ( $control instanceof \DOMElement && ! self::labelled( $xpath, $control ) ) {
				$problems[] = 'form control without label: ' . self::describe( $control );
			}
		}
		foreach ( $xpath->query( '//button | //input[@type="submit" or @type="button" or @type="reset"] | //a[@href]' ) as $control ) {
			if ( $control instanceof \DOMElement && '' === self::name( $xpath, $control ) ) {
				$problems[] = 'control without accessible name: ' . self::describe( $control );
			}
		}
		foreach ( $xpath->query( '//*[@tabindex]' ) as $node ) {
			if ( $node instanceof \DOMElement && (int) $node->getAttribute( 'tabindex' ) > 0 ) {
				$problems[] = 'positive tabindex: ' . self::describe( $node );
			}
		}
		foreach ( $xpath->query( '//img[not(@alt)]' ) as $node ) {
			$problems[] = 'image without alt: ' . self::describe( $node );
		}
		foreach ( $xpath->query( '//*[@aria-labelledby or @aria-describedby or @aria-controls]' ) as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			foreach ( array( 'aria-labelledby', 'aria-describedby', 'aria-controls' ) as $attribute ) {
				foreach ( explode( ' ', (string) preg_replace( '/\s+/', ' ', trim( $node->getAttribute( $attribute ) ) ) ) as $ref ) {
					if ( '' !== $ref && ! isset( $ids[ $ref ] ) ) {
						$problems[] = $attribute . ' points to missing id "' . $ref . '"';
					}
				}
			}
		}
		foreach ( $xpath->query( '//table[not(@role="presentation")]' ) as $table ) {
			if ( 0 === $xpath->query( './/th', $table )->length ) {
				$problems[] = 'data table without header cells';
			}
		}
		foreach ( $xpath->query( '//th[not(@scope)]' ) as $th ) {
			$problems[] = 'th without scope: ' . self::describe( $th );
		}
		foreach ( $xpath->query( '//fieldset' ) as $fieldset ) {
			if ( 0 === $xpath->query( './legend', $fieldset )->length ) {
				$problems[] = 'fieldset without legend';
			}
		}
		return $problems;
	}

	/**
	 * Problems of a full document (learning area, certificate, preferences page).
	 *
	 * @param string $html HTML document.
	 * @return list<string>
	 */
	public static function document_problems( string $html ): array {
		$problems = self::fragment_problems( $html );
		$xpath    = self::xpath( $html );
		$root     = $xpath->query( '/html' )->item( 0 );
		if ( ! $root instanceof \DOMElement || '' === $root->getAttribute( 'lang' ) ) {
			$problems[] = 'html without lang';
		}
		if ( 1 !== $xpath->query( '//main' )->length ) {
			$problems[] = 'not exactly one main landmark';
		}
		if ( 1 !== $xpath->query( '//h1' )->length ) {
			$problems[] = 'not exactly one h1';
		}
		if ( 1 !== $xpath->query( '//title' )->length || '' === trim( (string) $xpath->query( '//title' )->item( 0 )?->textContent ) ) {
			$problems[] = 'missing document title';
		}
		$navs  = array();
		$named = $xpath->query( '//nav' );
		foreach ( $named as $nav ) {
			$name = $nav instanceof \DOMElement ? self::name( $xpath, $nav ) : '';
			if ( '' === $name && $named->length > 1 ) {
				$problems[] = 'several nav landmarks, one without name';
			}
			if ( isset( $navs[ $name ] ) ) {
				$problems[] = 'two nav landmarks named "' . $name . '"';
			}
			$navs[ $name ] = true;
		}
		$headings = array();
		foreach ( $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $heading ) {
			$headings[] = (int) substr( $heading->nodeName, 1 );
		}
		$last = 0;
		foreach ( $headings as $level ) {
			if ( $level > $last + 1 ) {
				$problems[] = 'heading level skipped: h' . $last . ' -> h' . $level;
			}
			$last = $level;
		}
		return $problems;
	}

	/**
	 * Whether a form control has a label.
	 *
	 * @param \DOMXPath   $xpath   XPath.
	 * @param \DOMElement $control Control.
	 * @return bool
	 */
	private static function labelled( \DOMXPath $xpath, \DOMElement $control ): bool {
		if ( '' !== trim( $control->getAttribute( 'aria-label' ) ) || '' !== trim( $control->getAttribute( 'title' ) ) ) {
			return true;
		}
		if ( '' !== trim( $control->getAttribute( 'aria-labelledby' ) ) ) {
			return '' !== self::referenced_text( $xpath, $control->getAttribute( 'aria-labelledby' ) );
		}
		$id = $control->getAttribute( 'id' );
		if ( '' !== $id ) {
			foreach ( $xpath->query( '//label[@for="' . $id . '"]' ) as $label ) {
				if ( '' !== trim( (string) $label->textContent ) ) {
					return true;
				}
			}
		}
		for ( $parent = $control->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode ) {
			if ( 'label' === $parent->nodeName ) {
				return '' !== trim( (string) $parent->textContent );
			}
		}
		return false;
	}

	/**
	 * Accessible name (simplified accname: aria-labelledby, aria-label, text, value, alt, title).
	 *
	 * @param \DOMXPath   $xpath XPath.
	 * @param \DOMElement $node  Element.
	 * @return string
	 */
	public static function name( \DOMXPath $xpath, \DOMElement $node ): string {
		if ( '' !== trim( $node->getAttribute( 'aria-labelledby' ) ) ) {
			return self::referenced_text( $xpath, $node->getAttribute( 'aria-labelledby' ) );
		}
		if ( '' !== trim( $node->getAttribute( 'aria-label' ) ) ) {
			return trim( $node->getAttribute( 'aria-label' ) );
		}
		if ( 'input' === $node->nodeName ) {
			return trim( $node->getAttribute( 'value' ) );
		}
		if ( 'nav' === $node->nodeName ) {
			return '';
		}
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) $node->textContent ) );
		if ( '' === $text ) {
			foreach ( $xpath->query( './/img[@alt]', $node ) as $img ) {
				$text .= $img instanceof \DOMElement ? trim( $img->getAttribute( 'alt' ) ) : '';
			}
		}
		return '' !== $text ? $text : trim( $node->getAttribute( 'title' ) );
	}

	/**
	 * Text of the elements referenced by an id list.
	 *
	 * @param \DOMXPath $xpath XPath.
	 * @param string    $ids   Space separated ids.
	 * @return string
	 */
	private static function referenced_text( \DOMXPath $xpath, string $ids ): string {
		$text = '';
		foreach ( explode( ' ', (string) preg_replace( '/\s+/', ' ', trim( $ids ) ) ) as $id ) {
			foreach ( $xpath->query( '//*[@id="' . $id . '"]' ) as $node ) {
				$text .= ' ' . trim( (string) $node->textContent );
			}
		}
		return trim( $text );
	}

	/**
	 * Short description of an element for messages.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function describe( \DOMNode $node ): string {
		$out = '<' . $node->nodeName;
		if ( $node instanceof \DOMElement ) {
			foreach ( array( 'id', 'name', 'type', 'class', 'href' ) as $attribute ) {
				if ( $node->hasAttribute( $attribute ) ) {
					$out .= ' ' . $attribute . '="' . $node->getAttribute( $attribute ) . '"';
				}
			}
		}
		return $out . '>';
	}
}
