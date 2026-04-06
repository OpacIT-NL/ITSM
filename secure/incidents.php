<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION[ 'name' ];
incident_require_firstline_authorization( $con, $logged_in_user );
$operator_context = incident_get_operator_context( $con, $logged_in_user );
incident_require_access( $operator_context );

$view = $_GET['view'] ?? 'open';
$mode = incident_normalize_mode( $_GET['mode'] ?? '' );
$major_target = isset( $_GET['major_target'] ) && is_numeric( $_GET['major_target'] ) ? (int)$_GET['major_target'] : 0;
$problem_target = isset( $_GET['problem_target'] ) && is_numeric( $_GET['problem_target'] ) ? (int)$_GET['problem_target'] : 0;
$view_labels = [
  'open' => 'Open incidenten',
  'all' => 'Alle incidenten',
  'ready' => 'Gereede incidenten',
  'mine' => 'Incidenten op mijn naam',
  'minegroups' => 'Incidenten op mijn naam en groepen'
];
if ( !array_key_exists( $view, $view_labels ) ) {
  $view = 'open';
}

incident_store_list_location();

$where = [];
$params = [];
$types = '';

if ( $view === 'open' ) {
  $where[] = 'IFNULL(s.closed, 0) = 0';
} elseif ( $view === 'ready' ) {
  $where[] = 'IFNULL(s.ready, 0) = 1';
} elseif ( $view === 'mine' ) {
  $where[] = 'i.operatorid = ' . (int)$operator_context['id'];
} elseif ( $view === 'minegroups' ) {
  $group_ids = incident_collect_group_ids_for_operator( $con, (int)$operator_context['id'] );
  $clauses = [ 'i.operatorid = ' . (int)$operator_context['id'] ];
  if ( !empty( $group_ids ) ) {
    $clauses[] = 'i.operatorgroupid IN (' . implode( ',', array_map( 'intval', $group_ids ) ) . ')';
  }
  $where[] = '(' . implode( ' OR ', $clauses ) . ')';
}

if ( $mode !== '' ) {
  $where[] = "i.incidenttype = '" . mysqli_real_escape_string( $con, $mode ) . "'";
}

$page_title = $view_labels[$view];
if ( $mode !== '' ) {
  $page_title .= ' - ' . incident_mode_label( $mode );
}

$sql = "
    SELECT
      i.*,
      c.name AS customer_name,
      p.firstname AS person_firstname,
      p.lastname AS person_lastname,
      cat.name AS category_name,
      sub.name AS subcategory_name,
      a.objectid AS asset_objectid,
      s.name AS status_name,
      g.groupname,
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_im_incidents i
    LEFT JOIN itsm_ob_customers c ON i.customerid = c.id
    LEFT JOIN itsm_ob_persons p ON i.personid = p.id
    LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
    LEFT JOIN itsm_core_subcategory sub ON i.subcategoryid = sub.id
    LEFT JOIN itsm_am_assets a ON i.assetid = a.id
    LEFT JOIN itsm_core_status s ON i.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups g ON i.operatorgroupid = g.id
    LEFT JOIN itsm_ob_operators o ON i.operatorid = o.id
";
if ( !empty( $where ) ) {
  $sql .= ' WHERE ' . implode( ' AND ', $where );
}
$sql .= ' ORDER BY i.updatedat DESC, i.id DESC';

$stmt = mysqli_prepare( $con, $sql );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'im_menu.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($page_title) ?></h1>
  </center>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Nummer</th>
          <th style="text-align: start;">Klant</th>
          <th style="text-align: start;">Persoon</th>
          <th style="text-align: start;">Categorie</th>
          <th style="text-align: start;">Titel</th>
          <th style="text-align: start;">Status</th>
          <th style="text-align: start;">Groep</th>
          <th style="text-align: start;">Behandelaar</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
        <tr>
          <td><?= htmlspecialchars(incident_format_display_number($row)) ?></td>
          <td><?= htmlspecialchars($row['customer_name']) ?></td>
          <td><?= htmlspecialchars(trim(($row['person_lastname'] ?? '') . ', ' . ($row['person_firstname'] ?? ''), ', ')) ?></td>
          <td><?= htmlspecialchars($row['category_name']) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['status_name']) ?></td>
          <td><?= htmlspecialchars($row['groupname']) ?></td>
          <td><?= htmlspecialchars($row['operator_name']) ?></td>
          <td class="tblaction">
            <a class="btn" href="edit_incident.php?id=<?= $row['id'] ?>"> Open Incident </a>
            <?php if ( $major_target > 0 && $row['incidenttype'] !== 'major' && (int)$row['id'] !== $major_target ): ?>
            <a class="btn" href="edit_incident.php?id=<?= $row['id'] ?>&set_major=<?= $major_target ?>"> Koppel aan major </a>
            <?php endif; ?>
            <?php if ( $problem_target > 0 ): ?>
            <a class="btn" href="edit_incident.php?id=<?= $row['id'] ?>&set_problem=<?= $problem_target ?>"> Koppel aan problem </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
