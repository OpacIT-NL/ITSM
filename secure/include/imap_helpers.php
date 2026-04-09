<?php

require_once( __DIR__ . '/mail_helpers.php' );
require_once( __DIR__ . '/attachment_helpers.php' );
require_once( __DIR__ . '/task_helpers.php' );
require_once( __DIR__ . '/task_log_helpers.php' );

function imap_config_path() {
  return __DIR__ . '/../../../config/imap.ini';
}

function imap_load_config() {
  $path = imap_config_path();
  if ( !is_readable( $path ) ) {
    return [];
  }

  $config = parse_ini_file( $path );
  return is_array( $config ) ? $config : [];
}

function imap_mailbox_string( $config, $folder ) {
  $host = (string)( $config['host'] ?? '' );
  $port = (int)( $config['port'] ?? 993 );
  $encryption = strtolower( (string)( $config['encryption'] ?? 'ssl' ) );
  $validate_cert = (int)( $config['validate_cert'] ?? 1 );
  $flags = '/imap';

  if ( $encryption === 'ssl' ) {
    $flags .= '/ssl';
  } elseif ( $encryption === 'tls' ) {
    $flags .= '/tls';
  } else {
    $flags .= '/notls';
  }
  if ( $validate_cert === 0 ) {
    $flags .= '/novalidate-cert';
  }

  return '{' . $host . ':' . $port . $flags . '}' . $folder;
}

function imap_decode_header_text( $value ) {
  if ( !function_exists( 'imap_mime_header_decode' ) ) {
    return (string)$value;
  }

  $parts = imap_mime_header_decode( (string)$value );
  $decoded = '';
  foreach ( $parts as $part ) {
    $text = $part->text ?? '';
    $charset = strtoupper( (string)( $part->charset ?? 'UTF-8' ) );
    if ( $charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists( 'iconv' ) ) {
      $converted = @iconv( $charset, 'UTF-8//IGNORE', $text );
      $text = $converted !== false ? $converted : $text;
    }
    $decoded .= $text;
  }

  return trim( $decoded );
}

function imap_extract_email_address( $from ) {
  if ( preg_match( '/<([^>]+)>/', (string)$from, $matches ) ) {
    return strtolower( trim( $matches[1] ) );
  }
  if ( filter_var( trim( (string)$from ), FILTER_VALIDATE_EMAIL ) ) {
    return strtolower( trim( (string)$from ) );
  }

  return '';
}

function imap_decode_body_part( $body, $encoding ) {
  $encoding = (int)$encoding;
  if ( $encoding === 3 ) {
    return base64_decode( $body );
  }
  if ( $encoding === 4 ) {
    return quoted_printable_decode( $body );
  }

  return $body;
}

function imap_fetch_plain_body( $mailbox, $message_number ) {
  $structure = imap_fetchstructure( $mailbox, $message_number );
  if ( !$structure ) {
    return trim( imap_body( $mailbox, $message_number ) );
  }

  if ( empty( $structure->parts ) ) {
    return trim( imap_decode_body_part( imap_body( $mailbox, $message_number ), $structure->encoding ?? 0 ) );
  }

  foreach ( $structure->parts as $index => $part ) {
    $subtype = strtoupper( (string)( $part->subtype ?? '' ) );
    if ( $subtype === 'PLAIN' ) {
      $body = imap_fetchbody( $mailbox, $message_number, (string)( $index + 1 ) );
      return trim( imap_decode_body_part( $body, $part->encoding ?? 0 ) );
    }
  }

  foreach ( $structure->parts as $index => $part ) {
    $subtype = strtoupper( (string)( $part->subtype ?? '' ) );
    if ( $subtype === 'HTML' ) {
      $body = imap_fetchbody( $mailbox, $message_number, (string)( $index + 1 ) );
      return trim( strip_tags( imap_decode_body_part( $body, $part->encoding ?? 0 ) ) );
    }
  }

  return trim( imap_body( $mailbox, $message_number ) );
}

