<?php
if (!defined('ABSPATH')) exit;

class DSTB_Admin_Requests {

    public function __construct(){
        // Menüstruktur
        add_action('admin_menu', [$this, 'menu']);

        // AJAX Endpoints
        add_action('wp_ajax_dstb_add_suggestion',    [$this, 'ajax_add_suggestion']);
        add_action('wp_ajax_dstb_update_suggestion', [$this, 'ajax_update_suggestion']);
        add_action('wp_ajax_dstb_delete_suggestion', [$this, 'ajax_delete_suggestion']);
    }

    /* =========================
       MENÜ & ROUTING
       ========================= */
    public function menu(){

        // Hauptseite (leitet intern auf "Neue Anfragen" um)
        add_menu_page(
            'Tattoo Anfragen',
            'Tattoo Anfragen',
            'manage_options',
            'dstb-requests',
            function(){ $this->screen('new'); },
            'dashicons-clipboard',
            25
        );


        add_submenu_page(
            'dstb-requests',
            'In Bearbeitung',
            'In Bearbeitung',
            'manage_options',
            'dstb-requests-inprogress',
            function(){ $this->screen('draft'); }
        );

        add_submenu_page(
            'dstb-requests',
            'Gesendet an Kunden',
            'Gesendet an Kunden',
            'manage_options',
            'dstb-requests-sent',
            function(){ $this->screen('sent'); }
        );

        add_submenu_page(
            'dstb-requests',
            'Bestätigte Termine',
            'Bestätigte Termine',
            'manage_options',
            'dstb-requests-confirmed',
            function(){ $this->screen('confirmed'); }
        );
    }

