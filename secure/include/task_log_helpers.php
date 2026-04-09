<?php

function task_log_operator_id( $operator_context ) {
  if ( is_array( $operator_context ) && isset( $operator_context['id'] ) && $operator_context['id'] !== '' ) {
    return (int)$operator_context['id'];
  }

  return null;
}

function task_log_add( $con, $task_type, $task_id, $action_type, $message, $operator_id = null, $old_value = null, $new_value = null ) {
  $task_type = (string)$task_type;
  $task_id = (int)$task_id;
  $action_type = (string)$action_type;
  $message = (string)$message;
  $old_value = $old_value !== null ? (string)$old_value : null;
  $new_value = $new_value !== null ? (string)$new_value : null;
  $operator_id = $operator_id !== null && $operator_id !== '' ? (int)$operator_id : null;

  if ( $task_type === '' || $task_id <= 0 || $action_type === '' || $message === '' ) {
    return false;
  }

  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_core_tasklogs (
      tasktype, taskid, actiontype, message, oldvalue, newvalue, createdby
    ) VALUES (?,?,?,?,?,?,?)
  " );
  if ( !$stmt ) {
    return false;
  }

  mysqli_stmt_bind_param( $stmt, "sissssi", $task_type, $task_id, $action_type, $message, $old_value, $new_value, $operator_id );
  $ok = mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );

  return $ok;
}

function task_log_status_name( $con, $status_id ) {
  if ( empty( $status_id ) ) {
    return '';
  }

  $stmt = mysqli_prepare( $con, "SELECT name FROM itsm_core_status WHERE id = ?" );
  if ( !$stmt ) {
    return '';
  }
  $status_id = (int)$status_id;
  mysqli_stmt_bind_param( $stmt, "i", $status_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $name );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  return (string)($name ?? '');
}

