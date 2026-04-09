<?php
session_start();
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/priority_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  session_unset();
  session_destroy();
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
  header( 'Location: settings.php' );
  exit;
}

$errors = [];
$success = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $action = $_POST['action'] ?? '';
  if ( in_array( $action, [ 'add_impact', 'add_urgency', 'add_priority' ], true ) ) {
    $name = trim( $_POST['name'] ?? '' );
    $sortorder = isset( $_POST['sortorder'] ) && is_numeric( $_POST['sortorder'] ) ? (int)$_POST['sortorder'] : 0;
    $table = [
      'add_impact' => 'itsm_core_impacts',
      'add_urgency' => 'itsm_core_urgencies',
      'add_priority' => 'itsm_core_priorities'
    ][$action];

    if ( $name === '' ) {
      $errors[] = 'Naam is verplicht.';
    } else {
      $insert_stmt = mysqli_prepare( $con, "INSERT INTO $table (name, sortorder, active) VALUES (?, ?, 1)" );
      mysqli_stmt_bind_param( $insert_stmt, "si", $name, $sortorder );
      if ( mysqli_stmt_execute( $insert_stmt ) ) {
        $success = 'Optie opgeslagen.';
      } else {
        $errors[] = 'Opslaan mislukt: ' . mysqli_stmt_error( $insert_stmt );
      }
      mysqli_stmt_close( $insert_stmt );
    }
  } elseif ( $action === 'save_matrix' ) {
    $impact_id = isset( $_POST['impactid'] ) ? (int)$_POST['impactid'] : 0;
    $urgency_id = isset( $_POST['urgencyid'] ) ? (int)$_POST['urgencyid'] : 0;
    $priority_id = isset( $_POST['priorityid'] ) ? (int)$_POST['priorityid'] : 0;
    if ( $impact_id <= 0 || $urgency_id <= 0 || $priority_id <= 0 ) {
      $errors[] = 'Selecteer impact, urgentie en prioriteit.';
    } else {
      $matrix_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_core_prioritymatrix (impactid, urgencyid, priorityid)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE priorityid = VALUES(priorityid)
      " );
      mysqli_stmt_bind_param( $matrix_stmt, "iii", $impact_id, $urgency_id, $priority_id );
      if ( mysqli_stmt_execute( $matrix_stmt ) ) {
        $success = 'Matrixregel opgeslagen.';
      } else {
        $errors[] = 'Matrixregel opslaan mislukt: ' . mysqli_stmt_error( $matrix_stmt );
      }
      mysqli_stmt_close( $matrix_stmt );
    }
  }
}

