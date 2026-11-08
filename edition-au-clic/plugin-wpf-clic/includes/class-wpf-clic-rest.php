<?php
/**
 * Route REST d'enregistrement d'un champ : POST wpf-clic/v1/posts/{id}/fields.
 * Le type du champ est lu dans le contenu enregistré, jamais dans la requête.
 */

defined( 'ABSPATH' ) || exit;

class WPF_Clic_Rest {

	public static function register() {
		register_rest_route( 'wpf-clic/v1', '/posts/(?P<id>\d+)/fields', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'save' ),
			'permission_callback' => function ( WP_REST_Request $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
			'args'                => array(
				'field'         => array( 'type' => 'string', 'required' => true ),
				'base'          => array( 'type' => 'string', 'required' => true ),
				'value'         => array( 'type' => 'string' ),
				'href'          => array( 'type' => 'string' ),
				'attachment_id' => array( 'type' => 'integer' ),
				'alt'           => array( 'type' => 'string' ),
			),
		) );
	}

	public static function save( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new WP_Error( 'wpf_clic_no_post', 'Contenu introuvable.', array( 'status' => 404 ) );
		}
		// wp_check_post_lock() vit dans l'admin : la REST ne le charge pas.
		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}
		if ( wp_check_post_lock( $post->ID ) ) {
			return new WP_Error( 'wpf_clic_locked', 'Quelqu\'un modifie ce contenu dans l\'éditeur.', array( 'status' => 423 ) );
		}
		$html = $post->post_content;
		if ( ! hash_equals( WPF_Clic_Markup::hash( $html ), (string) $request['base'] ) ) {
			return new WP_Error( 'wpf_clic_conflict', 'Le contenu a changé depuis l\'ouverture de la page.', array( 'status' => 409 ) );
		}

		$field = (string) $request['field'];
		$loc   = WPF_Clic_Markup::locate( $html, $field );
		if ( is_wp_error( $loc ) ) {
			return $loc;
		}

		$value = (string) $request['value'];
		// Un marqueur écrit dans une valeur rendrait un identifiant ambigu : on le refuse.
		foreach ( array( $value, (string) $request['href'], (string) $request['alt'] ) as $input ) {
			if ( false !== stripos( $input, 'data-wpf-' ) ) {
				return new WP_Error( 'wpf_clic_marker', 'Valeur refusée.', array( 'status' => 400 ) );
			}
		}
		$extra = array();
		switch ( $loc['type'] ) {
			case 'text':
				$text = sanitize_text_field( $value );
				if ( '' === $text || mb_strlen( $text ) > 500 ) {
					return new WP_Error( 'wpf_clic_length', 'Texte vide ou trop long (500 caractères au plus).', array( 'status' => 400 ) );
				}
				$new = WPF_Clic_Markup::replace_inner( $html, $loc, esc_html( $text ) );
				break;

			case 'rich':
				$rich = trim( force_balance_tags( wp_kses( $value, WPF_Clic_Markup::rich_allowed( $loc['tag'] ) ) ) );
				$rich = preg_replace( '/<a\s([^>]*target="_blank")(?![^>]*rel=)/i', '<a $1 rel="noopener"', $rich );
				if ( '' === wp_strip_all_tags( $rich ) ) {
					return new WP_Error( 'wpf_clic_length', 'Le texte ne peut pas être vide.', array( 'status' => 400 ) );
				}
				$new = WPF_Clic_Markup::replace_inner( $html, $loc, $rich );
				break;

			case 'link':
				$text = sanitize_text_field( $value );
				$href = esc_url_raw( (string) $request['href'], array( 'http', 'https', 'mailto', 'tel' ) );
				if ( '' === $text || '' === $href ) {
					return new WP_Error( 'wpf_clic_link', 'Texte ou adresse du lien invalide.', array( 'status' => 400 ) );
				}
				$new = WPF_Clic_Markup::replace_inner( $html, $loc, esc_html( $text ) );
				$new = WPF_Clic_Markup::set_attributes( $new, $field, array( 'href' => $href ) );
				$extra['href'] = $href;
				break;

			case 'image':
				$att = (int) $request['attachment_id'];
				if ( ! $att || ! wp_attachment_is_image( $att ) ) {
					return new WP_Error( 'wpf_clic_image', 'Choisissez une image de la médiathèque.', array( 'status' => 400 ) );
				}
				$src = wp_get_attachment_image_src( $att, 'large' );
				$alt = null !== $request['alt'] ? sanitize_text_field( (string) $request['alt'] ) : (string) get_post_meta( $att, '_wp_attachment_image_alt', true );
				$attrs = array(
					'src'    => $src[0],
					'width'  => $src[1],
					'height' => $src[2],
					'srcset' => wp_get_attachment_image_srcset( $att, 'large' ) ?: null,
					'sizes'  => wp_get_attachment_image_sizes( $att, 'large' ) ?: null,
					'alt'    => $alt,
				);
				$new   = WPF_Clic_Markup::set_attributes( $html, $field, $attrs );
				$extra = $attrs;
				break;

			default:
				return new WP_Error( 'wpf_clic_type', 'Type inconnu.', array( 'status' => 422 ) );
		}

		// Seul le fragment ci-dessus vient de la requête, et il est assaini. On évite que
		// kses réécrive le reste du HTML (styles, SVG) pour un compte sans unfiltered_html.
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			kses_remove_filters();
		}
		$result = wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $new ) ), true );
		if ( $kses ) {
			kses_init_filters();
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$saved = get_post( $post->ID )->post_content;
		$revs  = wp_get_post_revisions( $post->ID, array( 'numberposts' => 1, 'fields' => 'ids' ) );
		$out   = array(
			'base'     => WPF_Clic_Markup::hash( $saved ),
			'type'     => $loc['type'],
			'revision' => $revs ? (int) reset( $revs ) : 0,
		) + $extra;
		if ( in_array( $loc['type'], array( 'text', 'rich', 'link' ), true ) ) {
			$out['html'] = WPF_Clic_Markup::inner( $saved, WPF_Clic_Markup::locate( $saved, $field ) );
		}
		return rest_ensure_response( $out );
	}
}
