<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_Emails {

    /** Optional: eigene Studio-Mailadresse fest verdrahten */
    private static function studio_email(){
        // Falls du eine feste Mail willst: return 'studio@dearskin.at';
        return get_option('admin_email');
    }

    /** Kleines, neutrales HTML-Layout für alle Mails */
    private static function wrap_html($title, $html){
        $style = 'background:#0f1216;color:#e9eef5;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;';
        $card  = 'max-width:640px;margin:24px auto;background:#12161c;border:1px solid #2a3340;border-radius:12px;padding:20px;';
        $h1    = 'margin:0 0 12px;font-size:18px;color:#9fbfff;';
        $p     = 'margin:6px 0;';
        return '<!doctype html><html><head><meta charset="utf-8"></head><body style="'.$style.'">
            <div style="'.$card.'">
              <h1 style="'.$h1.'">'.esc_html($title).'</h1>
              <div style="font-size:14px;line-height:1.55">'.$html.'</div>
            </div>
            <div style="max-width:640px;margin:8px auto 24px;text-align:center;color:#a8b3bf;font-size:12px">
              Diese Nachricht wurde automatisch vom Buchungssystem gesendet.
            </div>
        </body></html>';
    }

    /** Hilfsfunktion: einfache Liste rendern */
    private static function dl($label, $value){
        if ($value === '' || $value === null) return '';
        return '<p style="margin:6px 0"><strong style="display:inline-block;width:160px;color:#dfe7f3">'
                .esc_html($label).':</strong> '.wp_kses_post($value).'</p>';
    }

    /** 1) Kunde sendet Anfrage → Mail an Kunde + Studio */
    public static function send_request_emails($req_id){
        global $wpdb;
        $table = $wpdb->prefix . DSTB_DB::$requests;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $req_id), ARRAY_A);
        if (!$row) return;

        $slots   = $row['slots']   ? (json_decode($row['slots'], true) ?: []) : [];
        $uploads = $row['uploads'] ? (json_decode($row['uploads'], true) ?: []) : [];

        // Slots hübsch
        $slots_html = '';
        if ($slots){
            $slots_html .= '<ul style="margin:6px 0 0 18px;padding:0">';
            foreach($slots as $s){
                $d = esc_html($s['date'] ?? '');
                $st= esc_html($s['start'] ?? '');
                if ($d || $st){
                    $slots_html .= '<li style="margin:2px 0">'.$d.($st?' – Start: <strong>'.$st.'</strong>':'').'</li>';
                }
            }
            $slots_html .= '</ul>';
        } else {
            $slots_html = '<em>Keine Zeitfenster angegeben.</em>';
        }

        // Upload-Liste (nur Anzahl/Links)
        $uploads_html = '';
        if ($uploads){
            $items = [];
            foreach($uploads as $aid){
                $url = wp_get_attachment_url($aid);
                if ($url) $items[] = '<a href="'.esc_url($url).'">'.basename($url).'</a>';
            }
            if ($items){
                $uploads_html = implode('<br>', $items);
            }
        }

        // -------------- Mail an KUNDEN --------------
        $to_customer = sanitize_email($row['email']);
        if ($to_customer){
            $subject = 'Bestätigung deiner Tattoo-Anfrage (#'.$req_id.')';
            $body =
                self::dl('Name', esc_html($row['name'])).
                self::dl('E-Mail', esc_html($row['email'])).
                self::dl('Telefon', esc_html($row['phone'])).
                self::dl('Adresse', esc_html($row['address'])).
                self::dl('Artist', esc_html($row['artist'])).
                self::dl('Stilrichtung', esc_html($row['style'])).
                self::dl('Körperstelle', esc_html($row['bodypart'])).
                self::dl('Größe', esc_html($row['size'])).
                self::dl('Budget', $row['budget'] !== null ? intval($row['budget']).' €' : '').
                self::dl('Beschreibung', nl2br(esc_html($row['desc_text'] ?? ''))).
                self::dl('Verfügbarkeiten', $slots_html).
                self::dl('Uploads', $uploads_html ?: '<em>Keine</em>');

            $html = self::wrap_html('Danke für deine Anfrage', $body.'<p style="margin-top:14px">Wir melden uns so schnell wie möglich.</p>');
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($to_customer, $subject, $html, $headers);
        }

        // -------------- Mail an STUDIO --------------
        $to_studio = self::studio_email();
        if ($to_studio){
            $subjectS = 'Neue Tattoo-Anfrage (#'.$req_id.') von '.$row['name'];
            $admin_url = admin_url('admin.php?page=dstb-requests');
            $bodyS =
                self::dl('Name', esc_html($row['name'])).
                self::dl('E-Mail', esc_html($row['email'])).
                self::dl('Telefon', esc_html($row['phone'])).
                self::dl('Adresse', esc_html($row['address'])).
                self::dl('Artist', esc_html($row['artist'])).
                self::dl('Stilrichtung', esc_html($row['style'])).
                self::dl('Körperstelle', esc_html($row['bodypart'])).
                self::dl('Größe', esc_html($row['size'])).
                self::dl('Budget', $row['budget'] !== null ? intval($row['budget']).' €' : '').
                self::dl('Beschreibung', nl2br(esc_html($row['desc_text'] ?? ''))).
                self::dl('Verfügbarkeiten', $slots_html).
                self::dl('Uploads', $uploads_html ?: '<em>Keine</em>').
                '<p style="margin-top:14px"><a href="'.esc_url($admin_url).'" style="display:inline-block;background:#7aa3ff;color:#081016;padding:8px 12px;border-radius:8px;text-decoration:none">Zum Backend</a></p>';

            $htmlS = self::wrap_html('Neue Anfrage eingegangen', $bodyS);
            $headersS = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($to_studio, $subjectS, $htmlS, $headersS);
        }
    }

        /**
     * Wird aufgerufen, wenn das Studio Terminvorschläge an den Kunden sendet
     */
    public static function send_proposals_to_customer($req_id) {
        global $wpdb;
        $req = DSTB_DB::get_request($req_id);
        if (!$req) return;

        $table = $wpdb->prefix . DSTB_DB::$suggestions;
        $proposals = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE request_id = %d AND status = 'sent'", $req_id),
            ARRAY_A
        );
        if (!$proposals) return;

        // Terminvorschläge als HTML-Tabelle
        $rows = '';
        foreach ($proposals as $p) {
            $rows .= "<tr>
                <td>{$p['date']}</td>
                <td>{$p['start']}</td>
                <td>{$p['end']}</td>
                <td>{$p['price']} €</td>
                <td>{$p['note']}</td>
            </tr>";
        }

        // Bestätigungslink (auf neue Seite)
        $confirm_page = site_url('/termin-bestaetigen/');
        $confirm_link = add_query_arg(['req' => $req_id], $confirm_page);

        $subject = 'Tattoo-Studio Terminvorschläge';
        $body = "<h2>Hallo {$req['name']},</h2>
        <p>wir haben dir folgende Terminvorschläge zusammengestellt:</p>
        <table style='border-collapse:collapse; width:100%;' border='1' cellpadding='6'>
            <tr style='background:#f5f5f5'>
                <th>Datum</th><th>Start</th><th>Ende</th><th>Preis</th><th>Notiz</th>
            </tr>
            {$rows}
        </table>
        <p>Bitte bestätige deinen Wunschtermin über folgenden Link:</p>
        <p><a href='{$confirm_link}' 
              style='background:#0b5ed7;color:white;padding:10px 16px;text-decoration:none;border-radius:6px;'>
              Termin auswählen
           </a></p>
        <p>Liebe Grüße,<br>Dear Skin Tattoo Studio</p>";

        self::send_html_mail($req['email'], $subject, $body);
    }

       /** Hilfsfunktion für konsistenten HTML-Mailversand */
    private static function send_html_mail($to, $subject, $html) {
        if (empty($to)) return false;

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, self::wrap_html($subject, $html), $headers);
    }

        /** Kunde bestätigt einen Termin → Studio bekommt Mail */
    public static function send_confirmation_to_studio($req_id, $sug_id){
        global $wpdb;
        $req = DSTB_DB::get_request($req_id);
        $table = $wpdb->prefix . DSTB_DB::$suggestions;
        $sug = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$sug_id), ARRAY_A);

        if (!$req || !$sug) return;
        $to = self::studio_email();

        $subject = "Termin bestätigt (#{$req_id}) – {$req['name']}";
        $body = "<p>Der Kunde <strong>{$req['name']}</strong> hat folgenden Termin bestätigt:</p>
            <ul>
                <li><strong>Datum:</strong> {$sug['date']}</li>
                <li><strong>Start:</strong> {$sug['start']}</li>
                <li><strong>Ende:</strong> {$sug['end']}</li>
                <li><strong>Preis:</strong> {$sug['price']} €</li>
                <li><strong>Notiz:</strong> {$sug['note']}</li>
            </ul>";

        self::send_html_mail($to, $subject, $body);
    }

    /** Kunde lehnt alle Termine ab → Studio bekommt Mail */
    public static function send_decline_notice_to_studio($req_id){
        $req = DSTB_DB::get_request($req_id);
        if (!$req) return;
        $to = self::studio_email();

        $subject = "Kunde hat alle Terminvorschläge abgelehnt (#{$req_id})";
        $body = "<p>Der Kunde <strong>{$req['name']}</strong> hat leider keinen der angebotenen Termine akzeptiert.</p>";

        self::send_html_mail($to, $subject, $body);
    }

}
