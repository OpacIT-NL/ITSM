<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/news_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}

function dashboard_fetch_count( $con, $sql, $types = '', $params = [] ) {
  $stmt = mysqli_prepare( $con, $sql );
  if ( !$stmt ) {
    return 0;
  }

  if ( $types !== '' && !empty( $params ) ) {
    mysqli_stmt_bind_param( $stmt, $types, ...$params );
  }

  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $count );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  return (int)$count;
}

$logged_in_user = $_SESSION['name'];
$sql = "
    SELECT
      id,
      isadmin,
      firstname,
      lastname,
      firstlineincidents,
      secondlineincidents,
      reqforchange,
      simplechange,
      extchange,
      problems,
      events,
      ubm
    FROM itsm_ob_operators
    WHERE username = ?
";
$stmt = mysqli_prepare( $con, $sql );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$operator ) {
  die( 'Behandelaar niet gevonden' );
}

$operator_id = (int)$operator['id'];
$firstname = $operator['firstname'];
$lastname = $operator['lastname'];
$isadmin = (int)$operator['isadmin'];

$group_ids = [];
$group_stmt = mysqli_prepare( $con, "SELECT groupid FROM itsm_ob_opgrouplinks WHERE operatorid = ?" );
mysqli_stmt_bind_param( $group_stmt, "i", $operator_id );
mysqli_stmt_execute( $group_stmt );
$group_result = mysqli_stmt_get_result( $group_stmt );
while ( $group_row = mysqli_fetch_assoc( $group_result ) ) {
  $group_ids[] = (int)$group_row['groupid'];
}
mysqli_stmt_close( $group_stmt );

$group_sql_list = !empty( $group_ids ) ? implode( ',', array_map( 'intval', $group_ids ) ) : '';
$news_items = news_fetch_items( $con, 'secure', 6 );
$incident_group_clause = 'i.operatorid = ' . $operator_id;
if ( $group_sql_list !== '' ) {
  $incident_group_clause .= ' OR i.operatorgroupid IN (' . $group_sql_list . ')';
}
$change_group_clause = '(c.operatorid = ' . $operator_id . ' OR c.coordinatorid = ' . $operator_id;
if ( $group_sql_list !== '' ) {
  $change_group_clause .= ' OR c.operatorgroupid IN (' . $group_sql_list . ')';
}
$change_group_clause .= ')';
$activity_group_clause = 'a.operatorid = ' . $operator_id;
if ( $group_sql_list !== '' ) {
  $activity_group_clause .= ' OR a.operatorgroupid IN (' . $group_sql_list . ')';
}
$problem_group_clause = 'p.operatorid = ' . $operator_id;
if ( $group_sql_list !== '' ) {
  $problem_group_clause .= ' OR p.operatorgroupid IN (' . $group_sql_list . ')';
}
$ubm_group_clause = 'u.operatorid = ' . $operator_id;
if ( $group_sql_list !== '' ) {
  $ubm_group_clause .= ' OR u.operatorgroupid IN (' . $group_sql_list . ')';
}

$task_rows = [];

if ( (int)$operator['firstlineincidents'] === 1 ) {
  $task_rows[] = [
    'label' => 'Eerstelijns incidenten',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_im_incidents i
       LEFT JOIN itsm_core_status s ON i.statusid = s.id
       WHERE i.incidenttype = 'firstline'
         AND IFNULL(s.closed, 0) = 0
         AND i.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './incidents.php?view=mine&mode=firstline',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_im_incidents i
       LEFT JOIN itsm_core_status s ON i.statusid = s.id
       WHERE i.incidenttype = 'firstline'
         AND IFNULL(s.closed, 0) = 0
         AND (" . $incident_group_clause . ")"
    ),
    'group_link' => './incidents.php?view=minegroups&mode=firstline'
  ];
}

if ( (int)$operator['secondlineincidents'] === 1 ) {
  $task_rows[] = [
    'label' => 'Tweedelijns incidenten',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_im_incidents i
       LEFT JOIN itsm_core_status s ON i.statusid = s.id
       WHERE i.incidenttype = 'secondline'
         AND IFNULL(s.closed, 0) = 0
         AND i.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './incidents.php?view=mine&mode=secondline',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_im_incidents i
       LEFT JOIN itsm_core_status s ON i.statusid = s.id
       WHERE i.incidenttype = 'secondline'
         AND IFNULL(s.closed, 0) = 0
         AND (" . $incident_group_clause . ")"
    ),
    'group_link' => './incidents.php?view=minegroups&mode=secondline'
  ];

  $task_rows[] = [
    'label' => 'Major incidenten',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_im_incidents i
       LEFT JOIN itsm_core_status s ON i.statusid = s.id
       WHERE i.incidenttype = 'major'
         AND IFNULL(s.closed, 0) = 0
         AND i.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './incidents.php?view=mine&mode=major',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_im_incidents i
       LEFT JOIN itsm_core_status s ON i.statusid = s.id
       WHERE i.incidenttype = 'major'
         AND IFNULL(s.closed, 0) = 0
         AND (" . $incident_group_clause . ")"
    ),
    'group_link' => './incidents.php?view=minegroups&mode=major'
  ];
}

