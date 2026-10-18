<?php
/**
 * Lecture et réécriture ciblée du HTML balisé : un champ = un élément portant
 * data-wpf-id (identifiant stable) et data-wpf-edit (text, rich, link ou image).
 */

defined( 'ABSPATH' ) || exit;

class WPF_Clic_Markup {

	const VOID_TAGS = array( 'img', 'br', 'hr', 'input', 'source', 'wbr' );

	/** Balises acceptées pour chaque type de champ. */
	const TYPE_TAGS = array(
		'text'  => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'li', 'strong', 'em', 'small', 'label', 'figcaption', 'blockquote', 'cite', 'td', 'th', 'dt', 'dd', 'div' ),
		'rich'  => array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote', 'figcaption', 'div', 'section', 'article', 'td' ),
		'link'  => array( 'a' ),
		'image' => array( 'img' ),
	);

	const BLOCK_CONTAINERS = array( 'div', 'section', 'article', 'td', 'blockquote' );

	public static function hash( $html ) {
		return md5( (string) $html );
	}

	public static function valid_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,63}$/i', $id );
	}

	/**
	 * Localise l'élément portant data-wpf-id="$id".
	 *
	 * @return array|WP_Error tag, type, open_start, open_end, close_start, close_end.
	 */
	public static function locate( $html, $id ) {
		if ( ! self::valid_id( $id ) ) {
			return new WP_Error( 'wpf_clic_bad_id', 'Identifiant de champ invalide.', array( 'status' => 400 ) );
		}
		$pattern = '/\sdata-wpf-id\s*=\s*(["\'])' . preg_quote( $id, '/' ) . '\1/i';
		$count   = preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE );
		if ( 1 !== $count ) {
			return new WP_Error( 'wpf_clic_not_unique', 0 === $count ? 'Champ introuvable.' : 'Identifiant présent plusieurs fois.', array( 'status' => 0 === $count ? 404 : 409 ) );
		}
		$attr_pos   = $matches[0][0][1];
		$open_start = strrpos( substr( $html, 0, $attr_pos ), '<' );
		if ( false === $open_start || ! preg_match( '/^<([a-z][a-z0-9-]*)/i', substr( $html, $open_start, 40 ), $m ) ) {
			return new WP_Error( 'wpf_clic_parse', 'Balise ouvrante introuvable.', array( 'status' => 422 ) );
		}
		$tag      = strtolower( $m[1] );
		$open_end = self::tag_end( $html, $open_start );
		if ( false === $open_end || $open_end <= $attr_pos ) {
			return new WP_Error( 'wpf_clic_parse', 'Balise ouvrante incomplète.', array( 'status' => 422 ) );
		}

		$opener = new WP_HTML_Tag_Processor( substr( $html, $open_start, $open_end - $open_start ) );
		$opener->next_tag();
		$type = strtolower( (string) $opener->get_attribute( 'data-wpf-edit' ) );
		if ( ! isset( self::TYPE_TAGS[ $type ] ) || ! in_array( $tag, self::TYPE_TAGS[ $type ], true ) ) {
			return new WP_Error( 'wpf_clic_type', 'Type de champ non pris en charge pour cette balise.', array( 'status' => 422 ) );
		}

		if ( in_array( $tag, self::VOID_TAGS, true ) ) {
			$close_start = $open_end;
			$close_end   = $open_end;
		} else {
			$close = self::matching_close( $html, $tag, $open_end );
			if ( ! $close ) {
				return new WP_Error( 'wpf_clic_parse', 'Balise fermante introuvable.', array( 'status' => 422 ) );
			}
			list( $close_start, $close_end ) = $close;
		}

		return compact( 'tag', 'type', 'open_start', 'open_end', 'close_start', 'close_end' );
	}

	/** Position juste après le « > » de la balise ouvrante, en ignorant ceux des valeurs entre guillemets. */
	private static function tag_end( $html, $start ) {
		$quote = '';
		$len   = strlen( $html );
		for ( $i = $start + 1; $i < $len; $i++ ) {
			$c = $html[ $i ];
			if ( $quote ) {
				if ( $c === $quote ) {
					$quote = '';
				}
			} elseif ( '"' === $c || "'" === $c ) {
				$quote = $c;
			} elseif ( '>' === $c ) {
				return $i + 1;
			}
		}
		return false;
	}

	/** Balise fermante correspondante, en comptant les balises de même nom imbriquées. */
	private static function matching_close( $html, $tag, $from ) {
		$re    = '/<(\/?)' . preg_quote( $tag, '/' ) . '(?=[\s>\/])(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i';
		$depth = 1;
		$pos   = $from;
		while ( preg_match( $re, $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$offset = $m[0][1];
			$pos    = $offset + strlen( $m[0][0] );
			if ( '/' === $m[1][0] ) {
				$depth--;
				if ( 0 === $depth ) {
					return array( $offset, $pos );
				}
			} elseif ( '/>' !== substr( $m[0][0], -2 ) ) {
				$depth++;
			}
		}
		return null;
	}

	public static function inner( $html, $loc ) {
		return substr( $html, $loc['open_end'], $loc['close_start'] - $loc['open_end'] );
	}

	public static function replace_inner( $html, $loc, $inner ) {
		return substr_replace( $html, $inner, $loc['open_end'], $loc['close_start'] - $loc['open_end'] );
	}

	/** Modifie des attributs de l'élément ciblé (null supprime l'attribut). */
	public static function set_attributes( $html, $id, array $attrs ) {
		$p = new WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag() ) {
			if ( $id !== $p->get_attribute( 'data-wpf-id' ) ) {
				continue;
			}
			foreach ( $attrs as $name => $value ) {
				if ( null === $value ) {
					$p->remove_attribute( $name );
				} else {
					$p->set_attribute( $name, (string) $value );
				}
			}
			break;
		}
		return $p->get_updated_html();
	}

	/** Retire les marqueurs pour les visiteurs. */
	public static function strip_markers( $html ) {
		$p = new WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag() ) {
			foreach ( array( 'data-wpf-id', 'data-wpf-edit', 'data-wpf-label' ) as $name ) {
				if ( null !== $p->get_attribute( $name ) ) {
					$p->remove_attribute( $name );
				}
			}
		}
		return $p->get_updated_html();
	}

	/** Balises autorisées dans un champ « rich », selon le conteneur. */
	public static function rich_allowed( $tag ) {
		$inline = array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'br'     => array(),
			'a'      => array( 'href' => true, 'title' => true, 'target' => true, 'rel' => true ),
		);
		if ( in_array( $tag, self::BLOCK_CONTAINERS, true ) ) {
			$inline += array( 'p' => array(), 'ul' => array(), 'ol' => array(), 'li' => array() );
		}
		return $inline;
	}
}
