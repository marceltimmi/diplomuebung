<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * DearSkin Tattoo Booking – Allgemeine Admin-Funktionen
 * (bereinigt: kein Bezug mehr zu CPT 'tattoo_request')
 */
class DSTB_Admin {

    public function __construct() {
        // Späterer Platz für weitere Backend-Funktionen oder Admin-Menüs
        add_action('admin_menu', [$this, 'menu']);
    }

    /**
     * Menü – bindet das zentrale Admin-Dashboard (Tattoo Anfragen) ein
     */
    public function menu() {
        // Wir prüfen, ob das neue Dashboard bereits aktiv ist (aus DSTB_Admin_Requests)
        if ( ! has_action('admin_menu', [$this, 'menu']) ) {
            // keine direkte Seite notwendig, da DSTB_Admin_Requests eigenes Menü hat
        }
    }
}