if ( (int)$operator['reqforchange'] === 1 || (int)$operator['simplechange'] === 1 || (int)$operator['extchange'] === 1 ) {
  $task_rows[] = [
    'label' => 'Wijzigingsaanvragen',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c WHERE c.approvalstate = 'request' AND (c.operatorid = ? OR c.coordinatorid = ?)",
      'ii',
      [ $operator_id, $operator_id ]
    ),
    'mine_link' => './changes.php?section=requests&view=mine',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c WHERE c.approvalstate = 'request' AND " . $change_group_clause
    ),
    'group_link' => './changes.php?section=requests&view=minegroups'
  ];

  $task_rows[] = [
    'label' => 'Eenvoudige wijzigingen',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c
       LEFT JOIN itsm_core_status s ON c.statusid = s.id
       WHERE c.approvalstate = 'approved'
         AND c.requesttype = 'simple'
         AND c.closed = 0
         AND IFNULL(s.closed, 0) = 0
         AND c.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './changes.php?section=changes&view=mine&mode=simple',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c
       LEFT JOIN itsm_core_status s ON c.statusid = s.id
       WHERE c.approvalstate = 'approved'
         AND c.requesttype = 'simple'
         AND c.closed = 0
         AND IFNULL(s.closed, 0) = 0
         AND " . $change_group_clause
    ),
    'group_link' => './changes.php?section=changes&view=minegroups&mode=simple'
  ];

  $task_rows[] = [
    'label' => 'Uitgebreide wijzigingen',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c
       LEFT JOIN itsm_core_status s ON c.statusid = s.id
       WHERE c.approvalstate = 'approved'
         AND c.requesttype = 'extended'
         AND c.closed = 0
         AND IFNULL(s.closed, 0) = 0
         AND (c.operatorid = ? OR c.coordinatorid = ?)",
      'ii',
      [ $operator_id, $operator_id ]
    ),
    'mine_link' => './changes.php?section=changes&view=mine&mode=extended',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c
       LEFT JOIN itsm_core_status s ON c.statusid = s.id
       WHERE c.approvalstate = 'approved'
         AND c.requesttype = 'extended'
         AND c.closed = 0
         AND IFNULL(s.closed, 0) = 0
         AND " . $change_group_clause
    ),
    'group_link' => './changes.php?section=changes&view=minegroups&mode=extended'
  ];

  $task_rows[] = [
    'label' => 'Wijzigingsactiviteiten',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changeactivities a
       LEFT JOIN itsm_core_status s ON a.statusid = s.id
       WHERE IFNULL(s.closed, 0) = 0
         AND a.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './change_activities.php?view=mine',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changeactivities a
       LEFT JOIN itsm_core_status s ON a.statusid = s.id
       WHERE IFNULL(s.closed, 0) = 0
         AND (" . $activity_group_clause . ")"
    ),
    'group_link' => './change_activities.php?view=minegroups'
  ];
}

if ( (int)$operator['problems'] === 1 ) {
  $task_rows[] = [
    'label' => 'Problems',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_pm_problems p
       LEFT JOIN itsm_core_status s ON p.statusid = s.id
       WHERE IFNULL(s.closed, 0) = 0
         AND p.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './problems.php?view=mine',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*)
       FROM itsm_pm_problems p
       LEFT JOIN itsm_core_status s ON p.statusid = s.id
       WHERE IFNULL(s.closed, 0) = 0
         AND (" . $problem_group_clause . ")"
    ),
    'group_link' => './problems.php?view=minegroups'
  ];
}

if ( (int)$operator['events'] === 1 ) {
  $task_rows[] = [
    'label' => 'Events',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_em_events e WHERE e.closed = 0"
    ),
    'mine_link' => './events.php?view=open',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_em_events e WHERE e.closed = 0"
    ),
    'group_link' => './events.php?view=open'
  ];
}

