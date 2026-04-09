<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$view = $_GET['view'] ?? 'open';
$scope = ssp_scope_from_request( $person, $_GET['scope'] ?? 'mine' );
$view_labels = [
  'open' => 'Open incidenten',
  'all' => 'Alle incidenten'
];
if ( !isset( $view_labels[ $view ] ) ) {
  $view = 'open';
}

$sql = "
    SELECT i.*, cat.name AS category_name, s.name AS status_name, p.firstname, p.lastname
    FROM itsm_im_incidents i
    LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
    LEFT JOIN itsm_core_status s ON i.statusid = s.id
    LEFT JOIN itsm_ob_persons p ON i.personid = p.id
    WHERE " . ssp_incident_scope_clause( $scope ) . "
";
if ( $view === 'open' ) {
  $sql .= " AND IFNULL(s.closed, 0) = 0";
}
$sql .= " ORDER BY i.updatedat DESC, i.id DESC";

$stmt = mysqli_prepare( $con, $sql );
$scope_id = $scope === 'customer' ? (int)$person['customerid'] : (int)$person['id'];
mysqli_stmt_bind_param( $stmt, "i", $scope_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );

ssp_page_title( $view_labels[ $view ] );
ssp_render_header( $person, 'incidents' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($view_labels[$view]) ?></h2>
    <p><?= htmlspecialchars($scope === 'customer' ? 'Alle meldingen van alle personen van jouw klant.' : 'Alle meldingen die op jouw naam staan.') ?></p>
  </div>
  <a class="ssp-button" href="new_incident.php"><i class="fa-solid fa-phone"></i> Incident melden</a>
</section>

<div class="ssp-filter-bar">
  <a class="ssp-chip<?= $view === 'open' ? ' is-active' : '' ?>" href="incidents.php?view=open&amp;scope=<?= htmlspecialchars($scope) ?>">Open</a>
  <a class="ssp-chip<?= $view === 'all' ? ' is-active' : '' ?>" href="incidents.php?view=all&amp;scope=<?= htmlspecialchars($scope) ?>">Alle</a>
  <?php if ( ssp_person_is_manager( $person ) ): ?>
  <a class="ssp-chip<?= $scope === 'mine' ? ' is-active' : '' ?>" href="incidents.php?view=<?= htmlspecialchars($view) ?>&amp;scope=mine">Mijn incidenten</a>
  <a class="ssp-chip<?= $scope === 'customer' ? ' is-active' : '' ?>" href="incidents.php?view=<?= htmlspecialchars($view) ?>&amp;scope=customer">Klantincidenten</a>
  <?php endif; ?>
</div>

<?php if ( isset( $_GET['access_denied'] ) ): ?>
<div class="ssp-error">Je hebt geen toegang tot die melding.</div>
<?php endif; ?>

<section class="ssp-table-wrap">
  <table class="ssp-table">
    <thead>
      <tr>
        <th>Nummer</th>
        <th>Persoon</th>
        <th>Titel</th>
        <th>Categorie</th>
        <th>Status</th>
        <th>Bijgewerkt</th>
        <th>Actie</th>
      </tr>
    </thead>
    <tbody>
      <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
      <tr>
        <td data-label="Nummer"><?= htmlspecialchars($row['incidentnumber'] ?: ('#' . $row['id'])) ?></td>
        <td data-label="Persoon"><?= htmlspecialchars(trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''))) ?></td>
        <td data-label="Titel"><?= htmlspecialchars($row['title']) ?></td>
        <td data-label="Categorie"><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
        <td data-label="Status"><?= htmlspecialchars($row['status_name'] ?? '') ?></td>
        <td data-label="Bijgewerkt"><?= htmlspecialchars($row['updatedat']) ?></td>
        <td data-label="Actie"><a class="ssp-table-action" href="view_incident.php?id=<?= (int)$row['id'] ?>">Open</a></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <?php if ( mysqli_num_rows( $result ) === 0 ): ?>
  <div class="ssp-empty">Er zijn geen incidenten gevonden voor deze selectie.</div>
  <?php endif; ?>
</section>
<?php
mysqli_stmt_close( $stmt );
ssp_render_footer();
?>
