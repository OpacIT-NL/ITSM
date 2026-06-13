<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );
require_once( __DIR__ . '/include/pagination_helpers.php' );

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
$operator_context = change_get_operator_context( $con, $logged_in_user );
change_require_access( $operator_context );

$view = $_GET['view'] ?? 'mine';
$view_labels = [
  'mine' => 'Wijzigingsactiviteiten op mijn naam',
  'minegroups' => 'Wijzigingsactiviteiten op mijn naam of groepen'
];
if ( !isset( $view_labels[$view] ) ) {
  $view = 'mine';
}

$group_ids = change_collect_group_ids_for_operator( $con, (int)$operator_context['id'] );
$where = [];
if ( $view === 'mine' ) {
  $where[] = 'a.operatorid = ' . (int)$operator_context['id'];
} else {
  $clauses = [ 'a.operatorid = ' . (int)$operator_context['id'] ];
  if ( !empty( $group_ids ) ) {
    $clauses[] = 'a.operatorgroupid IN (' . implode( ',', array_map( 'intval', $group_ids ) ) . ')';
  }
  $where[] = '(' . implode( ' OR ', $clauses ) . ')';
}
$where[] = 'IFNULL(s.closed, 0) = 0';

$sql = "
    SELECT
      a.*,
      c.id AS change_id,
      c.changenumber,
      c.title AS change_title,
      s.name AS status_name,
      g.groupname,
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name,
      ac_preview.preview_comments
    FROM itsm_cm_changeactivities a
    INNER JOIN itsm_cm_changes c ON a.changeid = c.id
    LEFT JOIN itsm_core_status s ON a.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups g ON a.operatorgroupid = g.id
    LEFT JOIN itsm_ob_operators o ON a.operatorid = o.id
    LEFT JOIN (
      SELECT changeactivityid, GROUP_CONCAT(commenttext ORDER BY createdat DESC SEPARATOR '\n\n') AS preview_comments
      FROM itsm_cm_changeactivitycomments
      GROUP BY changeactivityid
    ) ac_preview ON ac_preview.changeactivityid = a.id
";
if ( !empty( $where ) ) {
  $sql .= ' WHERE ' . implode( ' AND ', $where );
}
$pagination = itsm_pagination_state( 100 );
$total_items = itsm_pagination_count( $con, $sql );
$sql .= ' ORDER BY a.updatedat DESC, a.id DESC';
$sql = itsm_pagination_limit_sql( $sql, $pagination );

$result = mysqli_query( $con, $sql );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'cm-menu.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($view_labels[$view]) ?></h1>
  </center>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Nummer</th>
          <th style="text-align: start;">Wijziging</th>
          <th style="text-align: start;">Activiteit</th>
          <th style="text-align: start;">Status</th>
          <th style="text-align: start;">Groep</th>
          <th style="text-align: start;">Behandelaar</th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
        <tr data-table-open-url="edit_change_activity.php?id=<?= htmlspecialchars((string)$row['id']) ?>" data-preview-description="<?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES) ?>" data-preview-comments="<?= htmlspecialchars((string)($row['preview_comments'] ?? ''), ENT_QUOTES) ?>">
          <td><?= htmlspecialchars(change_format_activity_number($row)) ?></td>
          <td><?= htmlspecialchars(change_format_display_number($row)) ?> <?= htmlspecialchars($row['change_title']) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['status_name']) ?></td>
          <td><?= htmlspecialchars($row['groupname']) ?></td>
          <td><?= htmlspecialchars($row['operator_name']) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php itsm_render_pagination( $total_items, $pagination ); ?>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