if ( (int)$operator['ubm'] === 1 ) {
  $ubm_rows = [
    [ 'label' => 'UBM Initiatives', 'itemtype' => 'initiative', 'view' => 'initiatives', 'open_only' => true ],
    [ 'label' => 'UBM Epics', 'itemtype' => 'epic', 'view' => 'epics', 'open_only' => true ],
    [ 'label' => 'UBM Features', 'itemtype' => 'feature', 'view' => 'features', 'open_only' => true ],
    [ 'label' => 'UBM Stories', 'itemtype' => 'story', 'view' => 'stories', 'open_only' => true ],
    [ 'label' => 'UBM Subtasks', 'itemtype' => 'subtask', 'view' => 'subtasks', 'open_only' => true ]
  ];

  foreach ( $ubm_rows as $ubm_row ) {
    $mine_sql = "SELECT COUNT(*) FROM itsm_ubm_items u LEFT JOIN itsm_core_status s ON u.statusid = s.id WHERE u.itemtype = ? AND u.operatorid = ?";
    $mine_types = 'si';
    $mine_params = [ $ubm_row['itemtype'], $operator_id ];
    $group_sql = "SELECT COUNT(*) FROM itsm_ubm_items u LEFT JOIN itsm_core_status s ON u.statusid = s.id WHERE u.itemtype = ? AND (" . $ubm_group_clause . ")";
    $group_types = 's';
    $group_params = [ $ubm_row['itemtype'] ];

    if ( $ubm_row['open_only'] ) {
      $mine_sql .= ' AND IFNULL(s.closed, 0) = 0';
      $group_sql .= ' AND IFNULL(s.closed, 0) = 0';
    }

    $task_rows[] = [
      'label' => $ubm_row['label'],
      'mine_count' => dashboard_fetch_count( $con, $mine_sql, $mine_types, $mine_params ),
      'mine_link' => './ubm_items.php?view=' . urlencode( $ubm_row['view'] ) . '&ownership=mine',
      'group_count' => dashboard_fetch_count( $con, $group_sql, $group_types, $group_params ),
      'group_link' => './ubm_items.php?view=' . urlencode( $ubm_row['view'] ) . '&ownership=minegroups'
    ];
  }
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <h1>ITSM Dashboard - Welkom terug <?= htmlspecialchars($firstname . ' ' . $lastname) ?></h1>
  <div class="dashboard-home-grid">
    <div class="dashboard-left-column">
      <h2>Hoofdmenu</h2>
      <div class="quicklinks">
        <a href="./modules.php"> <i class="fa-solid fa-cubes-stacked fa-2xl"></i><br>
        <span>Modules</span> </a>
        <?php if ( $isadmin == 1 ): ?>
        <a href="./settings.php"> <i class="fa-solid fa-screwdriver-wrench fa-2xl"></i><br>
        <span>Instellingen</span> </a>
        <?php endif; ?>
        <a href="./profile.php"> <i class="fa-solid fa-user fa-2xl"></i><br>
        <span>Profiel</span> </a>
      </div>

      <section class="dashboard-task-section">
        <h2>Taken</h2>
        <div class="results incident-results dashboard-task-results">
          <table border="0" class="results incident-results-table dashboard-task-table">
            <thead>
              <tr>
                <th style="text-align: start;">Module</th>
                <th class="dashboard-task-count-heading" title="<?= htmlspecialchars(t('Op mijn naam')) ?>" aria-label="<?= htmlspecialchars(t('Op mijn naam')) ?>"><i class="fa-solid fa-user"></i></th>
                <th class="dashboard-task-count-heading" title="<?= htmlspecialchars(t('Mijn naam of groepen')) ?>" aria-label="<?= htmlspecialchars(t('Mijn naam of groepen')) ?>"><i class="fa-solid fa-users"></i></th>
              </tr>
            </thead>
            <tbody>
              <?php if ( empty( $task_rows ) ): ?>
              <tr>
                <td colspan="3">Geen takenmodules beschikbaar voor jouw account.</td>
              </tr>
              <?php else: ?>
              <?php foreach ( $task_rows as $row ): ?>
              <tr>
                <td data-label="Module"><?= htmlspecialchars($row['label']) ?></td>
                <td data-label="Op mijn naam"><a class="btn" href="<?= htmlspecialchars($row['mine_link']) ?>"><?= htmlspecialchars((string)$row['mine_count']) ?></a></td>
                <td data-label="Mijn naam of groepen"><a class="btn" href="<?= htmlspecialchars($row['group_link']) ?>"><?= htmlspecialchars((string)$row['group_count']) ?></a></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <section class="dashboard-news-section">
      <h2>Nieuws</h2>
      <div class="news-dashboard-panel">
        <?= news_render_cards( $news_items ) ?>
      </div>
    </section>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
