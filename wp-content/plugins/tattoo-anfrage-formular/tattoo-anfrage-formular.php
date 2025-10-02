<?php
/**
 * Plugin Name: Tattoo Anfragen Manager
 * Description: Anfrageformular mit Admin-Verwaltung & Terminvergabe inkl. blockierter Zeitfenster
 * Version: 1.4
 * Author: Marcel Timmerer
 */

defined('ABSPATH') or die('Kein Zugriff erlaubt.');

// 🔁 Benutzerdefiniertes Cron-Intervall: alle 5 Sekunden (nur für Tests geeignet!)
add_filter('cron_schedules', function ($schedules) {
    $schedules['alle_5_sekunden'] = [
        'interval' => 5,
        'display'  => 'Alle 5 Sekunden'
    ];
    return $schedules;
});

// ⏱ Cronjob registrieren
register_activation_hook(__FILE__, 'tattoo_cron_starten');
function tattoo_cron_starten() {
    if (!wp_next_scheduled('tattoo_cron_loeschen')) {
        wp_schedule_event(time(), 'alle_5_sekunden', 'tattoo_cron_loeschen');
    }
}

// ⏹ Cronjob beenden
register_deactivation_hook(__FILE__, 'tattoo_cron_stoppen');
function tattoo_cron_stoppen() {
    wp_clear_scheduled_hook('tattoo_cron_loeschen');
}

