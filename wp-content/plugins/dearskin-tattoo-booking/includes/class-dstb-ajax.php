<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_Ajax {

    public function __construct() {
        // Frontend-Formular absenden
        add_action('wp_ajax_dstb_submit', [self::class, 'submit']);
        add_action('wp_ajax_nopriv_dstb_submit', [self::class, 'submit']);

        // (ALT) alter Bestätigungs-Endpoint – bleibt bestehen, wird aber NICHT mehr von der Seite genutzt
        add_action('wp_ajax_dstb_confirm_choice', [self::class, 'confirm_choice']);
        add_action('wp_ajax_nopriv_dstb_confirm_choice', [self::class, 'confirm_choice']);

        // (NEU) schlanker Bestätigungs-Endpoint NUR für die Bestätigungs-Seite
        add_action('wp_ajax_dstb_confirm_choice_v2', [self::class, 'confirm_choice_v2']);
        add_action('wp_ajax_nopriv_dstb_confirm_choice_v2', [self::class, 'confirm_choice_v2']);

        // Kalender
        add_action('wp_ajax_dstb_calendar_data', [self::class, 'calendar_data']);
        add_action('wp_ajax_nopriv_dstb_calendar_data', [self::class, 'calendar_data']);
        add_action('wp_ajax_dstb_free_slots', [self::class, 'free_slots']);
        add_action('wp_ajax_nopriv_dstb_free_slots', [self::class, 'free_slots']);
    }

    /** ========= Formular absenden → Anfrage speichern ========= */
    public static function submit() {
        check_ajax_referer('dstb_front', 'nonce');

        $firstname = sanitize_text_field($_POST['firstname'] ?? '');
        $lastname  = sanitize_text_field($_POST['lastname'] ?? '');
        $name      = trim($firstname . ' ' . $lastname);
        $email     = sanitize_email($_POST['email'] ?? '');
        $phone     = sanitize_text_field($_POST['phone'] ?? '');
        $artist    = sanitize_text_field($_POST['artist'] ?? '');
        $style     = sanitize_text_field($_POST['style'] ?? '');
        $bodypart  = sanitize_text_field($_POST['bodypart'] ?? '');
        $size      = sanitize_text_field($_POST['size'] ?? '');
        $budget    = intval($_POST['budget'] ?? 0);
        $desc      = wp_kses_post($_POST['desc'] ?? '');
        $gdpr      = !empty($_POST['gdpr']) ? 1 : 0;

        if (!$firstname || !$lastname || !$email) {
            wp_send_json_error(['msg' => 'Vorname, Nachname und E-Mail sind Pflichtfelder.']);
        }

        // bis zu 3 Zeitfenster (Datum + Start)
        $slots = [];
        if (!empty($_POST['slots']) && is_array($_POST['slots'])) {
            foreach ($_POST['slots'] as $slot) {
                $d = sanitize_text_field($slot['date'] ?? '');
                $s = sanitize_text_field($slot['start'] ?? '');
                if ($d && $s) $slots[] = ['date' => $d, 'start' => $s];
            }
            $slots = array_slice($slots, 0, 3);
        }

        // Uploads
        $attachments = [];
        if (!empty($_FILES['images'])) {
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
            $files = $_FILES['images'];
            $max = 10;
            for ($i = 0; $i < min(count($files['name']), $max); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file = [
                    'name'     => sanitize_file_name($files['name'][$i]),
                    'type'     => sanitize_mime_type($files['type'][$i]),
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => 0,
                    'size'     => intval($files['size'][$i]),
                ];
                $up = wp_handle_sideload($file, ['test_form' => false]);
                if (!empty($up['file'])) {
                    $aid = wp_insert_attachment([
                        'post_mime_type' => $up['type'],
                        'post_title'     => basename($file['name']),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    ], $up['file']);
                    if (!is_wp_error($aid)) {
                        $meta = wp_generate_attachment_metadata($aid, $up['file']);
                        wp_update_attachment_metadata($aid, $meta);
                        $attachments[] = $aid;
                    }
                }
            }
        }

        // speichern
        $req_id = DSTB_DB::insert_request([
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
            'artist'   => $artist,
            'style'    => $style,
            'bodypart' => $bodypart,
            'size'     => $size,
            'budget'   => $budget,
            'desc'     => $desc,
            'slots'    => $slots,
            'uploads'  => $attachments,
            'gdpr'     => $gdpr
        ]);

        if (!$req_id) {
            wp_send_json_error(['msg' => 'Beim Speichern der Anfrage ist ein Fehler aufgetreten.']);
        }

        // Mails raus
        DSTB_Emails::send_request_emails($req_id);

        wp_send_json_success(['msg' => 'Danke! Deine Anfrage wurde erfolgreich gesendet. Referenz-Nr.: '.$req_id]);
    }

    /** ========= (NEU) Finale Bestätigung – V2 für Bestätigungsseite ========= */
    public static function confirm_choice_v2() {
        check_ajax_referer('dstb_front', 'nonce');

        global $wpdb;

        $req_id  = intval($_POST['req_id'] ?? 0);
        $sug_id  = intval($_POST['choice'] ?? 0);
        $decline = !empty($_POST['decline']);

        if (!$req_id) {
            wp_send_json_error(['msg' => 'Fehlende Angaben: Anfrage-ID.']);
        }

        $req_table = $wpdb->prefix . DSTB_DB::$requests;
        $sug_table = $wpdb->prefix . DSTB_DB::$suggestions;

        // Anfrage laden
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM $req_table WHERE id=%d", $req_id), ARRAY_A);
        if (!$req) {
            wp_send_json_error(['msg' => 'Anfrage nicht gefunden.']);
        }

        // Ablehnen: alle gesendeten Vorschläge auf declined
        if ($decline) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $sug_table SET status='declined' WHERE request_id=%d AND status='sent'",
                $req_id
            ));
            if (method_exists('DSTB_Emails','send_decline_notice_to_studio')) {
                DSTB_Emails::send_decline_notice_to_studio($req_id);
            }
            wp_send_json_success(['msg' => 'Schade – wir melden uns ggf. mit neuen Terminen.']);
        }

        if (!$sug_id) {
            wp_send_json_error(['msg' => 'Bitte wähle einen Termin aus.']);
        }

        // Gewählten Vorschlag prüfen
        $sug = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sug_table WHERE id=%d AND request_id=%d AND status='sent'",
            $sug_id, $req_id
        ), ARRAY_A);
        if (!$sug) {
            wp_send_json_error(['msg' => 'Ungültige Auswahl oder Termin nicht mehr verfügbar.']);
        }

        // Bestätigen & andere ablehnen
        $wpdb->update($sug_table, ['status' => 'confirmed'], ['id' => $sug_id]);
        $wpdb->query($wpdb->prepare(
            "UPDATE $sug_table SET status='declined' WHERE request_id=%d AND status='sent' AND id<>%d",
            $req_id, $sug_id
        ));

        // Buchung eintragen (Kalender rot)
        if (method_exists('DSTB_DB','insert_confirmed_booking')) {
            $artist = $req['artist'] ?? '';
            $date   = $sug['date'];
            $start  = $sug['start'];
            $end    = $sug['end'];
            if (empty($end)) {
                $ts = strtotime($date.' '.$start);
                $end = $ts ? date('H:i', $ts + 3600) : date('H:i', strtotime($start.' +60 minutes'));
            }
            DSTB_DB::insert_confirmed_booking($artist, $date, $start, $end, $req_id);
        }

        // Studio informieren
        if (method_exists('DSTB_Emails','send_confirmation_to_studio')) {
            DSTB_Emails::send_confirmation_to_studio($req_id, $sug_id);
        }

        wp_send_json_success(['msg' => 'Danke! Dein Termin wurde bestätigt.']);
    }

    /** ========= (ALT) alter confirm_choice (unverändert lassen) ========= */
    public static function confirm_choice() {
        // <- alter Code bleibt wie er ist (wird von der neuen Seite nicht mehr verwendet)
        check_ajax_referer('dstb_front', 'nonce');
        $rid    = intval($_POST['rid'] ?? 0);
        $artist = sanitize_text_field($_POST['artist'] ?? '');
        $date   = sanitize_text_field($_POST['date'] ?? '');
        $start  = sanitize_text_field($_POST['start'] ?? '');
        $end    = sanitize_text_field($_POST['end'] ?? '');

        if (!$rid || !$artist || !$date || !$start || !$end) {
            wp_send_json_error(['msg' => 'Fehlende Angaben: Anfrage-ID, Datum, Startzeit']);
        }

        $free = DSTB_DB::free_slots_for_date($artist, $date);
        $isContained = false;
        foreach ($free as $fr) {
            if ($start >= $fr[0] && $end <= $fr[1]) { $isContained = true; break; }
        }
        if (!$isContained) {
            wp_send_json_error(['msg' => 'Dieser Slot ist nicht mehr verfügbar.']);
        }

        DSTB_DB::insert_confirmed_booking($artist, $date, $start, $end, $rid);
        wp_send_json_success(['msg' => 'Termin bestätigt – vielen Dank!']);
    }

    /** ========= Kalender & Free Slots ========= */
    public static function calendar_data() {
        $artist = sanitize_text_field($_GET['artist'] ?? '');
        $year   = intval($_GET['year'] ?? date('Y'));
        $month  = intval($_GET['month'] ?? date('n'));
        $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $booked = [];
        $free = [];
        for ($d = 1; $d <= $days; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            if (DSTB_DB::date_is_vacation($artist, $date)) {
                $booked[$d] = [['00:00','23:59']];
                continue;
            }
            $freeRanges   = DSTB_DB::free_slots_for_date($artist, $date);
            $bookedRanges = DSTB_DB::get_bookings_for_day($artist, $date);
            if (!empty($freeRanges))   $free[$d] = $freeRanges;
            if (!empty($bookedRanges)) $booked[$d] = $bookedRanges;
        }
        wp_send_json(['artist'=>$artist, 'booked'=>$booked, 'free'=>$free]);
    }

    public static function free_slots() {
        $artist = sanitize_text_field($_GET['artist'] ?? '');
        $date   = sanitize_text_field($_GET['date'] ?? date('Y-m-d'));
        $free   = DSTB_DB::free_slots_for_date($artist, $date);
        wp_send_json(['free' => $free]);
    }
}
