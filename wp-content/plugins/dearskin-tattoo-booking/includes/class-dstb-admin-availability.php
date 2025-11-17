<?php
if (!defined('ABSPATH')) exit;

class DSTB_Admin_Availability {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu() {
        add_submenu_page(
            'dstb-requests',
            'Artists – Verfügbarkeiten',
            'Verfügbarkeiten',
            'manage_options',
            'dstb-availability',
            [$this, 'screen']
        );
    }

    public function screen() {
        global $wpdb;

        $default_artists = function_exists('dstb_default_artist_names')
            ? dstb_default_artist_names()
            : ['Silvia', 'Sahrabie', 'Artist of Residence'];

        if (class_exists('DSTB_Admin_Artists')) {
            $artists = DSTB_Admin_Artists::get_artist_names(true);
            $no_calendar_artists = DSTB_Admin_Artists::get_no_calendar_artists();
        } else {
            $artists = $default_artists;
            $no_calendar_artists = ['Kein bestimmter Artist', 'Artist of Residence'];
        }

        if (!is_array($artists) || empty($artists)) {
            $artists = $default_artists;
        }

        $artists = array_values(array_unique(array_map('strval', $artists)));

        $wd_labels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $table_vac = $wpdb->prefix . DSTB_DB::$vacations;

        $current_artist = isset($_GET['artist']) ? sanitize_text_field(wp_unslash($_GET['artist'])) : ($artists[0] ?? '');
        if ($current_artist === '' && !empty($artists)) {
            $current_artist = $artists[0];
        }
        if ($current_artist !== '' && !in_array($current_artist, $artists, true)) {
            array_unshift($artists, $current_artist);
            $artists = array_values(array_unique($artists));
        }

        $saved_notice = false;
        $errors = [];

        if (isset($_GET['delete_vac'])) {
            $vac_id = intval($_GET['delete_vac']);
            $wpdb->delete($table_vac, ['id' => $vac_id]);
            echo '<div class="updated"><p>' . esc_html__('Urlaub wurde gelöscht.', 'dstb') . '</p></div>';
        }

        if (isset($_POST['dstb_save_avail']) && check_admin_referer('dstb_avail')) {
            $artist = sanitize_text_field(wp_unslash($_POST['artist'] ?? ''));
            if ($artist === '') {
                $errors[] = __('Bitte wähle einen Artist aus.', 'dstb');
            } else {
                $current_artist = $artist;

                for ($wd = 0; $wd < 7; $wd++) {
                    $ranges = [];
                    if (isset($_POST['ranges'][$wd]) && is_array($_POST['ranges'][$wd])) {
                        foreach ($_POST['ranges'][$wd] as $r) {
                            $from = sanitize_text_field(wp_unslash($r['from'] ?? ''));
                            $to = sanitize_text_field(wp_unslash($r['to'] ?? ''));
                            if ($from && $to) {
                                $ranges[] = [$from, $to];
                            }
                        }
                    }
                    DSTB_DB::set_weekday_ranges($artist, $wd, $ranges);
                }

                $vac_from = sanitize_text_field(wp_unslash($_POST['vac_from'] ?? ''));
                $vac_to   = sanitize_text_field(wp_unslash($_POST['vac_to'] ?? ''));
                if ($vac_from && $vac_to) {
                    DSTB_DB::add_vacation($artist, $vac_from, $vac_to);
                }

                $saved_notice = true;
            }
        }

        $map = $current_artist !== '' ? DSTB_DB::get_weekday_ranges_map($current_artist) : [];
        $vacations = $current_artist !== ''
            ? $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, start_date, end_date FROM $table_vac WHERE artist = %s ORDER BY start_date ASC",
                    $current_artist
                ),
                ARRAY_A
            )
            : [];

        echo '<div class="wrap"><h1>Verfügbarkeiten & Urlaub</h1>';

        foreach ($errors as $error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }

        if ($saved_notice) {
            printf(
                '<div class="updated notice is-dismissible"><p><strong>%s</strong> %s</p></div>',
                esc_html__('Gespeichert!', 'dstb'),
                sprintf(
                    wp_kses(
                        __('Verfügbarkeiten von <strong>%s</strong> aktualisiert.', 'dstb'),
                        ['strong' => []]
                    ),
                    esc_html($current_artist)
                )
            );
        }

        echo '<form method="post">';
        wp_nonce_field('dstb_avail');

        echo '<div class="dstb-artist-header" style="display:flex;align-items:center;gap:10px;margin-bottom:15px;">';
        echo '<label for="dstb-artist-select">' . esc_html__('Artist:', 'dstb') . '</label>';
        echo '<select name="artist" id="dstb-artist-select" style="min-width:220px;">';
        if (!empty($artists)) {
            foreach ($artists as $a) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($a),
                    selected($current_artist, $a, false),
                    esc_html($a)
                );
            }
        } else {
            echo '<option value="">' . esc_html__('— bitte Artist anlegen —', 'dstb') . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="button button-secondary" id="dstb-add-artist-btn">+ ' . esc_html__('Artist hinzufügen', 'dstb') . '</button>';
        echo '<button type="button" class="button" id="dstb-del-artist-btn">' . esc_html__('Artist löschen', 'dstb') . '</button>';
        echo '</div>';

        // 🧩 Bestimmte Artists sollen keinen Kalender haben
        $show_calendar = true;

        if (!empty($current_artist) && in_array(trim($current_artist), $no_calendar_artists, true)) {
            echo '<div style="margin:20px 0;padding:12px 16px;background:#1b1f27;color:#e9eef5;border-left:4px solid #cc0000;">';
            echo '<strong>' . esc_html($current_artist) . '</strong> hat keinen individuellen Kalender.</div>';
            $show_calendar = false;
        }

        if ($show_calendar) {
        echo '<table class="widefat striped" style="max-width:800px;">';
        echo '<thead><tr><th>' . esc_html__('Wochentag', 'dstb') . '</th><th>' . esc_html__('Zeiträume (von–bis)', 'dstb') . '</th></tr></thead><tbody>';

        $defaults = [['09:00', '12:00'], ['13:00', '16:00']];
        for ($wd = 0; $wd < 7; $wd++) {
            $ranges = $map[$wd] ?? $defaults;
            echo '<tr>';
            echo '<td style="width:120px;"><strong>' . esc_html($wd_labels[$wd]) . '</strong></td>';
            echo '<td>';
            echo '<div class="dstb-ranges" data-wd="' . esc_attr($wd) . '">';
            foreach ($ranges as $i => $r) {
                printf(
                    '<div class="dstb-range-row">
                        <input type="time" name="ranges[%1$d][%2$d][from]" value="%3$s" step="1800">
                        &nbsp;–&nbsp;
                        <input type="time" name="ranges[%1$d][%2$d][to]" value="%4$s" step="1800">
                        <button class="button remove-range" type="button">×</button>
                    </div>',
                    $wd,
                    $i,
                    esc_attr($r[0]),
                    esc_attr($r[1])
                );
            }
            echo '</div>';
            echo '<p><button class="button add-range" type="button" data-wd="' . esc_attr($wd) . '">+ ' . esc_html__('Zeitraum', 'dstb') . '</button></p>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3 style="margin-top:24px;">' . esc_html__('Urlaub / Betriebsurlaub', 'dstb') . '</h3>';
        echo '<p><label>' . esc_html__('Neuer Urlaub:', 'dstb') . ' ';
        echo esc_html__('Von', 'dstb') . ' <input type="date" name="vac_from"> ';
        echo esc_html__('bis', 'dstb') . ' <input type="date" name="vac_to"> ';
        echo '<button class="button-primary" name="dstb_save_avail" value="1">' . esc_html__('Speichern', 'dstb') . '</button></label></p>';

        if (!empty($vacations)) {
            echo '<h4>' . sprintf(esc_html__('Gespeicherte Urlaube für %s:', 'dstb'), esc_html($current_artist)) . '</h4>';
            echo '<table class="widefat striped" style="max-width:500px;"><thead><tr><th>' . esc_html__('Von', 'dstb') . '</th><th>' . esc_html__('Bis', 'dstb') . '</th><th>' . esc_html__('Aktion', 'dstb') . '</th></tr></thead><tbody>';
            foreach ($vacations as $v) {
                $del_url = add_query_arg([
                    'page'       => 'dstb-availability',
                    'artist'     => $current_artist,
                    'delete_vac' => $v['id']
                ], admin_url('admin.php'));
                printf(
                    '<tr><td>%s</td><td>%s</td><td><a href="%s" class="button button-small" onclick="return confirm(\'%s\')">🗑️ %s</a></td></tr>',
                    esc_html($v['start_date']),
                    esc_html($v['end_date']),
                    esc_url($del_url),
                    esc_js(__('Diesen Urlaub wirklich löschen?', 'dstb')),
                    esc_html__('Löschen', 'dstb')
                );
            }
            echo '</tbody></table>';
        } else {
            echo '<p><em>' . esc_html__('Keine Urlaube eingetragen.', 'dstb') . '</em></p>';
        }
    }
        echo '</form>';

        $delete_options = '';
        if (!empty($artists)) {
            foreach ($artists as $index => $name) {
                $delete_options .= sprintf(
                    '<label class="dstb-modal-option"><input type="radio" name="delete_artist" value="%1$s"%2$s> <span>%3$s</span></label>',
                    esc_attr($name),
                    $index === 0 ? ' checked' : '',
                    esc_html($name)
                );
            }
        } else {
            $delete_options .= '<p class="dstb-modal-empty">' . esc_html__('Keine Artists vorhanden.', 'dstb') . '</p>';
        }

        echo '<div class="dstb-modal-overlay" id="dstb-add-artist-modal" hidden>
                <div class="dstb-modal">
                    <button type="button" class="dstb-modal-close" data-modal-close>×</button>
                    <h2>' . esc_html__('Artist hinzufügen', 'dstb') . '</h2>
                    <form id="dstb-add-artist-form">
                        <label class="dstb-modal-field">
                            <span>' . esc_html__('Name des Artists', 'dstb') . '</span>
                            <input type="text" name="name" id="dstb-add-artist-input" required maxlength="150" autocomplete="off" placeholder="' . esc_attr__('z. B. Alex', 'dstb') . '">
                        </label>
                        <fieldset class="dstb-modal-fieldset dstb-modal-fieldset--columns">
                            <legend>' . esc_html__('Kalender-Typ', 'dstb') . '</legend>
                            <label class="dstb-modal-option">
                                <input type="radio" name="has_calendar" value="1" checked>
                                <div>
                                    <strong>' . esc_html__('Fixer Artist mit Terminkalender', 'dstb') . '</strong>
                                    <div class="dstb-modal-hint">' . esc_html__('Individuelle Verfügbarkeiten und Urlaube pflegen.', 'dstb') . '</div>
                                </div>
                            </label>
                            <label class="dstb-modal-option">
                                <input type="radio" name="has_calendar" value="0">
                                <div>
                                    <strong>' . esc_html__('Artist ohne Kalender', 'dstb') . '</strong>
                                    <div class="dstb-modal-hint">' . esc_html__('Nutze Sammel-Anfragen wie „Artist of Residence“ ohne individuelle Slots.', 'dstb') . '</div>
                                </div>
                            </label>
                        </fieldset>
                        <p class="dstb-modal-hint">' . esc_html__('Der Artist erscheint automatisch im Formular und im Frontend.', 'dstb') . '</p>
                        <div class="dstb-modal-actions">
                            <button type="submit" class="button button-primary">' . esc_html__('Speichern', 'dstb') . '</button>
                            <button type="button" class="button button-secondary" data-modal-close>' . esc_html__('Abbrechen', 'dstb') . '</button>
                        </div>
                        <div class="dstb-modal-feedback" role="alert" aria-live="polite"></div>
                    </form>
                </div>
            </div>';

        echo '<div class="dstb-modal-overlay" id="dstb-delete-artist-modal" hidden>
                <div class="dstb-modal">
                    <button type="button" class="dstb-modal-close" data-modal-close>×</button>
                    <h2>' . esc_html__('Artist löschen', 'dstb') . '</h2>
                    <form id="dstb-delete-artist-form">
                        <fieldset class="dstb-modal-fieldset">
                            <legend>' . esc_html__('Wähle den Artist, den du entfernen möchtest:', 'dstb') . '</legend>
                            <div class="dstb-modal-options" id="dstb-delete-artist-options">' . $delete_options . '</div>
                        </fieldset>
                        <p class="dstb-modal-hint">' . esc_html__('Verfügbarkeiten und Urlaube des Artists werden entfernt.', 'dstb') . '</p>
                        <div class="dstb-modal-actions">
                            <button type="submit" class="button button-secondary button-danger">' . esc_html__('Artist löschen', 'dstb') . '</button>
                            <button type="button" class="button" data-modal-close>' . esc_html__('Abbrechen', 'dstb') . '</button>
                        </div>
                        <div class="dstb-modal-feedback" role="alert" aria-live="polite"></div>
                    </form>
                </div>
            </div>';

        $no_artist_label = esc_js(__('— bitte Artist anlegen —', 'dstb'));

        echo <<<JS
<script>
(function(){
  const select = document.getElementById('dstb-artist-select');

  function updateArtists(list, options){
    const opts = Object.assign({focus:null,reload:false}, options || {});
    const names = Array.isArray(list) ? list.filter(function(name){
      return typeof name === 'string' && name.trim() !== '';
    }) : [];

    if (select) {
      const current = select.value;
      select.innerHTML = '';

      if (names.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '$no_artist_label';
        select.appendChild(opt);
      } else {
        names.forEach(function(name){
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          select.appendChild(opt);
        });
      }

      let target = opts.focus && names.indexOf(opts.focus) !== -1 ? opts.focus : '';
      if (!target) {
        if (names.indexOf(current) !== -1) {
          target = current;
        } else if (names.length) {
          target = names[0];
        }
      }

      if (target) {
        select.value = target;
      } else if (names.length === 0) {
        select.value = '';
      }

      if (opts.reload) {
        const url = new URL(window.location.href);
        if (target) {
          url.searchParams.set('artist', target);
        } else {
          url.searchParams.delete('artist');
        }
        window.location.href = url.toString();
      }

      return { target: target, names: names };
    }

    return { target: null, names: [] };
  }

  window.DSTBAvailability = window.DSTBAvailability || {};
  window.DSTBAvailability.updateArtists = updateArtists;

  if (select) {
    select.addEventListener('change', function(){
      const artist = this.value;
      const url = new URL(window.location.href);
      if (artist) {
        url.searchParams.set('artist', artist);
      } else {
        url.searchParams.delete('artist');
      }
      window.location.href = url.toString();
    });
  }

  document.querySelectorAll('.add-range').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const wd = this.dataset.wd;
      const wrap = document.querySelector('.dstb-ranges[data-wd="' + wd + '"]');
      if (!wrap) return;
      const i = wrap.querySelectorAll('.dstb-range-row').length;
      const div = document.createElement('div');
      div.className = 'dstb-range-row';
      div.innerHTML = '<input type="time" name="ranges[' + wd + '][' + i + '][from]" step="1800"> – '
        + '<input type="time" name="ranges[' + wd + '][' + i + '][to]" step="1800"> '
        + '<button class="button remove-range" type="button">×</button>';
      wrap.appendChild(div);
    });
  });

  document.addEventListener('click', function(e){
    if (e.target.classList.contains('remove-range')) {
      e.preventDefault();
      const row = e.target.closest('.dstb-range-row');
      if (row) {
        row.remove();
      }
    }
  });
})();
</script>
JS;

        echo '</div>';
    }
}