function imap_part_parameter_value( $part, $attribute_name ) {
  $attribute_name = strtolower( (string)$attribute_name );
  $sources = [];
  if ( !empty( $part->dparameters ) && is_array( $part->dparameters ) ) {
    $sources = array_merge( $sources, $part->dparameters );
  }
  if ( !empty( $part->parameters ) && is_array( $part->parameters ) ) {
    $sources = array_merge( $sources, $part->parameters );
  }

  foreach ( $sources as $parameter ) {
    $name = strtolower( (string)( $parameter->attribute ?? '' ) );
    if ( $name === $attribute_name ) {
      return imap_decode_header_text( (string)( $parameter->value ?? '' ) );
    }
  }

  return '';
}

function imap_collect_attachments_recursive( $mailbox, $message_number, $part, $part_number = '' ) {
  $attachments = [];
  $filename = imap_part_parameter_value( $part, 'filename' );
  if ( $filename === '' ) {
    $filename = imap_part_parameter_value( $part, 'name' );
  }

  $disposition = strtoupper( (string)( $part->disposition ?? '' ) );
  $is_attachment = $filename !== '' || in_array( $disposition, [ 'ATTACHMENT', 'INLINE' ], true );
  if ( $is_attachment && $filename !== '' ) {
    $fetch_section = $part_number !== '' ? $part_number : '1';
    $body = imap_fetchbody( $mailbox, $message_number, $fetch_section );
    $content = imap_decode_body_part( $body, $part->encoding ?? 0 );
    if ( $content !== false && $content !== '' ) {
      $attachments[] = [
        'filename' => $filename,
        'major_type' => (int)( $part->type ?? 7 ),
        'subtype' => strtolower( (string)( $part->subtype ?? 'octet-stream' ) ),
        'content' => $content,
        'filesize' => strlen( $content )
      ];
    }
  }

  if ( !empty( $part->parts ) && is_array( $part->parts ) ) {
    foreach ( $part->parts as $index => $child_part ) {
      $child_number = $part_number === '' ? (string)( $index + 1 ) : $part_number . '.' . ( $index + 1 );
      $attachments = array_merge( $attachments, imap_collect_attachments_recursive( $mailbox, $message_number, $child_part, $child_number ) );
    }
  }

  return $attachments;
}

function imap_fetch_attachments( $mailbox, $message_number ) {
  $structure = imap_fetchstructure( $mailbox, $message_number );
  if ( !$structure ) {
    return [];
  }

  if ( empty( $structure->parts ) ) {
    return imap_collect_attachments_recursive( $mailbox, $message_number, $structure, '' );
  }

  $attachments = [];
  foreach ( $structure->parts as $index => $part ) {
    $attachments = array_merge( $attachments, imap_collect_attachments_recursive( $mailbox, $message_number, $part, (string)( $index + 1 ) ) );
  }

  return array_values( array_filter(
    $attachments,
    function( $attachment ) {
      return !empty( $attachment['filename'] ) && !empty( $attachment['content'] );
    }
  ) );
}

function imap_attachment_mimetype( $attachment ) {
  $type_map = [
    0 => 'text',
    1 => 'multipart',
    2 => 'message',
    3 => 'application',
    4 => 'audio',
    5 => 'image',
    6 => 'video',
    7 => 'other'
  ];
  $major_type = $type_map[(int)( $attachment['major_type'] ?? 7 )] ?? 'application';
  $subtype = strtolower( (string)( $attachment['subtype'] ?? 'octet-stream' ) );

  return $major_type . '/' . $subtype;
}

function imap_extract_task_match_from_subject( $con, $subject ) {
  $subject = strtoupper( (string)$subject );
  if ( $subject === '' ) {
    return null;
  }

  preg_match_all( '/\b(INI\d{4}\s\d{4}|EPI\d{4}\s\d{4}|FEA\d{4}\s\d{4}|STR\d{4}\s\d{4}|SUB\d{4}\s\d{4}|WA\d{4}\s\d{4}|W\d{4}\s\d{4}|I\d{4}\s\d{4}|P\d{4}\s\d{4}|E\d{4}\s\d{4})\b/', $subject, $matches );
  $candidates = array_values( array_unique( $matches[1] ?? [] ) );
  foreach ( $candidates as $candidate ) {
    $task = task_find_by_number( $con, $candidate, 'secure' );
    if ( $task ) {
      return $task;
    }
  }

  return null;
}