// 🚮 Abgelehnte Anfragen löschen (älter als 5 Sekunden)
add_action('tattoo_cron_loeschen', 'tattoo_loesche_abgelehnte_termine');
function tattoo_loesche_abgelehnte_termine() {
    global $wpdb;
    $table = $wpdb->prefix . 'tattoo_anfragen2';

    $result = $wpdb->query($wpdb->prepare("
        DELETE FROM $table 
        WHERE status = %s AND erstellt_am < (NOW() - INTERVAL %d SECOND)
    ", 'abgelehnt', 5));

    if ($result !== false) {
        error_log("\ud83d\uddd1\ufe0f $result abgelehnte Anfrage(n) automatisch gel\u00f6scht.");
    } else {
        error_log("\u274c Fehler beim L\u00f6schen: " . $wpdb->last_error);
    }
}

// 📦 Datenbank-Tabelle
register_activation_hook(__FILE__, 'tattoo_anfragen_create_table');
function tattoo_anfragen_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'tattoo_anfragen2';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        vorname VARCHAR(100),
        nachname VARCHAR(100),
        email VARCHAR(100),
        geburtsdatum DATE,
        taetowierer VARCHAR(100),
        stilrichtung VARCHAR(100),
        farbe VARCHAR(50),
        hat_tattoo VARCHAR(20),
        koerperform VARCHAR(100),
        koerperstelle VARCHAR(100),
        groesse VARCHAR(100),
        beschreibung TEXT,
        verfuegbarkeit_datum DATE,
        zeit_von TIME,
        zeit_bis TIME,
        budget VARCHAR(100),
        telefon VARCHAR(100),
        final_datum DATE,
        final_zeit_von TIME,
        final_zeit_bis TIME,
        status VARCHAR(20) DEFAULT 'offen',
        erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// 📨 Formular absenden
add_action('template_redirect', 'tattoo_form_verarbeitung');
function tattoo_form_verarbeitung() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tattoo_form_submit'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'tattoo_anfragen2';

        $wpdb->insert($table, [
            'vorname' => sanitize_text_field($_POST['vorname']),
            'nachname' => sanitize_text_field($_POST['nachname']),
            'email' => sanitize_email($_POST['email']),
            'geburtsdatum' => $_POST['geburtsdatum'],
            'taetowierer' => sanitize_text_field($_POST['taetowierer']),
            'stilrichtung' => sanitize_text_field($_POST['stilrichtung']),
            'farbe' => sanitize_text_field($_POST['farbe']),
            'hat_tattoo' => sanitize_text_field($_POST['hat_tattoo']),
            'koerperform' => sanitize_text_field($_POST['koerperform']),
            'koerperstelle' => sanitize_text_field($_POST['koerperstelle']),
            'groesse' => sanitize_text_field($_POST['groesse']),
            'beschreibung' => sanitize_textarea_field($_POST['beschreibung']),
            'verfuegbarkeit_datum' => $_POST['verfuegbarkeit_datum'],
            'zeit_von' => $_POST['zeit_von'],
            'zeit_bis' => $_POST['zeit_bis'],
            'budget' => sanitize_text_field($_POST['budget']),
            'telefon' => sanitize_text_field($_POST['telefon']),
        ]);
		
		// 📧 E-Mail-Versand vorbereiten
$kunde_email = sanitize_email($_POST['email']);
$admin_email = get_option('admin_email'); // z. B. 'admin@dearskin.at'
$admin_page_url = site_url('/admin-anfragen');

$from_name = 'DearSkin Tattoo';
$from_email = 'wp@dearskin.at'; // Muss im SMTP-Plugin eingetragen sein

$headers = array(
    'From: ' . $from_name . ' <' . $from_email . '>',
    'Content-Type: text/html; charset=UTF-8'
);

// Kunden-Mailtext (vereinfacht und HTML-konform)
$kunden_nachricht = "
<p>Hallo " . esc_html($_POST['vorname']) . ",</p>
<p>vielen Dank für deine Tattoo-Anfrage! Hier eine Zusammenfassung deiner Angaben:</p>
<ul>
    <li><strong>Name:</strong> " . esc_html($_POST['vorname']) . " " . esc_html($_POST['nachname']) . "</li>
    <li><strong>E-Mail:</strong> " . esc_html($_POST['email']) . "</li>
    <li><strong>Telefon:</strong> " . esc_html($_POST['telefon']) . "</li>
    <li><strong>Wunschtermin:</strong> " . esc_html($_POST['verfuegbarkeit_datum']) . " – " . esc_html($_POST['zeit_von']) . " bis " . esc_html($_POST['zeit_bis']) . "</li>
    <li><strong>Körperstelle:</strong> " . esc_html($_POST['koerperstelle']) . "</li>
    <li><strong>Beschreibung:</strong> " . nl2br(esc_html($_POST['beschreibung'])) . "</li>
</ul>
<p>Wir melden uns in Kürze!</p>
<p>Liebe Grüße<br>Dein DearSkin Tattoo-Studio</p>
";

// Admin-Mailtext
$admin_nachricht = "
<p><strong>Neue Tattoo-Anfrage erhalten:</strong></p>
<ul>
    <li><strong>Name:</strong> " . esc_html($_POST['vorname']) . " " . esc_html($_POST['nachname']) . "</li>
    <li><strong>E-Mail:</strong> " . esc_html($_POST['email']) . "</li>
    <li><strong>Telefon:</strong> " . esc_html($_POST['telefon']) . "</li>
    <li><strong>Körperstelle:</strong> " . esc_html($_POST['koerperstelle']) . "</li>
    <li><strong>Datum:</strong> " . esc_html($_POST['verfuegbarkeit_datum']) . " (" . esc_html($_POST['zeit_von']) . " – " . esc_html($_POST['zeit_bis']) . ")</li>
    <li><strong>Beschreibung:</strong> " . nl2br(esc_html($_POST['beschreibung'])) . "</li>
</ul>
<p><a href=\"$admin_page_url\">👉 Anfrage verwalten</a></p>
";

// Mail an Kunden
$send_to_customer = wp_mail($kunde_email, 'Deine Tattoo-Anfrage wurde erhalten ✅', $kunden_nachricht, $headers);

// Mail an Admin
$send_to_admin = wp_mail($admin_email, 'Neue Tattoo-Anfrage ✉️', $admin_nachricht, $headers);

// Debug Logging (fürs Server-Log)
error_log("📧 Sende Kunden-Mail an: $kunde_email");
if (!$send_to_customer) {
    error_log("❌ Kunden-Mail konnte NICHT gesendet werden.");
} else {
    error_log("✅ Kunden-Mail wurde erfolgreich gesendet.");
}

error_log("📧 Sende Admin-Mail an: $admin_email");
if (!$send_to_admin) {
    error_log("❌ Admin-Mail konnte NICHT gesendet werden.");
} else {
    error_log("✅ Admin-Mail wurde erfolgreich gesendet.");
}





        wp_redirect(home_url('/anfrage-erfolgreich'));
        exit;
    }
}

