<?php
/**
 * Plugin Name: WPF Édition au clic (lab)
 * Description: Prototype de laboratoire WPFormation : modifier en un clic, depuis le site, les textes, liens et images d'un contenu HTML balisé (data-wpf-edit), sur n'importe quel thème. Historique par les révisions natives de WordPress.
 * Version: 0.1.0
 * Author: WPFormation
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: wpf-clic
 */

defined( 'ABSPATH' ) || exit;

define( 'WPF_CLIC_VERSION', '0.1.0' );
define( 'WPF_CLIC_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-wpf-clic-markup.php';
require_once __DIR__ . '/includes/class-wpf-clic-rest.php';

/**
 * L'utilisateur courant peut-il modifier le contenu affiché ?
 */
function wpf_clic_can_edit_current() {
	if ( ! is_singular() || ! is_user_logged_in() ) {
		return false;
	}
	$post_id = get_queried_object_id();
	return $post_id && current_user_can( 'edit_post', $post_id );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! wpf_clic_can_edit_current() ) {
		return;
	}
	$post = get_post( get_queried_object_id() );
	wp_enqueue_media();
	wp_enqueue_style( 'wpf-clic', WPF_CLIC_URL . 'assets/wpf-clic.css', array(), WPF_CLIC_VERSION );
	wp_enqueue_script( 'wpf-clic', WPF_CLIC_URL . 'assets/wpf-clic.js', array(), WPF_CLIC_VERSION, true );
	wp_localize_script( 'wpf-clic', 'wpfClic', array(
		'endpoint' => esc_url_raw( rest_url( 'wpf-clic/v1/posts/' . $post->ID . '/fields' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'base'     => WPF_Clic_Markup::hash( $post->post_content ),
		'i18n'     => array(
			'toggleOn'  => __( 'Modifier la page', 'wpf-clic' ),
			'toggleOff' => __( 'Terminer', 'wpf-clic' ),
			'save'      => __( 'Enregistrer', 'wpf-clic' ),
			'cancel'    => __( 'Annuler', 'wpf-clic' ),
			'bold'      => __( 'Gras', 'wpf-clic' ),
			'italic'    => __( 'Italique', 'wpf-clic' ),
			'link'      => __( 'Lien', 'wpf-clic' ),
			'linkText'  => __( 'Texte du lien', 'wpf-clic' ),
			'linkUrl'   => __( 'Adresse du lien', 'wpf-clic' ),
			'image'     => __( 'Choisir une image', 'wpf-clic' ),
			'imageAlt'  => __( 'Texte alternatif', 'wpf-clic' ),
			'saved'     => __( 'Enregistré. Une révision a été créée.', 'wpf-clic' ),
			'conflict'  => __( 'La page a changé entre-temps. Rechargez-la avant de continuer.', 'wpf-clic' ),
			'error'     => __( 'Enregistrement impossible.', 'wpf-clic' ),
		),
	) );
} );

/**
 * Pour les visiteurs, on retire les attributs data-wpf-* du HTML servi.
 */
add_filter( 'the_content', function ( $content ) {
	if ( false === strpos( $content, 'data-wpf-' ) || wpf_clic_can_edit_current() ) {
		return $content;
	}
	return WPF_Clic_Markup::strip_markers( $content );
}, 20 );

add_action( 'rest_api_init', array( 'WPF_Clic_Rest', 'register' ) );
