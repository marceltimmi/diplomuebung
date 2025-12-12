<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="dstb-wrap">
  <form id="dstb-form" class="dstb-card" enctype="multipart/form-data">
    <h2>Termin anfragen</h2>

    <div class="dstb-grid">
      <label class="dstb-field">
        <span>Vorname*</span>
        <input type="text" name="firstname" required>
      </label>

      <label class="dstb-field">
        <span>Nachname*</span>
        <input type="text" name="lastname" required>
      </label>

      <label class="dstb-field">
        <span>E-Mail*</span>
        <input type="email" name="email" required>
      </label>

      <label class="dstb-field">
        <span>Telefon</span>
        <input type="tel" name="phone">
      </label>

      <label class="dstb-field">
        <span>Adresse*</span>
        <input type="text" name="address" autocomplete="street-address" required>
      </label>

      <label class="dstb-field">
        <span>Bevorzugter Artist</span>
        <select name="artist" id="dstb-artist">
          <?php foreach(dstb_artists() as $k=>$v): ?>
            <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="dstb-field">
        <span>Stilrichtung</span>
        <select name="style">
          <?php foreach(dstb_styles_list() as $s): ?>
            <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="dstb-field">
        <span>Körperstelle</span>
        <select name="bodypart">
          <?php foreach(dstb_bodyparts_list() as $b): ?>
            <option value="<?php echo esc_attr($b); ?>"><?php echo esc_html($b); ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="dstb-field">
  <span>Ungefähre Größe</span>
  <select name="size" required>
    <option value="">Bitte auswählen …</option>
    <option value="5-10cm Durchmesser">5–10 cm Durchmesser</option>
    <option value="10-20cm Durchmesser">10–20 cm Durchmesser</option>
    <option value="20-40cm Durchmesser">20–40 cm Durchmesser</option>
    <option value="+40cm Durchmesser">+ 40 cm Durchmesser</option>
    <option value="noch unklar">noch unklar</option>
  </select>
</div>


      <div class="dstb-field">
        <span>Budget: <strong id="dstb-budget-val">€ 250</strong></span>
        <input type="range" name="budget" min="50" max="4000" step="50" value="250"
          oninput="document.getElementById('dstb-budget-val').textContent='€ '+this.value;">
      </div>

      <label class="dstb-field dstb-col-span">
        <span>Beschreibung</span>
        <textarea name="desc" rows="4"
          placeholder="Beschreibe kurz dein Tattoo (Motiv, Stil, Platzierung, ...)"></textarea>
      </label>
    </div>

    <div class="dstb-divider"></div>

    <!-- Kalenderbereich -->
    <div class="dstb-availability">
      <h3>Deine zeitlichen Verfügbarkeiten</h3>

      <div id="dstb-calendar-box" style="display:none;">
        <p>Wähle deinen Wunschtermin:</p>
        <div id="dstb-calendar"></div>
      </div>

      <div id="dstb-slot-box">
        <p>Wann hast du Zeit? Geben Sie hier bis zu 3 Terminen an. </p>
        <div id="dstb-slots"></div>
        <button class="dstb-btn" id="dstb-add-slot" type="button">+ weiteres Zeitfenster</button>
      </div>
    </div>

    <div class="dstb-divider"></div>

    <div class="dstb-upload">
      <h3>Inspirationen (bis zu 10 Bilder)</h3>
      <input type="file" name="images[]" id="dstb-images" accept="image/*" multiple>
      <div id="dstb-previews"></div>
    </div>

    <div class="dstb-info-box">
      <strong>Wichtige Hinweise zur Terminanfrage</strong>
      <ul>
        <li>Dies ist eine unverbindliche Anfrage. Ein verbindlicher Termin entsteht erst nach schriftlicher Bestätigung und fristgerechter Anzahlung (25&nbsp;%).</li>
        <li>Bei Nichterscheinen ohne rechtzeitige Stornierung kann der volle Betrag gemäß Studio-AGB verrechnet werden.</li>
        <li>Die Adresse wird zur Terminzuordnung und für eine spätere Rechnungsstellung benötigt.</li>
      </ul>
    </div>

    <label class="dstb-check">
      <input type="checkbox" name="gdpr" required>
      <span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung meiner Terminanfrage gemäß Datenschutzerklärung zu.</span>
    </label>

    <div class="dstb-actions">
      <button class="dstb-btn primary" type="submit">Anfrage absenden</button>
      <span id="dstb-msg"></span>
    </div>
  </form>
</div>
