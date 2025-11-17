<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_Assets {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function enqueue_frontend_assets() {

        // === CSS ===
        wp_register_style(
            'dstb-style',
            DSTB_URL . 'assets/css/style.css',
            [],
            DSTB_VERSION
        );
        wp_enqueue_style('dstb-style');

        // === Gemeinsame AJAX-Daten (Formular + Kalender) ===
        $no_calendar_artists = class_exists('DSTB_Admin_Artists')
            ? DSTB_Admin_Artists::get_no_calendar_artists()
            : ['Kein bestimmter Artist', 'Artist of Residence'];

        $calendar_artists = class_exists('DSTB_Admin_Artists')
            ? DSTB_Admin_Artists::get_calendar_artists()
            : ['Silvia', 'Sahrabie'];

        // Standard-Label für "keine Präferenz" ebenfalls als kalendarlose Option übergeben
        $no_calendar_artists[] = __('Kein bevorzugter Artist', 'dstb');
        $no_calendar_artists[] = '';

        $ajax_data = [
            'url'               => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('dstb_front'),
            'timeSteps'         => dstb_half_hour_steps(),
            'maxUploads'        => dstb_upload_constraints()['max_files'],
            'noCalendarArtists' => array_values(array_unique((array) $no_calendar_artists)),
            'calendarArtists'   => array_values(array_unique((array) $calendar_artists)),
        ];

        // === FORMULAR JS ===
        wp_register_script(
            'dstb-form',
            DSTB_URL . 'assets/js/form.js',
            ['jquery'],
            DSTB_VERSION,
            true
        );

        wp_localize_script('dstb-form', 'DSTB_Ajax', $ajax_data);

        wp_enqueue_script('dstb-form');

        // === KALENDER JS ===
        wp_register_script(
            'dstb-calendar',
            DSTB_URL . 'assets/js/tattoo-calendar.js',
            ['jquery'],
            DSTB_VERSION,
            true
        );

        wp_localize_script('dstb-calendar', 'DSTB_Ajax', $ajax_data);
        $no_calendar_artists = class_exists('DSTB_Admin_Artists')
            ? DSTB_Admin_Artists::get_no_calendar_artists()
            : ['Kein bestimmter Artist', 'Artist of Residence'];

        // Standard-Label für "keine Präferenz" ebenfalls als kalendarlose Option übergeben
        $no_calendar_artists[] = __('Kein bevorzugter Artist', 'dstb');
        $no_calendar_artists[] = '';

        wp_localize_script('dstb-calendar', 'DSTB_Ajax', [
            'url'                => admin_url('admin-ajax.php'),
            'nonce'              => wp_create_nonce('dstb_front'),
            'noCalendarArtists'  => array_values(array_unique((array) $no_calendar_artists)),
        ]);

        wp_enqueue_script('dstb-calendar');
    }
}
