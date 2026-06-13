<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/event_helpers.php' );

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
$operator_context = event_get_operator_context( $con, $logged_in_user );
event_require_access( $operator_context );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1>Event Management</h1>
  <h2>Aanmaken</h2>
  <div class="module-grid">
    <a href="new_event.php">Nieuw event</a>
  </div>
  <br>
  <h2>Bekijken</h2>
  <div class="module-grid">
    <a href="events.php?view=open">Open events</a>
    <a href="events.php?view=all">Alle events</a>
    <a href="events.php?view=closed">Gesloten events</a>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
