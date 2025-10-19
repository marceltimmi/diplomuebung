<?php
/**
 * Plugin Name:  DearSkin Tattoo Booking
 * Description:  Professionelles Termin-Buchungssystem für Tattoo-Studios mit Kalender, Verfügbarkeiten und Kundenanfragen.
 * Version:      2.0.0
 * Author:       DearSkin
 * Text Domain:  dearskin-tattoo-booking
 */

if ( ! defined('ABSPATH') ) exit;

/* -------------------------------------------------------------
 *  KONSTANTEN
 * ------------------------------------------------------------- */
define('DSTB_VERSION', '2.0.0');
define('DSTB_PATH', plugin_dir_path(__FILE__));
define('DSTB_URL',  plugin_dir_url(__FILE__));

/* -------------------------------------------------------------
 *  TEXTDOMAIN / I18N
 * ------------------------------------------------------------- */
function dstb_load_textdomain() {
	load_plugin_textdomain(
		'dearskin-tattoo-booking',
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);
}
add_action('plugins_loaded', 'dstb_load_textdomain');

/* -------------------------------------------------------------
 *  INCLUDE-DATEIEN
 * ------------------------------------------------------------- */
require_once DSTB_PATH . 'includes/helpers.php';
require_once DSTB_PATH . 'includes/class-dstb-assets.php';
require_once DSTB_PATH . 'includes/class-dstb-cpt.php';                 // optional, solange du das Menü nutzt
require_once DSTB_PATH . 'includes/class-dstb-admin.php';
require_once DSTB_PATH . 'includes/class-dstb-emails.php';
require_once DSTB_PATH . 'includes/class-dstb-ajax.php';
require_once DSTB_PATH . 'includes/class-dstb-router.php';
require_once DSTB_PATH . 'includes/class-dstb-calendar.php';
require_once DSTB_PATH . 'includes/class-dstb-db.php';                  // 💾 neue DB-Schicht
require_once DSTB_PATH . 'includes/class-dstb-admin-availability.php';  // 🗓️ Backend-Verfügbarkeiten

/* -------------------------------------------------------------
 *  BOOTSTRAP – KLASSEN LADEN
 * ------------------------------------------------------------- */
add_action('plugins_loaded', function () {
	new DSTB_Assets();
	new DSTB_CPT(); // optional – kann später entfernt werden, wenn du CPT nicht mehr brauchst
	new DSTB_Admin();
	new DSTB_Emails();
	new DSTB_Ajax();
	new DSTB_Router();
	new DSTB_Admin_Availability(); // neues Backend für freie Zeiten / Urlaub
});

/* -------------------------------------------------------------
 *  AKTIVIERUNG & DEAKTIVIERUNG
 * ------------------------------------------------------------- */
register_activation_hook(__FILE__, function () {
	DSTB_DB::install();   // Tabellen anlegen
	DSTB_CPT::register(); // optional, solange CPT aktiv
	flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
	flush_rewrite_rules();
});

/* -------------------------------------------------------------
 *  SHORTCODE
 * ------------------------------------------------------------- */
function dstb_register_shortcode() {
	add_shortcode('tattoo_booking_form', 'dstb_render_booking_form');
}
add_action('init', 'dstb_register_shortcode');

function dstb_render_booking_form() {
	if ( ! defined('ABSPATH') ) return '';

	$template = DSTB_PATH . 'templates/form.php';
	if ( ! file_exists($template) ) {
		return '<div style="color:#c33;padding:12px;border:1px solid #900;border-radius:6px;">
			<b>Fehler:</b> Template <code>templates/form.php</code> fehlt im Plugin-Verzeichnis.
		</div>';
	}

	ob_start();
	include $template;
	return ob_get_clean();
}

/* -------------------------------------------------------------
 *  OPTIONALE HILFSFUNKTIONEN FÜR SPÄTER
 * ------------------------------------------------------------- */
// Beispiel: Automatische Pufferung von Terminen (60 Minuten nach jeder Buchung)
function dstb_add_booking_buffer($artist, $date, $end_time) {
	$end_minutes = explode(':', $end_time);
	$h = intval($end_minutes[0]);
	$m = intval($end_minutes[1]);
	$m += 60;
	if ($m >= 60) { $h += floor($m / 60); $m = $m % 60; }
	return sprintf('%02d:%02d', $h, $m);
}
