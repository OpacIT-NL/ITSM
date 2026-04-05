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
$sql2 = "SELECT operators FROM itsm_ob_operators WHERE username = ?";
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
// Validate ID
if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = ( int )$_GET[ 'id' ];

if ( isset( $_POST[ 'delete' ] ) ) {

  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_core_subcategory WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $id );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Delete failed: " . mysqli_stmt_error( $stmt ) );
  }

  echo "Categorie verwijderd. <a href='set-ls-cat.php'>Ga terug</a>";
  exit;
}

// Handle form submit
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Convert checkboxes to 0/1

  $stmt = mysqli_prepare( $con, "
        UPDATE itsm_core_subcategory SET
            name=?
        WHERE id=?
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "si",
    $_POST[ 'name' ],
    $id
  );

  mysqli_stmt_execute( $stmt );
  // Only update password if a new one is entered
  $stmt = $con->prepare( "SELECT * FROM itsm_core_subcategory WHERE id = ?" );
  $stmt->bind_param( "i", $id );
  $stmt->execute();
  $result = $stmt->get_result();
  $row2 = $result->fetch_assoc();
  $parent = $row2[ 'parent' ];
  if ( !$result ) {
    die( "Operator not found" );
  }

  echo "<script>history.back();</script>";
  exit;
}

// Fetch operator
$stmt = $con->prepare( "SELECT * FROM itsm_core_subcategory WHERE id = ?" );
$stmt->bind_param( "i", $id );
$stmt->execute();
$result = $stmt->get_result();
$row2 = $result->fetch_assoc();
$parent = $row2[ 'parent' ];
if ( !$result ) {
  die( "Operator not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <a href="edit_cat.php?id=<?= $parent ?>">Ga terug</a>
  <center>
    <h1>Subcategorie Bewerken:
      <?= htmlspecialchars($row2['name']) ?>
    </h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post"  class="form-grid">
        <div class="form-group"> 
          <!-- Basic fields -->
          <label>Naam:
            <input type="text" name="name" value="<?= htmlspecialchars($row2['name']) ?>">
          </label>
          <br>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Opslaan</button>
          <button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze subcategorie wil verwijderen?');"
         class="btn-danger"> Verwijder subcategorie </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
