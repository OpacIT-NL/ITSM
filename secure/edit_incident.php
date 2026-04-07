<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );
require_once( __DIR__ . '/include/task_helpers.php' );
require_once( __DIR__ . '/include/mail_helpers.php' );
require_once( __DIR__ . '/include/attachment_helpers.php' );
require_once( __DIR__ . '/include/form_presence_helpers.php' );
require_once( __DIR__ . '/include/task_log_helpers.php' );

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  die( 'Invalid ID' );
}

$logged_in_user = $_SESSION[ 'name' ];
incident_require_firstline_authorization( $con, $logged_in_user );
$operator_context = incident_get_operator_context( $con, $logged_in_user );
incident_require_access( $operator_context );
$incident_id = (int)$_GET['id'];

$stmt = mysqli_prepare( $con, "
        SELECT i.*, s.name AS statusname
        FROM itsm_im_incidents i
        LEFT JOIN itsm_core_status s ON i.statusid = s.id
        WHERE i.id = ?
    " );
mysqli_stmt_bind_param( $stmt, "i", $incident_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$incident = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$incident ) {
  die( 'Incident niet gevonden' );
}

if ( isset( $_GET['set_major'] ) && is_numeric( $_GET['set_major'] ) && $incident['incidenttype'] !== 'major' ) {
  $set_major_id = (int)$_GET['set_major'];
  $major_stmt = mysqli_prepare( $con, "SELECT id FROM itsm_im_incidents WHERE id = ? AND incidenttype = 'major'" );
  mysqli_stmt_bind_param( $major_stmt, "i", $set_major_id );
  mysqli_stmt_execute( $major_stmt );
  $major_result = mysqli_stmt_get_result( $major_stmt );
  $major_row = mysqli_fetch_assoc( $major_result );
  mysqli_stmt_close( $major_stmt );

  if ( $major_row ) {
    $link_stmt = mysqli_prepare( $con, "UPDATE itsm_im_incidents SET majorincidentid = ? WHERE id = ?" );
    mysqli_stmt_bind_param( $link_stmt, "ii", $set_major_id, $incident_id );
    mysqli_stmt_execute( $link_stmt );
    header( 'Location: edit_incident.php?id=' . $set_major_id );
    exit;
  }
}

if ( isset( $_GET['set_problem'] ) && is_numeric( $_GET['set_problem'] ) ) {
  $set_problem_id = (int)$_GET['set_problem'];
  $problem_stmt = mysqli_prepare( $con, "SELECT id FROM itsm_pm_problems WHERE id = ?" );
  mysqli_stmt_bind_param( $problem_stmt, "i", $set_problem_id );
  mysqli_stmt_execute( $problem_stmt );
  $problem_result = mysqli_stmt_get_result( $problem_stmt );
  $problem_row = mysqli_fetch_assoc( $problem_result );
  mysqli_stmt_close( $problem_stmt );
  if ( $problem_row ) {
    task_create_link( $con, 'incident', $incident_id, 'Behoort bij problem', 'problem', $set_problem_id, (int)$operator_context['id'] );
    task_log_add( $con, 'incident', $incident_id, 'link_created', 'Incident gekoppeld aan problem #' . $set_problem_id . '.', (int)$operator_context['id'] );
    header( 'Location: edit_problem.php?id=' . $set_problem_id );
    exit;
  }
}

$reference_data = incident_load_reference_data( $con );
$errors = [];
$mail_messages = $_SESSION['manual_mail_messages'] ?? [];
unset( $_SESSION['manual_mail_messages'] );
$presence_error = form_presence_flash_error();
if ( $presence_error !== '' ) {
  $errors[] = $presence_error;
}
$edit_comment = null;

if ( isset( $_POST['manual_mail_rule_id'] ) && is_numeric( $_POST['manual_mail_rule_id'] ) ) {
  $manual_mail_rule_id = (int)$_POST['manual_mail_rule_id'];
  $_SESSION['manual_mail_messages'] = [
    mail_send_manual_rule( $con, $manual_mail_rule_id, 'incident', $incident_id, (int)$operator_context['id'] )
  ];
  header( 'Location: edit_incident.php?id=' . $incident_id );
  exit;
}

if ( isset( $_POST['delete_comment_id'] ) && is_numeric( $_POST['delete_comment_id'] ) ) {
  $delete_comment_id = (int)$_POST['delete_comment_id'];
  $deleted_comment_text = '';
  $deleted_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_im_incidentcomments WHERE id = ? AND incidentid = ?" );
  mysqli_stmt_bind_param( $deleted_comment_stmt, "ii", $delete_comment_id, $incident_id );
  mysqli_stmt_execute( $deleted_comment_stmt );
  mysqli_stmt_bind_result( $deleted_comment_stmt, $deleted_comment_text );
  mysqli_stmt_fetch( $deleted_comment_stmt );
  mysqli_stmt_close( $deleted_comment_stmt );
  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_im_incidentcomments WHERE id = ? AND incidentid = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "ii", $delete_comment_id, $incident_id );
  mysqli_stmt_execute( $delete_stmt );
  task_log_add( $con, 'incident', $incident_id, 'updated', 'Commentaar verwijderd: "' . task_log_text_snippet( $deleted_comment_text ) . '".', (int)$operator_context['id'], task_log_text_snippet( $deleted_comment_text ), null );
  header( 'Location: edit_incident.php?id=' . $incident_id );
  exit;
}
if ( isset( $_POST['delete_link_id'] ) && is_numeric( $_POST['delete_link_id'] ) ) {
  task_delete_link( $con, (int)$_POST['delete_link_id'] );
  header( 'Location: edit_incident.php?id=' . $incident_id );
  exit;
}
if ( isset( $_POST['add_task_link'] ) ) {
  $relation = trim( $_POST['link_relationtype'] ?? '' );
  $tasknumber = trim( $_POST['link_tasknumber'] ?? '' );
  if ( $relation === '' || $tasknumber === '' ) {
    $errors[] = 'Selecteer een linktype en vul een taaknummer in.';
  } else {
    $target = task_find_by_number( $con, $tasknumber, 'secure' );
    if ( !$target ) {
      $errors[] = 'Taaknummer niet gevonden.';
    } elseif ( $target['type'] === 'incident' && (int)$target['id'] === $incident_id ) {
      $errors[] = 'Een incident kan niet aan zichzelf gekoppeld worden.';
    } else {
      task_create_link( $con, 'incident', $incident_id, $relation, $target['type'], (int)$target['id'], (int)$operator_context['id'] );
      task_log_add( $con, 'incident', $incident_id, 'link_created', 'Link toegevoegd: ' . $relation . ' ' . $target['type'] . ' #' . (int)$target['id'] . '.', (int)$operator_context['id'] );
      header( 'Location: edit_incident.php?id=' . $incident_id );
      exit;
    }
  }
}

