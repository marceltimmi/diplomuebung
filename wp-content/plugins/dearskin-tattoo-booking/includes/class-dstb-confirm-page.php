<?php
if (!defined('ABSPATH')) exit;

class DSTB_Confirm_Page {

    public function __construct() {
        add_shortcode('dstb_confirm_page', [$this, 'render_page']);

        // Assets nur auf der Bestätigungsseite laden
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX (Frontend, auch für nicht eingeloggte Nutzer)
        add_action('wp_ajax_dstb_confirm_choice',      [$this, 'ajax_confirm_choice']);
        add_action('wp_ajax_nopriv_dstb_confirm_choice', [$this, 'ajax_confirm_choice']);
    }

    public function enqueue_assets() {
        // Passe den Seitenslug an, falls deine Seite anders heißt
        if (is_page('termin-bestaetigen')) {
            wp_enqueue_script(
                'dstb-confirm',
                plugins_url('../assets/js/confirm.js', __FILE__),
                ['jquery'],
                defined('DSTB_VERSION') ? DSTB_VERSION : '1.0.0',
                true
            );
            wp_localize_script('dstb-confirm', 'DSTB_Confirm', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dstb_front')
            ]);

            wp_enqueue_style(
                'dstb-confirm',
                plugins_url('../assets/css/confirm.css', __FILE__),
                [],
                defined('DSTB_VERSION') ? DSTB_VERSION : '1.0.0'
            );
        }
    }

    public function render_page() {
        $req_id = intval($_GET['req'] ?? 0);
        if (!$req_id) return '<p>Ungültiger Aufruf.</p>';

        global $wpdb;

        // Anfrage laden (für Begrüßung / Artist etc.)
        $req_table = $wpdb->prefix . DSTB_DB::$requests;
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM $req_table WHERE id=%d", $req_id), ARRAY_A);
        if (!$req) return '<p>Anfrage nicht gefunden.</p>';

        // Alle an den Kunden GESENDETEN Vorschläge
        $sug_table = $wpdb->prefix . DSTB_DB::$suggestions;
        $sugs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $sug_table WHERE request_id=%d AND status='sent' ORDER BY date, start",
                $req_id
            ),
            ARRAY_A
        );
        if (!$sugs) return '<p>Keine offenen Terminvorschläge vorhanden.</p>';

        ob_start(); ?>
        <div id="dstb-confirm-wrap" class="dstb-confirm">
            <h2>Hallo <?php echo esc_html($req['name']); ?>,</h2>
            <p>Bitte wähle einen der folgenden Termine aus oder lehne alle ab:</p>

            <form id="dstb-confirm-form">
                <input type="hidden" name="req_id" value="<?php echo esc_attr($req_id); ?>">
                <?php wp_nonce_field('dstb_front', 'nonce'); ?>

                <table class="dstb-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Start</th>
                            <th>Ende</th>
                            <th>Preis</th>
                            <th>Notiz</th>
                            <th>Auswahl</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sugs as $idx => $s): ?>
                        <tr data-sid="<?php echo esc_attr($s['id']); ?>">
                            <td><?php echo esc_html($s['date']); ?></td>
                            <td><?php echo esc_html($s['start']); ?></td>
                            <td><?php echo esc_html($s['end']); ?></td>
                            <td><?php echo intval($s['price']); ?> €</td>
                            <td><?php echo esc_html($s['note']); ?></td>
                            <td>
                                <input type="radio"
                                       name="choice"
                                       value="<?php echo esc_attr($s['id']); ?>"
                                       <?php checked($idx===0); ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="dstb-actions" style="margin-top:12px;display:flex;gap:10px;">
                    <button type="submit" class="dstb-btn dstb-confirm-btn">Termin bestätigen</button>
                    <button type="button" id="dstb-decline" class="dstb-btn dstb-decline-btn">Keiner passt</button>
                </div>

                <div id="dstb-msg" style="margin-top:12px"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Kunde bestätigt ODER lehnt ab (AJAX) */
    public function ajax_confirm_choice() {
        check_ajax_referer('dstb_front','nonce');

        global $wpdb;

        $req_id  = intval($_POST['req_id'] ?? 0);
        $sug_id  = intval($_POST['choice'] ?? 0);
        $decline = !empty($_POST['decline']);

        if (!$req_id) {
            wp_send_json_error(['msg' => 'Fehlende Angaben: Anfrage-ID.']);
        }

        $req_table = $wpdb->prefix . DSTB_DB::$requests;
        $sug_table = $wpdb->prefix . DSTB_DB::$suggestions;

        // Anfrage laden (für Artist / Plausibilität)
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM $req_table WHERE id=%d", $req_id), ARRAY_A);
        if (!$req) {
            wp_send_json_error(['msg' => 'Anfrage nicht gefunden.']);
        }

        if ($decline) {
            // alle noch offenen Vorschläge auf "declined"
            $wpdb->query($wpdb->prepare(
                "UPDATE $sug_table SET status='declined' WHERE request_id=%d AND status='sent'",
                $req_id
            ));

            // Studio benachrichtigen (optional, wenn Methode vorhanden ist)
            if (method_exists('DSTB_Emails','send_decline_notice_to_studio')) {
                DSTB_Emails::send_decline_notice_to_studio($req_id);
            }

            wp_send_json_success(['msg' => 'Schade – wir melden uns ggf. mit neuen Terminen.']);
        }

        if (!$sug_id) {
            wp_send_json_error(['msg' => 'Bitte wähle einen Termin aus.']);
        }

        // Gewählten Vorschlag prüfen & laden (muss zu dieser Anfrage gehören und noch "sent" sein)
        $sug = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sug_table WHERE id=%d AND request_id=%d AND status='sent'",
            $sug_id, $req_id
        ), ARRAY_A);

        if (!$sug) {
            wp_send_json_error(['msg' => 'Ungültige Auswahl oder Termin nicht mehr verfügbar.']);
        }

        // 1) Gewählten Vorschlag als "confirmed" markieren
        $wpdb->update($sug_table, ['status' => 'confirmed'], ['id' => $sug_id]);

        // 2) Alle anderen, noch offenen Vorschläge dieser Anfrage ablehnen
        $wpdb->query($wpdb->prepare(
            "UPDATE $sug_table SET status='declined' WHERE request_id=%d AND status='sent' AND id<>%d",
            $req_id, $sug_id
        ));

        // 3) Fixe Buchung in DB eintragen (erst jetzt wird der Kalender rot)
        //    (Methode kommt aus deiner DB-Klasse)
        if (method_exists('DSTB_DB','insert_confirmed_booking')) {
            $artist = $req['artist'] ?? '';
            $date   = $sug['date'];
            $start  = $sug['start'];
            $end    = $sug['end'];
            DSTB_DB::insert_confirmed_booking($artist, $date, $start, $end, $req_id);
        }

        // 4) Studio benachrichtigen (Mail)
        if (method_exists('DSTB_Emails','send_confirmation_to_studio')) {
            DSTB_Emails::send_confirmation_to_studio($req_id, $sug_id);
        }

        wp_send_json_success(['msg' => 'Danke! Dein Termin wurde bestätigt.']);
    }
}
