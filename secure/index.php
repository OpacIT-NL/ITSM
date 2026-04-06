<?php
session_start();

require_once( __DIR__ . '/../my.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  session_unset();
  session_destroy();
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
      extchange
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

$task_rows = [];

if ( (int)$operator['firstlineincidents'] === 1 ) {
  $task_rows[] = [
    'label' => 'Eerstelijns incidenten',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_im_incidents i WHERE i.incidenttype = 'firstline' AND i.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './incidents.php?view=mine&mode=firstline',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_im_incidents i WHERE i.incidenttype = 'firstline' AND (" . $incident_group_clause . ")"
    ),
    'group_link' => './incidents.php?view=minegroups&mode=firstline'
  ];
}

if ( (int)$operator['secondlineincidents'] === 1 ) {
  $task_rows[] = [
    'label' => 'Tweedelijns incidenten',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_im_incidents i WHERE i.incidenttype = 'secondline' AND i.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './incidents.php?view=mine&mode=secondline',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_im_incidents i WHERE i.incidenttype = 'secondline' AND (" . $incident_group_clause . ")"
    ),
    'group_link' => './incidents.php?view=minegroups&mode=secondline'
  ];

  $task_rows[] = [
    'label' => 'Major incidenten',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_im_incidents i WHERE i.incidenttype = 'major' AND i.operatorid = ?",
      'i',
      [ $operator_id ]
    ),
    'mine_link' => './incidents.php?view=mine&mode=major',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_im_incidents i WHERE i.incidenttype = 'major' AND (" . $incident_group_clause . ")"
    ),
    'group_link' => './incidents.php?view=minegroups&mode=major'
  ];
}

if ( (int)$operator['reqforchange'] === 1 || (int)$operator['simplechange'] === 1 || (int)$operator['extchange'] === 1 ) {
  $task_rows[] = [
    'label' => 'Wijzigingsaanvragen',
    'mine_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c WHERE c.approvalstate IN ('request', 'rejected') AND (c.operatorid = ? OR c.coordinatorid = ?)",
      'ii',
      [ $operator_id, $operator_id ]
    ),
    'mine_link' => './changes.php?section=requests&view=mine',
    'group_count' => dashboard_fetch_count(
      $con,
      "SELECT COUNT(*) FROM itsm_cm_changes c WHERE c.approvalstate IN ('request', 'rejected') AND " . $change_group_clause
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
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <h1>ITSM Dashboard - Welkom terug <?= htmlspecialchars($firstname . ' ' . $lastname) ?></h1>
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

  <br>
  <h2>Taken</h2>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%; max-width: 920px;">
      <thead>
        <tr>
          <th style="text-align: start;">Module</th>
          <th style="text-align: start;">Op mijn naam</th>
          <th style="text-align: start;">Mijn naam of groepen</th>
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
          <td><?= htmlspecialchars($row['label']) ?></td>
          <td><a class="btn" href="<?= htmlspecialchars($row['mine_link']) ?>"><?= htmlspecialchars((string)$row['mine_count']) ?></a></td>
          <td><a class="btn" href="<?= htmlspecialchars($row['group_link']) ?>"><?= htmlspecialchars((string)$row['group_count']) ?></a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
