<?php if ( ! defined('ABSPATH') ) exit;
$rid = intval($_GET['rid'] ?? 0); $t = sanitize_text_field($_GET['t'] ?? '');
$props = get_post_meta($rid,'proposals',true) ?: [];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<title>Termin bestätigen</title>
<?php wp_head(); ?>
</head>
<body class="dstb-confirm">
<div class="dstb-wrap">
<div class="dstb-card">
<h2>Termin auswählen</h2>
<?php if(!$props): ?>
<p>Aktuell liegen keine Vorschläge vor.</p>
<?php else: ?>
<ol class="dstb-list">
<?php foreach($props as $i=>$p): ?>
<li><label class="dstb-option"><input type="radio" name="choice" value="<?php echo $i; ?>"> <?php echo esc_html($p['date'].' '.$p['start'].' ('.$p['dur'].' Min)'); ?></label></li>
<?php endforeach; ?>
</ol>
<div class="dstb-actions">
<button class="dstb-btn primary" id="dstb-confirm-btn">Termin verbindlich bestätigen</button>
<span id="dstb-msg"></span>
</div>
<?php endif; ?>
</div>
</div>
<script>
(function(){
const btn=document.getElementById('dstb-confirm-btn'); if(!btn) return;
btn.addEventListener('click',function(){
const val=document.querySelector('input[name="choice"]:checked'); if(!val){ alert('Bitte wähle eine Option.'); return; }
const fd=new FormData();
fd.append('action','dstb_confirm_choice');
fd.append('rid','<?php echo esc_js($rid); ?>');
fd.append('t','<?php echo esc_js($t); ?>');
fd.append('choice',val.value);
fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
const m=document.getElementById('dstb-msg');
m.textContent = j.success ? 'Danke! Dein Termin ist bestätigt.' : (j.data?.msg||'Fehler');
});
});
})();
</script>
<?php wp_footer(); ?>
</body>
</html>