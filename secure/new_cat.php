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
        INSERT INTO itsm_core_category (
            name, type
        ) VALUES (
            ?,?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $_POST[ 'name' ],
    $_POST[ 'type' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: set-ls-cat.php' );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
<<<<<<< Updated upstream
<a href="set-ls-cat.php">Ga terug</a>
=======
<?php $list_back_url = 'set-ls-cat.php'; require(__DIR__ . '/include/back_links.php'); ?>
>>>>>>> Stashed changes
<center>
  <h1>Nieuwe categorie</h1>
</center>
<div class="form-wrapper">
<div class="form-card">
<form method="post" class="form-grid">
  
  <!-- Basic fields -->
  <div class="form-group">
    <label>Naam:
      <input type="text" name="name" required>
    </label>
    <br>
  </div>
  <div class="form-group">
    <label for="type">Type:</label>
    <select id="type" name="type">
      <option value="" disabled selected hidden>Selecteer een type</option>
      <option value="CHANGE">Wijziging</option>
      <option value="INCIDENT">Incident</option>
      <option value="PROBLEM">Problem</option>
      <option value="EVENT">Event</option>
    </select>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-primary">Maak categorie</button>
  </div>
</form>
</div></div></div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
