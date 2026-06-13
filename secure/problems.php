<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/problem_helpers.php' );

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
$operator_context = problem_get_operator_context( $con, $logged_in_user );
problem_require_access( $operator_context );

$view = $_GET['view'] ?? 'open';
$view_labels = [
  'open' => 'Open problemen',
  'all' => 'Alle problemen',
  'ready' => 'Gereede problemen',
  'mine' => 'Problemen op mijn naam',
  'minegroups' => 'Problemen op mijn naam en groepen'
];
if ( !isset( $view_labels[$view] ) ) {
  $view = 'open';
}

problem_store_list_location();

$where = [];
if ( $view === 'open' ) {
  $where[] = 'IFNULL(s.closed, 0) = 0';
} elseif ( $view === 'ready' ) {
  $where[] = 'IFNULL(s.ready, 0) = 1';
} elseif ( $view === 'mine' ) {
  $where[] = 'p.operatorid = ' . (int)$operator_context['id'];
} elseif ( $view === 'minegroups' ) {
  $group_ids = problem_collect_group_ids_for_operator( $con, (int)$operator_context['id'] );
  $clauses = [ 'p.operatorid = ' . (int)$operator_context['id'] ];
  if ( !empty( $group_ids ) ) {
    $clauses[] = 'p.operatorgroupid IN (' . implode( ',', array_map( 'intval', $group_ids ) ) . ')';
  }
  $where[] = '(' . implode( ' OR ', $clauses ) . ')';
}

if ( in_array( $view, [ 'mine', 'minegroups' ], true ) ) {
  $where[] = 'IFNULL(s.closed, 0) = 0';
}

$sql = "
    SELECT
      p.*,
      c.name AS customer_name,
      CONCAT(pr.lastname, ', ', pr.firstname) AS person_name,
      cat.name AS category_name,
      s.name AS status_name,
      g.groupname,
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_pm_problems p
    LEFT JOIN itsm_ob_customers c ON p.customerid = c.id
    LEFT JOIN itsm_ob_persons pr ON p.personid = pr.id
    LEFT JOIN itsm_core_category cat ON p.categoryid = cat.id
    LEFT JOIN itsm_core_status s ON p.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups g ON p.operatorgroupid = g.id
    LEFT JOIN itsm_ob_operators o ON p.operatorid = o.id
";
if ( !empty( $where ) ) {
  $sql .= ' WHERE ' . implode( ' AND ', $where );
}
$sql .= ' ORDER BY p.updatedat DESC, p.id DESC';

$result = mysqli_query( $con, $sql );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'pm-menu.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($view_labels[$view]) ?></h1>
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
          <td><?= htmlspecialchars(problem_format_display_number($row)) ?></td>
          <td><?= htmlspecialchars($row['customer_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['person_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['status_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['groupname'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['operator_name'] ?? '') ?></td>
          <td class="tblaction"><a class="btn" href="edit_problem.php?id=<?= htmlspecialchars((string)$row['id']) ?>">Open probleem</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