$validation_seed = incident_validate_form(
  [
    'title' => $incident['title'],
    'customerid' => (int)$incident['customerid'],
    'personid' => (int)$incident['personid'],
    'categoryid' => (int)$incident['categoryid'],
    'subcategoryid' => (int)$incident['subcategoryid'],
    'assetid' => (int)$incident['assetid'],
    'majorincidentid' => (int)$incident['majorincidentid'],
    'operatorgroupid' => (int)$incident['operatorgroupid'],
    'operatorid' => (int)$incident['operatorid'],
    'statusid' => (int)$incident['statusid'],
    'impactid' => (int)($incident['impactid'] ?? 0),
    'urgencyid' => (int)($incident['urgencyid'] ?? 0),
    'incidenttype' => $incident['incidenttype'],
    'current_incident_id' => $incident_id
  ],
  $reference_data
);

if ( isset( $_GET['edit_comment'] ) && is_numeric( $_GET['edit_comment'] ) ) {
  $edit_comment_id = (int)$_GET['edit_comment'];
  $comment_stmt = mysqli_prepare( $con, "SELECT * FROM itsm_im_incidentcomments WHERE id = ? AND incidentid = ?" );
  mysqli_stmt_bind_param( $comment_stmt, "ii", $edit_comment_id, $incident_id );
  mysqli_stmt_execute( $comment_stmt );
  $comment_result = mysqli_stmt_get_result( $comment_stmt );
  $edit_comment = mysqli_fetch_assoc( $comment_result );
  mysqli_stmt_close( $comment_stmt );
}