function imap_comment_text_from_message( $rule, $message ) {
  return "Geimporteerd vanuit e-mailmap: " . $rule['folder']
    . "\nVan: " . $message['from']
    . "\nDatum: " . $message['date']
    . "\nMessage-ID: " . $message['message_id']
    . "\nOnderwerp: " . $message['subject']
    . "\n\n" . $message['body'];
}

function imap_add_comment_to_existing_task( $con, $task, $rule, $message, $created_by ) {
  $task_type = (string)$task['type'];
  $task_id = (int)$task['id'];
  $comment_text = imap_comment_text_from_message( $rule, $message );
  $person = imap_find_person_by_email( $con, $message['from_email'] );
  $person_id = !empty( $person['id'] ) ? (int)$person['id'] : null;

  if ( $task_type === 'incident' ) {
    $operator_id = null;
    $stmt = mysqli_prepare( $con, "
      INSERT INTO itsm_im_incidentcomments (incidentid, operatorid, personid, commenttext, internalonly)
      VALUES (?,?,?,?,0)
    " );
    mysqli_stmt_bind_param( $stmt, 'iiis', $task_id, $operator_id, $person_id, $comment_text );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );
    return mysqli_insert_id( $con );
  }

  if ( $task_type === 'change' ) {
    $operator_id = null;
    $stmt = mysqli_prepare( $con, "
      INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly)
      VALUES (?,?,?,?,0)
    " );
    mysqli_stmt_bind_param( $stmt, 'iiis', $task_id, $operator_id, $person_id, $comment_text );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );
    return mysqli_insert_id( $con );
  }

  if ( $task_type === 'problem' ) {
    $comment_text = "Afzender: " . $message['from'] . "\n\n" . $comment_text;
    $stmt = mysqli_prepare( $con, "
      INSERT INTO itsm_pm_problemcomments (problemid, operatorid, commenttext, internalonly)
      VALUES (?,?,?,0)
    " );
    mysqli_stmt_bind_param( $stmt, 'iis', $task_id, $created_by, $comment_text );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );
    return mysqli_insert_id( $con );
  }

  return 0;
}

function imap_attach_attachments_to_task( $con, $task, $attachments, $created_by, $comment_id = 0, $person_id = null ) {
  $task_type = (string)$task['type'];
  $task_id = (int)$task['id'];
  $comment_type_map = [
    'incident' => 'incidentcomment',
    'change' => 'changecomment',
    'problem' => 'problemcomment'
  ];
  $comment_type = !empty( $comment_type_map[$task_type] ) && $comment_id > 0 ? $comment_type_map[$task_type] : null;
  $saved = 0;

  foreach ( $attachments as $attachment ) {
    $attachment_operator_id = $person_id ? null : $created_by;
    $saved_id = attachment_save_binary(
      $con,
      $task_type,
      $task_id,
      $attachment['filename'],
      imap_attachment_mimetype( $attachment ),
      $attachment['content'],
      $attachment_operator_id,
      $person_id,
      0,
      $comment_type,
      $comment_id > 0 ? $comment_id : null
    );
    if ( $saved_id > 0 ) {
      $saved++;
    }
  }

  return $saved;
}

