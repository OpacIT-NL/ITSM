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
$sql2 = "SELECT buildings FROM itsm_ob_operators WHERE username = ?";
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

$stmt2 = $con->prepare( "SELECT * FROM itsm_ob_customers" );
$stmt2->execute();
$result3 = $stmt2->get_result();


if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_ob_buildings (
            customer, address, postalcode, city, idvp
        ) VALUES (
            ?,?,?,?,?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "issss",
    $_POST[ 'customer' ],
    $_POST[ 'address' ],
    $_POST[ 'postalcode' ],
    $_POST[ 'city' ],
    $_POST[ 'idvp' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: buildings.php' );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<<<<<<< Updated upstream
<div class="content"> <a href="buildings.php">Ga terug</a>
=======
<div class="content"> <?php $list_back_url = 'buildings.php'; require(__DIR__ . '/include/back_links.php'); ?>
>>>>>>> Stashed changes
  <center>
    <h1>Gebouw aanmaken</h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        <div class="form-group"> 
          <!-- Basic fields --> 
          Klant:
          <?

          echo '<select name="customer"><option>--selecteer een klant--</option>';

          // Check if nothing is selected

          while ( $row2 = $result3->fetch_assoc() ) {
            $id = $row2[ 'id' ];
            $name = htmlspecialchars( $row2[ 'din' ] . ' - ' . $row2[ 'name' ] );
            echo "<option value='$id'>$name</option>";
          }

          echo '</select>';
          ?>
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
          <label>ICT Dienstverlener Pand:
            <input type="text" name="idvp">
          </label>
        </div>
        <br>
        <br>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Opslaan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
