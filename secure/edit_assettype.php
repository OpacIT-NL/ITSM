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
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: index.php" );
  exit();
}
// Validate ID
if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = ( int )$_GET[ 'id' ];

if ( isset( $_POST[ 'delete' ] ) ) {

  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_am_assettype WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $id );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Delete failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: set-am-types.php' );
  exit;
}

// Handle form submit
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Convert checkboxes to 0/1

  $stmt = mysqli_prepare( $con, "
        UPDATE itsm_am_types SET
            type=?
        WHERE id=?
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "si",
    $_POST[ 'type' ],
    $id
  );

  mysqli_stmt_execute( $stmt );
  // Only update password if a new one is entered


  header( 'Location: set-am-types.php' );
  exit;
}

// Fetch operator
$stmt = $con->prepare( "SELECT * FROM itsm_am_types WHERE id = ?" );
$stmt->bind_param( "i", $id );
$stmt->execute();
$result = $stmt->get_result();
$row2 = $result->fetch_assoc();

$stmt2 = $con->prepare( "SELECT * FROM itsm_am_fields WHERE type = ?" );
$stmt2->bind_param( "i", $id );
$stmt2->execute();
$result3 = $stmt2->get_result();

if ( !$result ) {
  die( "Operator not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
<a href="set-am-types.php">Ga terug</a>
</p>
<center>
  <h1>Asset type bewerken:
    <?= htmlspecialchars($row2['type']) ?>
  </h1>
</center>
<div class="form-wrapper">
  <div class="form-card">
    <form method="post" class="form-grid">
      <div class="form-group"> 
        <!-- Basic fields -->
        <label>Type:
          <input type="text" name="type" value="<?= htmlspecialchars($row2['type']) ?>">
        </label>
        <br>
      </div>
      <div class="form-actions"> <br>
        <br>
        <button class="btn-primary" type="submit">Opslaan</button>
        <button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je dit asset type wil verwijderen?');"
        class="btn-danger"> Verwijder asset type </button>
      </div>
    </form>
  </div>
</div>
<br>
<center>
  <h1>Vrije velden</h1>
</center>
<a href="new_subcat.php?id=<?= $id ?>">Nieuwe Subcategorie</a>
<div class="results">
  <table border="0" class=results style="width: 100%;">
    <thead>
      <tr>
        <th style="text-align: start;">Subcategorie</th>
        <th style="text-align: start;">Actie</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = mysqli_fetch_assoc($result3)): ?>
      <tr>
        <td><?= htmlspecialchars($row['type']) ?></td>
        <td class="tblaction"><a class="btn" href="edit_subcat.php?id=<?= $row['id'] ?>"> Open Subcategorie </a></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
