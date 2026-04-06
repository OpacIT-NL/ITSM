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
// Validate ID
if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = ( int )$_GET[ 'id' ];

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_ob_opgrouplinks (
            groupid, operatorid
        ) VALUES (
            ?,?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id,
    $_POST[ 'operatorid' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  echo "<script>history.go(-2);</script>";
  exit;
}

$stmt2 = $con->prepare( "SELECT o.id, o.firstname, o.lastname
FROM itsm_ob_operators o
LEFT JOIN itsm_ob_opgrouplinks l 
    ON o.id = l.operatorid AND l.groupid = ?
WHERE l.operatorid IS NULL
ORDER BY o.firstname, o.lastname;" );
$stmt2->bind_param( "i", $id );
$stmt2->execute();
$result3 = $stmt2->get_result();
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $list_back_url = 'edit_operatorgroup.php?id=' . urlencode( (string)$id ); require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Behandelaar toevoegen aan groep</h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        <div class="form-group"> <? echo '<select name="operatorid">';

        while ( $row = $result3->fetch_assoc() ) {
          $id = $row[ 'id' ];
          $name = $row[ 'firstname' ] . ' ' . $row[ 'lastname' ];

          echo "<option value='$id'>$name</option>";
        }

        echo '</select>';
        ?></div>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Toevoegen aan groep</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
