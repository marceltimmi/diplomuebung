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
            // ❗ gleiches Nonce-Prinzip wie Requests
            'nonce'    => wp_create_nonce('dstb_admin_requests'),
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

    protected static function default_rows() {
        $defaults = [
            ['name' => 'Silvia', 'has_calendar' => 1],
            ['name' => 'Sahrabie', 'has_calendar' => 1],
            ['name' => 'Artist of Residence', 'has_calendar' => 0],
        ];

        return apply_filters('dstb_default_artist_rows', $defaults);
    }

    protected static function default_names() {
        $rows = function_exists('dstb_default_artist_names')
            ? array_map(function($name){ return ['name' => $name, 'has_calendar' => 1]; }, (array) dstb_default_artist_names())
            : self::default_rows();

        $names = [];
        foreach ($rows as $row) {
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    public static function get_artist_names($with_fallback = true) {
        $ready = self::maybe_create_table();

        if (!$ready) {
            return $with_fallback ? self::default_names() : [];
        }

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

        if (!empty($names) || !$with_fallback) {
            return array_values(array_unique($names));
        }

        return self::default_names();
    }

    public static function get_artist_rows($with_fallback = true) {
        $ready = self::maybe_create_table();

        if (!$ready) {
            if (!$with_fallback) {
                return [];
            }

            $fallback = [];
            foreach (self::default_rows() as $row) {
                $fallback[] = [
                    'id'           => 0,
                    'name'         => $row['name'],
                    'has_calendar' => (int) ($row['has_calendar'] ?? 1),
                ];
            }

            return $fallback;
        }

        global $wpdb;

        $table = self::table();
        $like  = $wpdb->esc_like($table);

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        $items  = [];

        if ($exists === $table) {
            $rows = $wpdb->get_results("SELECT id, name, has_calendar FROM $table ORDER BY name ASC", ARRAY_A);
            foreach ((array) $rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $items[] = [
                    'id'           => isset($row['id']) ? (int) $row['id'] : 0,
                    'name'         => $name,
                    'has_calendar' => isset($row['has_calendar']) ? (int) $row['has_calendar'] : 1,
                ];
            }
        }

        if (!empty($items) || !$with_fallback) {
            return $items;
        }

        $fallback = [];
        foreach (self::default_rows() as $row) {
            $fallback[] = [
                'id'           => 0,
                'name'         => $row['name'],
                'has_calendar' => (int) ($row['has_calendar'] ?? 1),
            ];
        }

        return $fallback;
    }

    public static function get_no_calendar_artists($with_special = true) {
        $ready = self::maybe_create_table();
        $names = [];

        if ($ready) {
            global $wpdb;
            $table = self::table();
            $rows = $wpdb->get_col("SELECT name FROM $table WHERE has_calendar = 0 ORDER BY name ASC");
            foreach ((array) $rows as $row) {
                $row = trim((string) $row);
                if ($row !== '') {
                    $names[] = $row;
                }
            }
        }

        if (empty($names)) {
            foreach (self::default_rows() as $row) {
                if (!empty($row['name']) && empty($row['has_calendar'])) {
                    $names[] = $row['name'];
                }
            }
        }

        if ($with_special) {
            $names[] = 'Kein bestimmter Artist';
        }

        return array_values(array_unique($names));
    }

    /**
     * Liefert Artists, die explizit einen Kalender besitzen.
     */
    public static function get_calendar_artists() {
        $ready = self::maybe_create_table();
        $names = [];

        if ($ready) {
            global $wpdb;
            $table = self::table();
            $rows = $wpdb->get_col("SELECT name FROM $table WHERE has_calendar = 1 ORDER BY name ASC");
            foreach ((array) $rows as $row) {
                $row = trim((string) $row);
                if ($row !== '') {
                    $names[] = $row;
                }
            }
        }

        if (empty($names)) {
            foreach (self::default_rows() as $row) {
                if (!empty($row['name']) && !empty($row['has_calendar'])) {
                    $names[] = $row['name'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    /** AJAX: Artist hinzufügen */
    public static function add_artist() {
        check_ajax_referer('dstb_admin_requests','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

        if (!self::maybe_create_table()) {
            wp_send_json_error(['msg' => __('Artist-Tabelle konnte nicht erstellt werden.', 'dstb')]);
        }

        global $wpdb;
        $table = self::table();
        $name = sanitize_text_field($_POST['name'] ?? '');
        $name = trim($name);
        $has_calendar = isset($_POST['has_calendar']) ? intval($_POST['has_calendar']) : 1;
        $has_calendar = $has_calendar ? 1 : 0;

        if (!$name) wp_send_json_error(['msg'=>'Name darf nicht leer sein.']);

        // Doppeltes vermeiden
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE name=%s", $name));
        if ($exists) wp_send_json_error(['msg'=>'Artist existiert bereits.']);

        $ok = $wpdb->insert($table, [
            'name'        => $name,
            'has_calendar'=> $has_calendar,
            'created_at'  => current_time('mysql')
        ], ['%s','%d','%s']);
        if (!$ok) wp_send_json_error(['msg'=>'Speichern fehlgeschlagen.']);

        if (function_exists('dstb_artists')) {
            dstb_artists(true);
        }

        $artists = self::get_artist_names();

        wp_send_json_success([
            'msg'     => __('Artist hinzugefügt.', 'dstb'),
            'artists' => $artists,
            'focus'   => $name,
        ]);
    }

    /** AJAX: Artist löschen (Name oder ID) */
    public static function delete_artist() {
        check_ajax_referer('dstb_admin_requests','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

        if (!self::maybe_create_table()) {
            wp_send_json_error(['msg' => __('Artist-Tabelle konnte nicht erstellt werden.', 'dstb')]);
        }

        global $wpdb;
        $table = self::table();

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
        if ($ok === 0) wp_send_json_error(['msg'=>__('Artist wurde nicht gefunden.', 'dstb')]);

        if ($ok > 0 && $target !== '' && class_exists('DSTB_DB') && method_exists('DSTB_DB', 'delete_artist_data')) {
            DSTB_DB::delete_artist_data($target);
        }

        if (function_exists('dstb_artists')) {
            dstb_artists(true);
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
        check_ajax_referer('dstb_admin_requests','nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['msg'=>'Keine Berechtigung.']);
        }

        if (!self::maybe_create_table()) {
            wp_send_json_error(['msg' => __('Artist-Tabelle konnte nicht erstellt werden.', 'dstb')]);
        }
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results("SELECT id, name, has_calendar FROM $table ORDER BY name ASC", ARRAY_A);
        wp_send_json_success($rows);
    }

    /** DB: Tabelle erstellen */
    public static function maybe_create_table() {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)));

        if ($exists !== $table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $sql = "CREATE TABLE $table (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                has_calendar TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY (name)
            ) $charset;";
            dbDelta($sql);
        }

        self::ensure_schema_up_to_date();

        self::seed_default_artists();

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)));

        return $exists === $table;
    }

    /** Standardkünstler befüllen */
    protected static function seed_default_artists() {
        global $wpdb;
        $table = self::table();

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists !== $table) {
            return;
        }

        // Nur einmalig ausführen
        if (get_option('dstb_artists_seeded', '') === '1') {
            return;
        }

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count === 0) {
            foreach (self::default_rows() as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $wpdb->insert(
                    $table,
                    [
                        'name'         => $name,
                        'has_calendar' => (int) ($row['has_calendar'] ?? 1),
                        'created_at'   => current_time('mysql'),
                    ],
                    ['%s', '%d', '%s']
                );
            }
        }

        update_option('dstb_artists_seeded', '1');
    }

    protected static function ensure_schema_up_to_date() {
        global $wpdb;
        $table = self::table();

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists !== $table) {
            return;
        }

        $has_column = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'has_calendar'");
        if (!$has_column) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN has_calendar TINYINT(1) NOT NULL DEFAULT 1 AFTER name");
            $wpdb->query($wpdb->prepare("UPDATE $table SET has_calendar = 0 WHERE name = %s", 'Artist of Residence'));
        }
    }
}

DSTB_Admin_Artists::init();
