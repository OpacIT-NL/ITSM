<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/imap_helpers.php' );

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
$stmt = mysqli_prepare( $con, "SELECT id, isadmin FROM itsm_ob_operators WHERE username = ?" );
mysqli_stmt_bind_param( $stmt, 's', $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $operator_id, $is_admin );
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );

if ( (int)$is_admin === 0 ) {
  header( 'Location: index.php' );
  exit;
}

$summary = [];
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $summary = imap_run_all_imports( $con, (int)$operator_id, 25 );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'set-imaprules.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center><h1>IMAP import uitvoeren</h1></center>

  <div class="form-wrapper record-form-wrapper">
    <div class="form-card form-card-wide record-form-card">
      <p>Deze import leest ongelezen e-mails uit alle actieve IMAP-regels en maakt daar incidenten of wijzigingsaanvragen van.</p>
      <p class="info-note">Per map worden maximaal 25 berichten per run verwerkt. Verwerkte berichten worden gemarkeerd als gelezen en geregistreerd, zodat ze niet dubbel worden geimporteerd.</p>
      <form method="post" class="form-actions">
        <button type="submit" class="btn-primary">Import nu uitvoeren</button>
      </form>
    </div>
  </div>

  <?php if ( !empty( $summary ) ): ?>
    <div class="results">
      <table class="results">
        <thead>
          <tr>
            <th>Map</th>
            <th>Taaksoort</th>
            <th>Geimporteerd</th>
            <th>Meldingen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $summary as $row ): ?>
            <tr>
              <td><?= htmlspecialchars($row['folder']) ?></td>
              <td><?= htmlspecialchars($row['tasktype']) ?></td>
              <td><?= htmlspecialchars((string)$row['result']['imported']) ?></td>
              <td>
                <?php if ( empty( $row['result']['errors'] ) ): ?>
                  Geen fouten.
                <?php else: ?>
                  <?= htmlspecialchars( implode( ' | ', $row['result']['errors'] ) ) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
