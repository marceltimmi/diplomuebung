<?php
if ( ! defined('ABSPATH') ) exit;


class DSTB_Router{
public function __construct(){
add_action('init',[$this,'add_rewrite']);
add_action('template_redirect',[$this,'maybe_render']);
}
public function add_rewrite(){
add_rewrite_rule('^tattoo-confirm/?','index.php?dstb_confirm=1','top');
add_rewrite_tag('%dstb_confirm%','([^&]+)');
}
public function maybe_render(){
if( isset($_GET['dstb_confirm']) && isset($_GET['rid']) && isset($_GET['t']) ){
status_header(200);
include DSTB_PATH.'templates/confirm.php';
exit;
}
}
}