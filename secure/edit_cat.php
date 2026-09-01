<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Sliding idle timeout check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
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

  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_core_category WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $id );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    itsm_fail( 'category_delete_failed', mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: set-ls-cat.php' );
  exit;
}

// Handle form submit
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Convert checkboxes to 0/1

  $stmt = mysqli_prepare( $con, "
        UPDATE itsm_core_category SET
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


  header( 'Location: set-ls-cat.php' );
  exit;
}

// Fetch operator
$stmt = $con->prepare( "SELECT * FROM itsm_core_category WHERE id = ?" );
$stmt->bind_param( "i", $id );
$stmt->execute();
$result = $stmt->get_result();
$row2 = $result->fetch_assoc();

$stmt2 = $con->prepare( "SELECT * FROM itsm_core_subcategory WHERE parent = ?" );
$stmt2->bind_param( "i", $id );
$stmt2->execute();
$result3 = $stmt2->get_result();

if ( !$result ) {
  die( "Operator not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
<?php $list_back_url = 'set-ls-cat.php'; require(__DIR__ . '/include/back_links.php'); ?>
</p>
<center>
  <h1>Categorie Bewerken:
    <?= htmlspecialchars($row2['name']) ?>
  </h1>
</center>
<div class="form-wrapper record-form-wrapper">
  <div class="form-card record-form-card">
    <form method="post" class="form-grid">
      <div class="form-group"> 
        <!-- Basic fields -->
        <label>Naam:
          <input type="text" name="name" value="<?= htmlspecialchars($row2['name']) ?>">
        </label>
        <br>
      </div>
      <div class="form-actions"> <br>
        <br>
        <button class="btn-primary" type="submit">Opslaan</button>
        <button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze categorie wil verwijderen?');"
        class="btn-danger"> Verwijder categorie </button>
      </div>
    </form>
  </div>
</div>
<br>
<center>
  <h1>Subcategoriën</h1>
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
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td class="tblaction"><a class="btn" href="edit_subcat.php?id=<?= $row['id'] ?>"> Open Subcategorie </a></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
