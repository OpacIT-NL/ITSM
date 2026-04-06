<?php
session_start();

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}

if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION[ 'name' ];
require_once( __DIR__ . '/../my.php' );

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

if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = (int)$_GET['id'];

$stmt = $con->prepare( "
    SELECT f.*, t.type AS typename
    FROM itsm_am_fields f
    LEFT JOIN itsm_am_types t ON f.type = t.id
    WHERE f.id = ?
" );
$stmt->bind_param( "i", $id );
$stmt->execute();
$result = $stmt->get_result();
$field = $result->fetch_assoc();

if ( !$field ) {
  die( "Field not found" );
}

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  $name = trim( $_POST[ 'name' ] ?? '' );

  $update_stmt = $con->prepare( "
        UPDATE itsm_am_fields SET
            name = ?
        WHERE id = ?
    " );
  $update_stmt->bind_param( "si", $name, $id );

  if ( !$update_stmt->execute() ) {
    die( "Update failed: " . $update_stmt->error );
  }

  header( 'Location: edit_assettype.php?id=' . $field['type'] );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
<?php $list_back_url = 'edit_assettype.php?id=' . urlencode( (string)$field['type'] ); require(__DIR__ . '/include/back_links.php'); ?>
<center>
  <h1>Vrij veld bewerken</h1>
  <p>
    Asset type:
    <?= htmlspecialchars($field['typename']) ?>
  </p>
</center>
<div class="form-wrapper">
  <div class="form-card">
    <form method="post" class="form-grid">
      <div class="form-group">
        <label>Database veld:
          <input type="text" value="<?= htmlspecialchars($field['field']) ?>" readonly>
        </label>
      </div>
      <div class="form-group">
        <label>Weergavenaam:
          <input type="text" name="name" value="<?= htmlspecialchars($field['name'] ?? '') ?>">
        </label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Opslaan</button>
      </div>
    </form>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