// 📩 Shortcode
add_shortcode('tattoo-formular', 'tattoo_formular_shortcode');
function tattoo_formular_shortcode() {
    ob_start();
    ?>
    <form method="post" class="tattoo-formular">
        <h2>Terminanfrage</h2>

        <label>Vorname*</label>
        <input type="text" name="vorname" required>

        <label>Nachname*</label>
        <input type="text" name="nachname" required>

        <label>E-Mail*</label>
        <input type="email" name="email" required>

        <label>Geburtsdatum*</label>
        <input type="date" name="geburtsdatum" required>

        <label>Bevorzugte:r Tätowierer:in</label>
        <input type="text" name="taetowierer">

        <label>Stilrichtung*</label>
        <select name="stilrichtung" required>
            <option value="">Bitte wählen</option>
            <option>Realism</option>
            <option>Fine Line</option>
            <option>Blackwork</option>
            <option>Portrait</option>
            <option>Japanese</option>
            <option>Biomechanical</option>
            <option>Maori</option>
        </select>

        <label>Farbwahl*</label>
        <select name="farbe" required>
            <option>farbig</option>
            <option>gemischt</option>
            <option>schwarz / weiß</option>
            <option>noch unklar</option>
        </select>

        <label>Hast du bereits ein Tattoo?*</label>
        <select name="hat_tattoo" required>
            <option>Ja</option>
            <option>Nein</option>
        </select>

        <label>Körperform*</label>
        <select name="koerperform" required>
            <option>feminin</option>
            <option>maskulin</option>
            <option>dunkel</option>
            <option>eher dunkel</option>
            <option>eher hell</option>
            <option>hell</option>
        </select>

        <label>Körperstelle*</label>
        <input type="text" name="koerperstelle" required>

        <label>Ungefähre Größe*</label>
        <input type="text" name="groesse" required>

        <label>Beschreibung*</label>
        <textarea name="beschreibung" required></textarea>

        <label>Gewünschtes Zeitfenster*</label>
        <input type="date" name="verfuegbarkeit_datum" required>
        <select id="zeit_von" name="zeit_von" required></select>
        bis
        <select id="zeit_bis" name="zeit_bis" required></select>

        <label>Budget*</label>
        <input type="text" name="budget" required>

        <label>Telefon*</label>
        <input type="text" name="telefon" required>

        <button type="submit" name="tattoo_form_submit">Anfrage senden</button>
    </form>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    const datumFeld = document.querySelector('input[name="verfuegbarkeit_datum"]');
    const zeitVon = document.getElementById('zeit_von');
    const zeitBis = document.getElementById('zeit_bis');

    const generateTimeOptions = (blockierte) => {
        zeitVon.innerHTML = '';
        zeitBis.innerHTML = '';

        // Hilfsfunktion: Zeitstring ("HH:MM") in Minuten umwandeln
        const toMinutes = (zeit) => {
            const [h, m] = zeit.split(':').map(Number);
            return h * 60 + m;
        };

        // Überprüfen, ob die Zeit blockiert ist, inkl. 15 Minuten Vorlauf
        const isBlocked = (zeit) => {
            const zeitInMinuten = toMinutes(zeit);
            for (const [von, bis] of blockierte) {
                const vonMin = toMinutes(von) - 15; // 15 Minuten früher blockieren
                const bisMin = toMinutes(bis);
                if (zeitInMinuten >= vonMin && zeitInMinuten < bisMin) return true;
            }
            return false;
        };

        for (let h = 8; h <= 18; h++) {
            for (let m of [0, 30]) {
                const z = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                const disabled = isBlocked(z);
                const text = z + (disabled ? ' (belegt)' : '');

                const option1 = new Option(text, z);
                const option2 = new Option(text, z);
                if (disabled) {
                    option1.disabled = true;
                    option2.disabled = true;
                }

                zeitVon.appendChild(option1);
                zeitBis.appendChild(option2);
            }
        }
    };

    datumFeld.addEventListener('change', function () {
        const datum = this.value;
        if (!datum) return;

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_blockierte_zeiten&datum=' + encodeURIComponent(datum)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                generateTimeOptions(data.data);
            } else {
                console.error('AJAX Fehler', data);
            }
        });
    });

    if (datumFeld.value) datumFeld.dispatchEvent(new Event('change'));
});
</script>


    <style>
    .tattoo-formular {
        background: #fafafa;
        padding: 2em;
        max-width: 600px;
        margin: 2em auto;
        border-radius: 12px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .tattoo-formular label {
        display: block;
        margin-top: 1em;
        font-weight: bold;
    }
    .tattoo-formular input,
    .tattoo-formular select,
    .tattoo-formular textarea {
        width: 100%;
        padding: 0.5em;
        margin-top: 0.3em;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .tattoo-formular button {
        margin-top: 2em;
        padding: 1em;
        background: black;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    </style>
    <?php
    return ob_get_clean();
}

// 🛠️ Admin-Menü
add_action('admin_menu', 'tattoo_anfragen_admin_menu');
function tattoo_anfragen_admin_menu() {
    add_menu_page(
        'Tattoo Anfragen',
        'Tattoo Anfragen',
        'manage_options',
        'tattoo-anfragen',
        'tattoo_anfragen_admin_page',
        'dashicons-calendar-alt',
        25
    );
}

// 🧾 Admin-Seite
function tattoo_anfragen_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tattoo_anfragen2';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['speichern'])) {
        $id = intval($_POST['id']);
        $final_datum = sanitize_text_field($_POST['final_datum']);
        $final_zeit_von = sanitize_text_field($_POST['final_zeit_von']);
        $final_zeit_bis = sanitize_text_field($_POST['final_zeit_bis']);
        $status = sanitize_text_field($_POST['status']);

        $wpdb->update($table, [
            'final_datum' => $final_datum,
            'final_zeit_von' => $final_zeit_von,
            'final_zeit_bis' => $final_zeit_bis,
            'status' => $status
        ], ['id' => $id]);

        echo '<div class="updated"><p>Anfrage erfolgreich aktualisiert.</p></div>';
    }

    $anfragen = $wpdb->get_results("SELECT * FROM $table ORDER BY erstellt_am DESC");

    echo '<div class="wrap"><h1>Tattoo Anfragen</h1>';
    echo '<table class="tattoo-anfrage-table"><thead><tr>
        <th>Name</th>
        <th>Email</th>
        <th>Körperstelle</th>
        <th>Wunsch-Zeitfenster</th>
        <th>Status</th>
        <th>Finaler Termin</th>
        <th>Aktion</th>
    </tr></thead><tbody>';

    foreach ($anfragen as $anfrage) {
        echo '<tr>';
        echo '<td>' . esc_html($anfrage->vorname . ' ' . $anfrage->nachname) . '</td>';
        echo '<td>' . esc_html($anfrage->email) . '</td>';
        echo '<td>' . esc_html($anfrage->koerperstelle) . '</td>';
        echo '<td>' . esc_html($anfrage->verfuegbarkeit_datum) . ' (' . esc_html($anfrage->zeit_von) . ' – ' . esc_html($anfrage->zeit_bis) . ')</td>';
        echo '<td>' . esc_html($anfrage->status) . '</td>';
        echo '<td>' . ($anfrage->final_datum ? esc_html($anfrage->final_datum . ' (' . $anfrage->final_zeit_von . ' – ' . $anfrage->final_zeit_bis . ')') : '<em>offen</em>') . '</td>';

        echo '<td>
            <form method="post" class="tattoo-anfrage-form">
                <input type="hidden" name="id" value="' . esc_attr($anfrage->id) . '">
                <input type="date" name="final_datum" value="' . esc_attr($anfrage->final_datum) . '">
                <input type="time" name="final_zeit_von" value="' . esc_attr($anfrage->final_zeit_von) . '">
                <input type="time" name="final_zeit_bis" value="' . esc_attr($anfrage->final_zeit_bis) . '">
                <select name="status">
                    <option value="offen"' . selected($anfrage->status, 'offen', false) . '>offen</option>
                    <option value="bestätigt"' . selected($anfrage->status, 'bestätigt', false) . '>bestätigt</option>
                    <option value="abgelehnt"' . selected($anfrage->status, 'abgelehnt', false) . '>abgelehnt</option>
                </select>
                <button type="submit" name="speichern" value="1">Speichern</button>
            </form>
        </td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

// 📡 AJAX: Blockierte Zeiten abrufen
add_action('wp_ajax_get_blockierte_zeiten', 'ajax_get_blockierte_zeiten');
add_action('wp_ajax_nopriv_get_blockierte_zeiten', 'ajax_get_blockierte_zeiten');

function ajax_get_blockierte_zeiten() {
    if (!isset($_POST['datum'])) {
        wp_send_json_error('Kein Datum erhalten.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tattoo_anfragen2';
    $datum = sanitize_text_field($_POST['datum']);

    $termine = $wpdb->get_results($wpdb->prepare("
        SELECT final_zeit_von, final_zeit_bis 
        FROM $table 
        WHERE status = 'bestätigt' AND final_datum = %s
    ", $datum));

    $blockierte = [];
    foreach ($termine as $t) {
        $blockierte[] = [$t->final_zeit_von, $t->final_zeit_bis];
    }

    wp_send_json_success($blockierte);
}



