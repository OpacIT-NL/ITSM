<?php
session_start();

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

if ( isset( $_POST[ 'delete' ] ) ) {

  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_ob_operatorgroups WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $id );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Delete failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: operatorgroups.php' );
  exit;
}

// Handle form submit
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_operatorgroups SET
            groupname=?
        WHERE id=?
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "si",
    $_POST[ 'groupname' ],
    $id
  );

  mysqli_stmt_execute( $stmt );


  header( 'Location: operatorgroups.php' );
  exit;
}

// Fetch operator
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operatorgroups WHERE id=?" );
mysqli_stmt_bind_param( $stmt, "i", $id );
mysqli_stmt_execute( $stmt );

$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );

$stmt2 = $con->prepare( "SELECT l.id, o.firstname, o.lastname
FROM itsm_ob_opgrouplinks l
JOIN itsm_ob_operators o ON l.operatorid = o.id
WHERE l.groupid = ?
ORDER BY o.firstname, o.lastname;" );
$stmt2->bind_param( "i", $id );
$stmt2->execute();
$result3 = $stmt2->get_result();

if ( !$operator ) {
  die( "Operatorgroup not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <?php $list_back_url = 'operatorgroups.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars(t('Behandelaarsgroep bewerken:')) ?>
      <?= htmlspecialchars($operator['groupname']) ?>
    </h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        <div class="form-group"> 
          <!-- Basic fields -->
          <label><?= htmlspecialchars(t('Groepsnaam')) ?>:
            <input type="text" name="groupname" value="<?= htmlspecialchars($operator['groupname']) ?>">
          </label>
          <br>
        </div>
        <br>
        <br>
        <div class="form-actions">
          <button class="btn-primary" type="submit">Opslaan</button>
          <button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze behandelaarsgroep wil verwijderen?');"
        class="btn-danger"> Verwijder behandelaarsgroep </button>
        </div>
      </form>
    </div>
  </div>
  <br>
  <center>
    <h1><?= htmlspecialchars(t('Groepsleden')) ?></h1>
  </center>
  <a href="new_opgrouplink.php?id=<?= $id ?>"><?= htmlspecialchars(t('Behandelaar koppelen')) ?></a>
  <div class="results">
    <table border="0" class=results>
      <thead>
        <tr>
          <th style="text-align: start;">Naam</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result3)): ?>
        <tr>
          <td><?= htmlspecialchars($row['firstname']) ?>
            <?= htmlspecialchars($row['lastname']) ?></td>
          <td class="tblaction"><a class="btn" href="delete_opgrouplink.php?id=<?= $row['id'] ?>"><?= htmlspecialchars(t('Ontkoppelen')) ?></a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
