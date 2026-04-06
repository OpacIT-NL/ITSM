<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/ubm_helpers.php' );

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
$operator_context = ubm_get_operator_context( $con, $logged_in_user );
ubm_require_access( $operator_context );
ubm_store_list_location();

$view = $_GET['view'] ?? 'initiatives';
$parent_id = isset( $_GET['parent'] ) && is_numeric( $_GET['parent'] ) ? (int)$_GET['parent'] : 0;
$group_id = isset( $_GET['groupid'] ) && is_numeric( $_GET['groupid'] ) ? (int)$_GET['groupid'] : 0;
$ownership = $_GET['ownership'] ?? 'all';
$group_ids = ubm_collect_group_ids_for_operator( $con, (int)$operator_context['id'] );
$reference_data = ubm_load_reference_data( $con );

$where = [];
$page_title = 'UBM';
$ownership_labels = [
  'all' => '',
  'mine' => ' op mijn naam',
  'minegroups' => ' op mijn naam en groepen'
];

if ( !isset( $ownership_labels[$ownership] ) ) {
  $ownership = 'all';
}

$ownership_clauses = [ 'u.operatorid = ' . (int)$operator_context['id'] ];
if ( !empty( $group_ids ) ) {
  $ownership_clauses[] = 'u.operatorgroupid IN (' . implode( ',', array_map( 'intval', $group_ids ) ) . ')';
}

if ( $parent_id > 0 ) {
  $where[] = 'u.parentid = ' . $parent_id;
  $parent_result = mysqli_query( $con, "SELECT id, itemtype, title FROM itsm_ubm_items WHERE id = " . $parent_id . " LIMIT 1" );
  $parent_item = mysqli_fetch_assoc( $parent_result );
  $page_title = $parent_item ? 'Child-items onder ' . ubm_type_label( $parent_item['itemtype'] ) . ': ' . $parent_item['title'] : 'Child-items';
} elseif ( $view === 'initiatives' ) {
  $where[] = "u.itemtype = 'initiative'";
  $page_title = 'Initiatives' . $ownership_labels[$ownership];
} elseif ( $view === 'epics' ) {
  $where[] = "u.itemtype = 'epic'";
  $where[] = 'IFNULL(s.closed, 0) = 0';
  $page_title = 'Open epics' . $ownership_labels[$ownership];
} elseif ( $view === 'features' ) {
  $where[] = "u.itemtype = 'feature'";
  $where[] = 'IFNULL(s.closed, 0) = 0';
  $page_title = 'Open features' . $ownership_labels[$ownership];
} elseif ( $view === 'stories' ) {
  $where[] = "u.itemtype = 'story'";
  $where[] = 'IFNULL(s.closed, 0) = 0';
  if ( $group_id > 0 ) {
    $where[] = 'u.operatorgroupid = ' . $group_id;
  }
  $page_title = 'Open stories per team' . $ownership_labels[$ownership];
} elseif ( $view === 'subtasks' ) {
  $where[] = "u.itemtype = 'subtask'";
  $where[] = 'IFNULL(s.closed, 0) = 0';
  $page_title = 'Open subtasks' . $ownership_labels[$ownership];
} elseif ( $view === 'mywork' ) {
  $where[] = "u.itemtype = 'subtask'";
  $where[] = '(' . implode( ' OR ', $ownership_clauses ) . ')';
  $where[] = 'IFNULL(s.closed, 0) = 0';
  $page_title = 'Mijn Werk';
} else {
  $where[] = "u.itemtype = 'initiative'";
  $page_title = 'Initiatives' . $ownership_labels[$ownership];
}

if ( $parent_id === 0 ) {
  if ( $ownership === 'mine' ) {
    $where[] = 'u.operatorid = ' . (int)$operator_context['id'];
  } elseif ( $ownership === 'minegroups' ) {
    $where[] = '(' . implode( ' OR ', $ownership_clauses ) . ')';
  }
}

$sql = "
    SELECT
      u.*,
      c.name AS category_name,
      sub.name AS subcategory_name,
      s.name AS status_name,
      g.groupname,
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name,
      p.title AS parent_title
    FROM itsm_ubm_items u
    LEFT JOIN itsm_core_category c ON u.categoryid = c.id
    LEFT JOIN itsm_core_subcategory sub ON u.subcategoryid = sub.id
    LEFT JOIN itsm_core_status s ON u.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups g ON u.operatorgroupid = g.id
    LEFT JOIN itsm_ob_operators o ON u.operatorid = o.id
    LEFT JOIN itsm_ubm_items p ON u.parentid = p.id
";
if ( !empty( $where ) ) {
  $sql .= ' WHERE ' . implode( ' AND ', $where );
}
$sql .= ' ORDER BY u.title ASC, u.id ASC';

$result = mysqli_query( $con, $sql );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'ubm-menu.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($page_title) ?></h1>
  </center>
  <?php if ( $view === 'stories' && $parent_id === 0 ): ?>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="get">
        <input type="hidden" name="view" value="stories">
        <input type="hidden" name="ownership" value="<?= htmlspecialchars($ownership) ?>">
        <label>Team</label>
        <select name="groupid" onchange="this.form.submit()">
          <option value="0">Alle teams</option>
          <?php foreach ( $reference_data['groups'] as $group ): ?>
          <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= $group_id === (int)$group['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['groupname']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>
  <br>
  <?php endif; ?>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Laag</th>
          <th style="text-align: start;">Titel</th>
          <th style="text-align: start;">Bovenliggend</th>
          <th style="text-align: start;">Categorie</th>
          <th style="text-align: start;">Subcategorie</th>
          <th style="text-align: start;">Status</th>
          <th style="text-align: start;">Team</th>
          <th style="text-align: start;">Behandelaar</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
        <tr>
          <td><?= htmlspecialchars(ubm_type_label($row['itemtype'])) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['parent_title'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['subcategory_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['status_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['groupname'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['operator_name'] ?? '') ?></td>
          <td class="tblaction">
            <a class="btn" href="edit_ubm_item.php?id=<?= htmlspecialchars((string)$row['id']) ?>">Open item</a>
            <?php if ( $row['itemtype'] !== 'subtask' ): ?>
            <a class="btn" href="ubm_items.php?parent=<?= htmlspecialchars((string)$row['id']) ?>">Child-items</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
