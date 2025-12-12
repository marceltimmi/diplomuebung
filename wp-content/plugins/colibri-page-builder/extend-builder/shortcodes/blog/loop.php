<?php

namespace ExtendBuilder;

add_shortcode('colibri_item_template', function($attrs, $content = null) {
	$escaped_content = str_replace('<!---->', '', $content);
    $escaped_content = wp_kses_post($escaped_content);
    return do_shortcode($escaped_content);
});

add_shortcode('colibri_loop', '\ExtendBuilder\colibri_loop');


/**
 * Handles the [colibri_loop] shortcode used for rendering post loops on the frontend.
 *
 * @param array  $attrs   Shortcode attributes.
 * @param string $content Template content rendered for each post item.
 * @return string The rendered loop output.
 */
function colibri_loop($attrs, $content = null)
{
    ob_start();
    $atts = shortcode_atts(
        array(
            'query' => false,
            'no_posts_found_text' => 'No posts found',
            'posts' => '3',
            'filter_categories' => '',
            'filter_tags' => '',
            'filter_authors' => '',
            'order_by' => 'date',
            'order_type' => 'ASC',
            'post_order' => ''
        ),
        $attrs
    );

    $query = null;
    if ($atts['query'] == "true") {
	    $query = new \WP_Query(array(
		    'posts_per_page' =>  $atts['posts'],
            	    'ignore_sticky_posts' => 1,
		    'category_name' => $atts['filter_categories'],
		    'tag' => $atts['filter_tags'],
		    'author' => $atts['filter_authors'],
		    'orderby' => $atts['order_by'],
		    'order' => $atts['order_type'],
	    ));
    } else {
	    global $wp_query;
	    if (!$wp_query->in_the_loop) {
	    $query = $wp_query;
      }
    }

    $content = urldecode($content);
    $escaped_content = str_replace('<!---->', '', $content);
    /**
     * We sanitized the shortcode content this early using wp_kses_post() to ensure it is safe
     * before running do_shortcode(). This step removes script and other disallowed
     * HTML tags while preserving valid HTML structure and any nested shortcodes.
     */
    $filtered_content = wp_kses_post($escaped_content);

    if ($query) {

    if ($query->have_posts()):
        while ($query->have_posts()):
            $query->the_post();



            $shortcode_content = do_shortcode( $filtered_content );


            /**
             * Output is intentionally not escaped here because $filtered_content has already been
             * sanitized with wp_kses_post() prior to running do_shortcode(). Each nested shortcode
             * must handle its own escaping or sanitization as needed. Escaping again at this point
             * would strip or break valid HTML produced by those shortcodes.
             */
            //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $shortcode_content;
        endwhile;
        wp_reset_postdata();
    else:
        ?>
          <div><?php echo wp_kses_post($atts['no_posts_found_text']); ?></div>
        <?php
    endif;
    }

    $content = ob_get_contents();
    ob_end_clean();

    return $content;

}
