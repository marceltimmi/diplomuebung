<?php
if ( ! defined('ABSPATH') ) exit;


class DSTB_CPT{
public function __construct(){
add_action('init',[__CLASS__,'register']);
}
public static function register(){
register_post_type('tattoo_request',[
'label' => __('Tattoo Anfragen','dearskin-tattoo-booking'),
'public' => false,
'show_ui' => true,
'menu_icon' => 'dashicons-art',
'supports' => ['title','editor','author'],
'capability_type' => 'post',
]);
}
}