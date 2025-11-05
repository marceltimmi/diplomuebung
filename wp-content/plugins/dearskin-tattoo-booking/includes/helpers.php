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
    return ['Silvia', 'Sahrabie', 'Artist of Residence'];
}


function dstb_artists(){
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $default = ['' => 'Kein bevorzugter Artist'];
    foreach (dstb_default_artist_names() as $name) {
        $default[$name] = $name;
    }

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