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


function dstb_artists(){ return [''=>'Kein bevorzugter Artist','Silvia'=>'Silvia','Sahrabie'=>'Sahrabie','Artist of Residence'=>'Artist of Residence']; }


function dstb_upload_constraints(){
return [ 'max_files'=>10, 'max_size_mb'=>8, 'allowed_mimes'=>['image/jpeg','image/png','image/webp'] ];
}