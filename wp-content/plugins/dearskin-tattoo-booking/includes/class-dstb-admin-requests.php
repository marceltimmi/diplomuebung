<?php
if (!defined('ABSPATH')) exit;

class DSTB_Admin_Requests {

    public function __construct(){
        add_action('admin_menu', [$this, 'menu']);
        add_action('wp_ajax_dstb_add_suggestion', [$this, 'ajax_add_suggestion']);
        add_action('wp_ajax_dstb_update_suggestion', [$this, 'ajax_update_suggestion']);
    }

    public function menu(){
        add_menu_page(
            'Tattoo Anfragen',
            'Tattoo Anfragen',
            'manage_options',
            'dstb-requests',
            [$this, 'screen'],
            'dashicons-clipboard',
            25
        );
    }

    public function screen(){
        if (!current_user_can('manage_options')) {
            wp_die('Kein Zugriff.');
        }

        global $wpdb;
        $req_table = $wpdb->prefix . DSTB_DB::$requests;
        $sug_table = $wpdb->prefix . DSTB_DB::$suggestions;

        // Assets
        wp_enqueue_style('dstb-admin-requests', plugins_url('../assets/css/admin-requests.css', __FILE__), [], DSTB_VERSION);
        wp_enqueue_script('dstb-admin-requests', plugins_url('../assets/js/admin-requests.js', __FILE__), ['jquery'], DSTB_VERSION, true);
        wp_localize_script('dstb-admin-requests', 'DSTB_Ajax', [
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dstb_admin')
        ]);

        // Daten laden
        $rows = $wpdb->get_results("SELECT * FROM $req_table ORDER BY created_at DESC LIMIT 200", ARRAY_A);

        echo '<div class="wrap dstb-admin-wrap">';
        echo '<h1 style="margin-bottom:12px;">Tattoo-Anfragen</h1>';

        if (!$rows){
            echo '<div class="dstb-admin-empty">Keine Anfragen vorhanden.</div>';
            echo '</div>';
            return;
        }

        echo '<div class="dstb-admin-grid">';
        foreach ($rows as $r){
            $req_id = intval($r['id']);

            // Fallback für Namen (falls alte Datensätze ohne zusammengesetzten Namen)
            $display_name = trim($r['name'] ?? '');
            if ($display_name === '' && (!empty($r['firstname']) || !empty($r['lastname']))) {
                $display_name = trim(($r['firstname'] ?? '').' '.($r['lastname'] ?? ''));
            }
            if ($display_name === '') $display_name = 'Unbekannt';

            // Slots & Uploads
            $slots   = [];
            $uploads = [];
            if (!empty($r['slots']))   { $slots = json_decode($r['slots'], true) ?: []; }
            if (!empty($r['uploads'])) { $uploads = json_decode($r['uploads'], true) ?: []; }

            // Studio-Vorschläge laden
            $sugs = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $sug_table WHERE request_id=%d ORDER BY created_at DESC", $req_id),
                ARRAY_A
            );

            // Karte
            echo '<div class="dstb-card-admin">';

            // Kopf
            echo '<div class="dstb-card-head">';
            echo '<div class="dstb-title">🧍 '.esc_html($display_name).'</div>';
            $created = $r['created_at'] ?? '';
            echo '<div class="dstb-subline">'.($created ? esc_html(date_i18n('d.m.Y H:i', strtotime($created))) : '').'</div>';
            echo '</div>';

            // Body – vollständige Kundendaten
            echo '<div class="dstb-card-body">';
            echo '<div class="dstb-two-cols">';

            echo '<div>';
            echo '<div class="dstb-row"><span>📧</span><a href="mailto:'.esc_attr($r['email']).'">'.esc_html($r['email']).'</a></div>';
            if (!empty($r['phone']))    echo '<div class="dstb-row"><span>📞</span>'.esc_html($r['phone']).'</div>';
            if (!empty($r['artist']))   echo '<div class="dstb-row"><span>🧑‍🎨</span>'.esc_html($r['artist']).'</div>';
            if (!empty($r['style']))    echo '<div class="dstb-row"><span>🎨</span>'.esc_html($r['style']).'</div>';
            if (!empty($r['bodypart'])) echo '<div class="dstb-row"><span>📍</span>'.esc_html($r['bodypart']).'</div>';
            if (!empty($r['size']))     echo '<div class="dstb-row"><span>📏</span>'.esc_html($r['size']).'</div>';
            if (isset($r['budget']))    echo '<div class="dstb-row"><span>💶</span>'.esc_html(intval($r['budget'])).' €</div>';
            echo '</div>';

            echo '<div>';
            if (!empty($r['desc_text'])) {
                echo '<div class="dstb-row"><span>📝</span>'.nl2br(esc_html($r['desc_text'])).'</div>';
            }
            if (!empty($uploads)) {
                echo '<div class="dstb-uploads">';
                foreach ($uploads as $att_id) {
                    $img = wp_get_attachment_url($att_id);
                    if ($img) echo '<a href="'.esc_url($img).'" target="_blank"><img src="'.esc_url($img).'" alt="" /></a>';
                }
                echo '</div>';
            }
            echo '</div>';

            echo '</div>'; // two-cols
            echo '</div>'; // body

            // Kunden-Slots
            echo '<div class="dstb-card-section">';
            echo '<h4>⏰ Verfügbarkeiten (Kunde)</h4>';
            if ($slots){
                echo '<ul class="dstb-slots">';
                foreach ($slots as $s){
                    $ds = esc_html($s['date'] ?? '');
                    $st = esc_html($s['start'] ?? '');
                    $en = esc_html($s['end'] ?? '');
                    if ($ds && $st && $en){
                        echo '<li>📅 '.$ds.' – '.$st.' bis '.$en.'</li>';
                    }
                }
                echo '</ul>';
            } else {
                echo '<p><em>Keine Zeitfenster angegeben.</em></p>';
            }
            echo '</div>';

            echo '<div class="dstb-divider"></div>';

            // Studio-Vorschläge – Liste
            echo '<div class="dstb-card-section">';
            echo '<h4>🎯 Terminvorschläge (Studio)</h4>';
            if ($sugs){
                echo '<div class="dstb-sug-table">';
                echo '<div class="dstb-sug-row dstb-sug-head"><div>Datum</div><div>Start</div><div>Ende</div><div>Preis</div><div>Notiz</div><div>Status</div><div>Aktion</div></div>';
                foreach ($sugs as $s){
                    $sid = intval($s['id']);
                    echo '<div class="dstb-sug-row" 
                               data-sid="'.esc_attr($sid).'"
                               data-date="'.esc_attr($s['date']).'"
                               data-start="'.esc_attr($s['start']).'"
                               data-end="'.esc_attr($s['end']).'"
                               data-price="'.esc_attr($s['price']).'"
                               data-note="'.esc_attr($s['note']).'"
                               data-status="'.esc_attr($s['status']).'">';
                    echo '<div>'.esc_html($s['date']).'</div>';
                    echo '<div>'.esc_html($s['start']).'</div>';
                    echo '<div>'.esc_html($s['end']).'</div>';
                    echo '<div>'.esc_html(intval($s['price'])).' €</div>';
                    echo '<div>'.esc_html($s['note']).'</div>';
                    echo '<div>'.esc_html($s['status']).'</div>';
                    echo '<div><button type="button" class="button button-small dstb-edit-sug" data-sid="'.esc_attr($sid).'">✏️ Bearbeiten</button></div>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<p><em>Noch keine Vorschläge erstellt.</em></p>';
            }

            // Studio-Vorschläge – Formular (mehrere Zeilen + / senden)
            echo '<form class="dstb-sug-form" data-req="'.$req_id.'">';
            wp_nonce_field('dstb_admin', 'dstb_nonce');
            echo '<div class="dstb-sug-grid">';
            echo '<input type="date" name="date[]" required>';
            echo '<input type="time" name="start[]" step="1800" required>';
            echo '<input type="time" name="end[]" step="1800" required>';
            echo '<input type="number" name="price[]" placeholder="Preis €" min="0" step="10">';
            echo '<input type="text" name="note[]" placeholder="Notiz (optional)" class="dstb-col-2">';
            echo '</div>';
            echo '<p><button class="button add-sug" type="button">+ weiteren Vorschlag</button></p>';
            echo '<div class="dstb-actions">';
            echo '<button type="button" class="button dstb-save" data-action="draft">💾 Nur speichern</button>';
            echo '<button type="button" class="button-primary dstb-send" data-action="send">📤 An Kunden senden</button>';
            echo '<span class="dstb-sug-msg" aria-live="polite"></span>';
            echo '</div>';
            echo '</form>';

            echo '</div>'; // section
            echo '</div>'; // card
        }
        echo '</div>'; // grid

        // Ein einziges zentrales Modal für Bearbeitung (wird per JS befüllt)
        echo '<div id="dstb-modal" class="dstb-modal" style="display:none;">
                <div class="dstb-modal__dialog">
                    <div class="dstb-modal__head">
                        <strong>✏️ Vorschlag bearbeiten</strong>
                        <button type="button" class="dstb-modal__close" aria-label="Schließen">×</button>
                    </div>
                    <div class="dstb-modal__body">
                        <form id="dstb-modal-form">
                            <input type="hidden" name="id" value="">
                            <div class="dstb-modal-grid">
                                <label>Datum
                                    <input type="date" name="date" required>
                                </label>
                                <label>Start
                                    <input type="time" name="start" step="1800" required>
                                </label>
                                <label>Ende
                                    <input type="time" name="end" step="1800" required>
                                </label>
                                <label>Preis (€)
                                    <input type="number" name="price" min="0" step="10">
                                </label>
                                <label class="dstb-col-2">Notiz
                                    <input type="text" name="note" placeholder="z. B. Realism Unterarm – Sitzung 1">
                                </label>
                            </div>
                            '.wp_nonce_field('dstb_admin','dstb_nonce',true,false).'
                            <div class="dstb-modal__actions">
                                <button type="button" class="button dstb-modal-save">💾 Speichern</button>
                                <button type="button" class="button dstb-modal-cancel">Abbrechen</button>
                                <span class="dstb-modal-msg" aria-live="polite"></span>
                            </div>
                        </form>
                    </div>
                </div>
              </div>';

        echo '</div>'; // wrap
    }