$reference_data = priority_load_reference_data( $con );
$matrix_result = mysqli_query( $con, "
  SELECT m.id, i.name AS impactname, u.name AS urgencyname, p.name AS priorityname
  FROM itsm_core_prioritymatrix m
  INNER JOIN itsm_core_impacts i ON m.impactid = i.id
  INNER JOIN itsm_core_urgencies u ON m.urgencyid = u.id
  INNER JOIN itsm_core_priorities p ON m.priorityid = p.id
  ORDER BY i.sortorder ASC, u.sortorder ASC, i.name ASC, u.name ASC
" );
$all_impacts = mysqli_query( $con, "SELECT id, name, sortorder, active FROM itsm_core_impacts ORDER BY sortorder ASC, name ASC" );
$all_urgencies = mysqli_query( $con, "SELECT id, name, sortorder, active FROM itsm_core_urgencies ORDER BY sortorder ASC, name ASC" );
$all_priorities = mysqli_query( $con, "SELECT id, name, sortorder, active FROM itsm_core_priorities ORDER BY sortorder ASC, name ASC" );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <center><h1>Impact / Urgency / Priority</h1></center>
  <?php if ( $success !== '' ): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
  <?php foreach ( $errors as $error ): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endforeach; ?>

  <div class="page-layout">
    <div class="page-sidebar">
      <div class="form-card">
        <h2>Impact toevoegen</h2>
        <form method="post" class="form-grid">
          <input type="hidden" name="action" value="add_impact">
          <div class="form-group"><label>Naam</label><input type="text" name="name" required></div>
          <div class="form-group"><label>Sorteervolgorde</label><input type="number" name="sortorder" value="0"></div>
          <button type="submit" class="btn-primary">Impact opslaan</button>
        </form>
      </div>
      <br>
      <div class="form-card">
        <h2>Urgency toevoegen</h2>
        <form method="post" class="form-grid">
          <input type="hidden" name="action" value="add_urgency">
          <div class="form-group"><label>Naam</label><input type="text" name="name" required></div>
          <div class="form-group"><label>Sorteervolgorde</label><input type="number" name="sortorder" value="0"></div>
          <button type="submit" class="btn-primary">Urgency opslaan</button>
        </form>
      </div>
      <br>
      <div class="form-card">
        <h2>Priority toevoegen</h2>
        <form method="post" class="form-grid">
          <input type="hidden" name="action" value="add_priority">
          <div class="form-group"><label>Naam</label><input type="text" name="name" required></div>
          <div class="form-group"><label>Sorteervolgorde</label><input type="number" name="sortorder" value="0"></div>
          <button type="submit" class="btn-primary">Priority opslaan</button>
        </form>
      </div>
    </div>
    <div class="page-content">
      <div class="results">
        <table class="results" style="width:100%;">
          <thead><tr><th>Impact</th><th>Sorteervolgorde</th><th>Actief</th><th>Actie</th></tr></thead>
          <tbody>
            <?php while ( $row = mysqli_fetch_assoc( $all_impacts ) ): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= (int)$row['sortorder'] ?></td>
              <td><?= (int)$row['active'] === 1 ? 'Ja' : 'Nee' ?></td>
              <td class="tblaction"><a class="btn" href="edit_priority_item.php?kind=impact&id=<?= (int)$row['id'] ?>">Open Impact</a></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <br>
      <div class="results">
        <table class="results" style="width:100%;">
          <thead><tr><th>Urgency</th><th>Sorteervolgorde</th><th>Actief</th><th>Actie</th></tr></thead>
          <tbody>
            <?php while ( $row = mysqli_fetch_assoc( $all_urgencies ) ): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= (int)$row['sortorder'] ?></td>
              <td><?= (int)$row['active'] === 1 ? 'Ja' : 'Nee' ?></td>
              <td class="tblaction"><a class="btn" href="edit_priority_item.php?kind=urgency&id=<?= (int)$row['id'] ?>">Open Urgency</a></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <br>
      <div class="results">
        <table class="results" style="width:100%;">
          <thead><tr><th>Priority</th><th>Sorteervolgorde</th><th>Actief</th><th>Actie</th></tr></thead>
          <tbody>
            <?php while ( $row = mysqli_fetch_assoc( $all_priorities ) ): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= (int)$row['sortorder'] ?></td>
              <td><?= (int)$row['active'] === 1 ? 'Ja' : 'Nee' ?></td>
              <td class="tblaction"><a class="btn" href="edit_priority_item.php?kind=priority&id=<?= (int)$row['id'] ?>">Open Priority</a></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <br>
      <div class="form-card form-card-wide">
        <h2>Priority matrix</h2>
        <form method="post" class="form-grid">
          <input type="hidden" name="action" value="save_matrix">
          <div class="form-group">
            <label>Impact</label>
            <select name="impactid" required>
              <option value="">Selecteer impact</option>
              <?php foreach ( $reference_data['impacts'] as $impact ): ?>
              <option value="<?= (int)$impact['id'] ?>"><?= htmlspecialchars($impact['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Urgency</label>
            <select name="urgencyid" required>
              <option value="">Selecteer urgency</option>
              <?php foreach ( $reference_data['urgencies'] as $urgency ): ?>
              <option value="<?= (int)$urgency['id'] ?>"><?= htmlspecialchars($urgency['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Priority</label>
            <select name="priorityid" required>
              <option value="">Selecteer priority</option>
              <?php foreach ( $reference_data['priorities'] as $priority ): ?>
              <option value="<?= (int)$priority['id'] ?>"><?= htmlspecialchars($priority['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn-primary">Matrixregel opslaan</button>
        </form>
      </div>
      <br>
      <div class="results">
        <table class="results" style="width:100%;">
          <thead><tr><th>Impact</th><th>Urgency</th><th>Priority</th></tr></thead>
          <tbody>
            <?php while ( $row = mysqli_fetch_assoc( $matrix_result ) ): ?>
            <tr>
              <td><?= htmlspecialchars($row['impactname']) ?></td>
              <td><?= htmlspecialchars($row['urgencyname']) ?></td>
              <td><?= htmlspecialchars($row['priorityname']) ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
