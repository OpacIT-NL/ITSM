<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );
require_once( __DIR__ . '/include/pagination_helpers.php' );
require_once( __DIR__ . '/include/task_helpers.php' );
require_once( __DIR__ . '/include/task_log_helpers.php' );

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
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

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['link_major'] ) ) {
  $incident_id = (int)( $_POST['incident_id'] ?? 0 );
  $target_id = (int)( $_POST['major_target'] ?? 0 );
  $stmt = mysqli_prepare( $con, "
    UPDATE itsm_im_incidents source
    INNER JOIN itsm_im_incidents target ON target.id = ? AND target.incidenttype = 'major'
    SET source.majorincidentid = target.id
    WHERE source.id = ? AND source.incidenttype <> 'major'
  " );
  mysqli_stmt_bind_param( $stmt, 'ii', $target_id, $incident_id );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    itsm_fail( 'major_incident_link_failed', mysqli_stmt_error( $stmt ) );
  }
  $linked = mysqli_stmt_affected_rows( $stmt ) > 0;
  mysqli_stmt_close( $stmt );
  if ( !$linked ) {
    http_response_code( 404 );
    exit( 'Niet gevonden.' );
  }
  header( 'Location: edit_incident.php?id=' . $target_id );
  exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['link_problem'] ) ) {
  $incident_id = (int)( $_POST['incident_id'] ?? 0 );
  $target_id = (int)( $_POST['problem_target'] ?? 0 );
  $security_operator = itsm_current_operator_security_context( $con );
  itsm_require_operator_task_type_access( $security_operator, 'problem' );
  $incident_stmt = mysqli_prepare( $con, "SELECT id FROM itsm_im_incidents WHERE id = ? LIMIT 1" );
  mysqli_stmt_bind_param( $incident_stmt, 'i', $incident_id );
  mysqli_stmt_execute( $incident_stmt );
  $incident_row = mysqli_fetch_assoc( mysqli_stmt_get_result( $incident_stmt ) );
  mysqli_stmt_close( $incident_stmt );
  $problem_stmt = mysqli_prepare( $con, "SELECT id FROM itsm_pm_problems WHERE id = ? LIMIT 1" );
  mysqli_stmt_bind_param( $problem_stmt, 'i', $target_id );
  mysqli_stmt_execute( $problem_stmt );
  $problem_row = mysqli_fetch_assoc( mysqli_stmt_get_result( $problem_stmt ) );
  mysqli_stmt_close( $problem_stmt );
  if ( !$incident_row || !$problem_row ) {
    http_response_code( 404 );
    exit( 'Niet gevonden.' );
  }
  task_create_link( $con, 'incident', $incident_id, 'Behoort bij problem', 'problem', $target_id, (int)$operator_context['id'] );
  task_log_add( $con, 'incident', $incident_id, 'link_created', 'Incident gekoppeld aan problem #' . $target_id . '.', (int)$operator_context['id'] );
  header( 'Location: edit_problem.php?id=' . $target_id );
  exit;
}
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

if ( in_array( $view, [ 'mine', 'minegroups' ], true ) ) {
  $where[] = 'IFNULL(s.closed, 0) = 0';
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
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name,
      ic_preview.preview_comments
    FROM itsm_im_incidents i
    LEFT JOIN itsm_ob_customers c ON i.customerid = c.id
    LEFT JOIN itsm_ob_persons p ON i.personid = p.id
    LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
    LEFT JOIN itsm_core_subcategory sub ON i.subcategoryid = sub.id
    LEFT JOIN itsm_am_assets a ON i.assetid = a.id
    LEFT JOIN itsm_core_status s ON i.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups g ON i.operatorgroupid = g.id
    LEFT JOIN itsm_ob_operators o ON i.operatorid = o.id
    LEFT JOIN (
      SELECT incidentid, GROUP_CONCAT(commenttext ORDER BY createdat DESC SEPARATOR '\n\n') AS preview_comments
      FROM itsm_im_incidentcomments
      GROUP BY incidentid
    ) ic_preview ON ic_preview.incidentid = i.id
";
if ( !empty( $where ) ) {
  $sql .= ' WHERE ' . implode( ' AND ', $where );
}
$pagination = itsm_pagination_state( 100 );
$total_items = itsm_pagination_count( $con, $sql );
$sql .= ' ORDER BY i.updatedat DESC, i.id DESC';
$sql = itsm_pagination_limit_sql( $sql, $pagination );

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
          <?php if ( $major_target > 0 || $problem_target > 0 ): ?>
          <th style="text-align: start;">Actie</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
        <tr data-table-open-url="edit_incident.php?id=<?= htmlspecialchars((string)$row['id']) ?>" data-preview-description="<?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES) ?>" data-preview-comments="<?= htmlspecialchars((string)($row['preview_comments'] ?? ''), ENT_QUOTES) ?>">
          <td><?= htmlspecialchars(incident_format_display_number($row)) ?></td>
          <td><?= htmlspecialchars($row['customer_name']) ?></td>
          <td><?= htmlspecialchars(trim(($row['person_lastname'] ?? '') . ', ' . ($row['person_firstname'] ?? ''), ', ')) ?></td>
          <td><?= htmlspecialchars($row['category_name']) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['status_name']) ?></td>
          <td><?= htmlspecialchars($row['groupname']) ?></td>
          <td><?= htmlspecialchars($row['operator_name']) ?></td>
          <?php if ( $major_target > 0 || $problem_target > 0 ): ?>
          <td>
            <form method="post">
              <input type="hidden" name="incident_id" value="<?= (int)$row['id'] ?>">
              <?php if ( $major_target > 0 ): ?>
              <input type="hidden" name="major_target" value="<?= $major_target ?>">
              <button class="btn" type="submit" name="link_major" value="1">Koppelen</button>
              <?php else: ?>
              <input type="hidden" name="problem_target" value="<?= $problem_target ?>">
              <button class="btn" type="submit" name="link_problem" value="1">Koppelen</button>
              <?php endif; ?>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php itsm_render_pagination( $total_items, $pagination ); ?>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
