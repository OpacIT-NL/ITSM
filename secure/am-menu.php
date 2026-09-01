<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );

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

$sql2 = "SELECT assets FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: modules.php" );
  exit();
}

$stmt2 = $con->prepare( "SELECT type FROM itsm_am_types" );
$stmt2->execute();
$result = $stmt2->get_result();
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1>Asset Management</h1>
  <div class="module-grid">
    <?php
    while ( $row = $result->fetch_assoc() ) {
      $typeUrl = urlencode( $row[ 'type' ] );
      $typeText = htmlspecialchars( $row[ 'type' ] );

      echo '<a href="assets.php?filtertype=' . htmlspecialchars( $typeUrl ) . '">' . $typeText . '</a>';
    }
    ?>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