    /**
     * AJAX: mehrere Vorschläge speichern / senden
     * - action = 'draft'  → Zeilen einfügen als draft
     * - action = 'send'   → Zeilen (falls ausgefüllt) als draft einfügen + ALLE draft zu sent hochstufen
     */
    public function ajax_add_suggestion(){
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'No permission.']);

        global $wpdb;
        $table = $wpdb->prefix . DSTB_DB::$suggestions;

        $req_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $save_action = sanitize_text_field($_POST['save_action'] ?? 'draft');

        $dates  = isset($_POST['date'])  ? (array)$_POST['date']  : [];
        $starts = isset($_POST['start']) ? (array)$_POST['start'] : [];
        $ends   = isset($_POST['end'])   ? (array)$_POST['end']   : [];
        $prices = isset($_POST['price']) ? (array)$_POST['price'] : [];
        $notes  = isset($_POST['note'])  ? (array)$_POST['note']  : [];

        if (!$req_id) wp_send_json_error(['msg'=>'Ungültige Anfrage-ID.']);

        $inserted = 0;
        // 1) neue Zeilen (Form) immer erst als draft speichern, wenn Felder befüllt sind
        $n = max(count($dates), count($starts), count($ends));
        for ($i=0; $i<$n; $i++){
            $date  = sanitize_text_field($dates[$i]  ?? '');
            $start = sanitize_text_field($starts[$i] ?? '');
            $end   = sanitize_text_field($ends[$i]   ?? '');
            $price = isset($prices[$i]) ? intval($prices[$i]) : 0;
            $note  = sanitize_text_field($notes[$i] ?? '');

            if ($date && $start && $end){
                $wpdb->insert($table, [
                    'request_id' => $req_id,
                    'date'       => $date,
                    'start'      => $start,
                    'end'        => $end,
                    'price'      => $price,
                    'note'       => $note,
                    'status'     => 'draft',
                    'created_at' => current_time('mysql')
                ], ['%d','%s','%s','%s','%d','%s','%s','%s']);
                $inserted++;
            }
        }

        $updated = 0;
        // 2) bei "send": ALLE vorhandenen drafts auf sent setzen (inkl. gerade gespeicherter)
        if ($save_action === 'send') {
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status='sent' WHERE request_id=%d AND status='draft'",
                $req_id
            ));
        }

        $msg = ($save_action === 'send')
            ? sprintf('%d Vorschlag/Vorschläge gespeichert, %d an den Kunden gesendet.', $inserted, $updated)
            : sprintf('%d Vorschlag/Vorschläge gespeichert (Entwurf).', $inserted);

        wp_send_json_success(['msg'=>$msg]);
    }

    /**
     * AJAX: Vorschlag per Popup aktualisieren
     */
    public function ajax_update_suggestion(){
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'No permission.']);

        global $wpdb;
        $table = $wpdb->prefix . DSTB_DB::$suggestions;

        $id    = isset($_POST['id'])    ? intval($_POST['id']) : 0;
        $date  = sanitize_text_field($_POST['date']  ?? '');
        $start = sanitize_text_field($_POST['start'] ?? '');
        $end   = sanitize_text_field($_POST['end']   ?? '');
        $price = isset($_POST['price']) ? intval($_POST['price']) : 0;
        $note  = sanitize_text_field($_POST['note']  ?? '');

        if (!$id || !$date || !$start || !$end){
            wp_send_json_error(['msg'=>'Bitte Datum/Start/Ende ausfüllen.']);
        }

        $ok = $wpdb->update($table, [
            'date'  => $date,
            'start' => $start,
            'end'   => $end,
            'price' => $price,
            'note'  => $note
        ], ['id'=>$id], ['%s','%s','%s','%d','%s'], ['%d']);

        if ($ok === false){
            wp_send_json_error(['msg'=>'Aktualisieren fehlgeschlagen.']);
        }
        wp_send_json_success(['msg'=>'Gespeichert.']);
    }
}
