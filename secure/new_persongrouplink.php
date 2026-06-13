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
// Validate ID
if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = ( int )$_GET[ 'id' ];

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_ob_persongrouplinks (
            persongroup, person
        ) VALUES (
            ?,?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id,
    $_POST[ 'person' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  echo "<script>history.go(-2);</script>";
  exit;
}

$stmt2 = $con->prepare( "SELECT o.id, o.firstname, o.lastname
FROM itsm_ob_persons o
LEFT JOIN itsm_ob_persongrouplinks l 
    ON o.id = l.person AND l.persongroup = ?
WHERE l.person IS NULL
ORDER BY o.firstname, o.lastname;" );
$stmt2->bind_param( "i", $id );
$stmt2->execute();
$result3 = $stmt2->get_result();
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $list_back_url = 'edit_persongroup.php?id=' . urlencode( (string)$id ); require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Persoon toevoegen aan groep</h1>
  </center>
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card record-form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        <div class="form-group"> <?php echo '<select name="person">';

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
