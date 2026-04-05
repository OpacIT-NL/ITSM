<?php
session_start();
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
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
$sql = "SELECT isadmin, firstname, lastname FROM itsm_ob_operators WHERE username = ?";
$stmt = mysqli_prepare( $con, $sql );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $isadmin, $firstname, $lastname ); // add more as needed
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<!-- Main content -->
<div class="content">
  <h1>ITSM Dashboard - Welkom terug <?php echo $firstname . ' ' . $lastname;?></h1>
  <h2>Hoofdmenu</h2>
  <div class="quicklinks"> <a href="./modules.php"> <i class="fa-solid fa-cubes-stacked fa-2xl"></i><br>
    <span>Modules</span> </a>
    <?php if ($isadmin == 1): ?>
    <a href="./settings.php"> <i class="fa-solid fa-screwdriver-wrench fa-2xl"></i><br>
    <span>Instellingen</span> </a>
    <?php endif; ?>
    <a href="./profile.php"> <i class="fa-solid fa-user fa-2xl"></i><br>
    <span>Profiel</span> </a> </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