function task_log_value_label( $con, $field, $value ) {
  if ( $value === null || $value === '' || (string)$value === '0' ) {
    return 'leeg';
  }

  $field = (string)$field;
  $raw_value = (string)$value;

  $lookups = [
    'customerid' => [ 'table' => 'itsm_ob_customers', 'label' => 'name' ],
    'personid' => [ 'table' => 'itsm_ob_persons', 'label' => "CONCAT(lastname, ', ', firstname)" ],
    'categoryid' => [ 'table' => 'itsm_core_category', 'label' => 'name' ],
    'subcategoryid' => [ 'table' => 'itsm_core_subcategory', 'label' => 'name' ],
    'assetid' => [ 'table' => 'itsm_am_assets', 'label' => 'objectid' ],
    'operatorgroupid' => [ 'table' => 'itsm_ob_operatorgroups', 'label' => 'groupname' ],
    'operatorid' => [ 'table' => 'itsm_ob_operators', 'label' => "CONCAT(lastname, ', ', firstname)" ],
    'coordinatorid' => [ 'table' => 'itsm_ob_operators', 'label' => "CONCAT(lastname, ', ', firstname)" ],
    'statusid' => [ 'table' => 'itsm_core_status', 'label' => 'name' ],
    'majorincidentid' => [ 'table' => 'itsm_im_incidents', 'label' => 'incidentnumber' ]
  ];

  if ( !empty( $lookups[$field] ) ) {
    $lookup = $lookups[$field];
    $lookup_id = (int)$value;
    $stmt = mysqli_prepare( $con, "SELECT " . $lookup['label'] . " AS label FROM " . $lookup['table'] . " WHERE id = ?" );
    if ( $stmt ) {
      mysqli_stmt_bind_param( $stmt, "i", $lookup_id );
      mysqli_stmt_execute( $stmt );
      mysqli_stmt_bind_result( $stmt, $label );
      mysqli_stmt_fetch( $stmt );
      mysqli_stmt_close( $stmt );
      if ( (string)($label ?? '') !== '' ) {
        return (string)$label;
      }
    }
  }

  $maps = [
    'incidenttype' => [ 'firstline' => 'Eerstelijns incident', 'secondline' => 'Tweedelijns incident', 'major' => 'Major incident' ],
    'requesttype' => [ 'simple' => 'Eenvoudige Wijziging', 'extended' => 'Uitgebreide Wijziging' ],
    'approvalstate' => [ 'request' => 'Wijzigingsaanvraag', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen' ],
    'itemtype' => [ 'initiative' => 'Initiative', 'epic' => 'Epic', 'feature' => 'Feature', 'story' => 'Story', 'subtask' => 'Subtask' ],
    'changetype' => [ 'standard' => 'Standaard Wijziging', 'normal' => 'Normale Wijziging', 'normal_cab' => 'Normale CAB Wijziging', 'emergency' => 'Noodwijziging' ]
  ];

  if ( isset( $maps[$field] ) ) {
    return $maps[$field][$raw_value] ?? $raw_value;
  }

  return (string)$value;
}

function task_log_text_snippet( $value ) {
  $value = trim( preg_replace( '/\s+/', ' ', (string)$value ) );
  if ( strlen( $value ) > 180 ) {
    return substr( $value, 0, 177 ) . '...';
  }

  return $value !== '' ? $value : 'leeg';
}

function task_log_field_changes( $con, $task_type, $task_id, $old_values, $new_values, $field_labels, $operator_id = null ) {
  foreach ( $field_labels as $field => $label ) {
    $old_raw = $old_values[$field] ?? null;
    $new_raw = $new_values[$field] ?? null;
    if ( (string)($old_raw ?? '') === (string)($new_raw ?? '') ) {
      continue;
    }

    if ( in_array( $field, [ 'description', 'commenttext' ], true ) ) {
      $old_label = task_log_text_snippet( $old_raw );
      $new_label = task_log_text_snippet( $new_raw );
    } else {
      $old_label = task_log_value_label( $con, $field, $old_raw );
      $new_label = task_log_value_label( $con, $field, $new_raw );
    }

    task_log_add(
      $con,
      $task_type,
      $task_id,
      'updated',
      $label . ' gewijzigd van "' . $old_label . '" naar "' . $new_label . '".',
      $operator_id,
      task_log_text_snippet( $old_label ),
      task_log_text_snippet( $new_label )
    );
  }
}

function task_log_status_change( $con, $task_type, $task_id, $old_status_id, $new_status_id, $operator_id = null ) {
  if ( (int)$old_status_id === (int)$new_status_id ) {
    return false;
  }

  $old_name = task_log_status_name( $con, $old_status_id );
  $new_name = task_log_status_name( $con, $new_status_id );
  $old_label = $old_name !== '' ? $old_name : (string)$old_status_id;
  $new_label = $new_name !== '' ? $new_name : (string)$new_status_id;

  $logged = task_log_add(
    $con,
    $task_type,
    $task_id,
    'statuschange',
    'Status gewijzigd van ' . $old_label . ' naar ' . $new_label . '.',
    $operator_id,
    $old_label,
    $new_label
  );

  if ( task_log_status_closed_flag( $con, $new_status_id ) === 1 && task_log_status_closed_flag( $con, $old_status_id ) !== 1 ) {
    task_log_add( $con, $task_type, $task_id, 'closed', 'Taak gesloten met status ' . $new_label . '.', $operator_id );
  }

  return $logged;
}

function task_log_status_closed_flag( $con, $status_id ) {
  if ( empty( $status_id ) ) {
    return 0;
  }

  $stmt = mysqli_prepare( $con, "SELECT closed FROM itsm_core_status WHERE id = ?" );
  if ( !$stmt ) {
    return 0;
  }
  $status_id = (int)$status_id;
  mysqli_stmt_bind_param( $stmt, "i", $status_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $closed );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  return (int)($closed ?? 0);
}

function task_log_load( $con, $task_type, $task_id ) {
  $logs = [];
  $task_type = (string)$task_type;
  $task_id = (int)$task_id;
  if ( $task_type === '' || $task_id <= 0 ) {
    return $logs;
  }

  $stmt = mysqli_prepare( $con, "
    SELECT l.*, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_core_tasklogs l
    LEFT JOIN itsm_ob_operators o ON l.createdby = o.id
    WHERE l.tasktype = ? AND l.taskid = ?
    ORDER BY l.createdat DESC, l.id DESC
  " );
  if ( !$stmt ) {
    return $logs;
  }
  mysqli_stmt_bind_param( $stmt, "si", $task_type, $task_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $logs[] = $row;
  }
  mysqli_stmt_close( $stmt );

  return $logs;
}

function task_log_render_tab( $logs ) {
  ob_start();
  ?>
  <div class="ticket-log-list">
    <?php if ( empty( $logs ) ): ?>
    <p class="info-note">Nog geen logregels.</p>
    <?php else: ?>
    <?php foreach ( $logs as $log ): ?>
    <div class="ticket-log-entry">
      <div class="incident-comment-meta">
        <span><?= htmlspecialchars($log['operator_name'] ?: 'Systeem') ?></span>
        <span><?= htmlspecialchars($log['createdat']) ?></span>
      </div>
      <strong><?= htmlspecialchars(task_log_action_label($log['actiontype'] ?? 'log')) ?></strong>
      <p class="ticket-log-message"><?= nl2br( htmlspecialchars($log['message'] ?? '') ) ?></p>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

function task_log_action_label( $action_type ) {
  $labels = [
    'created' => 'Aangemaakt',
    'updated' => 'Bijgewerkt',
    'statuschange' => 'Statuswijziging',
    'closed' => 'Gesloten',
    'approved' => 'Goedgekeurd',
    'rejected' => 'Afgewezen',
    'email' => 'E-mail verzonden',
    'activity_created' => 'Activiteit aangemaakt',
    'incident_created' => 'Incident aangemaakt',
    'change_created' => 'Wijziging aangemaakt',
    'link_created' => 'Link aangemaakt'
  ];

  return $labels[$action_type] ?? ucfirst( (string)$action_type );
}
