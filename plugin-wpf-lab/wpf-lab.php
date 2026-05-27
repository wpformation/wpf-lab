<?php
/**
 * Plugin Name:       WPF Lab — Blocs Direction B
 * Description:       11 blocs PHP custom (utility / localnav / banner / hero / stats / why / pillars / feature / methods-table / team / cta-final) pour la méthode M7-A du WPF-AI-LAB. Design Direction B Lab — palette bone + ink + acid + violet, Space Grotesk + JetBrains Mono. Zéro JavaScript, zéro build. UI sidebar Gutenberg auto-générée via le pattern WP 7.0 « PHP-only block registration » (`supports.autoRegister: true`, dev note Miguel Fonseca du 3 mars 2026, ticket Trac #64639).
 * Version:           1.4.1
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Fabrice Ducarme (généré via Claude Code)
 * License:           GPL-2.0-or-later
 * Text Domain:       wpf-lab-blocks
 *
 * @package WpfLabBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enregistre les 3 blocs via leurs block.json.
 * Le flag supports.autoRegister:true du block.json permet à WP de gérer
 * la registration automatiquement, mais on appelle quand même register_block_type()
 * pour pointer vers le render.php côté serveur.
 */
add_action( 'init', function () {
    $base = plugin_dir_path( __FILE__ ) . 'blocks/';
    $blocks = array(
        'lab-utility',
        'lab-localnav',
        'lab-banner',
        'lab-hero',
        'lab-stats',
        'lab-why',
        'lab-pillars',
        'lab-feature-twocol',
        'lab-methods-table',
        'lab-team',
        'lab-cta-final',
    );
    foreach ( $blocks as $slug ) {
        $dir = $base . $slug;
        if ( is_dir( $dir ) && file_exists( $dir . '/block.json' ) ) {
            register_block_type( $dir );
        }
    }
} );

/**
 * Enregistre la feuille de style globale (partagée par les 3 blocs).
 * Le handle est référencé dans chaque block.json via "style": "wpf-lab-blocks-style".
 */
add_action( 'init', function () {
    wp_register_style(
        'wpf-lab-blocks-style',
        plugins_url( 'style.css', __FILE__ ),
        array(),
        '1.4.1'
    );
} );

/**
 * Helper de rendu : convertit les classes de support couleur/spacing WP
 * en attributs HTML class+style propres, à coller dans le wrapper.
 *
 * @param array $attrs Attributs du bloc.
 * @return string Chaîne HTML : class="..." style="..."
 */
function wpf_lab_blocks_wrapper_attrs( $attrs ) {
    $classes = array();
    $styles  = array();

    if ( ! empty( $attrs['backgroundColor'] ) ) {
        $classes[] = 'has-' . $attrs['backgroundColor'] . '-background-color';
        $classes[] = 'has-background';
    } elseif ( ! empty( $attrs['style']['color']['background'] ) ) {
        $styles[] = 'background-color:' . $attrs['style']['color']['background'];
    }

    if ( ! empty( $attrs['textColor'] ) ) {
        $classes[] = 'has-' . $attrs['textColor'] . '-color';
        $classes[] = 'has-text-color';
    } elseif ( ! empty( $attrs['style']['color']['text'] ) ) {
        $styles[] = 'color:' . $attrs['style']['color']['text'];
    }

    if ( ! empty( $attrs['style']['spacing']['padding'] ) ) {
        foreach ( $attrs['style']['spacing']['padding'] as $side => $val ) {
            $styles[] = "padding-{$side}:{$val}";
        }
    }
    if ( ! empty( $attrs['style']['spacing']['margin'] ) ) {
        foreach ( $attrs['style']['spacing']['margin'] as $side => $val ) {
            $styles[] = "margin-{$side}:{$val}";
        }
    }

    $out = '';
    if ( $classes ) {
        $out .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
    }
    if ( $styles ) {
        $out .= ' style="' . esc_attr( implode( ';', $styles ) ) . '"';
    }
    return $out;
}
