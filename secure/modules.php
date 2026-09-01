<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

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
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
