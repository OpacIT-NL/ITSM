<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Absolute expiration check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION[ 'name' ];
require_once( __DIR__ . '/../my.php' );

// Authorization check
$sql2 = "SELECT persons FROM itsm_ob_operators WHERE username = ?";
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


if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_ob_persongroups (
            groupname
        ) VALUES (
            ?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "s",
    $_POST[ 'name' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: persongroups.php' );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"><?php $list_back_url = 'persongroups.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Nieuwe persoonsgroep</h1>
  </center>
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card record-form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        
        <h3>Basic Information</h3>
        <div class="form-group">
          <label>Name:
            <input type="text" name="name" required>
          </label>
        </div>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Opslaan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
