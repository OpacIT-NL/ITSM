<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/ubm_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION['name'];
$operator_context = ubm_get_operator_context( $con, $logged_in_user );
ubm_require_access( $operator_context );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1>Universal Backlog Management</h1>
  <h2>Aanmaken</h2>
  <div class="module-grid">
    <a href="new_ubm_item.php?type=initiative">Nieuwe Initiative</a>
  </div>
  <br>
  <h2>Bekijken</h2>
  <div class="module-grid">
    <a href="ubm_tree.php">Tree-overzicht per initiative</a>
    <a href="ubm_items.php?view=initiatives">Initiatives</a>
    <a href="ubm_items.php?view=epics">Open epics</a>
    <a href="ubm_items.php?view=features">Open features</a>
    <a href="ubm_items.php?view=stories">Open stories per team</a>
    <a href="ubm_items.php?view=mywork">Mijn Werk</a>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
