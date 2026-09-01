<?php
require_once( __DIR__ . '/../../include/upload_security.php' );

function attachment_task_label( $task_type ) {
  $labels = [
    'incident' => 'Incident',
    'change' => 'Wijziging',
    'problem' => 'Problem',
    'event' => 'Event',
    'changeactivity' => 'Wijzigingsactiviteit',
    'ubm' => 'UBM'
  ];

  return $labels[$task_type] ?? $task_type;
}

function attachment_uploaded_file_available( $field_name = 'attachment' ) {
  return isset( $_FILES[$field_name] )
    && is_array( $_FILES[$field_name] )
    && (int)$_FILES[$field_name]['error'] !== UPLOAD_ERR_NO_FILE;
}

function attachment_upload_errors( $field_name = 'attachment' ) {
  if ( !attachment_uploaded_file_available( $field_name ) ) {
    return [];
  }

  $file = $_FILES[$field_name];
  if ( (int)$file['error'] !== UPLOAD_ERR_OK ) {
    return [ 'Bijlage uploaden mislukt. Upload foutcode: ' . (int)$file['error'] ];
  }

  $validation = itsm_uploaded_attachment_validation( $file );
  return $validation['ok'] ? [] : [ $validation['error'] ];
}

function attachment_save_upload( $con, $task_type, $task_id, $operator_id, $internal_only = 0, $comment_type = null, $comment_id = null, $field_name = 'attachment' ) {
  if ( !attachment_uploaded_file_available( $field_name ) ) {
    return 0;
  }

  $file = $_FILES[$field_name];
  $validation = itsm_uploaded_attachment_validation( $file );
  if ( !$validation['ok'] ) {
    return 0;
  }

  return attachment_save_binary(
    $con,
    $task_type,
    $task_id,
    basename( (string)$file['name'] ),
    $validation['mime'],
    $validation['content'],
    $operator_id,
    null,
    $internal_only,
    $comment_type,
    $comment_id
  );
}

function attachment_save_binary( $con, $task_type, $task_id, $filename, $mimetype, $content, $operator_id = null, $person_id = null, $internal_only = 0, $comment_type = null, $comment_id = null ) {
  $task_type = (string)$task_type;
  $task_id = (int)$task_id;
  $filename = basename( (string)$filename );
  $content = (string)$content;
  $validation = itsm_validate_attachment_content( $filename, $content );
  if ( !$validation['ok'] ) {
    return 0;
  }
  $mimetype = $validation['mime'];
  $filesize = strlen( $content );
  $operator_id = $operator_id !== null && $operator_id !== '' ? (int)$operator_id : null;
  $person_id = $person_id !== null && $person_id !== '' ? (int)$person_id : null;
  $internal_only = (int)$internal_only;
  $comment_id = $comment_id !== null ? (int)$comment_id : null;
  $null_blob = null;

  if ( $task_type === '' || $task_id <= 0 || $filename === '' || $filesize <= 0 ) {
    return 0;
  }

  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_core_attachments
      (tasktype, taskid, commenttype, commentid, filename, mimetype, filesize, content, uploadedby, uploadedbyperson, internalonly)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  " );
  if ( !$stmt ) {
    itsm_fail( 'attachment_insert_prepare_failed', mysqli_error( $con ) );
  }
  mysqli_stmt_bind_param(
    $stmt,
    'sisissibiii',
    $task_type,
    $task_id,
    $comment_type,
    $comment_id,
    $filename,
    $mimetype,
    $filesize,
    $null_blob,
    $operator_id,
    $person_id,
    $internal_only
  );
  mysqli_stmt_send_long_data( $stmt, 7, $content );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    itsm_fail( 'attachment_insert_failed', mysqli_stmt_error( $stmt ) );
  }
  $attachment_id = mysqli_insert_id( $con );
  mysqli_stmt_close( $stmt );

  return $attachment_id;
}

