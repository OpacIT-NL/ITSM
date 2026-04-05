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
$id = ( int )$_GET[ 'id' ];

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_core_subcategory (
            name, parent
        ) VALUES (
            ?,?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "si",
    $_POST[ 'name' ],
    $_POST[ 'parent' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }
  header( "Location: edit_cat.php?id=$id" );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <a href="edit_cat.php?id=<?= $id ?>">Ga terug</a>
  <center>
    <h1>Nieuwe subcategorie</h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        <div class="form-group">
          <label>Naam:
            <input type="text" name="name" required>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Hoofdcategorie:
            <input type="text" name="parent" value="<?= $id ?>" required>
          </label>
        </div>
        <br>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Maak subcategorie</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
