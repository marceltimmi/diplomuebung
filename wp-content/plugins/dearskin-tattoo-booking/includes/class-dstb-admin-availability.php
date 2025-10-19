<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_Admin_Availability {

    public function __construct(){
        add_action('admin_menu', [$this,'menu']);
    }

    public function menu(){
        add_submenu_page(
            'edit.php?post_type=tattoo_request',
            'Artists – Verfügbarkeiten',
            'Verfügbarkeiten',
            'manage_options',
            'dstb-availability',
            [$this,'screen']
        );
    }

    public function screen(){
        global $wpdb;

        $artists   = ['Silvia','Sahrabie']; // erweiterbar
        $wd_labels = ['Mo','Di','Mi','Do','Fr','Sa','So'];
        $table_vac = $wpdb->prefix . DSTB_DB::$vacations; // Urlaubstabelle

        // 🎯 aktiven Artist ermitteln: GET > POST > Standard
        $current_artist = isset($_GET['artist']) ? sanitize_text_field($_GET['artist'])
                         : (isset($_POST['artist']) ? sanitize_text_field($_POST['artist']) : 'Silvia');

        $saved_notice = false;

        /* ========== Urlaub löschen ========== */
        if (isset($_GET['delete_vac'])) {
            $vac_id = intval($_GET['delete_vac']);
            $wpdb->delete($table_vac, ['id' => $vac_id]);
            echo '<div class="updated"><p>Urlaub wurde gelöscht.</p></div>';
        }

        /* ========== SPEICHERN ========== */
        if ( isset($_POST['dstb_save_avail']) && check_admin_referer('dstb_avail') ) {
            $artist = sanitize_text_field($_POST['artist']);
            $current_artist = $artist;

            // Wochen-Ranges speichern
            for ($wd = 0; $wd < 7; $wd++) {
                $ranges = [];
                if (isset($_POST['ranges'][$wd]) && is_array($_POST['ranges'][$wd])) {
                    foreach ($_POST['ranges'][$wd] as $r) {
                        $from = sanitize_text_field($r['from'] ?? '');
                        $to   = sanitize_text_field($r['to'] ?? '');
                        if ($from && $to) $ranges[] = [$from, $to];
                    }
                }
                DSTB_DB::set_weekday_ranges($artist, $wd, $ranges);
            }

            // Urlaub speichern
            $vac_from = sanitize_text_field($_POST['vac_from'] ?? '');
            $vac_to   = sanitize_text_field($_POST['vac_to'] ?? '');
            if ($vac_from && $vac_to) {
                DSTB_DB::add_vacation($artist, $vac_from, $vac_to);
            }

            $saved_notice = true;
        }

        // 🧠 DB-Daten laden
        $map = DSTB_DB::get_weekday_ranges_map($current_artist);

        // Urlaube laden
        $vacations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, start_date, end_date FROM $table_vac WHERE artist = %s ORDER BY start_date ASC",
                $current_artist
            ),
            ARRAY_A
        );

        echo '<div class="wrap"><h1>Verfügbarkeiten & Urlaub</h1>';

        if ($saved_notice) {
            echo '<div class="updated notice is-dismissible"><p><strong>Gespeichert!</strong> Verfügbarkeiten von <b>'
                .esc_html($current_artist).'</b> aktualisiert.</p></div>';
        }

        /* ========== FORMULAR ========== */
        echo '<form method="post">';
        wp_nonce_field('dstb_avail');

        echo '<p><label>Artist: 
            <select name="artist" id="dstb-artist-select">';
        foreach ($artists as $a) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($a),
                selected($current_artist, $a, false),
                esc_html($a)
            );
        }
        echo '</select></label></p>';

        echo '<table class="widefat striped" style="max-width:800px;">
                <thead><tr><th>Wochentag</th><th>Zeiträume (von–bis)</th></tr></thead><tbody>';

        $defaults = [['09:00','12:00'],['13:00','16:00']];
        for ($wd = 0; $wd < 7; $wd++) {
            $ranges = $map[$wd] ?? [];
            echo '<tr>';
            echo '<td style="width:120px;"><strong>'.esc_html($wd_labels[$wd]).'</strong></td>';
            echo '<td>';
            echo '<div class="dstb-ranges" data-wd="'.esc_attr($wd).'">';

            if (empty($ranges)) $ranges = $defaults;
            foreach ($ranges as $i => $r) {
                printf(
                    '<div class="dstb-range-row">
                        <input type="time" name="ranges[%1$d][%2$d][from]" value="%3$s" step="1800">
                        &nbsp;–&nbsp;
                        <input type="time" name="ranges[%1$d][%2$d][to]" value="%4$s" step="1800">
                        <button class="button remove-range" type="button">×</button>
                    </div>',
                    $wd, $i, esc_attr($r[0]), esc_attr($r[1])
                );
            }

            echo '</div>';
            echo '<p><button class="button add-range" type="button" data-wd="'.esc_attr($wd).'">+ Zeitraum</button></p>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        /* ========== Urlaub / Betriebsurlaub ========== */
        echo '<h3 style="margin-top:24px;">Urlaub / Betriebsurlaub</h3>';
        echo '<p><label>Neuer Urlaub: Von <input type="date" name="vac_from"> bis <input type="date" name="vac_to"> ';
        echo '<button class="button-primary" name="dstb_save_avail" value="1">Speichern</button></label></p>';

        // Bestehende Urlaube anzeigen
        if (!empty($vacations)) {
            echo '<h4>Gespeicherte Urlaube für '.esc_html($current_artist).':</h4>';
            echo '<table class="widefat striped" style="max-width:500px;"><thead><tr><th>Von</th><th>Bis</th><th>Aktion</th></tr></thead><tbody>';
            foreach ($vacations as $v) {
                $del_url = add_query_arg([
                    'page' => 'dstb-availability',
                    'post_type' => 'tattoo_request',
                    'artist' => $current_artist,
                    'delete_vac' => $v['id']
                ], admin_url('edit.php'));
                printf(
                    '<tr><td>%s</td><td>%s</td><td><a href="%s" class="button button-small delete-vac" onclick="return confirm(\'Diesen Urlaub wirklich löschen?\')">🗑️ Löschen</a></td></tr>',
                    esc_html($v['start_date']),
                    esc_html($v['end_date']),
                    esc_url($del_url)
                );
            }
            echo '</tbody></table>';
        } else {
            echo '<p><em>Keine Urlaube eingetragen.</em></p>';
        }

        echo '</form>';

        /* ========== JAVASCRIPT ========== */
        echo '<script>
        (function(){
          // Artist-Wechsel: redirect mit GET (behält Auswahl!)
          const select = document.getElementById("dstb-artist-select");
          if(select){
            select.addEventListener("change", function(){
              const artist = this.value;
              const url = new URL(window.location.href);
              url.searchParams.set("artist", artist);
              window.location.href = url.toString();
            });
          }

          // Zeiträume hinzufügen
          document.querySelectorAll(".add-range").forEach(btn=>{
            btn.addEventListener("click", e=>{
              e.preventDefault();
              const wd = btn.dataset.wd;
              const wrap = document.querySelector(\'.dstb-ranges[data-wd="\'+wd+\'"]\');
              if(!wrap) return;
              const i = wrap.querySelectorAll(".dstb-range-row").length;
              const div = document.createElement("div");
              div.className = "dstb-range-row";
              div.innerHTML = `
                <input type="time" name="ranges[${wd}][${i}][from]" step="1800"> –
                <input type="time" name="ranges[${wd}][${i}][to]" step="1800">
                <button class="button remove-range" type="button">×</button>`;
              wrap.appendChild(div);
            });
          });

          // Zeitraum entfernen
          document.addEventListener("click", e=>{
            if(e.target.classList.contains("remove-range")){
              e.preventDefault();
              const row=e.target.closest(".dstb-range-row");
              if(row) row.remove();
            }
          });
        })();
        </script>';

        echo '</div>'; // .wrap
    }
}