function attachment_load_for_task( $con, $task_type, $task_id ) {
  $stmt = mysqli_prepare( $con, "
    SELECT a.id, a.tasktype, a.taskid, a.commenttype, a.commentid, a.filename, a.mimetype, a.filesize, a.internalonly, a.createdat,
           COALESCE(CONCAT(o.lastname, ', ', o.firstname), CONCAT(p.lastname, ', ', p.firstname), 'Onbekend') AS operator_name
    FROM itsm_core_attachments a
    LEFT JOIN itsm_ob_operators o ON a.uploadedby = o.id
    LEFT JOIN itsm_ob_persons p ON a.uploadedbyperson = p.id
    WHERE a.tasktype = ? AND a.taskid = ?
    ORDER BY a.createdat DESC, a.id DESC
  " );
  mysqli_stmt_bind_param( $stmt, 'si', $task_type, $task_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rows = mysqli_fetch_all( $result, MYSQLI_ASSOC );
  mysqli_stmt_close( $stmt );

  return $rows;
}

function attachment_group_by_comment( $attachments ) {
  $grouped = [];
  foreach ( $attachments as $attachment ) {
    $key = (string)( $attachment['commentid'] ?? '' );
    if ( $key === '' || $key === '0' ) {
      continue;
    }
    if ( !isset( $grouped[$key] ) ) {
      $grouped[$key] = [];
    }
    $grouped[$key][] = $attachment;
  }

  return $grouped;
}

function attachment_format_filesize( $bytes ) {
  $bytes = (int)$bytes;
  if ( $bytes >= 1048576 ) {
    return round( $bytes / 1048576, 1 ) . ' MB';
  }
  if ( $bytes >= 1024 ) {
    return round( $bytes / 1024, 1 ) . ' KB';
  }

  return $bytes . ' B';
}

function attachment_render_links( $attachments ) {
  if ( empty( $attachments ) ) {
    return '';
  }

  $html = '<div class="attachment-list">';
  foreach ( $attachments as $attachment ) {
    $html .= '<a class="task-inline-link attachment-link" href="download_attachment.php?id=' . htmlspecialchars( (string)$attachment['id'] ) . '">';
    $html .= '<i class="fa-solid fa-paperclip"></i> ' . htmlspecialchars( $attachment['filename'] );
    $html .= ' <span>(' . htmlspecialchars( attachment_format_filesize( $attachment['filesize'] ) ) . ')</span>';
    $html .= '</a>';
    if ( itsm_attachment_mimetype_can_inline( $attachment['mimetype'] ?? '' ) ) {
      $html .= '<img class="inline-task-image attachment-preview" src="download_attachment.php?id=' . htmlspecialchars( (string)$attachment['id'] ) . '&amp;inline=1" alt="' . htmlspecialchars( $attachment['filename'] ) . '">';
    }
  }
  $html .= '</div>';

  return $html;
}

function attachment_render_as_comments( $attachments ) {
  $standalone = array_values( array_filter(
    $attachments,
    function( $attachment ) {
      return empty( $attachment['commentid'] );
    }
  ) );
  if ( empty( $standalone ) ) {
    return '';
  }

  $html = '<div class="incident-history attachment-history">';
  foreach ( $standalone as $attachment ) {
    $html .= '<div class="incident-comment">';
    $html .= '<div class="incident-comment-meta">';
    $html .= '<span>' . htmlspecialchars( $attachment['operator_name'] ?? 'Onbekend' ) . '</span>';
    $html .= '<span>' . htmlspecialchars( $attachment['createdat'] ) . '</span>';
    $html .= '</div>';
    $html .= '<span class="incident-badge">Bijlage</span>';
    if ( (int)$attachment['internalonly'] === 1 ) {
      $html .= ' <span class="incident-badge">Niet voor klant</span>';
    }
    $html .= attachment_render_links( [ $attachment ] );
    $html .= '</div>';
  }
  $html .= '</div>';

  return $html;
}

function attachment_render_upload_field( $field_name = 'attachment' ) {
  ?>
  <div class="form-group">
    <label class="incident-meta-label">Bijlage</label>
    <label><input type="file" name="<?= htmlspecialchars($field_name) ?>"></label>
  </div>
  <?php
}
