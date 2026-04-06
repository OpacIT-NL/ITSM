<?php
session_start();
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
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
require_once( __DIR__ . '/../my.php' );

// Authorization check
$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $isadmin );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $isadmin == 0 ) {
  header( "Location: index.php" );
  exit();
}

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_am_types (
            type
        ) VALUES (
            ?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "s",
    $_POST[ 'type' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: set-am-types.php' );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
<?php $list_back_url = 'set-am-types.php'; require(__DIR__ . '/include/back_links.php'); ?>
<center>
  <h1>Nieuw Asset type</h1>
</center>
<div class="form-wrapper">
<div class="form-card">
<form method="post" class="form-grid">
  
  <!-- Basic fields -->
  <div class="form-group">
    <label>Naam:
      <input type="text" name="type" required>
    </label>
    <br>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-primary">Maak Asset type</button>
  </div>
</form></div></div></div>

<?php require_once(__DIR__ . '/nav/end.php'); ?>
