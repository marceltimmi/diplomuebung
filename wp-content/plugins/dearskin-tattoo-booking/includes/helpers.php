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

function dstb_artists(){
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $label = __('Kein bevorzugter Artist', 'dstb');
    $options = ['' => $label];

    $names = [];
    if (class_exists('DSTB_Admin_Artists') && method_exists('DSTB_Admin_Artists', 'get_artist_names')) {
        $names = DSTB_Admin_Artists::get_artist_names(true);
    }

    if (empty($names)) {
        $names = dstb_default_artist_names();
    }

    foreach ((array) $names as $name) {
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

    $default = [
        '' => 'Kein bevorzugter Artist',
        'Silvia' => 'Silvia',
        'Sahrabie' => 'Sahrabie',
        'Artist of Residence' => 'Artist of Residence',
    ];

    global $wpdb;
    if (!isset($wpdb)) {
        $cached = $default;
        return $cached;
    }

    $table = $wpdb->prefix . 'dstb_artists';
    $like  = $wpdb->esc_like($table);
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));

    if ($exists === $table) {
        $rows = $wpdb->get_col("SELECT name FROM $table ORDER BY name ASC");

        if (!empty($rows)) {
            $dynamic = ['' => $default['']];

            foreach ($rows as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $dynamic[$name] = $name;
            }

            if (count($dynamic) > 1) {
                $cached = $dynamic;
                return $cached;
            }
        }
    }

    $cached = $default;
    return $cached;
}


function dstb_upload_constraints(){
return [ 'max_files'=>10, 'max_size_mb'=>8, 'allowed_mimes'=>['image/jpeg','image/png','image/webp'] ];
}