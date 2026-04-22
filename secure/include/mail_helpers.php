<?php

require_once( __DIR__ . '/task_log_helpers.php' );

function mail_config_path() {
  return __DIR__ . '/../../../config/smtp.ini';
}

function mail_template_dir() {
  return __DIR__ . '/../itsm-config/templates/mail';
}

function mail_load_config() {
  $path = mail_config_path();
  if ( !is_readable( $path ) ) {
    return [];
  }

  $config = parse_ini_file( $path );
  return is_array( $config ) ? $config : [];
}

function mail_template_safe_path( $template_file ) {
  $template_file = basename( (string)$template_file );
  if ( $template_file === '' || !preg_match( '/^[a-zA-Z0-9._-]+\.html$/', $template_file ) ) {
    return '';
  }

  return mail_template_dir() . '/' . $template_file;
}

function mail_read_template( $template_file ) {
  $path = mail_template_safe_path( $template_file );
  if ( $path === '' || !is_readable( $path ) ) {
    return '';
  }

  return (string)file_get_contents( $path );
}

function mail_format_task_number( $row, $type ) {
  if ( $type === 'incident' ) {
    return $row['incidentnumber'] ?? ( '#' . $row['id'] );
  }
  if ( $type === 'change' ) {
    return $row['changenumber'] ?? ( '#' . $row['id'] );
  }
  if ( $type === 'problem' ) {
    return $row['problemnumber'] ?? ( '#' . $row['id'] );
  }

  return '#' . ( $row['id'] ?? '' );
}

function mail_task_url( $type, $id ) {
  $path = '/secure/index.php';
  if ( $type === 'incident' ) {
    $path = '/secure/edit_incident.php?id=' . (int)$id;
  } elseif ( $type === 'change' ) {
    $path = '/secure/edit_change.php?id=' . (int)$id;
  } elseif ( $type === 'problem' ) {
    $path = '/secure/edit_problem.php?id=' . (int)$id;
  }

  $scheme = !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? '';
  return $host !== '' ? $scheme . '://' . $host . $path : $path;
}

function mail_full_name( $firstname, $lastname ) {
  return trim( trim( (string)$firstname ) . ' ' . trim( (string)$lastname ) );
}

function mail_logged_in_operator_context( $con, $operator_id ) {
  $operator_id = (int)$operator_id;
  if ( $operator_id <= 0 ) {
    return [
      'logged_in_operator' => '',
      'logged_in_operator_email' => '',
      'logged_in_operator_group' => ''
    ];
  }

  $stmt = mysqli_prepare( $con, "
    SELECT o.firstname, o.lastname, o.email, g.groupname
    FROM itsm_ob_operators o
    LEFT JOIN itsm_ob_opgrouplinks l ON o.id = l.operatorid
    LEFT JOIN itsm_ob_operatorgroups g ON l.groupid = g.id
    WHERE o.id = ?
    ORDER BY g.groupname ASC, l.id ASC
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 'i', $operator_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$row ) {
    return [
      'logged_in_operator' => '',
      'logged_in_operator_email' => '',
      'logged_in_operator_group' => ''
    ];
  }

  return [
    'logged_in_operator' => mail_full_name( $row['firstname'] ?? '', $row['lastname'] ?? '' ),
    'logged_in_operator_email' => (string)( $row['email'] ?? '' ),
    'logged_in_operator_group' => (string)( $row['groupname'] ?? '' )
  ];
}

