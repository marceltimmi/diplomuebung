<?php

namespace ExtendBuilder;

function set_shortcode_output($tag, $output) {
    $key = "colibri_shortcodes_output";
    $colibri_run = colibri_cache_get($key);
    $colibri_run[$tag] = $output;
    colibri_cache_set($key, $colibri_run);
}

function get_shortcodes_output() {
    $key = "colibri_shortcodes_output";
    return colibri_cache_get($key);
}

function get_shortcode_output($tag) {
    $outputs = get_shortcodes_outputs();
    return array_get_value($outputs, $tag, null);
}


add_filter('do_shortcode_tag', function ( $output, $tag, $attr, $m) {
    if ($output && strpos($tag, "colibri_") !== FALSE) {
        set_shortcode_output($tag, $output);
        return $output;
    }
    return $output;
}, PHP_INT_MAX, 4);

add_shortcode( 'colibri_layout_wrapper', function ( $attrs , $content = null) {
    $atts = shortcode_atts(
        array(
            "name" => "",
        ),
        $attrs
    );

    $name = $atts['name'];
    $escaped_content = do_shortcode( $content );
    return apply_filters('colibri_layout_wrapper_output_' . $name, $escaped_content);
} );

/**
 * Parse shortcodes from content without rendering them.
 *
 * This function extracts shortcode information from the given content, including
 * the full shortcode match, tag name, attributes, and inner content. It does not
 * execute or render the shortcodes. Any content outside of shortcodes is skipped
 * and not included in the returned data.
 *
 * @param string $content The content to parse for shortcodes
 * @param array|null $only_tags Optional. Array of specific shortcode tags to look for.
 *                              If null, all registered shortcodes will be parsed.
 *
 * @return array Array of parsed shortcode data. Each element contains:
 *               - 'full': The complete shortcode string (e.g., '[tag attr="value"]content[/tag]')
 *               - 'tag': The shortcode tag name (e.g., 'forminator_form')
 *               - 'attr': The attributes string (e.g., 'id="36"')
 *               - 'content': The inner content between opening and closing tags
 *
 * @global array $shortcode_tags WordPress global containing all registered shortcodes
 *
 * @since 1.0.0
 */
function colibri_parse_shortcodes_only($content, $only_tags = null)
{
    global $shortcode_tags;

    if (is_array($only_tags) && !empty($only_tags)) {
        $tagnames = $only_tags;
    } else {

        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
        $tagnames = array_intersect(array_keys($shortcode_tags), $matches[1]);

        if (empty($tagnames)) {
            return [];
        }
    }

    $pattern = get_shortcode_regex($tagnames);
    $found_tags_no = preg_match_all("/$pattern/", $content, $matches);

    if (!$found_tags_no) {
        return [];
    }


    $full_matches = isset($matches[0]) ? $matches[0] : [];
    $tags = isset($matches[2]) ? $matches[2] : [];
    $attrs = isset($matches[3]) ? $matches[3] : [];
    $contents = isset($matches[5]) ? $matches[5] : [];

    $parsed = [];
    for ($i = 0; $i < $found_tags_no; $i++) {
        if (!isset($tags[$i])) {
            continue;
        }

        $parsed[] = array(
            'full' => isset($full_matches[$i]) ? $full_matches[$i] : '',
            'tag' => $tags[$i],
            'attr' => isset($attrs[$i]) ? $attrs[$i] : '',
            'content' => isset($contents[$i]) ? $contents[$i] : ''
        );
    }


    return $parsed;
}


/**
 * Parse and render shortcodes from content.
 *
 * This function combines parsing and rendering of shortcodes. It first extracts
 * shortcode information using extedthemes_parse_shortcodes_only(), then executes
 * each shortcode to generate the rendered output. Any content outside of shortcodes
 * is skipped and not included in the processing or returned data.
 *
 * @param string $content The content to parse and render shortcodes from
 * @param array|null $only_tags Optional. Array of specific shortcode tags to process.
 *                              If null, all registered shortcodes will be processed.
 *
 * @return array Array of shortcode data with rendered output. Each element contains:
 *               - 'full': The complete shortcode string
 *               - 'tag': The shortcode tag name
 *               - 'attr': The attributes string
 *               - 'content': The inner content between tags
 *               - 'rendered': The final rendered output from the shortcode callback
 *
 * @global array $shortcode_tags WordPress global containing all registered shortcodes
 *
 * @since 1.0.0
 *
 * @uses extedthemes_parse_shortcodes_only() To extract shortcode data
 * @uses apply_filters() WordPress function to apply pre_do_shortcode_tag filter
 * @uses call_user_func() To execute the shortcode callback function
 */
function colibri_parse_and_render_shortcodes($content, $only_tags = null)
{

    global $shortcode_tags;
    $parsed = colibri_parse_shortcodes_only($content, $only_tags);

    if (empty($parsed)) {
        return [];
    }

    $results = [];

    foreach ($parsed as $p) {
        $m = array(
            $p['full'], // this is the content before the shortcode in original code this is $m[1]
            '',
            $p['tag'], // in original code this is $m[2]
            $p['attr'], // in original code this is $m[3]
            '',
            $p['content'], // in original code this is $m[5]
            '', // this is the content after the shortcode in original code this is $m[6]
        );

        $attr =  shortcode_parse_atts($p['attr']);

        $return = apply_filters('pre_do_shortcode_tag', false,  $p['tag'],  $attr, $m);
        if (false !== $return) {
            $results[] = array_merge($p, array('rendered' => $return));
            continue;
        }


        $rendered = call_user_func($shortcode_tags[$p['tag']], $attr, $p['content'], $p['tag']);
        $results[] = array_merge($p, array('rendered' => $rendered));
    }

    return $results;
}