$form_values = [
  'commentid' => $edit_comment ? (string)$edit_comment['id'] : '',
  'title' => $incident['title'],
  'description' => $incident['description'],
  'commenttext' => $edit_comment['commenttext'] ?? '',
  'internalonly' => isset( $edit_comment['internalonly'] ) ? (int)$edit_comment['internalonly'] : 0,
  'customerid' => (string)$incident['customerid'],
  'personid' => (string)$incident['personid'],
  'personemail' => $validation_seed['person']['email'] ?? $incident['personemail'],
  'personphone' => $validation_seed['person']['phone'] ?? $incident['personphone'],
  'categoryid' => (string)$incident['categoryid'],
  'subcategoryid' => (string)$incident['subcategoryid'],
  'assetid' => (string)$incident['assetid'],
  'assettype' => $validation_seed['asset']['typename'] ?? '',
  'majorincidentid' => (string)$incident['majorincidentid'],
  'operatorgroupid' => (string)$incident['operatorgroupid'],
  'operatorid' => (string)$incident['operatorid'],
  'statusid' => (string)$incident['statusid'],
  'statusready' => isset( $validation_seed['status']['ready'] ) ? (int)$validation_seed['status']['ready'] : 0,
  'statusclosed' => isset( $validation_seed['status']['closed'] ) ? (int)$validation_seed['status']['closed'] : 0,
  'impactid' => (string)($incident['impactid'] ?? ''),
  'urgencyid' => (string)($incident['urgencyid'] ?? ''),
  'priorityid' => (string)($incident['priorityid'] ?? ''),
  'priorityname' => priority_name_by_id( $reference_data, $incident['priorityid'] ?? null ),
  'applied_template_id' => '',
  'template_used' => (string)($incident['template_used'] ?? '')
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['add_task_link'] ) && !isset( $_POST['manual_mail_rule_id'] ) ) {
  form_presence_redirect_if_stale( $con, 'incident', $incident_id, $_POST['presence_token'] ?? '', 'edit_incident.php?id=' . $incident_id );
  $form_values = [
    'commentid' => $_POST['commentid'] ?? '',
    'title' => trim( $_POST['title'] ?? '' ),
    'description' => trim( $_POST['description'] ?? '' ),
    'commenttext' => trim( $_POST['commenttext'] ?? '' ),
    'internalonly' => isset( $_POST['internalonly'] ) ? 1 : 0,
    'customerid' => $_POST['customerid'] ?? '',
    'personid' => $_POST['personid'] ?? '',
    'personemail' => '',
    'personphone' => '',
    'categoryid' => $_POST['categoryid'] ?? '',
    'subcategoryid' => $_POST['subcategoryid'] ?? '',
    'assetid' => $_POST['assetid'] ?? '',
    'assettype' => '',
    'majorincidentid' => $_POST['majorincidentid'] ?? '',
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? '',
    'statusready' => 0,
    'statusclosed' => 0,
    'impactid' => $_POST['impactid'] ?? '',
    'urgencyid' => $_POST['urgencyid'] ?? '',
    'priorityid' => '',
    'priorityname' => '',
    'applied_template_id' => $_POST['applied_template_id'] ?? '',
    'template_used' => ( $incident['template_used'] ?? '' ) !== '' ? (string)$incident['template_used'] : ( $_POST['applied_template_id'] ?? '' )
  ];

  $action = $_POST['incident_action'] ?? 'save';
  $new_mode = $incident['incidenttype'];
  if ( $action === 'escalate' && $incident['incidenttype'] === 'firstline' ) {
    $new_mode = 'secondline';
  } elseif ( $action === 'deescalate' && $incident['incidenttype'] === 'secondline' ) {
    $new_mode = 'firstline';
  } elseif ( $action === 'major' && in_array( $incident['incidenttype'], [ 'firstline', 'secondline' ], true ) ) {
    $new_mode = 'major';
    $form_values['majorincidentid'] = '';
  }

  $validation = incident_validate_form(
    [
      'title' => $form_values['title'],
      'customerid' => (int)$form_values['customerid'],
      'personid' => (int)$form_values['personid'],
      'categoryid' => (int)$form_values['categoryid'],
      'subcategoryid' => (int)$form_values['subcategoryid'],
      'assetid' => (int)$form_values['assetid'],
      'majorincidentid' => (int)$form_values['majorincidentid'],
      'operatorgroupid' => (int)$form_values['operatorgroupid'],
      'operatorid' => (int)$form_values['operatorid'],
      'statusid' => (int)$form_values['statusid'],
      'impactid' => (int)$form_values['impactid'],
      'urgencyid' => (int)$form_values['urgencyid'],
      'incidenttype' => $new_mode,
      'current_incident_id' => $incident_id
    ],
    $reference_data
  );

  $errors = $validation['errors'];

  if ( $validation['person'] ) {
    $form_values['personemail'] = $validation['person']['email'] ?? '';
    $form_values['personphone'] = $validation['person']['phone'] ?? '';
  }
  if ( $validation['asset'] ) {
    $form_values['assettype'] = $validation['asset']['typename'] ?? '';
  }
  if ( $validation['status'] ) {
    $form_values['statusready'] = (int)$validation['status']['ready'];
    $form_values['statusclosed'] = (int)$validation['status']['closed'];
  }
  $form_values['priorityid'] = $validation['priority']['priorityid'] ? (string)$validation['priority']['priorityid'] : '';
  $form_values['priorityname'] = priority_name_by_id( $reference_data, $validation['priority']['priorityid'] );
  $errors = array_merge( $errors, attachment_upload_errors() );
  $presence_check = form_presence_check_before_save( $con, 'incident', $incident_id, $_POST['presence_token'] ?? '' );
  if ( !$presence_check['ok'] ) {
    $errors[] = $presence_check['message'];
  }

  if ( empty( $errors ) ) {
    $customer_id = (int)$validation['customer']['id'];
    $person_id = (int)$validation['person']['id'];
    $category_id = (int)$validation['category']['id'];
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $asset_id = $validation['asset'] ? (int)$validation['asset']['id'] : null;
    $major_incident_id = $validation['major_incident'] ? (int)$validation['major_incident']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $assigned_operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $old_status_id = (int)$incident['statusid'];
    $status_id = (int)$validation['status']['id'];
    $impact_id = $validation['priority']['impactid'];
    $urgency_id = $validation['priority']['urgencyid'];
    $priority_id = $validation['priority']['priorityid'];
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $template_used = $form_values['template_used'] !== '' ? (int)$form_values['template_used'] : null;

    $update_stmt = mysqli_prepare( $con, "
            UPDATE itsm_im_incidents SET
                incidenttype = ?,
                majorincidentid = ?,
                title = ?,
                description = ?,
                customerid = ?,
                personid = ?,
                personemail = ?,
                personphone = ?,
                categoryid = ?,
                subcategoryid = ?,
                assetid = ?,
                operatorgroupid = ?,
                operatorid = ?,
                statusid = ?,
                impactid = ?,
                urgencyid = ?,
                priorityid = ?,
                template_used = ?
            WHERE id = ?
        " );
    mysqli_stmt_bind_param(
      $update_stmt,
      "sissiissiiiiiiiiiii",
      $new_mode,
      $major_incident_id,
      $form_values['title'],
      $form_values['description'],
      $customer_id,
      $person_id,
      $person_email,
      $person_phone,
      $category_id,
      $subcategory_id,
      $asset_id,
      $group_id,
      $assigned_operator_id,
      $status_id,
      $impact_id,
      $urgency_id,
      $priority_id,
      $template_used,
      $incident_id
    );

    if ( !mysqli_stmt_execute( $update_stmt ) ) {
      $errors[] = 'Incident bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
    } else {
      form_presence_mark_saved( $con, 'incident', $incident_id, (int)$operator_context['id'] );
      task_log_add( $con, 'incident', $incident_id, 'updated', 'Incident opgeslagen.', (int)$operator_context['id'] );
      if ( $new_mode !== $incident['incidenttype'] ) {
        task_log_add( $con, 'incident', $incident_id, 'updated', 'Incidentsoort gewijzigd van ' . incident_mode_label( $incident['incidenttype'] ) . ' naar ' . incident_mode_label( $new_mode ) . '.', (int)$operator_context['id'], incident_mode_label( $incident['incidenttype'] ), incident_mode_label( $new_mode ) );
      }
      task_log_field_changes(
        $con,
        'incident',
        $incident_id,
        [
          'title' => $incident['title'],
          'description' => $incident['description'],
          'majorincidentid' => $incident['majorincidentid'],
          'customerid' => $incident['customerid'],
          'personid' => $incident['personid'],
          'categoryid' => $incident['categoryid'],
          'subcategoryid' => $incident['subcategoryid'],
          'assetid' => $incident['assetid'],
          'operatorgroupid' => $incident['operatorgroupid'],
          'operatorid' => $incident['operatorid'],
          'impactid' => $incident['impactid'] ?? null,
          'urgencyid' => $incident['urgencyid'] ?? null,
          'priorityid' => $incident['priorityid'] ?? null
        ],
        [
          'title' => $form_values['title'],
          'description' => $form_values['description'],
          'majorincidentid' => $major_incident_id,
          'customerid' => $customer_id,
          'personid' => $person_id,
          'categoryid' => $category_id,
          'subcategoryid' => $subcategory_id,
          'assetid' => $asset_id,
          'operatorgroupid' => $group_id,
          'operatorid' => $assigned_operator_id,
          'impactid' => $impact_id,
          'urgencyid' => $urgency_id,
          'priorityid' => $priority_id
        ],
        [
          'title' => 'Titel',
          'description' => 'Omschrijving',
          'majorincidentid' => 'Major incident',
          'customerid' => 'Klant',
          'personid' => 'Persoon',
          'categoryid' => 'Categorie',
          'subcategoryid' => 'Subcategorie',
          'assetid' => 'Object ID',
          'operatorgroupid' => 'Behandelaarsgroep',
          'operatorid' => 'Behandelaar',
          'impactid' => 'Impact',
          'urgencyid' => 'Urgency',
          'priorityid' => 'Priority'
        ],
        (int)$operator_context['id']
      );
      if ( $form_values['commenttext'] !== '' || attachment_uploaded_file_available() ) {
        $attachment_comment_id = null;
        if ( !empty( $form_values['commentid'] ) && is_numeric( $form_values['commentid'] ) ) {
          $comment_id = (int)$form_values['commentid'];
          $old_comment_text = '';
          $old_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_im_incidentcomments WHERE id = ? AND incidentid = ?" );
          mysqli_stmt_bind_param( $old_comment_stmt, "ii", $comment_id, $incident_id );
          mysqli_stmt_execute( $old_comment_stmt );
          mysqli_stmt_bind_result( $old_comment_stmt, $old_comment_text );
          mysqli_stmt_fetch( $old_comment_stmt );
          mysqli_stmt_close( $old_comment_stmt );
          $attachment_comment_id = $comment_id;
          $comment_stmt = mysqli_prepare( $con, "
                    UPDATE itsm_im_incidentcomments
                    SET commenttext = ?, internalonly = ?
                    WHERE id = ? AND incidentid = ?
                " );
          mysqli_stmt_bind_param(
            $comment_stmt,
            "siii",
            $form_values['commenttext'],
            $form_values['internalonly'],
            $comment_id,
            $incident_id
          );
        } else {
          $comment_stmt = mysqli_prepare( $con, "
                    INSERT INTO itsm_im_incidentcomments (
                        incidentid, operatorid, personid, commenttext, internalonly
                    ) VALUES (?, ?, NULL, ?, ?)
                " );
          $operator_id_for_comment = (int)$operator_context['id'];
          $comment_text = $form_values['commenttext'] !== '' ? $form_values['commenttext'] : 'Bijlage toegevoegd.';
          mysqli_stmt_bind_param(
            $comment_stmt,
            "iisi",
            $incident_id,
            $operator_id_for_comment,
            $comment_text,
            $form_values['internalonly']
          );
        }
        mysqli_stmt_execute( $comment_stmt );
        if ( empty( $attachment_comment_id ) ) {
          $attachment_comment_id = mysqli_insert_id( $con );
          task_log_add( $con, 'incident', $incident_id, 'updated', 'Commentaar toegevoegd: "' . task_log_text_snippet( $comment_text ) . '".', (int)$operator_context['id'], null, task_log_text_snippet( $comment_text ) );
        } else {
          task_log_add( $con, 'incident', $incident_id, 'updated', 'Commentaar bijgewerkt van "' . task_log_text_snippet( $old_comment_text ?? '' ) . '" naar "' . task_log_text_snippet( $form_values['commenttext'] ) . '".', (int)$operator_context['id'], task_log_text_snippet( $old_comment_text ?? '' ), task_log_text_snippet( $form_values['commenttext'] ) );
        }
        attachment_save_upload( $con, 'incident', $incident_id, (int)$operator_context['id'], $form_values['internalonly'], 'incidentcomment', $attachment_comment_id );
      }
      mail_process_status_change( $con, 'incident', $incident_id, $old_status_id, $status_id );
      task_log_status_change( $con, 'incident', $incident_id, $old_status_id, $status_id, (int)$operator_context['id'] );

      header( 'Location: edit_incident.php?id=' . $incident_id );
      exit;
    }
  }
}

$comments_result = mysqli_query( $con, "
    SELECT
      c.*,
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name,
      CONCAT(p.lastname, ', ', p.firstname) AS person_name
    FROM itsm_im_incidentcomments c
    LEFT JOIN itsm_ob_operators o ON c.operatorid = o.id
    LEFT JOIN itsm_ob_persons p ON c.personid = p.id
    WHERE c.incidentid = " . $incident_id . "
    ORDER BY c.createdat DESC, c.id DESC
" );
$comments = [];
while ( $row = mysqli_fetch_assoc( $comments_result ) ) {
  if ( (int)($row['personid'] ?? 0) > 0 ) {
    $row['operator_name'] = $row['person_name'] ?: 'Klant';
  } else {
    $row['operator_name'] = $row['operator_name'] ?: 'Onbekend';
  }
  $comments[] = $row;
}
$attachments = attachment_load_for_task( $con, 'incident', $incident_id );
$attachments_by_comment = attachment_group_by_comment( $attachments );
$attachments_html = attachment_render_as_comments( $attachments );

$linked_incidents = [];
if ( $incident['incidenttype'] === 'major' ) {
  $linked_result = mysqli_query( $con, "
      SELECT i.id, i.incidentnumber, i.incidenttype, i.title, s.name AS status_name
      FROM itsm_im_incidents i
      LEFT JOIN itsm_core_status s ON i.statusid = s.id
      WHERE i.majorincidentid = " . $incident_id . "
      ORDER BY i.updatedat DESC, i.id DESC
  " );
  while ( $row = mysqli_fetch_assoc( $linked_result ) ) {
    $linked_incidents[] = $row;
  }
}

$page_title = incident_mode_label( $incident['incidenttype'] ) . ' ' . incident_format_display_number( $incident );
$tab_title = incident_format_display_number( $incident );
$tab_subtitle = incident_mode_label( $incident['incidenttype'] );
$submit_label = 'Incident opslaan';
$show_history = true;
$show_linked_incidents = $incident['incidenttype'] === 'major';
$mode_label = incident_mode_label( $new_mode ?? $incident['incidenttype'] );
$list_back_url = incident_get_list_back_url( 'incidents.php?view=all' );
$current_mode = $new_mode ?? $incident['incidenttype'];
$show_major_link_control = $current_mode !== 'major';
$action_links = [];
$action_buttons = [];
if ( $incident['incidenttype'] === 'firstline' ) {
  $action_buttons[] = [ 'value' => 'escalate', 'label' => 'Escaleren' ];
  $action_buttons[] = [ 'value' => 'major', 'label' => 'Major aanmaken' ];
} elseif ( $incident['incidenttype'] === 'secondline' ) {
  $action_buttons[] = [ 'value' => 'deescalate', 'label' => 'De-escaleren' ];
  $action_buttons[] = [ 'value' => 'major', 'label' => 'Major aanmaken' ];
} elseif ( $incident['incidenttype'] === 'major' ) {
  $action_links[] = [
    'href' => 'incidents.php?view=all&major_target=' . $incident_id,
    'label' => 'Incidenten koppelen'
  ];
}
$action_links[] = [
  'href' => 'new_change.php?source_type=incident&source_id=' . $incident_id,
  'label' => 'Wijziging aanmaken'
];
$action_links[] = [
  'href' => 'new_problem.php?source_type=incident&source_id=' . $incident_id,
  'label' => 'Problem aanmaken'
];
$action_buttons[] = [ 'value' => 'save', 'label' => 'Opslaan' ];
$links_html = task_render_links_section( task_load_links( $con, 'incident', $incident_id, 'secure' ) );
$task_logs_html = task_log_render_tab( task_log_load( $con, 'incident', $incident_id ) );
$mail_tab_html = mail_render_manual_tab( mail_load_manual_rules( $con, 'incident' ), $mail_messages );
?>
<?php require_once(__DIR__ . '/include/incident_form.php'); ?>
