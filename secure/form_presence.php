<?php
session_start();
require_once( __DIR__ . '/../my.php' );

header( 'Content-Type: application/json' );

if ( !isset( $_SESSION['operatorloggedin'] ) || !isset( $_SESSION['name'] ) ) {
  http_response_code( 401 );
  echo json_encode( [ 'ok' => false, 'message' => 'Niet ingelogd.' ] );
  exit;
}

$task_type = trim( $_POST['tasktype'] ?? '' );
$task_id = isset( $_POST['taskid'] ) ? (int)$_POST['taskid'] : 0;
$token = trim( $_POST['token'] ?? '' );
$action = trim( $_POST['action'] ?? 'heartbeat' );
$allowed = [ 'incident', 'change', 'problem', 'event', 'changeactivity', 'ubm' ];
if ( !in_array( $task_type, $allowed, true ) || $task_id <= 0 || $token === '' ) {
  http_response_code( 400 );
  echo json_encode( [ 'ok' => false, 'message' => 'Ongeldige presence-aanvraag.' ] );
  exit;
}

$logged_in_user = $_SESSION['name'];
$stmt = mysqli_prepare( $con, "SELECT id, firstname, lastname FROM itsm_ob_operators WHERE username = ? LIMIT 1" );
mysqli_stmt_bind_param( $stmt, 's', $logged_in_user );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );
if ( !$operator ) {
  http_response_code( 403 );
  echo json_encode( [ 'ok' => false, 'message' => 'Behandelaar niet gevonden.' ] );
  exit;
}

$operator_id = (int)$operator['id'];
$operator_name = trim( ( $operator['lastname'] ?? '' ) . ', ' . ( $operator['firstname'] ?? '' ) );

if ( $action === 'close' ) {
  $stmt = mysqli_prepare( $con, "
    DELETE FROM itsm_core_form_presence
    WHERE token = ? AND tasktype = ? AND taskid = ? AND operatorid = ?
  " );
  mysqli_stmt_bind_param( $stmt, 'ssii', $token, $task_type, $task_id, $operator_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );
  echo json_encode( [ 'ok' => true ] );
  exit;
}

$stmt = mysqli_prepare( $con, "
  INSERT INTO itsm_core_form_presence (token, tasktype, taskid, operatorid, operatorname, openedat, lastseen)
  VALUES (?, ?, ?, ?, ?, NOW(), NOW())
  ON DUPLICATE KEY UPDATE operatorid = VALUES(operatorid), operatorname = VALUES(operatorname), lastseen = NOW()
" );
mysqli_stmt_bind_param( $stmt, 'ssiis', $token, $task_type, $task_id, $operator_id, $operator_name );
mysqli_stmt_execute( $stmt );
mysqli_stmt_close( $stmt );

$cleanup_stmt = mysqli_prepare( $con, "DELETE FROM itsm_core_form_presence WHERE tasktype = ? AND taskid = ? AND operatorid = ? AND token <> ?" );
mysqli_stmt_bind_param( $cleanup_stmt, 'siis', $task_type, $task_id, $operator_id, $token );
mysqli_stmt_execute( $cleanup_stmt );
mysqli_stmt_close( $cleanup_stmt );

mysqli_query( $con, "DELETE FROM itsm_core_form_presence WHERE lastseen < (NOW() - INTERVAL 25 SECOND)" );

$stmt = mysqli_prepare( $con, "
  SELECT operatorid, operatorname, lastseen
  FROM itsm_core_form_presence
  WHERE tasktype = ? AND taskid = ? AND token <> ? AND lastseen >= (NOW() - INTERVAL 25 SECOND)
  ORDER BY lastseen DESC
" );
mysqli_stmt_bind_param( $stmt, 'sis', $task_type, $task_id, $token );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$others = [];
while ( $row = mysqli_fetch_assoc( $result ) ) {
  $others[] = [
    'operatorid' => (int)$row['operatorid'],
    'operatorname' => $row['operatorname'],
    'lastseen' => $row['lastseen']
  ];
}
mysqli_stmt_close( $stmt );

$stale = false;
$saved_by = '';
$stmt = mysqli_prepare( $con, "
  SELECT s.savedby, s.lastsavedat, CONCAT(o.lastname, ', ', o.firstname) AS operator_name, p.openedat
  FROM itsm_core_form_presence p
  LEFT JOIN itsm_core_form_saves s ON s.tasktype = p.tasktype AND s.taskid = p.taskid
  LEFT JOIN itsm_ob_operators o ON s.savedby = o.id
  WHERE p.token = ? AND p.tasktype = ? AND p.taskid = ?
  LIMIT 1
" );
mysqli_stmt_bind_param( $stmt, 'ssi', $token, $task_type, $task_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$row = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );
if ( $row && !empty( $row['lastsavedat'] ) && (int)$row['savedby'] !== $operator_id && strtotime( $row['lastsavedat'] ) > strtotime( $row['openedat'] ) ) {
  $stale = true;
  $saved_by = $row['operator_name'] ?: 'een andere behandelaar';
}

echo json_encode( [
  'ok' => true,
  'others' => $others,
  'stale' => $stale,
  'saved_by' => $saved_by
] );
