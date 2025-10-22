<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_Ajax {

    public function __construct() {
        // Frontend-Formular absenden
        add_action('wp_ajax_dstb_submit', [self::class, 'submit']);
        add_action('wp_ajax_nopriv_dstb_submit', [self::class, 'submit']);

        // Kunde bestätigt finalen Termin
        add_action('wp_ajax_dstb_confirm_choice', [self::class, 'confirm_choice']);
        add_action('wp_ajax_nopriv_dstb_confirm_choice', [self::class, 'confirm_choice']);

        // Kalenderdaten Monatsübersicht + freie Slots
        add_action('wp_ajax_dstb_calendar_data', [self::class, 'calendar_data']);
        add_action('wp_ajax_nopriv_dstb_calendar_data', [self::class, 'calendar_data']);

        // Tagesgenaue freie Slots
        add_action('wp_ajax_dstb_free_slots', [self::class, 'free_slots']);
        add_action('wp_ajax_nopriv_dstb_free_slots', [self::class, 'free_slots']);
    }

    /** ========= Formular absenden → Anfrage speichern ========= */
    public static function submit() {

        // 🧠 Nonce-Schutz (Frontend-Nonce)
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

        // 🕓 bis zu 3 Zeitfenster einsammeln
        $slots = [];
        if (!empty($_POST['slots'])) {
            foreach ($_POST['slots'] as $slot) {
                $d = sanitize_text_field($slot['date'] ?? '');
                $s = sanitize_text_field($slot['start'] ?? '');
                $e = sanitize_text_field($slot['end'] ?? '');
                if ($d && $s && $e) {
                    $slots[] = ['date'=>$d,'start'=>$s,'end'=>$e];
                }
            }
            $slots = array_slice($slots, 0, 3);
        }

        // 📸 Uploads verarbeiten (max. 10 Bilder)
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

        // 💾 Anfrage speichern (nicht als Beitrag)
        $req_id = DSTB_DB::insert_request([
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'artist'    => $artist,
            'style'     => $style,
            'bodypart'  => $bodypart,
            'size'      => $size,
            'budget'    => $budget,
            'desc'      => $desc,
            'slots'     => $slots,
            'uploads'   => $attachments,
            'gdpr'      => $gdpr
        ]);

        if (!$req_id) {
            wp_send_json_error(['msg' => 'Beim Speichern der Anfrage ist ein Fehler aufgetreten.']);
        }

        // 📧 Optional: E-Mail an Studio & Kunde
        // DSTB_Emails::send_admin_new_request($req_id);
        // DSTB_Emails::send_user_receipt($req_id, $email);

        wp_send_json_success([
            'msg' => 'Danke! Deine Anfrage wurde erfolgreich gesendet. Referenz-Nr.: '.$req_id
        ]);
    }

    /** ========= Finale Bestätigung → Termin fixieren ========= */
    public static function confirm_choice() {
        check_ajax_referer('dstb_front', 'nonce');

        $rid    = intval($_POST['rid'] ?? 0);
        $artist = sanitize_text_field($_POST['artist'] ?? '');
        $date   = sanitize_text_field($_POST['date'] ?? '');
        $start  = sanitize_text_field($_POST['start'] ?? '');
        $end    = sanitize_text_field($_POST['end'] ?? '');

        if (!$rid || !$artist || !$date || !$start || !$end) {
            wp_send_json_error(['msg' => 'Ungültige Eingaben.']);
        }

        // Prüfen, ob Slot frei ist
        $free = DSTB_DB::free_slots_for_date($artist, $date);
        $isContained = false;
        foreach ($free as $fr) {
            if ($start >= $fr[0] && $end <= $fr[1]) { $isContained = true; break; }
        }
        if (!$isContained) {
            wp_send_json_error(['msg' => 'Dieser Slot ist nicht mehr verfügbar.']);
        }

        // Slot bestätigen + speichern
        DSTB_DB::insert_confirmed_booking($artist, $date, $start, $end, $rid);
        wp_send_json_success(['msg' => 'Termin bestätigt – vielen Dank!']);
    }

    /** ========= Monatsübersicht für Kalender ========= */
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
                $booked[$d] = [['00:00','23:59']]; // Betriebsurlaub = gesperrt
                continue;
            }

            $freeRanges   = DSTB_DB::free_slots_for_date($artist, $date);
            $bookedRanges = DSTB_DB::get_bookings_for_day($artist, $date);

            if (!empty($freeRanges))   $free[$d] = $freeRanges;
            if (!empty($bookedRanges)) $booked[$d] = $bookedRanges;
        }

        wp_send_json(['artist'=>$artist, 'booked'=>$booked, 'free'=>$free]);
    }

    /** ========= Tagesgenaue freie Slots (nach Klick im Kalender) ========= */
    public static function free_slots() {
        $artist = sanitize_text_field($_GET['artist'] ?? '');
        $date   = sanitize_text_field($_GET['date'] ?? date('Y-m-d'));
        $free   = DSTB_DB::free_slots_for_date($artist, $date);
        wp_send_json(['free' => $free]);
    }
}