    /* =========================
       DATEN-HOLER je Filter
       ========================= */
    private function get_requests_by_filter($filter){
        global $wpdb;
        $req_table = $wpdb->prefix . DSTB_DB::$requests;
        $sug_table = $wpdb->prefix . DSTB_DB::$suggestions;

        switch ($filter) {
            case 'new':
                // Keine Vorschläge vorhanden
                $sql = "
                    SELECT r.*
                    FROM $req_table r
                    WHERE NOT EXISTS (
                        SELECT 1 FROM $sug_table s WHERE s.request_id = r.id
                    )
                    ORDER BY r.created_at DESC
                    LIMIT 500
                ";

                break;

            case 'draft':
                // Mindestens ein draft & KEIN sent/confirmed
                $sql = "
                    SELECT r.*
                    FROM $req_table r
                    WHERE EXISTS (
                        SELECT 1 FROM $sug_table s1 WHERE s1.request_id = r.id AND s1.status='draft'
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM $sug_table s2 WHERE s2.request_id = r.id AND s2.status IN ('sent','confirmed')
                    )
                    ORDER BY r.created_at DESC
                    LIMIT 500
                ";

                break;

            case 'sent':
                // Mindestens ein sent & KEIN confirmed (confirmed wandern ins andere Menü)
               $sql = "
                    SELECT r.*
                    FROM $req_table r
                    WHERE EXISTS (
                        SELECT 1 FROM $sug_table s1 WHERE s1.request_id = r.id AND s1.status='sent'
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM $sug_table s2 WHERE s2.request_id = r.id AND s2.status='confirmed'
                    )
                    ORDER BY r.created_at DESC
                    LIMIT 500
                ";

                break;
            case 'confirmed':
                // Mindestens EIN confirmed — egal ob daneben noch andere Stati existieren
                $sql = "
                    SELECT r.*
                    FROM $req_table r
                    WHERE EXISTS (
                        SELECT 1 FROM $sug_table s1
                        WHERE s1.request_id = r.id AND s1.status = 'confirmed'
                    )
                    ORDER BY r.created_at DESC
                    LIMIT 500
                ";
                break;


            default:
                // Fallback: alles
                $sql = "SELECT * FROM $req_table ORDER BY created_at DESC LIMIT 200";
                break;
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /* =========================
       HAUPT-SCREEN (gerendert für alle Filter)
       ========================= */
    public function screen($filter = 'new'){
        if (!current_user_can('manage_options')) wp_die('Kein Zugriff.');

        global $wpdb;
        $req_table = $wpdb->prefix . DSTB_DB::$requests;
        $sug_table = $wpdb->prefix . DSTB_DB::$suggestions;

        // Anfrage löschen (hart)
        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);
            $wpdb->delete($req_table, ['id'=>$id]);
            echo '<div class="updated"><p><strong>Anfrage gelöscht.</strong></p></div>';
        }

        // Admin JS/CSS für Modal & Buttons
        wp_enqueue_style('dstb-admin-requests', plugins_url('../assets/css/admin-requests.css', __FILE__), [], defined('DSTB_VERSION')?DSTB_VERSION:'1.0.0');
        $custom_css = "
        .dstb-admin-wrap {
        background:#0f1216;
        margin:0 -20px;
        padding:40px;
        line-height:1.6;
        overflow:hidden;
        }
        .dstb-scroll-wrapper {
        position:relative;
        width:100%;
        overflow:hidden;
        margin-top:25px;
        }
        .dstb-grid {
        display:flex;
        gap:32px;
        overflow-x:auto;
        scroll-behavior:smooth;
        padding:10px 20px 20px 20px;
        }
        .dstb-card {
        flex:0 0 720px;
        max-width:720px;
        background:#1b1f27;
        border:1px solid #2a3340;
        border-radius:18px;
        padding:28px;
        color:#e9eef5;
        box-shadow:0 6px 22px rgba(0,0,0,.45);
        transition:transform .2s ease, box-shadow .2s ease;
        }
        .dstb-card:hover {
        transform:translateY(-4px);
        box-shadow:0 10px 28px rgba(0,0,0,.6);
        z-index:2;
        }";
        wp_add_inline_style('dstb-admin-requests', $custom_css);

        wp_enqueue_script('dstb-admin-requests', plugins_url('../assets/js/admin-requests.js', __FILE__), ['jquery'], defined('DSTB_VERSION')?DSTB_VERSION:'1.0.0', true);
        wp_localize_script('dstb-admin-requests', 'DSTB_Ajax', [
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dstb_admin')
        ]);

        // Datensätze je Filter holen
        $rows = $this->get_requests_by_filter($filter);

        // Titel passend zum Tab
        $titles = [
            'new'       => 'Neue Anfragen',
            'draft'     => 'In Bearbeitung',
            'sent'      => 'Gesendet an Kunden',
            'confirmed' => 'Bestätigte Termine'
        ];
        $title = $titles[$filter] ?? 'Tattoo-Anfragen';

        // Schwarzer Hintergrund nur auf unserer Seite (scoped Box)
       echo '<div class="wrap dstb-admin-wrap">';
        echo '<h1 style="margin-bottom:20px;">'.$title.'</h1>';

        /* Suchfeld */
        echo '<div style="margin-bottom:20px;display:flex;gap:10px;align-items:center;">
                <input type="text" id="dstb-search" placeholder="🔍 Name oder Datum suchen..."
                    style="flex:1;max-width:400px;padding:8px 12px;border-radius:8px;
                    border:1px solid #2a3340;background:#0f1216;color:#e9eef5;">
            </div>';
         echo '<style>
            /* ==== Modernes horizontales Layout ==== */
            .dstb-admin-wrap{
            background:#0f1216;
            margin:0 -20px;
            padding:40px;
            line-height:1.6;
            overflow:hidden;
            }
            .dstb-scroll-wrapper{
            position:relative;
            width:100%;
            overflow:hidden;
            margin-top:25px;
            }
            .dstb-grid{
            display:flex;
            gap:32px;
            overflow-x:auto;
            scroll-behavior:smooth;
            padding:10px 20px 20px 20px;
            }
            .dstb-grid::-webkit-scrollbar{
            height:10px;
            }
            .dstb-grid::-webkit-scrollbar-thumb{
            background:#2a3340;
            border-radius:10px;
            }

            /* ==== Karten ==== */
            .dstb-card{
            flex:0 0 720px;
            max-width:720px;
            background:#1b1f27;
            border:1px solid #2a3340;
            border-radius:18px;
            padding:28px;
            color:#e9eef5;
            box-shadow:0 6px 22px rgba(0,0,0,.45);
            transition:transform .2s ease, box-shadow .2s ease;
            }
            .dstb-card:hover{
            transform:translateY(-4px);
            box-shadow:0 10px 28px rgba(0,0,0,.6);
            z-index:2;
            }
            .dstb-head{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:16px;
            flex-wrap:wrap;
            }
            .dstb-head h2{
            margin:0;
            color:#9fbfff;
            font-size:20px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
            max-width:260px;
            }
            .dstb-section{
            margin-top:18px;
            padding-top:14px;
            border-top:1px solid #2a3340;
            }
            .dstb-info-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px 36px;
            }
            .dstb-row{
            margin:4px 0;
            font-size:15px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
            }
            .dstb-row strong{
            color:#dfe7f3;
            width:150px;
            display:inline-block;
            }
            .dstb-slots{
            margin:8px 0 0 16px;
            padding:0;
            list-style:none;
            font-size:14px;
            }
            .dstb-slots li{margin:4px 0;}
            .dstb-sug-table{
            margin-top:14px;
            width:100%;
            border-collapse:collapse;
            font-size:14px;
            table-layout:fixed;
            word-wrap:break-word;
            }
            .dstb-sug-table th,.dstb-sug-table td{
            padding:8px 6px;
            border-top:1px solid #2a3340;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            }
            .dstb-sug-table th{
            color:#a8b3bf;
            background:#10161e;
            text-align:left;
            }
            .dstb-actions{
            margin-top:14px;
            display:flex;
            gap:12px;
            align-items:center;
            flex-wrap:wrap;
            }

            /* ==== Scroll Buttons ==== */
            .dstb-scroll-btn{
            position:absolute;
            top:50%;
            transform:translateY(-50%);
            background:#2a3340;
            color:#fff;
            border:none;
            border-radius:50%;
            width:44px;
            height:44px;
            cursor:pointer;
            z-index:10;
            opacity:0.8;
            box-shadow:0 2px 6px rgba(0,0,0,.4);
            }
            .dstb-scroll-btn:hover{opacity:1;background:#3b4a5c;}
            .dstb-scroll-left{left:5px;}
            .dstb-scroll-right{right:5px;}
        </style>';
        /* Scroll-Wrapper mit Pfeilen */
        echo '<div class="dstb-scroll-wrapper">
                <button class="dstb-scroll-btn dstb-scroll-left" title="Zurück">&#x276E;</button>
                <div class="dstb-grid" id="dstb-grid">';

        if (!$rows){
            echo '<p style="color:#e9eef5"><em>Keine Anfragen im gewählten Bereich.</em></p></div>';
            return;
        }

        foreach($rows as $r){
            $req_id  = intval($r['id']);
            $slots   = json_decode($r['slots'] ?? '[]', true) ?: [];
            $uploads = json_decode($r['uploads'] ?? '[]', true) ?: [];

            // Vorschläge je Request laden (für Tabelle)
            $sugs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sug_table WHERE request_id=%d ORDER BY created_at DESC",
                $req_id
            ), ARRAY_A);

            // Im Tab "Bestätigte Termine" nur bestätigte Zeilen zeigen
            $current_page = $_GET['page'] ?? '';
            if ($current_page === 'dstb-requests-confirmed' && !empty($sugs)) {
                $sugs = array_values(array_filter($sugs, static function($row){
                    return ($row['status'] === 'confirmed');
                }));
            }

            $name = trim($r['name'] ?? '');
            if ($name==='') $name='Unbekannt';

            echo '<div class="dstb-card">';
            // Kopf
            echo '<div class="dstb-head"><h2>'.esc_html($name).'</h2>';
            $created = !empty($r['created_at']) ? date_i18n('d.m.Y H:i', strtotime($r['created_at'])) : '';
            echo '<span style="font-size:12px;color:#a8b3bf">'.esc_html($created).'</span></div>';

            // Stammdaten
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
              echo '</div>';
            echo '</div>';

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

            // Kunden-Zeitfenster
            echo '<div class="dstb-section"><strong>Verfügbarkeiten (Kunde):</strong>';
            if($slots){
                echo '<ul class="dstb-slots">';
                foreach($slots as $s){
                    $ds = esc_html($s['date'] ?? ''); $st = esc_html($s['start'] ?? '');
                    if ($ds || $st) echo '<li>'.$ds.($st?' – Start: <strong>'.$st.'</strong>':'').'</li>';
                }
                echo '</ul>';
            } else echo '<p><em>Keine Zeitfenster angegeben.</em></p>';
            echo '</div>';

            // Studio-Vorschläge
            echo '<div class="dstb-section"><div style="display:flex;justify-content:space-between;align-items:center;"><strong>Terminvorschläge (Studio):</strong>';

            // Status-Badge je Request ableiten
            $badge = '';
            if ($sugs){
                $hasConfirmed = array_filter($sugs, fn($x)=>$x['status']==='confirmed');
                $hasSent      = array_filter($sugs, fn($x)=>$x['status']==='sent');
                $hasDraft     = array_filter($sugs, fn($x)=>$x['status']==='draft');

                if ($hasConfirmed) $badge = '<span class="dstb-badge">Bestätigt</span>';
                elseif ($hasSent)  $badge = '<span class="dstb-badge">Gesendet</span>';
                elseif ($hasDraft) $badge = '<span class="dstb-badge">In Bearbeitung</span>';
            } else {
                $badge = '<span class="dstb-badge">Neu</span>';
            }
            echo $badge.'</div>';

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
                        echo '<button type="button" class="button button-small dstb-edit-sug" data-sid="'.$sid.'">Bearbeiten</button> ';
                        echo '<button type="button" class="button button-small dstb-del-sug" data-sid="'.$sid.'" style="color:#a00;border-color:#a00;">Löschen</button>';
                    } else {
                        echo '<span style="color:#888;">gesperrt</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p><em>Noch keine Vorschläge erstellt.</em></p>';
            }

