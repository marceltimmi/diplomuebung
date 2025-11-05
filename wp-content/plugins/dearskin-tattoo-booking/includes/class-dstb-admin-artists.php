<?php
if (!defined('ABSPATH')) exit;

class DSTB_Admin_Artists {

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_create_table']);

        // AJAX
        add_action('wp_ajax_dstb_add_artist', [__CLASS__, 'add_artist']);
        add_action('wp_ajax_dstb_delete_artist', [__CLASS__, 'delete_artist']);
        add_action('wp_ajax_dstb_get_artists', [__CLASS__, 'get_artists']);

        // Enqueue assets for admin pages (only where needed)
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets($hook) {
        if (strpos($hook, 'dstb-availability') === false) {
            return;
        }

        wp_enqueue_script(
            'dstb-admin-artists',
            plugins_url('../assets/js/admin-artists.js', __FILE__),
            [],
            defined('DSTB_VERSION') ? DSTB_VERSION : '1.0.0',
            true
        );

        wp_enqueue_style(
            'dstb-admin-artists',
            plugins_url('../assets/css/admin-artists.css', __FILE__),
            [],
            defined('DSTB_VERSION') ? DSTB_VERSION : '1.0.0'
        );

        wp_localize_script('dstb-admin-artists', 'DSTB_Artists', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('dstb_admin'),
            'i18n'     => [
                'added'   => __('Artist hinzugefügt.', 'dstb'),
                'deleted' => __('Artist gelöscht.', 'dstb'),
                'error'   => __('Es ist ein Fehler aufgetreten.', 'dstb'),
                'loading' => __('Bitte warten…', 'dstb'),
                'invalidName'   => __('Bitte gib einen Namen ein.', 'dstb'),
                'confirmDelete' => __('Diesen Artist wirklich löschen? Alle Verfügbarkeiten gehen verloren.', 'dstb'),
                'empty'         => __('Keine Artists vorhanden.', 'dstb'),
            ],
        ]);
    }

    protected static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dstb_artists';
    }

    protected static function default_names() {
        if (function_exists('dstb_default_artist_names')) {
            return dstb_default_artist_names();
        }

        return ['Silvia', 'Sahrabie', 'Artist of Residence'];
    }

    public static function get_artist_names($with_fallback = true) {
        global $wpdb;

        $table = self::table();
        $like  = $wpdb->esc_like($table);

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        $names  = [];

        if ($exists === $table) {
            $rows = $wpdb->get_col("SELECT name FROM $table ORDER BY name ASC");
            foreach ((array) $rows as $row) {
                $row = trim((string) $row);
                if ($row !== '') {
                    $names[] = $row;
                }
            }
        }

        if (!empty($names) || ! $with_fallback) {
            return array_values(array_unique($names));
        }

        return self::default_names();
    }

    /** AJAX: Artist hinzufügen */
    public static function add_artist() {
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

        global $wpdb;
        $table = self::table();
        $name = sanitize_text_field($_POST['name'] ?? '');
        $name = trim($name);

        if (!$name) wp_send_json_error(['msg'=>'Name darf nicht leer sein.']);

        // Doppeltes vermeiden
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE name=%s", $name));
        if ($exists) wp_send_json_error(['msg'=>'Artist existiert bereits.']);

        $ok = $wpdb->insert($table, ['name'=>$name,'created_at'=>current_time('mysql')], ['%s','%s']);
        if (!$ok) wp_send_json_error(['msg'=>'Speichern fehlgeschlagen.']);

        $artists = self::get_artist_names();

        wp_send_json_success([
            'msg'     => __('Artist hinzugefügt.', 'dstb'),
            'artists' => $artists,
            'focus'   => $name,
        ]);
    }

    /** AJAX: Artist löschen (Name oder ID) */
    public static function delete_artist() {
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

        global $wpdb;
        $table = self::table();

        // Unterstütze entweder name oder id
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize_text_field($_POST['name'] ?? '');
        $name = trim($name);

        $target = '';

        if ($id) {
            $target = (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM $table WHERE id=%d", $id));
            $ok = $wpdb->delete($table, ['id'=>$id], ['%d']);
        } elseif ($name !== '') {
            $target = $name;
            $ok = $wpdb->delete($table, ['name'=>$name], ['%s']);
        } else {
            wp_send_json_error(['msg'=>'Kein Artist ausgewählt.']);
        }

        if ($ok === false) wp_send_json_error(['msg'=>'Löschen fehlgeschlagen.']);

        if ($ok > 0 && $target !== '' && class_exists('DSTB_DB') && method_exists('DSTB_DB', 'delete_artist_data')) {
            DSTB_DB::delete_artist_data($target);
        }

        $artists = self::get_artist_names();

        wp_send_json_success([
            'msg'     => __('Artist gelöscht.', 'dstb'),
            'artists' => $artists,
            'focus'   => $artists[0] ?? '',
        ]);
    }

    /** AJAX: Liste aller Artists */
    public static function get_artists() {
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) {
            // Für Frontend-Prozesse könntest du dies lockern, hier ist admin-only.
            wp_send_json_error(['msg'=>'Keine Berechtigung.']);
        }
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results("SELECT id, name FROM $table ORDER BY name ASC", ARRAY_A);
        wp_send_json_success($rows);
    }

    /** DB: Tabelle erstellen (zur Integration in activation hook) */
    public static function maybe_create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'dstb_artists';
        $charset = $wpdb->get_charset_collate();

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)));

        if ($exists !== $table) {
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

        self::seed_default_artists();
    }

    protected static function seed_default_artists() {
        global $wpdb;
        $table = self::table();

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists !== $table) {
            return;
        }

        if (get_option('dstb_artists_seeded', '') === '1') {
            return;
        }

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count === 0) {
            foreach (self::default_names() as $name) {
                $wpdb->insert(
                    $table,
                    [
                        'name'       => $name,
                        'created_at' => current_time('mysql'),
                    ],
                    ['%s', '%s']
                );
            }
        }

        update_option('dstb_artists_seeded', '1');
    }
}

DSTB_Admin_Artists::init();
