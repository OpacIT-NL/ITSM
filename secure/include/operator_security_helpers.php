<?php

function itsm_current_operator_security_context( $con ) {
  $operator_id = (int)( $_SESSION['id'] ?? 0 );
  if ( $operator_id <= 0 ) {
    return null;
  }

  $stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE id = ? AND allowlogin = 1 LIMIT 1" );
  if ( !$stmt ) {
    itsm_fail( 'operator_context_prepare_failed', mysqli_error( $con ) );
  }
  mysqli_stmt_bind_param( $stmt, 'i', $operator_id );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    itsm_fail( 'operator_context_query_failed', mysqli_stmt_error( $stmt ) );
  }
  $result = mysqli_stmt_get_result( $stmt );
  $operator = mysqli_fetch_assoc( $result ) ?: null;
  mysqli_stmt_close( $stmt );

  return $operator;
}

function itsm_operator_can_access_task_type( $operator, $task_type ) {
  if ( !$operator ) {
    return false;
  }
  if ( (int)( $operator['isadmin'] ?? 0 ) === 1 ) {
    return true;
  }

  $task_type = strtolower( (string)$task_type );
  if ( $task_type === 'incident' ) {
    return (int)( $operator['firstlineincidents'] ?? 0 ) === 1
      || (int)( $operator['secondlineincidents'] ?? 0 ) === 1;
  }
  if ( in_array( $task_type, [ 'change', 'changeactivity', 'activity' ], true ) ) {
    return (int)( $operator['reqforchange'] ?? 0 ) === 1
      || (int)( $operator['simplechange'] ?? 0 ) === 1
      || (int)( $operator['extchange'] ?? 0 ) === 1;
  }

  $permission_map = [
    'problem' => 'problems',
    'event' => 'events',
    'ubm' => 'ubm',
    'asset' => 'assets',
    'knowledge' => 'isadmin'
  ];
  $permission = $permission_map[$task_type] ?? '';
  return $permission !== '' && (int)( $operator[$permission] ?? 0 ) === 1;
}

function itsm_require_operator_task_type_access( $operator, $task_type ) {
  if ( itsm_operator_can_access_task_type( $operator, $task_type ) ) {
    return;
  }
  http_response_code( 404 );
  exit( 'Niet gevonden.' );
}

function itsm_operator_can_access_attachment( $operator, $attachment ) {
  if ( !$attachment ) {
    return false;
  }
  return itsm_operator_can_access_task_type( $operator, $attachment['tasktype'] ?? '' );
}

?>