function imap_attach_message_to_existing_task( $con, $task, $rule, $message, $attachments, $created_by ) {
  $person = imap_find_person_by_email( $con, $message['from_email'] );
  $person_id = !empty( $person['id'] ) ? (int)$person['id'] : null;
  $comment_id = imap_add_comment_to_existing_task( $con, $task, $rule, $message, $created_by );
  $saved_attachments = imap_attach_attachments_to_task( $con, $task, $attachments, $created_by, $comment_id, $person_id );

  $task_number = $task['number'] ?? ( '#' . (int)$task['id'] );
  $log_message = 'E-mail gekoppeld via IMAP-import';
  if ( $message['from'] !== '' ) {
    $log_message .= ' van ' . $message['from'];
  }
  if ( $saved_attachments > 0 ) {
    $log_message .= ' met ' . $saved_attachments . ' bijlage(n)';
  }
  $log_message .= ' op ' . $task_number . '.';
  if ( !in_array( $task['type'], [ 'incident', 'change', 'problem' ], true ) ) {
    $log_message .= "\n\n" . imap_comment_text_from_message( $rule, $message );
  }

  task_log_add( $con, $task['type'], (int)$task['id'], 'email', $log_message, $created_by );

  return [
    'tasktype' => $task['type'],
    'taskid' => (int)$task['id']
  ];
}

function imap_default_status_id( $con, $type ) {
  $stmt = mysqli_prepare( $con, "SELECT id FROM itsm_core_status WHERE type = ? AND closed = 0 ORDER BY id ASC LIMIT 1" );
  mysqli_stmt_bind_param( $stmt, 's', $type );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );
  return $row ? (int)$row['id'] : 0;
}

function imap_generate_incident_number( $con ) {
  require_once( __DIR__ . '/incident_helpers.php' );
  return incident_generate_number( $con );
}

function imap_generate_change_number( $con ) {
  require_once( __DIR__ . '/change_helpers.php' );
  return change_generate_number( $con );
}

function imap_find_person_by_email( $con, $email ) {
  if ( $email === '' ) {
    return null;
  }
  $stmt = mysqli_prepare( $con, "
    SELECT p.id, p.customerid, p.firstname, p.lastname, p.email, p.phone
    FROM itsm_ob_persons p
    WHERE LOWER(p.email) = LOWER(?)
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 's', $email );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );
  return $row ?: null;
}

