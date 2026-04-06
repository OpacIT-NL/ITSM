<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );

$stmt = mysqli_prepare( $con, "
    SELECT a.*, t.type AS typename, s.name AS status_name
    FROM itsm_am_assets a
    LEFT JOIN itsm_am_types t ON a.type = t.id
    LEFT JOIN itsm_core_status s ON a.status = s.id
    WHERE a.owner = ?
    ORDER BY a.objectid ASC, a.id ASC
" );
mysqli_stmt_bind_param( $stmt, "i", $person['id'] );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );

ssp_page_title( 'Mijn assets' );
ssp_render_header( $person, 'assets' );
?>
<section class="ssp-page-head">
  <div>
    <h2>Mijn assets</h2>
    <p>Alle objecten die aan jouw persoon zijn gekoppeld.</p>
  </div>
</section>

<section class="ssp-table-wrap">
  <table class="ssp-table">
    <thead>
      <tr>
        <th>Object ID</th>
        <th>Type</th>
        <th>Status</th>
        <th>Startdatum</th>
        <th>Einddatum</th>
        <th>Prijs</th>
      </tr>
    </thead>
    <tbody>
      <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
      <tr>
        <td data-label="Object ID"><?= htmlspecialchars($row['objectid']) ?></td>
        <td data-label="Type"><?= htmlspecialchars($row['typename'] ?? '') ?></td>
        <td data-label="Status"><?= htmlspecialchars($row['status_name'] ?? '') ?></td>
        <td data-label="Startdatum"><?= htmlspecialchars($row['startdate'] ?? '') ?></td>
        <td data-label="Einddatum"><?= htmlspecialchars($row['enddate'] ?? '') ?></td>
        <td data-label="Prijs"><?= htmlspecialchars($row['price'] ?? '') ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <?php if ( mysqli_num_rows( $result ) === 0 ): ?>
  <div class="ssp-empty">Er zijn nog geen assets aan jouw persoon gekoppeld.</div>
  <?php endif; ?>
</section>
<?php
mysqli_stmt_close( $stmt );
ssp_render_footer();
?>
