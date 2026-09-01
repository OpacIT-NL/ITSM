<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Sliding idle timeout check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION[ 'name' ];
require_once( __DIR__ . '/../my.php' );
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset( $_POST['id'] ) || !is_numeric( $_POST['id'] ) ) {
  http_response_code( 405 );
  header( "Location: operatorgroups.php" );
  exit();
}
$id = (int)$_POST['id'];

// Authorization check
$sql2 = "SELECT groups FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: ob-menu.php" );
  exit();
}
$stmt = mysqli_prepare( $con, "DELETE FROM itsm_ob_opgrouplinks WHERE id = ?" );
mysqli_stmt_bind_param( $stmt, "i", $id );

if ( !mysqli_stmt_execute( $stmt ) ) {
  itsm_fail( 'operator_group_link_delete_failed', mysqli_stmt_error( $stmt ) );
}

echo "<script>history.back();</script>";
exit;
