<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_DB {

    /** Tabellen-Namen */
    public static $requests     = 'dstb_requests';
    public static $availability = 'dstb_availability';
    public static $vacations    = 'dstb_vacations';
    public static $bookings     = 'dstb_bookings';
    public static $suggestions  = 'dstb_suggestions'; // 💡 NEU: Terminvorschläge vom Studio

    public static function table($name){
        global $wpdb; 
        return $wpdb->prefix . $name;
    }

    /** Tabellen anlegen / upgraden */
    public static function install(){
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Kunden-Anfragen (nicht als CPT)
        $sql1 = "CREATE TABLE ".self::table(self::$requests)." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(100) DEFAULT '',
            artist VARCHAR(64) DEFAULT '',
            style VARCHAR(64) DEFAULT '',
            bodypart VARCHAR(64) DEFAULT '',
            size VARCHAR(64) DEFAULT '',
            budget INT DEFAULT 0,
            desc_text MEDIUMTEXT,
            slots JSON NULL,
            uploads JSON NULL,
            gdpr TINYINT(1) DEFAULT 0,
            confirm_token VARCHAR(64) DEFAULT NULL,
            confirmed_slot JSON NULL,
            PRIMARY KEY(id),
            KEY artist (artist),
            KEY email (email)
        ) $charset;";

        // Verfügbarkeiten (freie Zeitfenster)
        $sql2 = "CREATE TABLE ".self::table(self::$availability)." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            artist VARCHAR(64) NOT NULL,
            weekday TINYINT UNSIGNED NOT NULL,
            ranges JSON NOT NULL,
            UNIQUE KEY artist_day (artist, weekday),
            PRIMARY KEY(id)
        ) $charset;";

        // Urlaube / Sperrzeiten
        $sql3 = "CREATE TABLE ".self::table(self::$vacations)." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            artist VARCHAR(64) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            PRIMARY KEY(id),
            KEY artist (artist),
            KEY range_idx (start_date, end_date)
        ) $charset;";

        // Bestätigte Buchungen (finale Termine)
        $sql4 = "CREATE TABLE ".self::table(self::$bookings)." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            artist VARCHAR(64) NOT NULL,
            start_dt DATETIME NOT NULL,
            end_dt DATETIME NOT NULL,
            request_id BIGINT UNSIGNED NULL,
            status ENUM('tentative','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY artist_start (artist, start_dt),
            KEY status (status)
        ) $charset;";

        // 💡 NEU: Terminvorschläge des Studios (z. B. Preisvorschlag)
        $sql5 = "CREATE TABLE ".self::table(self::$suggestions)." (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            start TIME NOT NULL,
            end TIME NOT NULL,
            price INT UNSIGNED NOT NULL DEFAULT 0,
            note VARCHAR(255) DEFAULT '' NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'sent',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY date_idx (date)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5); // 💾 neue Tabelle anlegen
    }

    /** Admin pflegt freie Standard-Ranges (Wochentag) */
    public static function set_weekday_ranges($artist, $weekday, array $ranges){
        global $wpdb;
        $table = self::table(self::$availability);
        $json = wp_json_encode(array_values($ranges));
        $wpdb->replace($table, [
            'artist'  => $artist,
            'weekday' => $weekday,
            'ranges'  => $json
        ], ['%s','%d','%s']);
    }

    public static function get_weekday_ranges_map($artist){
        global $wpdb;
        $table = self::table(self::$availability);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT weekday, ranges FROM $table WHERE artist=%s", $artist), ARRAY_A);
        $map = [];
        foreach($rows as $r){
            $map[(int)$r['weekday']] = json_decode($r['ranges'], true) ?: [];
        }
        return $map;
    }

    /** Urlaube/Sperren */
    public static function add_vacation($artist, $start, $end){
        global $wpdb;
        $table = self::table(self::$vacations);
        $wpdb->insert($table, [
            'artist' => $artist,
            'start_date' => $start,
            'end_date' => $end
        ], ['%s','%s','%s']);
    }

    public static function date_is_vacation($artist, $date){
        global $wpdb;
        $table = self::table(self::$vacations);
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE artist=%s AND %s BETWEEN start_date AND end_date",
            $artist, $date
        ));
        return (int)$count > 0;
    }

    /** Bestätigte Buchungen (blockieren Slots) */
    public static function get_bookings_for_day($artist, $date){
        global $wpdb;
        $table = self::table(self::$bookings);
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT start_dt, end_dt FROM $table 
             WHERE artist=%s AND status='confirmed'
             AND start_dt <= %s AND end_dt >= %s",
            $artist, $end, $start
        ), ARRAY_A);
        return array_map(function($r){
            return [ substr($r['start_dt'],11,5), substr($r['end_dt'],11,5) ];
        }, $rows);
    }

    public static function insert_confirmed_booking($artist, $date, $start, $end, $request_id=null){
        global $wpdb;
        $table = self::table(self::$bookings);
        $wpdb->insert($table, [
            'artist'   => $artist,
            'start_dt' => "$date $start:00",
            'end_dt'   => "$date $end:00",
            'request_id'=> $request_id,
            'status'   => 'confirmed'
        ], ['%s','%s','%s','%d','%s']);
        return $wpdb->insert_id;
    }

    /** Kundenanfragen */
    public static function insert_request($data){
        global $wpdb;
        $table = self::table(self::$requests);
        $wpdb->insert($table, [
            'name'   => $data['name'],
            'email'  => $data['email'],
            'phone'  => $data['phone'],
            'artist' => $data['artist'],
            'style'  => $data['style'],
            'bodypart'=> $data['bodypart'],
            'size'   => $data['size'],
            'budget' => $data['budget'],
            'desc_text' => $data['desc'],
            'slots'  => wp_json_encode($data['slots']),
            'uploads'=> wp_json_encode($data['uploads']),
            'gdpr'   => $data['gdpr'],
            'confirm_token' => !empty($data['confirm_token']) ? $data['confirm_token'] : null
        ], ['%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%d','%s']);
        return $wpdb->insert_id;
    }

    /** Aus Standard-Ranges & Buchungen freie Slots ableiten */
    public static function free_slots_for_date($artist, $date, $stepMinutes=30){
        if ( self::date_is_vacation($artist, $date) ) return [];
        $weekday_php = (int)date('N', strtotime($date)) - 1;
        $ranges_map = self::get_weekday_ranges_map($artist);
        $ranges = $ranges_map[$weekday_php] ?? [];
        if (empty($ranges)) return [];
        $booked = self::get_bookings_for_day($artist, $date);
        $mark = [];
        foreach($ranges as $r){
            $from = self::hm2min($r[0]); $to = self::hm2min($r[1]);
            for($m=$from; $m<$to; $m+=$stepMinutes){ $mark[$m] = true; }
        }
        foreach($booked as $b){
            $from = self::hm2min($b[0]); $to = self::hm2min($b[1]);
            for($m=$from; $m<$to; $m+=$stepMinutes){ $mark[$m] = false; }
        }
        $free = [];
        $current = null;
        ksort($mark);
        foreach($mark as $m=>$ok){
            if($ok && $current===null){ $current = $m; }
            if((!$ok || !isset($mark[$m+$stepMinutes])) && $current!==null){
                $free[] = [ self::min2hm($current), self::min2hm($ok ? $m+$stepMinutes : $m) ];
                $current = null;
            }
        }
        return $free;
    }

    private static function hm2min($hm){ [$h,$m] = array_map('intval', explode(':',$hm)); return $h*60+$m; }
    private static function min2hm($min){ $h=floor($min/60); $m=$min%60; return sprintf('%02d:%02d',$h,$m); }
}
