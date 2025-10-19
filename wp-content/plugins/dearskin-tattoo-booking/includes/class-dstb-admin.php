<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_Admin {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'metaboxes']);
        add_action('save_post_tattoo_request', [$this, 'save'], 10, 2);
    }

    public function metaboxes() {
        add_meta_box(
            'dstb_meta',
            'Anfrage Details',
            [$this, 'box_details'],
            'tattoo_request',
            'normal',
            'high'
        );
        add_meta_box(
            'dstb_proposals',
            'Termin-Vorschläge',
            [$this, 'box_proposals'],
            'tattoo_request',
            'normal',
            'default'
        );
    }

    private function get($post, $key, $default = '') {
        return get_post_meta($post->ID, $key, true) ?: $default;
    }

    public function box_details($post) {
        wp_nonce_field('dstb_save_' . $post->ID, 'dstb_save_nonce');
        $data = [
            'name'     => $this->get($post, 'name'),
            'email'    => $this->get($post, 'email'),
            'phone'    => $this->get($post, 'phone'),
            'artist'   => $this->get($post, 'artist'),
            'style'    => $this->get($post, 'style'),
            'bodypart' => $this->get($post, 'bodypart'),
            'size'     => $this->get($post, 'size'),
            'budget'   => $this->get($post, 'budget'),
            'desc'     => $this->get($post, 'desc'),
            'slots'    => $this->get($post, 'slots', []),
            'uploads'  => $this->get($post, 'uploads', []),
            'gdpr'     => $this->get($post, 'gdpr', '0'),
        ];

        echo '<style>.dstb-admin table{width:100%} .dstb-admin th{text-align:left;width:200px}</style>';
        echo '<div class="dstb-admin"><table class="form-table">';

        foreach ([
            'name'      => 'Name',
            'email'     => 'E-Mail',
            'phone'     => 'Telefon',
            'artist'    => 'Bevorzugter Artist',
            'style'     => 'Stilrichtung',
            'bodypart'  => 'Körperstelle',
            'size'      => 'Größe',
            'budget'    => 'Budget',
            'desc'      => 'Beschreibung',
            'gdpr'      => 'DSGVO'
        ] as $k => $label) {
            echo '<tr><th>' . $label . '</th><td>' . esc_html(is_array($data[$k]) ? json_encode($data[$k]) : $data[$k]) . '</td></tr>';
        }

        echo '<tr><th>Zeitfenster</th><td>';
        if (!empty($data['slots'])) {
            echo '<ul>';
            foreach ($data['slots'] as $s) {
                echo '<li>' . esc_html($s['date'] . ' ' . $s['start'] . '–' . $s['end']) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '—';
        }
        echo '</td></tr>';

        echo '<tr><th>Uploads</th><td>';
        if (!empty($data['uploads'])) {
            foreach ($data['uploads'] as $id) {
                echo wp_get_attachment_image($id, 'thumbnail', false, ['style' => 'margin-right:6px;border-radius:6px']);
            }
        } else {
            echo '—';
        }
        echo '</td></tr>';
        echo '</table></div>';
    }

    public function box_proposals($post) {
        $proposals = get_post_meta($post->ID, 'proposals', true) ?: [];
        echo '<p>Schlage konkrete Startzeiten (mit geschätzter Dauer) vor. Der Kunde wählt später eine Option.</p>';
        echo '<div id="dstb-proposals">';
        echo '<table class="widefat"><thead><tr><th>Datum</th><th>Start</th><th>Dauer (Min)</th></tr></thead><tbody>';

        if (!$proposals) $proposals = [['date' => '', 'start' => '', 'dur' => '120']];
        foreach ($proposals as $i => $p) {
            printf(
                '<tr><td><input type="date" name="dstb_proposals[%1$d][date]" value="%2$s"/></td>
                 <td><input type="time" step="1800" name="dstb_proposals[%1$d][start]" value="%3$s"/></td>
                 <td><input type="number" min="30" step="30" name="dstb_proposals[%1$d][dur]" value="%4$s"/></td></tr>',
                $i,
                esc_attr($p['date']),
                esc_attr($p['start']),
                esc_attr($p['dur'])
            );
        }

        echo '</tbody></table>';
        echo '<p><button class="button" onclick="dstbAddProposalRow();return false;">+ Zeile</button></p>';
        echo '<p><button class="button button-primary" name="dstb_send_proposals" value="1">Vorschläge an Kunden senden</button></p>';
        echo '</div>';
        echo '<script>
            function dstbAddProposalRow(){
              const tb=document.querySelector("#dstb-proposals tbody");
              const i=tb.rows.length;
              const tr=document.createElement("tr");
              tr.innerHTML=`<td><input type="date" name="dstb_proposals[${i}][date]"></td>
                            <td><input type="time" step="1800" name="dstb_proposals[${i}][start]"></td>
                            <td><input type="number" min="30" step="30" name="dstb_proposals[${i}][dur]" value="120"></td>`;
              tb.appendChild(tr);
            }
        </script>';
    }

    public function save($post_id, $post) {
        if (!isset($_POST['dstb_save_nonce']) || !wp_verify_nonce($_POST['dstb_save_nonce'], 'dstb_save_' . $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['dstb_proposals'])) {
            $props = array_values(array_filter(array_map(function($p) {
                return [
                    'date'  => sanitize_text_field($p['date'] ?? ''),
                    'start' => sanitize_text_field($p['start'] ?? ''),
                    'dur'   => intval($p['dur'] ?? 0),
                ];
            }, $_POST['dstb_proposals'])));
            update_post_meta($post_id, 'proposals', $props);

            if (isset($_POST['dstb_send_proposals'])) {
                DSTB_Emails::send_proposals_email($post_id, $props);
            }
        }
    }
}
