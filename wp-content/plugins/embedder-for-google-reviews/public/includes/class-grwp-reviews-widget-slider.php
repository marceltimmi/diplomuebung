<?php

class GRWP_Reviews_Widget_Slider
    extends
    GRWP_Google_Reviews_Output {

    /**
     * Slider HTML
     * @return string
     */
    public function render( $style_type, $link_user_profiles, $max_reviews = null, $show_place_info = false, $txt = '' ) {

        // error handling
        if ( $this->reviews_have_error ) {
            /* translators: no reviews available */
            return __( 'No reviews available', 'embedder-for-google-reviews' );

        }

		$hide_date = '';
		if ( isset($this->options['hide_date_string']) ) {
			if ( $this->options['hide_date_string'] !== '' ) {
				$hide_date = 'hide_date';
			}
		}

        $show_verified = false;
        if ( isset($this->options['show_verified']) ) {
            if ( $this->options['show_verified'] === '1' ) {
                $show_verified = true;
            }
        }

        $hide_slider_arrows = false;
        if ( isset($this->options['hide_slider_arrows']) ) {
            if ( $this->options['hide_slider_arrows'] === '1' ) {
                $hide_slider_arrows = true;
            }
        }

	    $google_svg = GR_PLUGIN_DIR_URL . 'dist/images/google-logo-svg.svg';
        $verified_svg = GR_PLUGIN_DIR_URL . 'dist/images/verified-badge.svg';
        $url = 'https://reviewsembedder.com';
	    $stars = $this->get_total_stars();

		$output = '';

	    $output = sprintf( '<div id="g-review" class="%s grwp_grid %s">', $style_type, $hide_date );

		if ( $show_place_info ) {

			$this->place_title = $this->place_title === '' ? 'Lorem Ipsum Business Title' : $this->place_title;

			$output .= '<div class="grwp_header">';
			$output .= '<div class="grwp_header-inner">';
			$output .= sprintf( '<h3 class="grwp_business-title">%s</h3>', $this->place_title );
			$output .= sprintf(
				'<span class="grwp_total-rating">%s</span><span class="grwp_5_stars">%s</span>',
				$this->rating_formatted,
                /* translators: out of 5 stars */
				__( 'Out of 5 stars', 'embedder-for-google-reviews' )
			);
			$output .= $stars;
			$output .= sprintf(
            /* translators: %s: total reviews */
				'<h3 class="grwp_overall">' . __( 'Overall rating out of %s Google reviews', 'embedder-for-google-reviews' ) . '</h3>',
				$this->total_reviews
			);
            if ($show_verified) {
                $output .= sprintf(
                /* translators: 'Verified by' badge */
                    '<div class="grwp_verified"><a href="%s" target="_blank">'.__('Verified by', 'embedder-for-google-reviews').' <img src="'.$verified_svg.'" alt="'.$txt.'" /></a></div>',
                    $url);
            }
			$output .= '</div></div>';

		}

	    $output .= '<div class="grwp_body">';

        // loop through reviews
        $output .= sprintf('<div id="g-review" class="%s">', $style_type);
        $slider_output = '';

        $count = 0;
        foreach ( $this->reviews as $review ) {

            if ( $max_reviews && is_numeric( $max_reviews ) && intval($max_reviews) <= $count ) {
                break;
            }

            $star_output = $this->get_star_output($review);

            $slide_duration = isset($this->options['slide_duration']) ? intval($this->options['slide_duration']) * 1000 : '';

            ob_start();
            require 'partials/slider/markup.php';
            $slider_output .= ob_get_clean();

            $count++;

        }

        ob_start();
        require 'partials/slider/slider-header.php';
        echo wp_kses( $slider_output, $this->allowed_html );
        require 'partials/slider/slider-footer.php';

        $output .= ob_get_clean();

        $output .= '</div></div></div>';

        return wp_kses( $output, $this->allowed_html );

    }

}
