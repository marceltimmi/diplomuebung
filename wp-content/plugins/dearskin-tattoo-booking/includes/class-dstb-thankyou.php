<?php
if ( ! defined('ABSPATH') ) exit;

class DSTB_ThankYou {

    public static function init() {
        add_shortcode('dstb_thankyou_request', [__CLASS__, 'request_page']);
        add_shortcode('dstb_thankyou_confirmed', [__CLASS__, 'confirmed_page']);
    }

    /**
     * Thank You – nach Terminanfrage
     */
    public static function request_page() {
        ob_start(); ?>

        <div class="dstb-wrapper">
            <div class="dstb-card dstb-thankyou">

                <div class="dstb-thankyou-icon">📨</div>

                <h2>Vielen Dank für deine Anfrage</h2>

                <p class="dstb-text">
                    Deine Terminanfrage wurde erfolgreich übermittelt.<br>
                    Wir melden uns in Kürze mit passenden Terminvorschlägen bei dir.
                </p>

                <div class="dstb-info-box">
                    <h4>Nächste Schritte</h4>
                    <ul>
                        <li>Prüfung deiner Anfrage durch das Studio</li>
                        <li>Terminvorschläge per E-Mail</li>
                        <li>Fixierung deines Wunschtermins</li>
                    </ul>
                </div>

                <p class="dstb-footer">
                    Dear Skin Tattoo Studio
                </p>

            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Thank You – nach fixer Terminbestätigung
     */
    public static function confirmed_page() {
        ob_start(); ?>

        <div class="dstb-wrapper">
            <div class="dstb-card dstb-thankyou">

                <div class="dstb-thankyou-icon success">✔</div>

                <h2>Dein Termin ist bestätigt</h2>

                <p class="dstb-text">
                    Vielen Dank für deine Bestätigung.<br>
                    Dein Tattoo-Termin wurde verbindlich reserviert.
                </p>

                <div class="dstb-info-box success">
                    <h4>Wichtige Hinweise</h4>
                    <ul>
                        <li>Bitte erscheine pünktlich zum Termin</li>
                        <li>Bei Verhinderung rechtzeitig absagen</li>
                        <li>Alle Details erhältst du per E-Mail</li>
                    </ul>
                </div>

                <p class="dstb-footer">
                    Wir freuen uns auf dich<br>
                    Dear Skin Tattoo Studio
                </p>

            </div>
        </div>

        <?php
        return ob_get_clean();
    }
}

DSTB_ThankYou::init();
