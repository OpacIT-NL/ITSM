<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );
require_once( __DIR__ . '/include/pagination_helpers.php' );

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
$operator_context = change_get_operator_context( $con, $logged_in_user );
change_require_access( $operator_context );

$section = $_GET['section'] ?? 'changes';
$view = $_GET['view'] ?? 'open';
$mode = $_GET['mode'] ?? '';

$section_labels = [
  'requests' => 'Wijzigingsaanvragen',
  'changes' => 'Wijzigingen'
];
$view_labels = [
  'requests' => [
    'open' => 'Open wijzigingsaanvragen',
    'rejected' => 'Afgewezen wijzigingsaanvragen',
    'all' => 'Alle wijzigingsaanvragen',
    'mine' => 'Wijzigingsaanvragen op mijn naam',
    'minegroups' => 'Wijzigingsaanvragen op mijn naam of groepen'
  ],
  'changes' => [
    'open' => 'Open wijzigingen',
    'all' => 'Alle wijzigingen',
    'mine' => 'Wijzigingen op mijn naam',
    'minegroups' => 'Wijzigingen op mijn naam of groepen'
  ]
];

if ( !array_key_exists( $section, $section_labels ) ) {
  $section = 'changes';
}
if ( !isset( $view_labels[$section][$view] ) ) {
  $view = 'open';
}
if ( !in_array( $mode, [ '', 'simple', 'extended' ], true ) ) {
  $mode = '';
}

change_store_list_location();

$where = [];
$group_ids = change_collect_group_ids_for_operator( $con, (int)$operator_context['id'] );
$mine_or_groups_clauses = [ 'c.operatorid = ' . (int)$operator_context['id'], 'c.coordinatorid = ' . (int)$operator_context['id'] ];
if ( !empty( $group_ids ) ) {
  $mine_or_groups_clauses[] = 'c.operatorgroupid IN (' . implode( ',', array_map( 'intval', $group_ids ) ) . ')';
}

if ( $section === 'requests' ) {
  $where[] = "c.approvalstate IN ('request', 'rejected')";

  if ( $view === 'open' ) {
    $where[] = "c.approvalstate = 'request'";
    $where[] = "c.closed = 0";
  } elseif ( $view === 'rejected' ) {
    $where[] = "c.approvalstate = 'rejected'";
  } elseif ( $view === 'mine' ) {
    $where[] = "c.approvalstate = 'request'";
    $where[] = "c.closed = 0";
    $where[] = "(c.operatorid = " . (int)$operator_context['id'] . " OR c.coordinatorid = " . (int)$operator_context['id'] . ")";
  } elseif ( $view === 'minegroups' ) {
    $where[] = "c.approvalstate = 'request'";
    $where[] = "c.closed = 0";
    $where[] = '(' . implode( ' OR ', $mine_or_groups_clauses ) . ')';
  }
} else {
  $where[] = "c.approvalstate = 'approved'";

  if ( $view === 'open' ) {
    $where[] = "c.closed = 0";
    $where[] = "IFNULL(s.closed, 0) = 0";
  } elseif ( $view === 'mine' ) {
    $where[] = "c.closed = 0";
    $where[] = "IFNULL(s.closed, 0) = 0";
    $where[] = "(c.operatorid = " . (int)$operator_context['id'] . " OR c.coordinatorid = " . (int)$operator_context['id'] . ")";
  } elseif ( $view === 'minegroups' ) {
    $where[] = "c.closed = 0";
    $where[] = "IFNULL(s.closed, 0) = 0";
    $where[] = '(' . implode( ' OR ', $mine_or_groups_clauses ) . ')';
  }
}

if ( $section === 'changes' && $mode !== '' ) {
  $where[] = "c.requesttype = '" . mysqli_real_escape_string( $con, $mode ) . "'";
}

$sql = "
    SELECT
      c.*,
      cust.name AS customer_name,
      per.firstname AS person_firstname,
      per.lastname AS person_lastname,
      cat.name AS category_name,
      s.name AS status_name,
      grp.groupname,
      CONCAT(op.lastname, ', ', op.firstname) AS operator_name,
      CONCAT(coord.lastname, ', ', coord.firstname) AS coordinator_name,
      cc_preview.preview_comments
    FROM itsm_cm_changes c
    LEFT JOIN itsm_ob_customers cust ON c.customerid = cust.id
    LEFT JOIN itsm_ob_persons per ON c.personid = per.id
    LEFT JOIN itsm_core_category cat ON c.categoryid = cat.id
    LEFT JOIN itsm_core_status s ON c.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups grp ON c.operatorgroupid = grp.id
    LEFT JOIN itsm_ob_operators op ON c.operatorid = op.id
    LEFT JOIN itsm_ob_operators coord ON c.coordinatorid = coord.id
    LEFT JOIN (
      SELECT changeid, GROUP_CONCAT(commenttext ORDER BY createdat DESC SEPARATOR '\n\n') AS preview_comments
      FROM itsm_cm_changecomments
      GROUP BY changeid
    ) cc_preview ON cc_preview.changeid = c.id
";
if ( !empty( $where ) ) {
  $sql .= ' WHERE ' . implode( ' AND ', $where );
}
$pagination = itsm_pagination_state( 100 );
$total_items = itsm_pagination_count( $con, $sql );
$sql .= ' ORDER BY c.updatedat DESC, c.id DESC';
$sql = itsm_pagination_limit_sql( $sql, $pagination );

$page_title = $view_labels[$section][$view];
if ( $section === 'changes' && $mode !== '' ) {
  $page_title .= ' - ' . change_request_type_label( $mode );
}

$result = mysqli_query( $con, $sql );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'cm-menu.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($page_title) ?></h1>
  </center>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Nummer</th>
          <th style="text-align: start;">Fase</th>
          <th style="text-align: start;">Klant</th>
          <th style="text-align: start;">Persoon</th>
          <th style="text-align: start;">Categorie</th>
          <th style="text-align: start;">Titel</th>
          <th style="text-align: start;">Status</th>
          <th style="text-align: start;">Groep</th>
          <th style="text-align: start;">Eigenaar</th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
        <tr data-table-open-url="edit_change.php?id=<?= htmlspecialchars((string)$row['id']) ?>" data-preview-description="<?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES) ?>" data-preview-comments="<?= htmlspecialchars((string)($row['preview_comments'] ?? ''), ENT_QUOTES) ?>">
          <td><?= htmlspecialchars(change_format_display_number($row)) ?></td>
          <td><?= htmlspecialchars(change_approval_state_label($row)) ?></td>
          <td><?= htmlspecialchars($row['customer_name']) ?></td>
          <td><?= htmlspecialchars(trim(($row['person_lastname'] ?? '') . ', ' . ($row['person_firstname'] ?? ''), ', ')) ?></td>
          <td><?= htmlspecialchars($row['category_name']) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['approvalstate'] === 'approved' ? ($row['status_name'] ?? '') : '-') ?></td>
          <td><?= htmlspecialchars($row['groupname']) ?></td>
          <td><?= htmlspecialchars($row['requesttype'] === 'extended' ? ($row['coordinator_name'] ?? '') : ($row['operator_name'] ?? '')) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php itsm_render_pagination( $total_items, $pagination ); ?>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
