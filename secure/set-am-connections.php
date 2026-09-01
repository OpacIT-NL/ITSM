<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}

if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}

$logged_in_user = $_SESSION[ 'name' ];
require_once( __DIR__ . '/../my.php' );

$stmt = mysqli_prepare( $con, "SELECT isadmin FROM itsm_ob_operators WHERE username = ?" );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $isadmin );
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );

if ( (int)$isadmin === 0 ) {
  header( "Location: index.php" );
  exit();
}

$message = '';
$error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $action = $_POST['action'] ?? '';

  if ( $action === 'create' ) {
    $name = trim( $_POST['name'] ?? '' );
    $reverse_name = trim( $_POST['reverse_name'] ?? '' );
    $active = isset( $_POST['active'] ) ? 1 : 0;

    if ( $name === '' ) {
      $error = 'Vul een naam in.';
    } else {
      $stmt = mysqli_prepare( $con, "INSERT INTO itsm_am_connectiontypes (name, reverse_name, active) VALUES (?, ?, ?)" );
      mysqli_stmt_bind_param( $stmt, "ssi", $name, $reverse_name, $active );
      if ( mysqli_stmt_execute( $stmt ) ) {
        header( 'Location: set-am-connections.php?saved=1' );
        exit;
      }
      $error = itsm_error_reference( 'asset_connection_insert_failed', mysqli_stmt_error( $stmt ) );
      mysqli_stmt_close( $stmt );
    }
  }

  if ( $action === 'update' ) {
    $id = isset( $_POST['id'] ) && is_numeric( $_POST['id'] ) ? (int)$_POST['id'] : 0;
    $name = trim( $_POST['name'] ?? '' );
    $reverse_name = trim( $_POST['reverse_name'] ?? '' );
    $active = isset( $_POST['active'] ) ? 1 : 0;

    if ( $id <= 0 || $name === '' ) {
      $error = 'Controleer de ingevulde verbinding.';
    } else {
      $stmt = mysqli_prepare( $con, "UPDATE itsm_am_connectiontypes SET name = ?, reverse_name = ?, active = ? WHERE id = ?" );
      mysqli_stmt_bind_param( $stmt, "ssii", $name, $reverse_name, $active, $id );
      if ( mysqli_stmt_execute( $stmt ) ) {
        header( 'Location: set-am-connections.php?saved=1' );
        exit;
      }
      $error = itsm_error_reference( 'asset_connection_update_failed', mysqli_stmt_error( $stmt ) );
      mysqli_stmt_close( $stmt );
    }
  }

  if ( $action === 'delete' ) {
    $id = isset( $_POST['id'] ) && is_numeric( $_POST['id'] ) ? (int)$_POST['id'] : 0;

    if ( $id > 0 ) {
      $stmt = mysqli_prepare( $con, "DELETE FROM itsm_am_connectiontypes WHERE id = ?" );
      mysqli_stmt_bind_param( $stmt, "i", $id );
      if ( mysqli_stmt_execute( $stmt ) ) {
        header( 'Location: set-am-connections.php?deleted=1' );
        exit;
      }
      $error = 'Verwijderen mislukt. Deze verbinding wordt mogelijk nog gebruikt.';
      mysqli_stmt_close( $stmt );
    }
  }
}

if ( isset( $_GET['saved'] ) ) {
  $message = 'Asset verbinding opgeslagen.';
}
if ( isset( $_GET['deleted'] ) ) {
  $message = 'Asset verbinding verwijderd.';
}

$result = mysqli_query( $con, "
    SELECT id, name, reverse_name, active
    FROM itsm_am_connectiontypes
    ORDER BY active DESC, name ASC
" );

if ( !$result ) {
  itsm_fail( 'asset_connection_query_failed', mysqli_error( $con ) );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
<?php $module_back_url = 'set-am.php'; require(__DIR__ . '/include/module_links.php'); ?>
<center>
  <h1>Asset Connections</h1>
</center>

<?php if ( $message !== '' ): ?>
<p class="success"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
<?php if ( $error !== '' ): ?>
<p class="ssp-error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="form-wrapper record-form-wrapper">
  <div class="form-card form-card-wide record-form-card">
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label>Naam:
          <input type="text" name="name" required placeholder="Bijvoorbeeld: draait op">
        </label>
      </div>
      <div class="form-group">
        <label>Omgekeerde naam:
          <input type="text" name="reverse_name" placeholder="Bijvoorbeeld: host">
        </label>
      </div>
      <label class="checkbox-inline"><input type="checkbox" name="active" value="1" checked> Actief</label>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Maak verbindingstype</button>
      </div>
    </form>
  </div>
</div>

<div class="results">
  <table border="0" class="results" style="width: 100%;">
    <thead>
      <tr>
        <th>Naam</th>
        <th>Omgekeerde naam</th>
        <th>Actief</th>
        <th>Actie</th>
      </tr>
    </thead>
    <tbody>
      <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
      <tr>
        <td colspan="4">
          <form method="post" class="asset-connection-row-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>" required>
            <input type="text" name="reverse_name" value="<?= htmlspecialchars($row['reverse_name'] ?? '') ?>">
            <label><input type="checkbox" name="active" value="1" <?= (int)$row['active'] === 1 ? 'checked' : '' ?>> Actief</label>
            <button type="submit" class="btn-primary">Opslaan</button>
            <button type="submit" name="action" value="delete" class="btn-danger" onclick="return confirm('Weet je zeker dat je dit verbindingstype wil verwijderen?');">Verwijder</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
