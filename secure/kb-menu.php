<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1><?= htmlspecialchars(t('Kennisbank')) ?></h1>
  <div class="module-grid">
    <a href="new_kb_item.php"><?= htmlspecialchars(t('Nieuw kennisitem')) ?></a>
    <a href="kb_items.php"><?= htmlspecialchars(t('Alle kennisitems')) ?></a>
    <a href="kb_items.php?visibility=public"><?= htmlspecialchars(t('Publieke kennisitems')) ?></a>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