function mail_load_task_context( $con, $task_type, $task_id, $old_status_id, $new_status_id, $logged_in_operator_id = null ) {
  $queries = [
    'incident' => "
      SELECT i.*, c.name AS customer_name,
             p.firstname AS person_firstname, p.lastname AS person_lastname,
             p.email AS person_email_real, g.groupname,
             o.firstname AS operator_firstname, o.lastname AS operator_lastname, o.email AS operator_email
      FROM itsm_im_incidents i
      LEFT JOIN itsm_ob_customers c ON i.customerid = c.id
      LEFT JOIN itsm_ob_persons p ON i.personid = p.id
      LEFT JOIN itsm_ob_operatorgroups g ON i.operatorgroupid = g.id
      LEFT JOIN itsm_ob_operators o ON i.operatorid = o.id
      WHERE i.id = ?
    ",
    'change' => "
      SELECT ch.*, c.name AS customer_name,
             p.firstname AS person_firstname, p.lastname AS person_lastname,
             p.email AS person_email_real, g.groupname,
             o.firstname AS operator_firstname, o.lastname AS operator_lastname, o.email AS operator_email,
             co.firstname AS coordinator_firstname, co.lastname AS coordinator_lastname, co.email AS coordinator_email
      FROM itsm_cm_changes ch
      LEFT JOIN itsm_ob_customers c ON ch.customerid = c.id
      LEFT JOIN itsm_ob_persons p ON ch.personid = p.id
      LEFT JOIN itsm_ob_operatorgroups g ON ch.operatorgroupid = g.id
      LEFT JOIN itsm_ob_operators o ON ch.operatorid = o.id
      LEFT JOIN itsm_ob_operators co ON ch.coordinatorid = co.id
      WHERE ch.id = ?
    ",
    'problem' => "
      SELECT pr.*, c.name AS customer_name,
             p.firstname AS person_firstname, p.lastname AS person_lastname,
             p.email AS person_email_real, g.groupname,
             o.firstname AS operator_firstname, o.lastname AS operator_lastname, o.email AS operator_email
      FROM itsm_pm_problems pr
      LEFT JOIN itsm_ob_customers c ON pr.customerid = c.id
      LEFT JOIN itsm_ob_persons p ON pr.personid = p.id
      LEFT JOIN itsm_ob_operatorgroups g ON pr.operatorgroupid = g.id
      LEFT JOIN itsm_ob_operators o ON pr.operatorid = o.id
      WHERE pr.id = ?
    "
  ];

  if ( empty( $queries[$task_type] ) ) {
    return [];
  }

  $stmt = mysqli_prepare( $con, $queries[$task_type] );
  mysqli_stmt_bind_param( $stmt, 'i', $task_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$row ) {
    return [];
  }

  if ( empty( $new_status_id ) && isset( $row['statusid'] ) ) {
    $new_status_id = (int)$row['statusid'];
  }

  $old_status_name = mail_status_name( $con, $old_status_id );
  $new_status_name = mail_status_name( $con, $new_status_id );
  $task_number = mail_format_task_number( $row, $task_type );
  $latest_comment = mail_latest_comment( $con, $task_type, $task_id );
  $logged_in_operator = mail_logged_in_operator_context( $con, $logged_in_operator_id );
  $person_name = mail_full_name( $row['person_firstname'] ?? '', $row['person_lastname'] ?? '' );
  $operator_name = mail_full_name( $row['operator_firstname'] ?? '', $row['operator_lastname'] ?? '' );
  $coordinator_name = mail_full_name( $row['coordinator_firstname'] ?? '', $row['coordinator_lastname'] ?? '' );

  return [
    'task_type' => $task_type,
    'task_id' => (string)$task_id,
    'task_number' => $task_number,
    'task_url' => mail_task_url( $task_type, $task_id ),
    'title' => $row['title'] ?? '',
    'description' => $row['description'] ?? '',
    'latest_comment' => $latest_comment,
    'old_status_id' => (string)$old_status_id,
    'old_status_name' => $old_status_name,
    'status_id' => (string)$new_status_id,
    'status_name' => $new_status_name,
    'customer_name' => $row['customer_name'] ?? '',
    'firstname' => $row['person_firstname'] ?? '',
    'lastname' => $row['person_lastname'] ?? '',
    'person_name' => $person_name,
    'person_email' => $row['person_email_real'] ?? ( $row['personemail'] ?? '' ),
    'person_phone' => $row['personphone'] ?? '',
    'operator_name' => $operator_name,
    'operator_email' => $row['operator_email'] ?? '',
    'coordinator_name' => $coordinator_name,
    'coordinator_email' => $row['coordinator_email'] ?? '',
    'group_name' => $row['groupname'] ?? '',
    'operator_group' => $row['groupname'] ?? '',
    'logged_in_operator' => $logged_in_operator['logged_in_operator'],
    'logged_in_operator_email' => $logged_in_operator['logged_in_operator_email'],
    'logged_in_operator_group' => $logged_in_operator['logged_in_operator_group'],
    'createdat' => $row['createdat'] ?? '',
    'updatedat' => $row['updatedat'] ?? ''
  ];
}

