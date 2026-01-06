<?php
if ( ! defined('ABSPATH') ) exit;


function dstb_sanitize_text($v){ return sanitize_text_field($v ?? ''); }
function dstb_sanitize_textarea($v){ return wp_kses_post($v ?? ''); }
function dstb_bool($v){ return !empty($v) && in_array($v, ['1',1,true,'true','on'], true); }


function dstb_half_hour_steps(){
$out=[]; $t = strtotime('00:00');
for ($i=0; $i<48; $i++) { $out[] = date('H:i', $t + $i*30*60); }
return $out; // ["00:00","00:30",...]
}


function dstb_generate_token(){ return wp_generate_password(32,false,false); }


function dstb_styles_list(){
return [
'realism','fineline','blackwork','portrait','lettering','black&grey','dotwork','sketch work','geometric','Neo-traditional','ornamental','concept design','illustrative','surrealism','microrealism','floral','trash polka','new school','neo-japanese','comic','watercolor','tribal','Japanese','biomechanical','maori','Horror realism','noch unklar'
];
}


function dstb_bodyparts_list(){
return [ 'Oberarm','Unterarm','Hand','Schulter','Brust','Rücken','Bauch','Oberschenkel','Unterschenkel','Knöchel','Fuß','Hals','Nacken','Gesicht','Sonstiges' ];
}


function dstb_default_artist_names(){
    $defaults = ['Silvia', 'Sahrabie', 'Artist of Residence'];

    /**
     * Erlaubt es Themes/Plugins, die Standard-Künstlerliste anzupassen.
     */
    return apply_filters('dstb_default_artist_names', $defaults);
}


function dstb_artists($force_refresh = false){
    static $cached = null;

    if ($force_refresh) {
        $cached = null;
    }

    if (is_array($cached)) {
        return $cached;
    }

    $label   = __('Kein bevorzugter Artist', 'dstb');
    $options = ['' => $label];

    $names = [];
    if (class_exists('DSTB_Admin_Artists') && method_exists('DSTB_Admin_Artists', 'get_artist_names')) {
        $names = (array) call_user_func(['DSTB_Admin_Artists', 'get_artist_names'], true);
    }

    /**
     * Erlaube es, die dynamische Liste programmgesteuert zu verändern.
     */
    $names = apply_filters('dstb_artists_names', $names, $force_refresh);

    if (empty($names)) {
        $names = dstb_default_artist_names();
    }

    foreach ($names as $name) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }

        $options[$name] = $name;
    }

    if (count($options) === 1) {
        foreach (dstb_default_artist_names() as $fallback) {
            $fallback = trim((string) $fallback);
            if ($fallback === '') {
                continue;
            }

            $options[$fallback] = $fallback;
        }
    }

    /**
     * Finalen Options-Array (value => label) filtern.
     */
    $cached = apply_filters('dstb_artists_options', $options, $names);

    return $cached;
}


function dstb_upload_constraints(){
return [ 'max_files'=>10, 'max_size_mb'=>8, 'allowed_mimes'=>['image/jpeg','image/png','image/webp'] ];
}

/**
 * Resolves an URL either from constants/options or matching page slugs.
 */
function dstb_resolve_url($constant, $option, array $slugs, $fallback = ''){
    if ($constant && defined($constant) && filter_var(constant($constant), FILTER_VALIDATE_URL)) {
        return constant($constant);
    }

    if ($option) {
        $opt = get_option($option, '');
        if ($opt && filter_var($opt, FILTER_VALIDATE_URL)) {
            return $opt;
        }
    }

    foreach ($slugs as $slug) {
        $page = get_page_by_path($slug);
        if ($page) {
            return get_permalink($page);
        }
    }

    return $fallback;
}

/**
 * Thank-you page after a booking request was sent.
 */
function dstb_thankyou_request_url(){
    $url = dstb_resolve_url(
        'DSTB_THANKYOU_REQUEST_URL',
        'dstb_thankyou_request_url',
        ['danke-fuer-deine-anfrage', 'thank-you', 'danke']
    );

    if (!$url) {
        // Legacy fallback for earlier single thank-you configuration.
        $url = dstb_resolve_url('DSTB_THANKYOU_URL', 'dstb_thankyou_url', ['thank-you', 'danke']);
    }

    return apply_filters('dstb_thankyou_request_url', $url ?: '');
}

/**
 * Thank-you page after a final appointment confirmation.
 */
function dstb_thankyou_confirm_url(){
    $url = dstb_resolve_url(
        'DSTB_THANKYOU_CONFIRM_URL',
        'dstb_thankyou_confirm_url',
        ['terminbestaetigung']
    );

    if (!$url) {
        // Fallback to the request thank-you page if no dedicated confirmation page is found.
        $url = dstb_thankyou_request_url();
    }

    return apply_filters('dstb_thankyou_confirm_url', $url ?: '');
}

// Backwards compatibility for existing usages.
function dstb_thankyou_url(){
    return dstb_thankyou_request_url();
}

