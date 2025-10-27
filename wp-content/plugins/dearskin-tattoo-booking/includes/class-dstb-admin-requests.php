<?php
if (!defined('ABSPATH')) exit;

class DSTB_Admin_Requests {

    public function __construct(){
        add_action('admin_menu', [$this, 'menu']);
        add_action('wp_ajax_dstb_add_suggestion', [$this, 'ajax_add_suggestion']);
        add_action('wp_ajax_dstb_update_suggestion', [$this, 'ajax_update_suggestion']);
        add_action('wp_ajax_dstb_delete_suggestion', [$this, 'ajax_delete_suggestion']);
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
        if (!current_user_can('manage_options')) wp_die('Kein Zugriff.');

        global $wpdb;
        $req_table = $wpdb->prefix . DSTB_DB::$requests;
        $sug_table = $wpdb->prefix . DSTB_DB::$suggestions;

        // Anfrage löschen
        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);
            $wpdb->delete($req_table, ['id'=>$id]);
            echo '<div class="updated"><p><strong>Anfrage gelöscht.</strong></p></div>';
        }

        // Assets (JS steuert Modal + AJAX Speichern/Senden)
        wp_enqueue_style('dstb-admin-requests', plugins_url('../assets/css/admin-requests.css', __FILE__), [], DSTB_VERSION);
        wp_enqueue_script('dstb-admin-requests', plugins_url('../assets/js/admin-requests.js', __FILE__), ['jquery'], DSTB_VERSION, true);
        wp_localize_script('dstb-admin-requests', 'DSTB_Ajax', [
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dstb_admin')
        ]);

        // Daten holen
        $rows = $wpdb->get_results("SELECT * FROM $req_table ORDER BY created_at DESC LIMIT 200", ARRAY_A);

        echo '<div class="wrap"><h1 style="margin-bottom:14px;">Tattoo-Anfragen</h1>';
        if (!$rows){ echo '<p><em>Keine Anfragen vorhanden.</em></p></div>'; return; }

        // kleines Inline-Layout (Farbwelt wie Frontend)
        echo '<style>
            .dstb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px;}
            .dstb-card{background:#1b1f27;border:1px solid #2a3340;border-radius:14px;padding:20px;color:#e9eef5;
                        box-shadow:0 3px 12px rgba(0,0,0,.3);}
            .dstb-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
            .dstb-head h2{margin:0;color:#9fbfff;font-size:18px;}
            .dstb-section{margin-top:12px;padding-top:10px;border-top:1px solid #2a3340;}
            .dstb-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 30px;}
            .dstb-row{margin:4px 0;font-size:14px;}
            .dstb-row strong{color:#dfe7f3;width:150px;display:inline-block;}
            .dstb-slots{margin:6px 0 0 14px;padding:0;list-style:none;font-size:14px;}
            .dstb-slots li{margin:2px 0;}
            .dstb-delete{background:#a93226;color:#fff;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;}
            .dstb-delete:hover{background:#c0392b;}
            .dstb-sug-table{margin-top:10px;width:100%;border-collapse:collapse;font-size:13px;}
            .dstb-sug-table th,.dstb-sug-table td{padding:6px 4px;border-top:1px solid #2a3340;}
            .dstb-sug-table th{color:#a8b3bf;background:#10161e;text-align:left;}
            .dstb-sug-grid{display:grid;grid-template-columns:repeat(5,1fr) 2fr;gap:6px;}
            .dstb-actions{margin-top:8px;display:flex;gap:8px;align-items:center;}
            /* Modal rudimentär (wenn eigenes CSS fehlt) */
            .dstb-modal{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;display:none;}
            .dstb-modal__dialog{background:#1b1f27;border:1px solid #2a3340;border-radius:12px;max-width:720px;margin:60px auto;padding:0;box-shadow:0 10px 30px rgba(0,0,0,.6);}
            .dstb-modal__head{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #2a3340;color:#e9eef5;}
            .dstb-modal__close{background:#10161e;color:#e9eef5;border:1px solid #2a3340;border-radius:8px;cursor:pointer;padding:4px 10px;}
            .dstb-modal__body{padding:16px;color:#e9eef5;}
            .dstb-modal-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
            .dstb-modal-grid label{display:flex;flex-direction:column;gap:6px;font-size:14px;}
            .dstb-modal-grid input{background:#0c1117;color:#e9eef5;border:1px solid #1d2633;border-radius:8px;padding:8px 10px;}
            .dstb-modal__actions{display:flex;gap:8px;align-items:center;margin-top:12px;}
        </style>';

        echo '<div class="dstb-grid">';
        foreach($rows as $r){
            $req_id = intval($r['id']);
            $slots   = json_decode($r['slots'] ?? '[]', true) ?: [];
            $uploads = json_decode($r['uploads'] ?? '[]', true) ?: [];
            $sugs    = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sug_table WHERE request_id=%d ORDER BY created_at DESC",$req_id),ARRAY_A);

            $name = trim($r['name'] ?? '');
            if ($name==='') $name='Unbekannt';

            echo '<div class="dstb-card">';

            // Kopf
            echo '<div class="dstb-head"><h2>'.esc_html($name).'</h2>';
            $created = !empty($r['created_at']) ? date_i18n('d.m.Y H:i', strtotime($r['created_at'])) : '';
            echo '<span style="font-size:12px;color:#a8b3bf">'.esc_html($created).'</span></div>';

            // Stammdaten & Tattoo-Infos
            echo '<div class="dstb-info-grid">';
            echo '<div>';
            if(!empty($r['email']))    echo '<div class="dstb-row"><strong>E-Mail:</strong> <a href="mailto:'.esc_attr($r['email']).'" style="color:#9fbfff">'.esc_html($r['email']).'</a></div>';
            if(!empty($r['phone']))    echo '<div class="dstb-row"><strong>Telefon:</strong> '.esc_html($r['phone']).'</div>';
            if(!empty($r['artist']))   echo '<div class="dstb-row"><strong>Artist:</strong> '.esc_html($r['artist']).'</div>';
            echo '</div><div>';
            if(!empty($r['style']))    echo '<div class="dstb-row"><strong>Stilrichtung:</strong> '.esc_html($r['style']).'</div>';
            if(!empty($r['bodypart'])) echo '<div class="dstb-row"><strong>Körperstelle:</strong> '.esc_html($r['bodypart']).'</div>';
            if(!empty($r['size']))     echo '<div class="dstb-row"><strong>Größe:</strong> '.esc_html($r['size']).'</div>';
            if(isset($r['budget']))    echo '<div class="dstb-row"><strong>Budget:</strong> '.intval($r['budget']).' €</div>';
            echo '</div></div>';

            if(!empty($r['desc_text'])){
                echo '<div class="dstb-section"><strong>Beschreibung:</strong><br>'.nl2br(esc_html($r['desc_text'])).'</div>';
            }

            if($uploads){
                echo '<div class="dstb-section"><strong>Bilder:</strong><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">';
                foreach($uploads as $id){
                    $url = wp_get_attachment_url($id);
                    if($url) echo '<a href="'.esc_url($url).'" target="_blank"><img src="'.esc_url($url).'"
                         style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #2a3340;"></a>';
                }
                echo '</div></div>';
            }

            // Kunden-Zeitfenster (nur Datum + Start)
            echo '<div class="dstb-section"><strong>Verfügbarkeiten (Kunde):</strong>';
            if($slots){
                echo '<ul class="dstb-slots">';
                foreach($slots as $s){
                    $ds = esc_html($s['date'] ?? '');
                    $st = esc_html($s['start'] ?? '');
                    if ($ds || $st) {
                        echo '<li>'.$ds.($st ? ' – Start: <strong>'.$st.'</strong>' : '').'</li>';
                    }
                }
                echo '</ul>';
            } else {
                echo '<p><em>Keine Zeitfenster angegeben.</em></p>';
            }
            echo '</div>';

            // Studio-Vorschläge – Tabelle
            echo '<div class="dstb-section"><strong>Terminvorschläge (Studio):</strong>';
            if($sugs){
                echo '<table class="dstb-sug-table"><tr><th>Datum</th><th>Start</th><th>Ende</th><th>Preis</th><th>Notiz</th><th>Status</th><th>Aktion</th></tr>';
                foreach($sugs as $s){
                    $sid = intval($s['id']);
                    echo '<tr data-sid="'.esc_attr($sid).'"
                              data-date="'.esc_attr($s['date']).'"
                              data-start="'.esc_attr($s['start']).'"
                              data-end="'.esc_attr($s['end']).'"
                              data-price="'.esc_attr($s['price']).'"
                              data-note="'.esc_attr($s['note']).'"
                              data-status="'.esc_attr($s['status']).'">';
                    echo '<td>'.esc_html($s['date']).'</td>';
                    echo '<td>'.esc_html($s['start']).'</td>';
                    echo '<td>'.esc_html($s['end']).'</td>';
                    echo '<td>'.intval($s['price']).' €</td>';
                    echo '<td>'.esc_html($s['note']).'</td>';
                    echo '<td>'.esc_html($s['status']).'</td>';
                    echo '<td>';
                    if ($s['status'] === 'draft') {
                        echo '<button type="button" class="button button-small dstb-edit-sug" data-sid="'.esc_attr($sid).'">Bearbeiten</button> ';
                        echo '<button type="button" class="button button-small dstb-del-sug" data-sid="'.esc_attr($sid).'" style="color:#a00;border-color:#a00;">Löschen</button>';
                    } else {
                        echo '<span style="color:#888;">Gesendet (keine Bearbeitung möglich)</span>';
                    }
                    echo '</td>';

                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p><em>Noch keine Vorschläge erstellt.</em></p>';
            }

            // Studio-Vorschläge – Formular (mehrere Zeilen + Speichern/Senden)
            echo '<form class="dstb-sug-form" data-req="'.$req_id.'">';
            wp_nonce_field('dstb_admin', 'dstb_nonce');
            echo '<div class="dstb-sug-grid" style="margin-top:10px;">';
            echo '<input type="date" name="date[]" required>';
            echo '<input type="time" name="start[]" step="1800" required>';
            echo '<input type="time" name="end[]" step="1800" required>';
            echo '<input type="number" name="price[]" placeholder="Preis €" min="0" step="10">';
            echo '<input type="text" name="note[]" placeholder="Notiz (optional)" class="dstb-col-2">';
            echo '</div>';
            echo '<p><button class="button add-sug" type="button">+ weiteren Vorschlag</button></p>';
            echo '<div class="dstb-actions">';
            echo '<button type="button" class="button dstb-save" data-action="draft">Nur speichern</button>';
            echo '<button type="button" class="button-primary dstb-send" data-action="send">An Kunden senden</button>';
            echo '<span class="dstb-sug-msg" aria-live="polite" style="margin-left:8px;"></span>';
            echo '</div>';
            echo '</form>';
            echo '</div>'; // section

            // Löschen
            $del = add_query_arg(['page'=>'dstb-requests','delete'=>$req_id],admin_url('admin.php'));
            echo '<div class="dstb-section" style="text-align:right"><a href="'.esc_url($del).'" class="dstb-delete" onclick="return confirm(\'Anfrage wirklich löschen?\')">Anfrage löschen</a></div>';

            echo '</div>'; // card
        }
        echo '</div>'; // grid

        // Modal für Bearbeitung
        echo '<div id="dstb-modal" class="dstb-modal" style="display:none;">
                <div class="dstb-modal__dialog">
                    <div class="dstb-modal__head">
                        <strong>Vorschlag bearbeiten</strong>
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
                                <button type="button" class="button dstb-modal-save">Speichern</button>
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
     * - action = 'draft' → Zeilen als draft einfügen
     * - action = 'send'  → neue Zeilen als draft + alle draft auf sent hochstufen
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
        if ($save_action === 'send') {
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status='sent' WHERE request_id=%d AND status='draft'",
                $req_id
            ));

            // 📧 Nur wenn wirklich gesendet wurde
            DSTB_Emails::send_proposals_to_customer($req_id);
        }

        $msg = ($save_action === 'send')
            ? sprintf('%d Vorschlag/Vorschläge gespeichert, %d an den Kunden gesendet.', $inserted, $updated)
            : sprintf('%d Vorschlag/Vorschläge gespeichert (Entwurf).', $inserted);

        wp_send_json_success(['msg' => $msg]);

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

    /**
 * AJAX: Vorschlag löschen (nur bei Entwürfen erlaubt)
 */
public function ajax_delete_suggestion(){
    check_ajax_referer('dstb_admin','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

    global $wpdb;
    $table = $wpdb->prefix . DSTB_DB::$suggestions;
    $id = intval($_POST['id'] ?? 0);
    if(!$id) wp_send_json_error(['msg'=>'Ungültige ID.']);

    // Nur löschen, wenn Entwurf
    $ok = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id=%d AND status='draft'", $id));

    if($ok) wp_send_json_success(['msg'=>'Vorschlag gelöscht.']);
    wp_send_json_error(['msg'=>'Löschen nicht möglich (evtl. schon gesendet).']);
}

}
