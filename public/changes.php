<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$view = $_GET['view'] ?? 'open';
$scope = ssp_scope_from_request( $person, $_GET['scope'] ?? 'mine' );
$view_labels = [
  'open' => 'Open wijzigingen',
  'requests' => 'Open wijzigingsaanvragen',
  'all' => 'Alle wijzigingen'
];
if ( !isset( $view_labels[ $view ] ) ) {
  $view = 'open';
}

$sql = "
    SELECT c.*, cat.name AS category_name, s.name AS status_name, p.firstname, p.lastname
    FROM itsm_cm_changes c
    LEFT JOIN itsm_core_category cat ON c.categoryid = cat.id
    LEFT JOIN itsm_core_status s ON c.statusid = s.id
    LEFT JOIN itsm_ob_persons p ON c.personid = p.id
    WHERE " . ssp_change_scope_clause( $scope ) . "
";
if ( $view === 'open' ) {
  $sql .= " AND (c.approvalstate = 'request' OR (c.approvalstate = 'approved' AND c.closed = 0 AND IFNULL(s.closed, 0) = 0))";
} elseif ( $view === 'requests' ) {
  $sql .= " AND c.approvalstate = 'request'";
}
$sql .= " ORDER BY c.updatedat DESC, c.id DESC";

$stmt = mysqli_prepare( $con, $sql );
$scope_id = $scope === 'customer' ? (int)$person['customerid'] : (int)$person['id'];
mysqli_stmt_bind_param( $stmt, "i", $scope_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );

ssp_page_title( $view_labels[ $view ] );
ssp_render_header( $person, 'changes' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($view_labels[$view]) ?></h2>
    <p><?= htmlspecialchars($scope === 'customer' ? 'Alle wijzigingsaanvragen en wijzigingen van alle personen van jouw klant.' : 'Alle wijzigingsaanvragen en wijzigingen die bij jouw persoon horen.') ?></p>
  </div>
  <a class="ssp-button" href="new_change.php"><i class="fa-solid fa-file-circle-plus"></i> Wijziging aanvragen</a>
</section>

<div class="ssp-filter-bar">
  <a class="ssp-chip<?= $view === 'open' ? ' is-active' : '' ?>" href="changes.php?view=open&amp;scope=<?= htmlspecialchars($scope) ?>">Open</a>
  <a class="ssp-chip<?= $view === 'requests' ? ' is-active' : '' ?>" href="changes.php?view=requests&amp;scope=<?= htmlspecialchars($scope) ?>">Aanvragen</a>
  <a class="ssp-chip<?= $view === 'all' ? ' is-active' : '' ?>" href="changes.php?view=all&amp;scope=<?= htmlspecialchars($scope) ?>">Alle</a>
  <?php if ( ssp_person_is_manager( $person ) ): ?>
  <a class="ssp-chip<?= $scope === 'mine' ? ' is-active' : '' ?>" href="changes.php?view=<?= htmlspecialchars($view) ?>&amp;scope=mine">Mijn wijzigingen</a>
  <a class="ssp-chip<?= $scope === 'customer' ? ' is-active' : '' ?>" href="changes.php?view=<?= htmlspecialchars($view) ?>&amp;scope=customer">Klantwijzigingen</a>
  <?php endif; ?>
</div>

<section class="ssp-table-wrap">
  <table class="ssp-table">
    <thead>
      <tr>
        <th>Nummer</th>
        <th>Persoon</th>
        <th>Fase</th>
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
        <td data-label="Nummer"><?= htmlspecialchars($row['changenumber'] ?: ('#' . $row['id'])) ?></td>
        <td data-label="Persoon"><?= htmlspecialchars(trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''))) ?></td>
        <td data-label="Fase">
          <?php
          if ( $row['approvalstate'] === 'request' ) {
            echo 'Wijzigingsaanvraag';
          } elseif ( $row['approvalstate'] === 'rejected' ) {
            echo 'Afgewezen';
          } else {
            echo htmlspecialchars( $row['requesttype'] === 'extended' ? 'Uitgebreide Wijziging' : 'Eenvoudige Wijziging' );
          }
          ?>
        </td>
        <td data-label="Titel"><?= htmlspecialchars($row['title']) ?></td>
        <td data-label="Categorie"><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
        <td data-label="Status"><?= htmlspecialchars($row['approvalstate'] === 'approved' ? ($row['status_name'] ?? '') : 'Aanvraag') ?></td>
        <td data-label="Bijgewerkt"><?= htmlspecialchars($row['updatedat']) ?></td>
        <td data-label="Actie"><a class="ssp-table-action" href="view_change.php?id=<?= (int)$row['id'] ?>">Open</a></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <?php if ( mysqli_num_rows( $result ) === 0 ): ?>
  <div class="ssp-empty">Er zijn geen wijzigingen gevonden voor deze selectie.</div>
  <?php endif; ?>
</section>
<?php
mysqli_stmt_close( $stmt );
ssp_render_footer();
?>
