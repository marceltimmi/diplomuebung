<?php
if (!defined('ABSPATH')) exit;

class DSTB_Admin_Artists {

    public static function init() {
        // AJAX
        add_action('wp_ajax_dstb_add_artist', [__CLASS__, 'add_artist']);
        add_action('wp_ajax_dstb_delete_artist', [__CLASS__, 'delete_artist']);
        add_action('wp_ajax_dstb_get_artists', [__CLASS__, 'get_artists']);

        // Enqueue assets for admin pages (only where needed)
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets($hook) {
        // Optional: beschränke auf gewisse admin-pages, z.B. plugin-seite
        // if (strpos($hook, 'dstb-requests') === false) return;

        wp_enqueue_script('dstb-admin-artists', plugins_url('../assets/js/admin-artists.js', __FILE__), ['jquery'], defined('DSTB_VERSION')?DSTB_VERSION:'1.0.0', true);
        wp_enqueue_style('dstb-admin-artists', plugins_url('../assets/css/admin-artists.css', __FILE__), [], defined('DSTB_VERSION')?DSTB_VERSION:'1.0.0');

        // Nutze selben nonce wie dein Admin-JS (bei dir: 'dstb_admin')
        wp_localize_script('dstb-admin-artists', 'DSTB_Artists', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('dstb_admin'),
            'i18n'     => [
                'added' => __('Artist hinzugefügt.','dstb'),
                'deleted' => __('Artist gelöscht.','dstb'),
                'error' => __('Fehler','dstb'),
            ],
        ]);
    }

    /** AJAX: Artist hinzufügen */
    public static function add_artist() {
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

        global $wpdb;
        $table = $wpdb->prefix . 'dstb_artists';
        $name = sanitize_text_field($_POST['name'] ?? '');

        if (!$name) wp_send_json_error(['msg'=>'Name darf nicht leer sein.']);

        // Doppeltes vermeiden
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE name=%s", $name));
        if ($exists) wp_send_json_error(['msg'=>'Artist existiert bereits.']);

        $ok = $wpdb->insert($table, ['name'=>$name,'created_at'=>current_time('mysql')], ['%s','%s']);
        if (!$ok) wp_send_json_error(['msg'=>'Speichern fehlgeschlagen.']);

        wp_send_json_success(['msg'=>'Artist hinzugefügt.']);
    }

    /** AJAX: Artist löschen (Name oder ID) */
    public static function delete_artist() {
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

        global $wpdb;
        $table = $wpdb->prefix . 'dstb_artists';

        // Unterstütze entweder name oder id
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize_text_field($_POST['name'] ?? '');

        if ($id) {
            $ok = $wpdb->delete($table, ['id'=>$id], ['%d']);
        } elseif ($name) {
            $ok = $wpdb->delete($table, ['name'=>$name], ['%s']);
        } else {
            wp_send_json_error(['msg'=>'Kein Artist ausgewählt.']);
        }

        if ($ok === false) wp_send_json_error(['msg'=>'Löschen fehlgeschlagen.']);
        wp_send_json_success(['msg'=>'Artist gelöscht.']);
    }

    /** AJAX: Liste aller Artists */
    public static function get_artists() {
        if (!current_user_can('manage_options')) {
            // Für Frontend-Prozesse könntest du dies lockern, hier ist admin-only.
            wp_send_json_error(['msg'=>'Keine Berechtigung.']);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'dstb_artists';
        $rows = $wpdb->get_results("SELECT id, name FROM $table ORDER BY name ASC", ARRAY_A);
        wp_send_json_success($rows);
    }

    /** DB: Tabelle erstellen (zur Integration in activation hook) */
    public static function maybe_create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'dstb_artists';
        $charset = $wpdb->get_charset_collate();

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table))) === $table) {
            return; // existiert bereits
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY (name)
        ) $charset;";
        dbDelta($sql);
    }
}

DSTB_Admin_Artists::init();
