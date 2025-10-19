<?php

namespace ExtendBuilder;


add_shortcode( 'colibri_contact_form', '\ExtendBuilder\colibri_contact_form_shortcode' );

function colibri_shortcode_is_colibri_contact_form( $shortcode ) {
	return strpos( $shortcode, 'colibri_contact_form' ) !== false;
}

function colibri_get_colibri_contact_form_shortcode( $shortcode ) {

	$matches_found = preg_match( "/shortcode=\"(.+)\"/", $shortcode, $matches );
	if ( ! $matches_found ) {
		return null;
	}
	$inner_shortcode = $matches[1];

	return colibri_shortcode_decode( $inner_shortcode );

}
/**
 * Colibri Contact Form Shortcode Handler
 *
 * This shortcode is a safe wrapper that only processes other registered WordPress shortcodes.
 *
 * Security measures:
 * - Only processes content through WordPress's built-in shortcode system
 * - Uses colibri_parse_and_render_shortcodes() which only finds and renders registered shortcodes
 * - Any content outside of shortcodes is completely ignored and skipped
 *
 * This function acts as a container that allows other shortcodes to be nested within
 * the newsletter functionality, providing a controlled way to render only legitimate
 * WordPress shortcodes while ignoring any other content.
 *
 * @param array $atts Shortcode attributes
 * @return string Rendered shortcode content (only the content from legitimate shortcodes)
 */
function colibri_contact_form_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'shortcode'           => '',
			'use_shortcode_style' => '0'
		),
		$atts
	);

	$atts['shortcode'] = colibri_shortcode_decode( $atts['shortcode'] );
	$shortcode         = $atts['shortcode'];
	if ( shortcode_render_can_apply_forminator_filters( $shortcode ) ) {
		if ( is_customize_preview() && colibri_forminator_is_auth_form( $shortcode ) ) {
			return colibri_forminator_get_auth_placeholder();
		}
	}
    // SECURITY NOTE: This function only processes registered WordPress shortcodes
    // No arbitrary content outside of shortcodes is processed or executed
    $parsed_shortcodes = colibri_parse_and_render_shortcodes($shortcode);

    if (empty($parsed_shortcodes)) {
        return '';
    }

    // Only concatenate the rendered output from legitimate shortcodes
    // Any content outside shortcodes is completely ignored
    $content = '';
    foreach ($parsed_shortcodes as $s) {
        $content .=  $s['rendered'];
    }

    return $content;
}

function colibri_forminator_get_auth_placeholder() {
	return '<p class="shortcode-placeholder-preview">Forminator\'s login and register forms are not visible if you are logged in</p>';
}

function colibri_forminator_is_auth_form( $shortcode ) {
	$id_found = preg_match( '/id="(\d+)"/', $shortcode, $matches );
	if ( ! $id_found ) {
		return false;
	}
	$form_id    = $matches[1];
	$form_class = null;

	//free
	if ( class_exists( '\Forminator_Custom_Form_Model' ) ) {
		$form_class = '\Forminator_Custom_Form_Model';
	}
	//pro
	if ( class_exists( '\Forminator_Form_Model' ) ) {
		$form_class = '\Forminator_Form_Model';
	}

	if ( ! $form_class ) {
		return false;
	}
	try {
		$model = $form_class::model()->load( $form_id );
		if ( ! $model ) {
			return false;
		}

		return in_array( $model->settings['form-type'], array( 'login', 'registration' ) );
	} catch ( \Exception $e ) {
		return false;
	}
}

function colibri_forminator_form_shortcode( $shortcode ) {

	$html             = do_shortcode( $shortcode );
	return $html;

}
