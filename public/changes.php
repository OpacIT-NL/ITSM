<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$view = $_GET['view'] ?? 'open';
$view_labels = [
  'open' => 'Open wijzigingen',
  'requests' => 'Open wijzigingsaanvragen',
  'all' => 'Alle wijzigingen'
];
if ( !isset( $view_labels[ $view ] ) ) {
  $view = 'open';
}

$sql = "
    SELECT c.*, cat.name AS category_name, s.name AS status_name
    FROM itsm_cm_changes c
    LEFT JOIN itsm_core_category cat ON c.categoryid = cat.id
    LEFT JOIN itsm_core_status s ON c.statusid = s.id
    WHERE c.personid = ?
";
if ( $view === 'open' ) {
  $sql .= " AND (c.approvalstate = 'request' OR (c.approvalstate = 'approved' AND c.closed = 0 AND IFNULL(s.closed, 0) = 0))";
} elseif ( $view === 'requests' ) {
  $sql .= " AND c.approvalstate = 'request'";
}
$sql .= " ORDER BY c.updatedat DESC, c.id DESC";

$stmt = mysqli_prepare( $con, $sql );
mysqli_stmt_bind_param( $stmt, "i", $person['id'] );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );

ssp_page_title( $view_labels[ $view ] );
ssp_render_header( $person, 'changes' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($view_labels[$view]) ?></h2>
    <p>Alle wijzigingsaanvragen en wijzigingen die bij jouw persoon horen.</p>
  </div>
  <a class="ssp-button" href="new_change.php"><i class="fa-solid fa-file-circle-plus"></i> Wijziging aanvragen</a>
</section>

<div class="ssp-filter-bar">
  <a class="ssp-chip<?= $view === 'open' ? ' is-active' : '' ?>" href="changes.php?view=open">Open</a>
  <a class="ssp-chip<?= $view === 'requests' ? ' is-active' : '' ?>" href="changes.php?view=requests">Aanvragen</a>
  <a class="ssp-chip<?= $view === 'all' ? ' is-active' : '' ?>" href="changes.php?view=all">Alle</a>
</div>

<section class="ssp-table-wrap">
  <table class="ssp-table">
    <thead>
      <tr>
        <th>Nummer</th>
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
        <td><?= htmlspecialchars($row['changenumber'] ?: ('#' . $row['id'])) ?></td>
        <td>
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
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['approvalstate'] === 'approved' ? ($row['status_name'] ?? '') : 'Aanvraag') ?></td>
        <td><?= htmlspecialchars($row['updatedat']) ?></td>
        <td><a class="ssp-table-action" href="view_change.php?id=<?= (int)$row['id'] ?>">Open</a></td>
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
