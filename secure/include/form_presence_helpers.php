<?php

function form_presence_check_before_save( $con, $task_type, $task_id, $presence_token ) {
  $task_id = (int)$task_id;
  $presence_token = trim( (string)$presence_token );
  if ( $presence_token === '' ) {
    return [ 'ok' => true, 'message' => '' ];
  }

  $stmt = mysqli_prepare( $con, "
    SELECT s.lastsavedat, s.savedby, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_core_form_saves s
    LEFT JOIN itsm_ob_operators o ON s.savedby = o.id
    WHERE s.tasktype = ? AND s.taskid = ?
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 'si', $task_type, $task_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $save = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );
  if ( !$save ) {
    return [ 'ok' => true, 'message' => '' ];
  }

  $stmt = mysqli_prepare( $con, "
    SELECT operatorid, openedat
    FROM itsm_core_form_presence
    WHERE token = ? AND tasktype = ? AND taskid = ?
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 'ssi', $presence_token, $task_type, $task_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $presence = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );
  if ( !$presence ) {
    return [ 'ok' => true, 'message' => '' ];
  }

  if ( (int)$save['savedby'] !== (int)$presence['operatorid'] && strtotime( $save['lastsavedat'] ) > strtotime( $presence['openedat'] ) ) {
    $operator_name = $save['operator_name'] ?: 'een andere behandelaar';
    return [
      'ok' => false,
      'message' => 'Opslaan geblokkeerd: ' . $operator_name . ' heeft deze kaart tussendoor opgeslagen. Vernieuw de kaart en probeer daarna opnieuw.'
    ];
  }

  return [ 'ok' => true, 'message' => '' ];
}

function form_presence_redirect_if_stale( $con, $task_type, $task_id, $presence_token, $redirect_url ) {
  $check = form_presence_check_before_save( $con, $task_type, $task_id, $presence_token );
  if ( !empty( $check['ok'] ) ) {
    return false;
  }

  $_SESSION['form_presence_error'] = $check['message'];
  $separator = strpos( $redirect_url, '?' ) === false ? '?' : '&';
  header( 'Location: ' . $redirect_url . $separator . 'presence_blocked=1' );
  exit;
}

function form_presence_flash_error() {
  $message = $_SESSION['form_presence_error'] ?? '';
  if ( isset( $_SESSION['form_presence_error'] ) ) {
    unset( $_SESSION['form_presence_error'] );
  }

  return (string)$message;
}

function form_presence_mark_saved( $con, $task_type, $task_id, $operator_id ) {
  $task_id = (int)$task_id;
  $operator_id = (int)$operator_id;
  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_core_form_saves (tasktype, taskid, savedby, lastsavedat)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE savedby = VALUES(savedby), lastsavedat = NOW()
  " );
  mysqli_stmt_bind_param( $stmt, 'sii', $task_type, $task_id, $operator_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );
}