function imap_create_incident_from_message( $con, $rule, $message, $created_by ) {
  $person = imap_find_person_by_email( $con, $message['from_email'] );
  $customer_id = $person ? (int)$person['customerid'] : ( !empty( $rule['fallback_customerid'] ) ? (int)$rule['fallback_customerid'] : null );
  $person_id = $person ? (int)$person['id'] : null;
  $person_email = $person ? (string)$person['email'] : $message['from_email'];
  $person_phone = $person ? (string)$person['phone'] : '';
  $category_id = !empty( $rule['categoryid'] ) ? (int)$rule['categoryid'] : null;
  $subcategory_id = !empty( $rule['subcategoryid'] ) ? (int)$rule['subcategoryid'] : null;
  $operatorgroup_id = !empty( $rule['operatorgroupid'] ) ? (int)$rule['operatorgroupid'] : null;
  $status_id = imap_default_status_id( $con, 'INCIDENT' );
  $incident_number = imap_generate_incident_number( $con );
  $incident_type = 'firstline';
  $major_id = null;
  $asset_id = null;
  $operator_id = null;
  $template_used = null;
  $title = $message['subject'] !== '' ? $message['subject'] : 'E-mail zonder onderwerp';
  $description = "Geimporteerd vanuit e-mailmap: " . $rule['folder'] . "\n\nVan: " . $message['from'] . "\nDatum: " . $message['date'] . "\nMessage-ID: " . $message['message_id'] . "\n\n" . $message['body'];

  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_im_incidents (
      incidentnumber, incidenttype, majorincidentid, title, description, customerid, personid, personemail, personphone,
      categoryid, subcategoryid, assetid, operatorgroupid, operatorid, statusid, template_used, createdby
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  " );
  mysqli_stmt_bind_param(
    $stmt,
    'ssissiissiiiiiiii',
    $incident_number,
    $incident_type,
    $major_id,
    $title,
    $description,
    $customer_id,
    $person_id,
    $person_email,
    $person_phone,
    $category_id,
    $subcategory_id,
    $asset_id,
    $operatorgroup_id,
    $operator_id,
    $status_id,
    $template_used,
    $created_by
  );
  mysqli_stmt_execute( $stmt );
  $incident_id = mysqli_insert_id( $con );
  task_log_add( $con, 'incident', $incident_id, 'created', 'Incident aangemaakt via IMAP-import.', $created_by );
  mail_process_ticket_created( $con, 'incident', $incident_id );
  return $incident_id;
}

function imap_create_change_from_message( $con, $rule, $message, $created_by ) {
  $person = imap_find_person_by_email( $con, $message['from_email'] );
  $customer_id = $person ? (int)$person['customerid'] : ( !empty( $rule['fallback_customerid'] ) ? (int)$rule['fallback_customerid'] : null );
  $person_id = $person ? (int)$person['id'] : null;
  $person_email = $person ? (string)$person['email'] : $message['from_email'];
  $person_phone = $person ? (string)$person['phone'] : '';
  $category_id = !empty( $rule['categoryid'] ) ? (int)$rule['categoryid'] : null;
  $subcategory_id = !empty( $rule['subcategoryid'] ) ? (int)$rule['subcategoryid'] : null;
  $operatorgroup_id = !empty( $rule['operatorgroupid'] ) ? (int)$rule['operatorgroupid'] : null;
  $change_number = imap_generate_change_number( $con );
  $request_type = 'simple';
  $approval_state = 'request';
  $change_type = 'normal';
  $asset_id = null;
  $operator_id = null;
  $coordinator_id = null;
  $status_id = null;
  $template_used = null;
  $closed = 0;
  $title = $message['subject'] !== '' ? $message['subject'] : 'E-mail zonder onderwerp';
  $description = "Geimporteerd vanuit e-mailmap: " . $rule['folder'] . "\n\nVan: " . $message['from'] . "\nDatum: " . $message['date'] . "\nMessage-ID: " . $message['message_id'] . "\n\n" . $message['body'];

  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_cm_changes (
      changenumber, requesttype, approvalstate, changetype, title, description, customerid, personid, personemail, personphone,
      categoryid, subcategoryid, assetid, operatorgroupid, operatorid, coordinatorid, statusid, template_used, closed, createdby
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  " );
  mysqli_stmt_bind_param(
    $stmt,
    'ssssssiissiiiiiiiiii',
    $change_number,
    $request_type,
    $approval_state,
    $change_type,
    $title,
    $description,
    $customer_id,
    $person_id,
    $person_email,
    $person_phone,
    $category_id,
    $subcategory_id,
    $asset_id,
    $operatorgroup_id,
    $operator_id,
    $coordinator_id,
    $status_id,
    $template_used,
    $closed,
    $created_by
  );
  mysqli_stmt_execute( $stmt );
  $change_id = mysqli_insert_id( $con );
  task_log_add( $con, 'change', $change_id, 'created', 'Wijzigingsaanvraag aangemaakt via IMAP-import.', $created_by );
  mail_process_ticket_created( $con, 'change', $change_id );
  return $change_id;
}

function imap_message_already_imported( $con, $folder, $uid, $message_id ) {
  $stmt = mysqli_prepare( $con, "
    SELECT id
    FROM itsm_core_imap_imported
    WHERE folder = ? AND (uid = ? OR (messageid <> '' AND messageid = ?))
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 'sis', $folder, $uid, $message_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );
  return (bool)$row;
}

function imap_mark_message_imported( $con, $rule_id, $folder, $uid, $message_id, $task_type, $task_id ) {
  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_core_imap_imported (ruleid, folder, uid, messageid, tasktype, taskid)
    VALUES (?, ?, ?, ?, ?, ?)
  " );
  mysqli_stmt_bind_param( $stmt, 'isissi', $rule_id, $folder, $uid, $message_id, $task_type, $task_id );
  mysqli_stmt_execute( $stmt );
}

function imap_import_folder_rule( $con, $rule, $created_by, $limit = 25 ) {
  if ( !function_exists( 'imap_open' ) ) {
    return [ 'imported' => 0, 'errors' => [ 'PHP IMAP extensie is niet beschikbaar.' ] ];
  }

  $config = imap_load_config();
  if ( empty( $config['host'] ) || empty( $config['username'] ) ) {
    return [ 'imported' => 0, 'errors' => [ 'IMAP configuratie ontbreekt in ../config/imap.ini.' ] ];
  }

  $folder = (string)$rule['folder'];
  $mailbox = @imap_open( imap_mailbox_string( $config, $folder ), (string)$config['username'], (string)( $config['password'] ?? '' ) );
  if ( !$mailbox ) {
    return [ 'imported' => 0, 'errors' => [ 'Kan IMAP-map niet openen: ' . $folder . ' - ' . imap_last_error() ] ];
  }

  $message_numbers = imap_search( $mailbox, 'UNSEEN' );
  $imported = 0;
  $errors = [];
  if ( empty( $message_numbers ) ) {
    imap_close( $mailbox );
    return [ 'imported' => 0, 'errors' => [] ];
  }

  sort( $message_numbers );
  foreach ( array_slice( $message_numbers, 0, $limit ) as $message_number ) {
    $uid = (int)imap_uid( $mailbox, $message_number );
    $header = imap_headerinfo( $mailbox, $message_number );
    $message_id = trim( (string)( $header->message_id ?? '' ) );
    if ( imap_message_already_imported( $con, $folder, $uid, $message_id ) ) {
      continue;
    }

    $from = '';
    if ( !empty( $header->fromaddress ) ) {
      $from = imap_decode_header_text( $header->fromaddress );
    }
    $message = [
      'subject' => imap_decode_header_text( $header->subject ?? '' ),
      'from' => $from,
      'from_email' => imap_extract_email_address( $from ),
      'date' => (string)( $header->date ?? '' ),
      'message_id' => $message_id,
      'body' => imap_fetch_plain_body( $mailbox, $message_number )
    ];
    $attachments = imap_fetch_attachments( $mailbox, $message_number );

    try {
      $matched_task = imap_extract_task_match_from_subject( $con, $message['subject'] );
      if ( $matched_task ) {
        $linked = imap_attach_message_to_existing_task( $con, $matched_task, $rule, $message, $attachments, $created_by );
        $task_type = $linked['tasktype'];
        $task_id = $linked['taskid'];
      } elseif ( $rule['tasktype'] === 'incident' ) {
        $task_type = 'incident';
        $task_id = imap_create_incident_from_message( $con, $rule, $message, $created_by );
        imap_attach_attachments_to_task( $con, [ 'type' => $task_type, 'id' => $task_id ], $attachments, $created_by );
      } else {
        $task_type = 'change';
        $task_id = imap_create_change_from_message( $con, $rule, $message, $created_by );
        imap_attach_attachments_to_task( $con, [ 'type' => $task_type, 'id' => $task_id ], $attachments, $created_by );
      }
      imap_mark_message_imported( $con, (int)$rule['id'], $folder, $uid, $message_id, $task_type, $task_id );
      imap_setflag_full( $mailbox, (string)$message_number, '\\Seen' );
      $imported++;
    } catch ( Exception $exception ) {
      $errors[] = 'Bericht ' . $uid . ' importeren mislukt: ' . $exception->getMessage();
    }
  }

  imap_close( $mailbox );
  return [ 'imported' => $imported, 'errors' => $errors ];
}

function imap_run_all_imports( $con, $created_by, $limit_per_folder = 25 ) {
  $rules_result = mysqli_query( $con, "SELECT * FROM itsm_core_imap_rules WHERE active = 1 ORDER BY id ASC" );
  $summary = [];
  while ( $rule = mysqli_fetch_assoc( $rules_result ) ) {
    $summary[] = [
      'folder' => $rule['folder'],
      'tasktype' => $rule['tasktype'],
      'result' => imap_import_folder_rule( $con, $rule, $created_by, $limit_per_folder )
    ];
  }
  return $summary;
}