            // Formular (nur sinnvoll, wenn noch nicht confirmed)
            $allowForm = true;
            if (!empty($sugs)) {
                foreach($sugs as $s) { if ($s['status']==='confirmed') { $allowForm = false; break; } }
            }

            if ($allowForm) {
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
            } else {
                echo '<p style="margin-top:10px;color:#a8b3bf"><em>Termin ist bestätigt – keine neuen Vorschläge mehr möglich.</em></p>';
            }

            echo '</div>'; // section

            // Anfrage löschen
            $del = add_query_arg(['page'=>$_GET['page'] ?? 'dstb-requests','delete'=>$req_id],admin_url('admin.php'));
            echo '<div class="dstb-section" style="text-align:right"><a href="'.esc_url($del).'" class="dstb-delete" onclick="return confirm(\'Anfrage wirklich löschen?\')">Anfrage löschen</a></div>';

            echo '</div>'; // card
        }

        // Grid schließen + rechter Pfeil + Wrapper schließen
        echo '</div><button type="button" class="dstb-scroll-btn dstb-scroll-right" title="Weiter">&#x276F;</button></div>';


        // Modal für Bearbeitung
        echo '<div id="dstb-modal" class="dstb-modal" style="display:none;">
                <div class="dstb-modal__dialog" style="background:#1b1f27;border:1px solid #2a3340;border-radius:12px;max-width:720px;margin:60px auto;padding:0;box-shadow:0 10px 30px rgba(0,0,0,.6);">
                    <div class="dstb-modal__head" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #2a3340;color:#e9eef5;">
                        <strong>Vorschlag bearbeiten</strong>
                        <button type="button" class="dstb-modal__close" aria-label="Schließen" style="background:#10161e;color:#e9eef5;border:1px solid #2a3340;border-radius:8px;cursor:pointer;padding:4px 10px;">×</button>
                    </div>
                    <div class="dstb-modal__body" style="padding:16px;color:#e9eef5;">
                        <form id="dstb-modal-form">
                            <input type="hidden" name="id" value="">
                            <div class="dstb-modal-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
                                <label>Datum
                                    <input type="date" name="date" required style="background:#0c1117;color:#e9eef5;border:1px solid #1d2633;border-radius:8px;padding:8px 10px;">
                                </label>
                                <label>Start
                                    <input type="time" name="start" step="1800" required style="background:#0c1117;color:#e9eef5;border:1px solid #1d2633;border-radius:8px;padding:8px 10px;">
                                </label>
                                <label>Ende
                                    <input type="time" name="end" step="1800" required style="background:#0c1117;color:#e9eef5;border:1px solid #1d2633;border-radius:8px;padding:8px 10px;">
                                </label>
                                <label>Preis (€)
                                    <input type="number" name="price" min="0" step="10" style="background:#0c1117;color:#e9eef5;border:1px solid #1d2633;border-radius:8px;padding:8px 10px;">
                                </label>
                                <label class="dstb-col-2">Notiz
                                    <input type="text" name="note" placeholder="z. B. Realism Unterarm – Sitzung 1" style="background:#0c1117;color:#e9eef5;border:1px solid #1d2633;border-radius:8px;padding:8px 10px;">
                                </label>
                            </div>'.
                            wp_nonce_field('dstb_admin','dstb_nonce',true,false).'
                            <div class="dstb-modal__actions" style="display:flex;gap:8px;align-items:center;margin-top:12px;">
                                <button type="button" class="button dstb-modal-save">Speichern</button>
                                <button type="button" class="button dstb-modal-cancel">Abbrechen</button>
                                <span class="dstb-modal-msg" aria-live="polite"></span>
                            </div>
                        </form>
                    </div>
                </div>
              </div>';

        echo '</div>'; // wrap

        echo '<script>
        document.addEventListener("DOMContentLoaded", function(){
        const wrapper = document.querySelector(".dstb-scroll-wrapper");
        const grid    = wrapper ? wrapper.querySelector("#dstb-grid") : null;
        const left    = wrapper ? wrapper.querySelector(".dstb-scroll-left") : null;
        const right   = wrapper ? wrapper.querySelector(".dstb-scroll-right") : null;
        const search  = document.getElementById("dstb-search");

        function cardWidthWithGap(){
            const card = grid ? grid.querySelector(".dstb-card") : null;
            if(!card) return 480;
            const style = window.getComputedStyle(grid);
            const gap = parseFloat(style.columnGap || style.gap || "0");
            return card.getBoundingClientRect().width + (isNaN(gap) ? 0 : gap);
        }

        function scrollByCards(dir){
            if(!grid) return;
            const amount = Math.max(cardWidthWithGap(), grid.clientWidth * 0.9) * dir;
            grid.scrollBy({ left: amount, behavior: "smooth" });
        }

        if (left && right && grid) {
            left.addEventListener("click",  (e)=>{ e.preventDefault(); scrollByCards(-1); });
            right.addEventListener("click", (e)=>{ e.preventDefault(); scrollByCards(1);  });

            // Keyboard: Pfeil-links / Pfeil-rechts
            wrapper.addEventListener("keydown", (e)=>{
            if (e.key === "ArrowLeft")  { e.preventDefault(); scrollByCards(-1); }
            if (e.key === "ArrowRight") { e.preventDefault(); scrollByCards(1);  }
            });
            // Fokus damit Keyboard sofort geht
            wrapper.setAttribute("tabindex","0");
        }

        if (search && grid) {
            search.addEventListener("input", function(){
            const term = this.value.toLowerCase().trim();
            grid.querySelectorAll(".dstb-card").forEach((card)=>{
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(term) ? "" : "none";
            });
            });
        }
        });
        </script>';


    }

    /* =========================
       AJAX: Vorschläge speichern/senden
       ========================= */
    public function ajax_add_suggestion(){
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

        global $wpdb;
        $table  = $wpdb->prefix . DSTB_DB::$suggestions;

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
            // Mail an Kunden
            if (method_exists('DSTB_Emails','send_proposals_to_customer')) {
                DSTB_Emails::send_proposals_to_customer($req_id);
            }
        }

        $msg = ($save_action === 'send')
            ? sprintf('%d Vorschlag/Vorschläge gespeichert, %d an den Kunden gesendet.', $inserted, $updated)
            : sprintf('%d Vorschlag/Vorschläge gespeichert (Entwurf).', $inserted);

        wp_send_json_success(['msg'=>$msg]);
    }

    /* =========================
       AJAX: Vorschlag aktualisieren (Modal)
       ========================= */
    public function ajax_update_suggestion(){
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);

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

        // Blockieren, falls schon gesendet/confirmed
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE id=%d", $id));
        if ($status !== 'draft') {
            wp_send_json_error(['msg'=>'Dieser Vorschlag ist gesperrt (nicht mehr Draft).']);
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

    /* =========================
       AJAX: Vorschlag löschen (nur Draft)
       ========================= */
    public function ajax_delete_suggestion(){
        check_ajax_referer('dstb_admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Keine Berechtigung.']);
        global $wpdb;
        $table = $wpdb->prefix . DSTB_DB::$suggestions;
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'Ungültige ID.']);
        $ok = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id=%d AND status='draft'", $id));
        if($ok) wp_send_json_success(['msg'=>'Vorschlag gelöscht.']);
        wp_send_json_error(['msg'=>'Löschen nicht möglich (gesendet/bestätigt).']);
    }
}
