<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$view = $_GET['view'] ?? 'open';
$view_labels = [
  'open' => 'Open incidenten',
  'all' => 'Alle incidenten'
];
if ( !isset( $view_labels[ $view ] ) ) {
  $view = 'open';
}

$sql = "
    SELECT i.*, cat.name AS category_name, s.name AS status_name
    FROM itsm_im_incidents i
    LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
    LEFT JOIN itsm_core_status s ON i.statusid = s.id
    WHERE i.personid = ?
";
if ( $view === 'open' ) {
  $sql .= " AND IFNULL(s.closed, 0) = 0";
}
$sql .= " ORDER BY i.updatedat DESC, i.id DESC";

$stmt = mysqli_prepare( $con, $sql );
mysqli_stmt_bind_param( $stmt, "i", $person['id'] );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );

ssp_page_title( $view_labels[ $view ] );
ssp_render_header( $person, 'incidents' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($view_labels[$view]) ?></h2>
    <p>Alle meldingen die op jouw naam staan.</p>
  </div>
  <a class="ssp-button" href="new_incident.php"><i class="fa-solid fa-phone"></i> Incident melden</a>
</section>

<div class="ssp-filter-bar">
  <a class="ssp-chip<?= $view === 'open' ? ' is-active' : '' ?>" href="incidents.php?view=open">Open</a>
  <a class="ssp-chip<?= $view === 'all' ? ' is-active' : '' ?>" href="incidents.php?view=all">Alle</a>
</div>

<section class="ssp-table-wrap">
  <table class="ssp-table">
    <thead>
      <tr>
        <th>Nummer</th>
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
        <td><?= htmlspecialchars($row['incidentnumber'] ?: ('#' . $row['id'])) ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['status_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['updatedat']) ?></td>
        <td><a class="ssp-table-action" href="view_incident.php?id=<?= (int)$row['id'] ?>">Open</a></td>
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
