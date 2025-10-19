<?php
if ( ! defined('ABSPATH') ) exit;


class DSTB_Emails{
public function __construct(){ }


public static function send_admin_new_request($post_id){
$to = get_option('admin_email');
$sub = 'Neue Tattoo-Anfrage #'.$post_id;
$link = admin_url('post.php?post='.$post_id.'&action=edit');
wp_mail($to,$sub,"Es gibt eine neue Anfrage.\n\nÖffnen: $link");
}


public static function send_user_receipt($post_id,$email){
$sub='Wir haben deine Tattoo-Anfrage erhalten';
$body="Danke für deine Anfrage bei DearSkin. Wir melden uns bald mit Terminvorschlägen.\n\nReferenz: #$post_id";
wp_mail($email,$sub,$body);
}


public static function send_proposals_email($post_id, array $props){
$email = get_post_meta($post_id,'email',true);
if(!$email) return;
$token = get_post_meta($post_id,'confirm_token',true);
if(!$token){ $token = dstb_generate_token(); update_post_meta($post_id,'confirm_token',$token); }
$url = add_query_arg(['dstb_confirm'=>1,'rid'=>$post_id,'t'=>$token], home_url('/'));
$lines = array_map(function($p,$i){ return ($i+1).") ".$p['date'].' '.$p['start'].' ('.$p['dur'].' Min)'; }, $props, array_keys($props));
$body = "Hallo!\n\nHier sind unsere Terminvorschläge:\n".implode("\n",$lines)."\n\nBitte klicke auf den Link und wähle deinen Wunschtermin:\n$url\n\nLiebe Grüße";
wp_mail($email,'Deine Terminvorschläge',$body);
}
}