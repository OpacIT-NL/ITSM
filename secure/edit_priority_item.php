<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
require_once( __DIR__ . '/../my.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION['name'];
$stmt = mysqli_prepare( $con, "SELECT isadmin FROM itsm_ob_operators WHERE username = ?" );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $isadmin );
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );
if ( (int)$isadmin !== 1 ) {
  header( 'Location: set-priority.php' );
  exit;
}

if ( !isset( $_GET['kind'], $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  die( 'Invalid request' );
}

$kind = (string)$_GET['kind'];
$id = (int)$_GET['id'];
$kind_map = [
  'impact' => [ 'table' => 'itsm_core_impacts', 'label' => t('Impact') ],
  'urgency' => [ 'table' => 'itsm_core_urgencies', 'label' => t('Urgency') ],
  'priority' => [ 'table' => 'itsm_core_priorities', 'label' => t('Priority') ]
];

if ( !isset( $kind_map[$kind] ) ) {
  die( 'Ongeldig type' );
}

$table = $kind_map[$kind]['table'];
$label = $kind_map[$kind]['label'];
$errors = [];

$load_stmt = mysqli_prepare( $con, "SELECT * FROM $table WHERE id = ? LIMIT 1" );
mysqli_stmt_bind_param( $load_stmt, "i", $id );
mysqli_stmt_execute( $load_stmt );
$result = mysqli_stmt_get_result( $load_stmt );
$item = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $load_stmt );

if ( !$item ) {
  die( $label . ' niet gevonden' );
}

if ( isset( $_POST['delete'] ) ) {
  $delete_stmt = mysqli_prepare( $con, "DELETE FROM $table WHERE id = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "i", $id );
  if ( !mysqli_stmt_execute( $delete_stmt ) ) {
    $errors[] = itsm_error_reference( 'priority_delete_failed', mysqli_stmt_error( $delete_stmt ) );
  }
  mysqli_stmt_close( $delete_stmt );

  if ( empty( $errors ) ) {
    header( 'Location: set-priority.php' );
    exit;
  }
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['delete'] ) ) {
  $name = trim( $_POST['name'] ?? '' );
  $sortorder = isset( $_POST['sortorder'] ) && is_numeric( $_POST['sortorder'] ) ? (int)$_POST['sortorder'] : 0;
  $active = isset( $_POST['active'] ) ? 1 : 0;

  if ( $name === '' ) {
    $errors[] = 'Naam is verplicht.';
  }

  if ( empty( $errors ) ) {
    $update_stmt = mysqli_prepare( $con, "UPDATE $table SET name = ?, sortorder = ?, active = ? WHERE id = ?" );
    mysqli_stmt_bind_param( $update_stmt, "siii", $name, $sortorder, $active, $id );
    if ( mysqli_stmt_execute( $update_stmt ) ) {
      mysqli_stmt_close( $update_stmt );
      header( 'Location: set-priority.php' );
      exit;
    }
    $errors[] = itsm_error_reference( 'priority_update_failed', mysqli_stmt_error( $update_stmt ) );
    mysqli_stmt_close( $update_stmt );
  }

  $item['name'] = $name;
  $item['sortorder'] = $sortorder;
  $item['active'] = $active;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $list_back_url = 'set-priority.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($label) ?> bewerken: <?= htmlspecialchars($item['name']) ?></h1>
  </center>
  <?php foreach ( $errors as $error ): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card record-form-card">
      <form method="post" class="form-grid">
        <div class="form-group">
          <label>Naam</label>
          <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Sorteervolgorde</label>
          <input type="number" name="sortorder" value="<?= (int)$item['sortorder'] ?>">
        </div>
        <div class="form-group">
          <label class="checkbox-label"><input type="checkbox" name="active" value="1" <?= (int)$item['active'] === 1 ? 'checked' : '' ?>> Actief</label>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= htmlspecialchars($label . ' ' . t('opslaan')) ?></button>
          <button type="submit" name="delete" value="1" class="btn-danger" onclick="return confirm('Weet je zeker dat je dit item wil verwijderen?');">Verwijderen</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
