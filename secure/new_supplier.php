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
$sql2 = "SELECT suppliers FROM itsm_ob_operators WHERE username = ?";
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
        INSERT INTO itsm_ob_suppliers (
            cin, name, address, postalcode, city, primaryemail, primaryphone
        ) VALUES (
            ?,?,?,?,?,?,?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "sssssss",
    $_POST[ 'din' ],
    $_POST[ 'name' ],
    $_POST[ 'address' ],
    $_POST[ 'postalcode' ],
    $_POST[ 'city' ],
    $_POST[ 'primaryemail' ],
    $_POST[ 'primaryphone' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: suppliers.php' );
  exit;
}

?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<<<<<<< Updated upstream
<div class="content"> <a href="suppliers.php">Ga terug</a>
=======
<div class="content"> <?php $list_back_url = 'suppliers.php'; require(__DIR__ . '/include/back_links.php'); ?>
>>>>>>> Stashed changes
  <center>
    <h1>Nieuwe leverancier</h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        <div class="form-group">
          <label>Crediteur Identificatie Nummer (CIN):
            <input type="text" name="din" required>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Naam:
            <input type="text" name="name" required>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Adres:
            <input type="text" name="address">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Postcode:
            <input type="text" name="postalcode">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Plaats:
            <input type="text" name="city">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Primair e-mailadres:
            <input type="text" name="primaryemail">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Primair telefoonnummer:
            <input type="text" name="primaryphone">
          </label>
        </div>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Maak leverancier</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
