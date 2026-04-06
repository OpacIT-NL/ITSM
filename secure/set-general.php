<?php
session_start();

require_once( __DIR__ . '/../my.php' );

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Absolute expiration check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION[ 'name' ];
require_once( __DIR__ . '/../version.php' );
// Fetch user permissions
$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: index.php" );
  exit();
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/settings.php'); ?>
<div class="module-section">
  <h1>Algemene Instellingen</h1>
  <div class="module-grid"> <a href="set-ls-cat.php"> Categoriebeheer </a> <a href="set-ls-status.php"> Statussen </a> </div>
  <br>
  Current Version:
  <?= htmlspecialchars($version) ?>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
