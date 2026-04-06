<?php
if ( !isset( $module_back_url ) || $module_back_url === '' ) {
  $module_back_url = 'modules.php';
}
if ( !isset( $module_back_label ) || $module_back_label === '' ) {
  $module_back_label = 'Terug naar module';
}
?>
<div class="form-actions">
  <a href='javascript:history.back(1)'>Ga terug</a>
  <a href="<?= htmlspecialchars($module_back_url) ?>"><?= htmlspecialchars($module_back_label) ?></a>
</div>
