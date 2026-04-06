<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/event_helpers.php' );

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
$operator_context = event_get_operator_context( $con, $logged_in_user );
event_require_access( $operator_context );

$view = $_GET['view'] ?? 'open';
$labels = [
  'open' => 'Open events',
  'all' => 'Alle events',
  'closed' => 'Gesloten events',
  'mine' => 'Events op mijn naam',
  'minegroups' => 'Events op mijn naam en groepen'
];
if ( !isset( $labels[$view] ) ) {
  $view = 'open';
}

event_store_list_location();

$where = [];
if ( $view === 'open' ) {
  $where[] = 'e.closed = 0';
} elseif ( $view === 'closed' ) {
  $where[] = 'e.closed = 1';
} elseif ( $view === 'mine' ) {
  $where[] = 'e.createdby = ' . (int)$operator_context['id'];
} elseif ( $view === 'minegroups' ) {
  // Events do not have an assignee/group model yet, so fall back to the creator.
  $where[] = 'e.createdby = ' . (int)$operator_context['id'];
}

$sql = "
    SELECT
      e.*,
      cat.name AS category_name,
      sub.name AS subcategory_name,
      a.objectid,
      i.incidentnumber
    FROM itsm_em_events e
    LEFT JOIN itsm_core_category cat ON e.categoryid = cat.id
    LEFT JOIN itsm_core_subcategory sub ON e.subcategoryid = sub.id
    LEFT JOIN itsm_am_assets a ON e.assetid = a.id
    LEFT JOIN itsm_im_incidents i ON e.incidentid = i.id
";
if ( !empty( $where ) ) {
  $sql .= ' WHERE ' . implode( ' AND ', $where );
}
$sql .= ' ORDER BY e.updatedat DESC, e.id DESC';

$result = mysqli_query( $con, $sql );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'em-menu.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($labels[$view]) ?></h1>
  </center>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Nummer</th>
          <th style="text-align: start;">Categorie</th>
          <th style="text-align: start;">Subcategorie</th>
          <th style="text-align: start;">Object</th>
          <th style="text-align: start;">Omschrijving</th>
          <th style="text-align: start;">Incident</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
        <tr>
          <td><?= htmlspecialchars(event_format_display_number($row)) ?></td>
          <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['subcategory_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['objectid'] ?? '') ?></td>
          <td><?= htmlspecialchars(( function_exists( 'mb_strimwidth' ) ? mb_strimwidth( (string)$row['description'], 0, 120, '...' ) : substr( (string)$row['description'], 0, 120 ) )) ?></td>
          <td><?= htmlspecialchars($row['incidentnumber'] ?? '') ?></td>
          <td class="tblaction"><a class="btn" href="edit_event.php?id=<?= htmlspecialchars((string)$row['id']) ?>">Open event</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