function mail_latest_comment( $con, $task_type, $task_id ) {
  $comment_sources = [
    'incident' => [ 'table' => 'itsm_im_incidentcomments', 'task_column' => 'incidentid' ],
    'change' => [ 'table' => 'itsm_cm_changecomments', 'task_column' => 'changeid' ],
    'problem' => [ 'table' => 'itsm_pm_problemcomments', 'task_column' => 'problemid' ]
  ];

  if ( empty( $comment_sources[$task_type] ) ) {
    return '';
  }

  $source = $comment_sources[$task_type];
  $sql = "
    SELECT commenttext
    FROM " . $source['table'] . "
    WHERE " . $source['task_column'] . " = ?
      AND IFNULL(internalonly, 0) = 0
    ORDER BY createdat DESC, id DESC
    LIMIT 1
  ";
  $stmt = mysqli_prepare( $con, $sql );
  mysqli_stmt_bind_param( $stmt, 'i', $task_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $commenttext );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  return (string)($commenttext ?? '');
}

function mail_status_name( $con, $status_id ) {
  if ( empty( $status_id ) ) {
    return '';
  }
  $stmt = mysqli_prepare( $con, "SELECT name FROM itsm_core_status WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, 'i', $status_id );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $name );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );
  return (string)$name;
}

function mail_escape_html_variable( $value, $preserve_line_breaks = false ) {
  $escaped = htmlspecialchars( (string)$value, ENT_QUOTES, 'UTF-8' );
  if ( $preserve_line_breaks ) {
    return nl2br( $escaped );
  }

  return $escaped;
}

function mail_prepare_subject_variables( $variables ) {
  $prepared = [];
  foreach ( $variables as $key => $value ) {
    $prepared[$key] = preg_replace( "/\r\n|\r|\n/", ' ', (string)$value );
  }

  return $prepared;
}

function mail_prepare_body_variables( $variables ) {
  $prepared = [];
  $multiline_keys = [
    'description',
    'latest_comment'
  ];

  foreach ( $variables as $key => $value ) {
    $prepared[$key] = mail_escape_html_variable( $value, in_array( $key, $multiline_keys, true ) );
  }

  return $prepared;
}

function mail_apply_variables( $content, $variables ) {
  foreach ( $variables as $key => $value ) {
    $content = str_replace( '%' . $key . '%', (string)$value, $content );
    $content = str_replace( '{{' . $key . '}}', (string)$value, $content );
  }

  return $content;
}

function mail_recipients_for_rule( $rule, $variables ) {
  $recipients = [];
  $type = $rule['recipienttype'] ?? 'requester';

  if ( $type === 'requester' && !empty( $variables['person_email'] ) ) {
    $recipients[] = $variables['person_email'];
  } elseif ( $type === 'operator' && !empty( $variables['operator_email'] ) ) {
    $recipients[] = $variables['operator_email'];
  } elseif ( $type === 'coordinator' && !empty( $variables['coordinator_email'] ) ) {
    $recipients[] = $variables['coordinator_email'];
  } elseif ( $type === 'custom' ) {
    $custom = preg_split( '/[,;\r\n]+/', (string)( $rule['customrecipients'] ?? '' ) );
    foreach ( $custom as $recipient ) {
      $recipient = trim( $recipient );
      if ( $recipient !== '' ) {
        $recipients[] = $recipient;
      }
    }
  }

  return array_values( array_unique( array_filter( $recipients, 'filter_var_email' ) ) );
}

function filter_var_email( $value ) {
  return filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false;
}

function mail_smtp_command( $socket, $command, $expected_codes ) {
  if ( $command !== null ) {
    fwrite( $socket, $command . "\r\n" );
  }

  $response = '';
  while ( !feof( $socket ) ) {
    $line = fgets( $socket, 515 );
    if ( $line === false ) {
      break;
    }
    $response .= $line;
    if ( strlen( $line ) >= 4 && substr( $line, 3, 1 ) === ' ' ) {
      break;
    }
  }

  $code = (int)substr( $response, 0, 3 );
  if ( !in_array( $code, (array)$expected_codes, true ) ) {
    throw new Exception( 'SMTP fout: ' . trim( $response ) );
  }

  return $response;
}

function mail_smtp_send( $to, $subject, $html_body ) {
  $config = mail_load_config();
  if ( empty( $config['host'] ) || empty( $config['from_email'] ) ) {
    return false;
  }

  $host = (string)$config['host'];
  $port = (int)( $config['port'] ?? 587 );
  $encryption = strtolower( (string)( $config['encryption'] ?? 'tls' ) );
  $timeout = (int)( $config['timeout'] ?? 15 );
  $remote = $encryption === 'ssl' ? 'ssl://' . $host : $host;
  $socket = fsockopen( $remote, $port, $errno, $errstr, $timeout );

  if ( !$socket ) {
    return false;
  }

  try {
    stream_set_timeout( $socket, $timeout );
    mail_smtp_command( $socket, null, 220 );
    mail_smtp_command( $socket, 'EHLO ' . ( $_SERVER['SERVER_NAME'] ?? 'localhost' ), 250 );

    if ( $encryption === 'tls' ) {
      mail_smtp_command( $socket, 'STARTTLS', 220 );
      stream_socket_enable_crypto( $socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );
      mail_smtp_command( $socket, 'EHLO ' . ( $_SERVER['SERVER_NAME'] ?? 'localhost' ), 250 );
    }

    if ( !empty( $config['username'] ) ) {
      mail_smtp_command( $socket, 'AUTH LOGIN', 334 );
      mail_smtp_command( $socket, base64_encode( (string)$config['username'] ), 334 );
      mail_smtp_command( $socket, base64_encode( (string)( $config['password'] ?? '' ) ), 235 );
    }

    $from_email = (string)$config['from_email'];
    $from_name = trim( (string)( $config['from_name'] ?? 'ITSM' ) );
    $encoded_from_name = function_exists( 'mb_encode_mimeheader' ) ? mb_encode_mimeheader( $from_name ) : $from_name;
    $encoded_subject = function_exists( 'mb_encode_mimeheader' ) ? mb_encode_mimeheader( $subject ) : $subject;
    $headers = [
      'From: ' . ( $from_name !== '' ? $encoded_from_name . ' <' . $from_email . '>' : $from_email ),
      'To: ' . implode( ', ', $to ),
      'Subject: ' . $encoded_subject,
      'MIME-Version: 1.0',
      'Content-Type: text/html; charset=UTF-8',
      'Content-Transfer-Encoding: 8bit'
    ];
    $message = implode( "\r\n", $headers ) . "\r\n\r\n" . $html_body;
    $message = preg_replace( "/^\./m", '..', $message );

    mail_smtp_command( $socket, 'MAIL FROM:<' . $from_email . '>', 250 );
    foreach ( $to as $recipient ) {
      mail_smtp_command( $socket, 'RCPT TO:<' . $recipient . '>', [ 250, 251 ] );
    }
    mail_smtp_command( $socket, 'DATA', 354 );
    mail_smtp_command( $socket, $message . "\r\n.", 250 );
    mail_smtp_command( $socket, 'QUIT', 221 );
    fclose( $socket );
    return true;
  } catch ( Exception $exception ) {
    fclose( $socket );
    return false;
  }
}

function mail_send_rule( $con, $rule, $variables, $operator_id = null, $manual = false ) {
  $template = mail_read_template( $rule['templatefile'] ?? '' );
  if ( $template === '' ) {
    return [ 'ok' => false, 'message' => 'Template niet gevonden of niet leesbaar.' ];
  }

  $recipients = mail_recipients_for_rule( $rule, $variables );
  if ( empty( $recipients ) ) {
    return [ 'ok' => false, 'message' => 'Geen geldige ontvangers gevonden voor deze regel.' ];
  }

  $subject = mail_apply_variables( $rule['subject'] ?? '', mail_prepare_subject_variables( $variables ) );
  $body = mail_apply_variables( $template, mail_prepare_body_variables( $variables ) );
  $final_subject = $subject !== '' ? $subject : 'ITSM ' . $variables['task_number'];
  if ( !mail_smtp_send( $recipients, $final_subject, $body ) ) {
    return [ 'ok' => false, 'message' => 'E-mail verzenden mislukt. Controleer SMTP-instellingen en template.' ];
  }

  $log_message = ( $manual ? 'E-mail handmatig verzonden naar ' : 'E-mail verzonden naar ' ) . implode( ', ', $recipients ) . ' met template ' . ( $rule['templatefile'] ?? '' ) . '.';
  task_log_add(
    $con,
    $variables['task_type'] ?? '',
    (int)($variables['task_id'] ?? 0),
    'email',
    $log_message,
    $operator_id,
    null,
    $final_subject
  );

  return [ 'ok' => true, 'message' => 'E-mail verzonden naar ' . implode( ', ', $recipients ) . '.' ];
}

function mail_dispatch_rules( $con, $rules, $variables, $operator_id = null ) {
  foreach ( $rules as $rule ) {
    mail_send_rule( $con, $rule, $variables, $operator_id, false );
  }
}

function mail_load_manual_rules( $con, $task_type ) {
  $stmt = mysqli_prepare( $con, "
    SELECT
      r.*,
      fs.name AS from_status_name,
      ts.name AS to_status_name
    FROM itsm_core_mailrules r
    LEFT JOIN itsm_core_status fs ON r.fromstatusid = fs.id
    LEFT JOIN itsm_core_status ts ON r.tostatusid = ts.id
    WHERE r.active = 1
      AND r.tasktype = ?
    ORDER BY r.templatefile ASC, r.subject ASC, r.id ASC
  " );
  mysqli_stmt_bind_param( $stmt, 's', $task_type );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rules = [];
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $rules[] = $row;
  }
  mysqli_stmt_close( $stmt );

  return $rules;
}

function mail_manual_rule_label( $rule ) {
  $trigger = $rule['triggertype'] ?? '';
  if ( $trigger === 'created' ) {
    $trigger_label = 'Bij aanmaak';
  } elseif ( $trigger === 'statuschange' ) {
    $from = $rule['from_status_name'] ?? '';
    $to = $rule['to_status_name'] ?? '';
    $trigger_label = 'Status: ' . ( $from !== '' ? $from : 'elke status' ) . ' -> ' . ( $to !== '' ? $to : 'onbekend' );
  } else {
    $trigger_label = $trigger !== '' ? $trigger : 'Handmatig';
  }

  $subject = trim( (string)( $rule['subject'] ?? '' ) );
  $template = trim( (string)( $rule['templatefile'] ?? '' ) );
  $parts = [ $trigger_label ];
  if ( $subject !== '' ) {
    $parts[] = $subject;
  }
  if ( $template !== '' ) {
    $parts[] = $template;
  }

  return implode( ' - ', $parts );
}

function mail_send_manual_rule( $con, $rule_id, $task_type, $task_id, $operator_id ) {
  $stmt = mysqli_prepare( $con, "
    SELECT *
    FROM itsm_core_mailrules
    WHERE id = ?
      AND active = 1
      AND tasktype = ?
    LIMIT 1
  " );
  mysqli_stmt_bind_param( $stmt, 'is', $rule_id, $task_type );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rule = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$rule ) {
    return [ 'ok' => false, 'message' => 'Mailregel niet gevonden of niet actief.' ];
  }

  $variables = mail_load_task_context( $con, $task_type, $task_id, null, null, $operator_id );
  if ( empty( $variables ) ) {
    return [ 'ok' => false, 'message' => 'Taakgegevens konden niet geladen worden.' ];
  }

  return mail_send_rule( $con, $rule, $variables, $operator_id, true );
}

function mail_render_manual_tab( $rules, $messages = [] ) {
  ob_start();
  ?>
  <h2>E-mail</h2>
  <p>Kies een actieve e-mailregel om deze handmatig voor deze taak uit te voeren.</p>
  <?php foreach ( $messages as $message ): ?>
    <?php $class = !empty( $message['ok'] ) ? 'success' : 'error'; ?>
    <p class="<?= $class ?>"><?= htmlspecialchars( $message['message'] ?? '' ) ?></p>
  <?php endforeach; ?>
  <?php if ( empty( $rules ) ): ?>
    <p>Er zijn geen actieve e-mailregels voor deze taaksoort.</p>
  <?php else: ?>
    <form method="post" class="manual-mail-rule-form">
      <div class="form-group">
        <label>E-mailregel</label>
        <label>
          <select name="manual_mail_rule_id" required>
            <option value="">Selecteer een e-mailregel</option>
            <?php foreach ( $rules as $rule ): ?>
            <option value="<?= htmlspecialchars( (string)$rule['id'] ) ?>"><?= htmlspecialchars( mail_manual_rule_label( $rule ) ) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <button type="submit" class="btn-primary" formnovalidate>E-mail verzenden</button>
    </form>
    <hr>
    <h3>Variabelen</h3>
    <p class="muted">Deze variabelen kun je in het onderwerp en HTML-template gebruiken met <code>%variable%</code> of <code>{{variable}}</code>.</p>
    <div class="mail-variable-list">
      <?php foreach ( [ 'task_number', 'task_url', 'title', 'description', 'latest_comment', 'status_name', 'customer_name', 'firstname', 'lastname', 'person_name', 'person_email', 'person_phone', 'operator_name', 'operator_email', 'coordinator_name', 'coordinator_email', 'group_name', 'operator_group', 'logged_in_operator', 'logged_in_operator_email', 'logged_in_operator_group', 'createdat', 'updatedat' ] as $variable ): ?>
      <code>%<?= htmlspecialchars( $variable ) ?>%</code>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php
  return ob_get_clean();
}

function mail_process_status_change( $con, $task_type, $task_id, $old_status_id, $new_status_id, $logged_in_operator_id = null ) {
  if ( (int)$old_status_id === (int)$new_status_id ) {
    return;
  }

  $stmt = mysqli_prepare( $con, "
    SELECT *
    FROM itsm_core_mailrules
    WHERE active = 1
      AND triggertype = 'statuschange'
      AND tasktype = ?
      AND (fromstatusid IS NULL OR fromstatusid = ?)
      AND tostatusid = ?
    ORDER BY id ASC
  " );
  mysqli_stmt_bind_param( $stmt, 'sii', $task_type, $old_status_id, $new_status_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rules = [];
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $rules[] = $row;
  }
  mysqli_stmt_close( $stmt );

  if ( empty( $rules ) ) {
    return;
  }

  $variables = mail_load_task_context( $con, $task_type, $task_id, $old_status_id, $new_status_id, $logged_in_operator_id );
  if ( empty( $variables ) ) {
    return;
  }

  mail_dispatch_rules( $con, $rules, $variables, $logged_in_operator_id );
}

function mail_process_ticket_created( $con, $task_type, $task_id, $logged_in_operator_id = null ) {
  $stmt = mysqli_prepare( $con, "
    SELECT *
    FROM itsm_core_mailrules
    WHERE active = 1
      AND triggertype = 'created'
      AND tasktype = ?
    ORDER BY id ASC
  " );
  mysqli_stmt_bind_param( $stmt, 's', $task_type );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rules = [];
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $rules[] = $row;
  }
  mysqli_stmt_close( $stmt );

  if ( empty( $rules ) ) {
    return;
  }

  $variables = mail_load_task_context( $con, $task_type, $task_id, null, null, $logged_in_operator_id );
  if ( empty( $variables ) ) {
    return;
  }

  mail_dispatch_rules( $con, $rules, $variables, $logged_in_operator_id );
}
