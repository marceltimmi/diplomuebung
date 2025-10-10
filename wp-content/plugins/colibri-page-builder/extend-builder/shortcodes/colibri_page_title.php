<?php

namespace ExtendBuilder;

add_shortcode( 'colibri_page_title', function ( $atts ) {
	ob_start();
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo colibri_page_title_html( $atts, colibri_titles() );
	$content = ob_get_clean();

	return $content;
} );


function colibri_titles() {
	$titles = get_current_theme_data( "global.titles", array() );
	return $titles;
}

function colibri_page_title_html( $atts, $titles ) {
	$tag = htmlspecialchars($atts['tag'], ENT_QUOTES, 'UTF-8');

	$allowed_tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');

	if (!in_array($tag, $allowed_tags)) {
		$tag = 'h2';
	}

	$title = wp_kses_post(get_title( $titles ));

	$html = "<span><" . $tag . " style='margin-bottom:0'>" . $title . "</" . $tag. "></span>";

	return $html;
}

function get_title( $titles ) {
	ob_start();
	$final_title = '';

	if ( is_404() ) {
		$final_title = $titles['errorPage'];
	} elseif ( is_search() ) {
		$title       = sprintf(  '%s', get_search_query() );
		$final_title = $title ? str_replace( "{TITLE}", $title, $titles['normalResultsPage'] ) : "";
	} elseif ( is_home() ) {
		if ( is_front_page() ) {
			$title = get_bloginfo( 'name' );
		} else {
			$title = single_post_title();
		}
		$final_title = $title ? str_replace( "{TITLE}", $title, $titles['normalResultsPage'] ) : "";
	} elseif ( is_archive() ) {
		if ( is_post_type_archive() ) {
			$final_title = post_type_archive_title( '', false );
		} else {
			if ( is_category() ) {

				$title       = sprintf( '%s', single_cat_title( '', false ) );
				$final_title = $title ? str_replace( "{TITLE}", $title, $titles['categoryArchive'] ) : "";
			} elseif
			( is_tag() ) {

				$title       = sprintf( '%s', single_tag_title( '', false ) );
				$final_title = $title ? str_replace( "{TITLE}", $title, $titles['tagArchive'] ) : "";
			} elseif
			( is_author() ) {

				$title       = sprintf(  '%s', '<span class="vcard">' . get_the_author() . '</span>' );
				$final_title = $title ? str_replace( "{TITLE}", $title, $titles['authorArchive'] ) : "";
			} elseif
			( is_year() ) {

				$title       = sprintf('%s',get_the_date( _x( 'Y', 'yearly archives date format','colibri-page-builder' ) ) );
				$final_title = $title ? str_replace( "{TITLE}", $title, $titles['yearArchive'] ) : "";
			} elseif
			( is_month() ) {

				$title       = sprintf( '%s', get_the_date( _x( 'F Y', 'monthly archives date format','colibri-page-builder' ) ) );
				$final_title = $title ? str_replace( "{TITLE}", $title, $titles['monthArchive'] ) : "";
			} elseif
			( is_day() ) {

				$title       = sprintf( '%s', get_the_date( _x( 'F j, Y', 'daily archives date format','colibri-page-builder' ) ) );
				$final_title = $title ? str_replace( "{TITLE}", $title, $titles['dayArchive'] ) : "";
			} elseif ( is_tax( 'post_format' ) ) {
				if ( is_tax( 'post_format', 'post-format-aside' ) ) {
					$final_title = _x( 'Asides', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-gallery' ) ) {
					$final_title = _x( 'Galleries', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-image' ) ) {
					$final_title = _x( 'Images', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-video' ) ) {
					$final_title = _x( 'Videos', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-quote' ) ) {
					$final_title = _x( 'Quotes', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-link' ) ) {
					$final_title = _x( 'Links', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-status' ) ) {
					$final_title = _x( 'Statuses', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-audio' ) ) {
					$final_title = _x( 'Audio', 'post format archive title', 'colibri-page-builder' );
				} elseif ( is_tax( 'post_format', 'post-format-chat' ) ) {
					$final_title = _x( 'Chats', 'post format archive title', 'colibri-page-builder' );
				}
			} elseif ( is_post_type_archive() ) {
				/* translators: Post type archive title. 1: Post type name */
				$final_title = sprintf( __( 'Archives: %s', 'colibri-page-builder' ), post_type_archive_title( '', false ) );
			} elseif ( is_tax() ) {
				$tax = get_taxonomy( get_queried_object()->taxonomy );
				/* translators: Taxonomy term archive title. 1: Taxonomy singular name, 2: Current taxonomy term */
				$final_title = sprintf(  '%1$s: %2$s', $tax->labels->singular_name, single_term_title( '', false ) );
			} else {
				$final_title = __( 'Archives', 'colibri-page-builder' );
			}
		}
	} elseif ( is_single() ) {
		$title = get_bloginfo( 'name' );

		global $post;
		if ( $post ) {
			// apply core filter
			$title = apply_filters( 'single_post_title', $post->post_title, $post );
		}
		$final_title = $title ? str_replace( "{TITLE}", $title, $titles['singlePost'] ) : "";
	} else {
		$title       = get_the_title();
		$final_title = $title ? str_replace( "{TITLE}", $title, $titles['normalPageFormat'] ) : "";
	}

	// @TODO fix mesmerize_wp_kses_post
	$value = apply_filters( 'colibri_page_builder/output/header_title', $final_title );

	$content = ob_get_clean();

	return $content ? $content : $value;
}




