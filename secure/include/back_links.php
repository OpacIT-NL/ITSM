<?php
if ( !isset( $list_back_url ) || $list_back_url === '' ) {
  $list_back_url = 'index.php';
}
if ( !isset( $list_back_label ) || $list_back_label === '' ) {
  $list_back_label = 'Terug naar lijst';
}
?>
<div class="form-actions">
  <a href='javascript:history.back(1)'>Ga terug</a>
  <a href="<?= htmlspecialchars($list_back_url) ?>"><?= htmlspecialchars($list_back_label) ?></a>
</div>
