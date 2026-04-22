<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );
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
$operator_context = change_get_operator_context( $con, $logged_in_user );
change_require_access( $operator_context );
$change_id = (int)$_GET['id'];

$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_cm_changes WHERE id = ?" );
mysqli_stmt_bind_param( $stmt, "i", $change_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$change = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$change ) {
  die( 'Wijziging niet gevonden' );
}

$reference_data = change_load_reference_data( $con );
$errors = [];
$mail_messages = $_SESSION['manual_mail_messages'] ?? [];
unset( $_SESSION['manual_mail_messages'] );
$presence_error = form_presence_flash_error();
if ( $presence_error !== '' ) {
  $errors[] = $presence_error;
}

if ( isset( $_POST['manual_mail_rule_id'] ) && is_numeric( $_POST['manual_mail_rule_id'] ) ) {
  $manual_mail_rule_id = (int)$_POST['manual_mail_rule_id'];
  $_SESSION['manual_mail_messages'] = [
    mail_send_manual_rule( $con, $manual_mail_rule_id, 'change', $change_id, (int)$operator_context['id'] )
  ];
  header( 'Location: edit_change.php?id=' . $change_id );
  exit;
}
$edit_comment = null;

if ( isset( $_POST['delete_comment_id'] ) && is_numeric( $_POST['delete_comment_id'] ) ) {
  $delete_comment_id = (int)$_POST['delete_comment_id'];
  $deleted_comment_text = '';
  $deleted_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_cm_changecomments WHERE id = ? AND changeid = ?" );
  mysqli_stmt_bind_param( $deleted_comment_stmt, "ii", $delete_comment_id, $change_id );
  mysqli_stmt_execute( $deleted_comment_stmt );
  mysqli_stmt_bind_result( $deleted_comment_stmt, $deleted_comment_text );
  mysqli_stmt_fetch( $deleted_comment_stmt );
  mysqli_stmt_close( $deleted_comment_stmt );

  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_cm_changecomments WHERE id = ? AND changeid = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "ii", $delete_comment_id, $change_id );
  mysqli_stmt_execute( $delete_stmt );
  mysqli_stmt_close( $delete_stmt );

  task_log_add( $con, 'change', $change_id, 'updated', 'Commentaar verwijderd: "' . task_log_text_snippet( $deleted_comment_text ) . '".', (int)$operator_context['id'], task_log_text_snippet( $deleted_comment_text ), null );
  header( 'Location: edit_change.php?id=' . $change_id );
  exit;
}

if ( isset( $_POST['delete_link_id'] ) && is_numeric( $_POST['delete_link_id'] ) ) {
  task_delete_link( $con, (int)$_POST['delete_link_id'] );
  header( 'Location: edit_change.php?id=' . $change_id );
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
    } elseif ( $target['type'] === 'change' && (int)$target['id'] === $change_id ) {
      $errors[] = 'Een wijziging kan niet aan zichzelf gekoppeld worden.';
    } else {
      task_create_link( $con, 'change', $change_id, $relation, $target['type'], (int)$target['id'], (int)$operator_context['id'] );
      task_log_add( $con, 'change', $change_id, 'link_created', 'Link toegevoegd: ' . $relation . ' ' . $target['type'] . ' #' . (int)$target['id'] . '.', (int)$operator_context['id'] );
      header( 'Location: edit_change.php?id=' . $change_id );
      exit;
    }
  }
}

$seed_validation = change_validate_form(
  [
    'requesttype' => $change['requesttype'],
    'changetype' => $change['changetype'],
    'title' => $change['title'],
    'customerid' => (int)$change['customerid'],
    'personid' => (int)$change['personid'],
    'categoryid' => (int)$change['categoryid'],
    'subcategoryid' => (int)$change['subcategoryid'],
    'assetid' => (int)$change['assetid'],
    'operatorgroupid' => (int)$change['operatorgroupid'],
    'operatorid' => (int)$change['operatorid'],
    'coordinatorid' => (int)$change['coordinatorid'],
    'statusid' => (int)$change['statusid'],
    'impactid' => (int)($change['impactid'] ?? 0),
    'urgencyid' => (int)($change['urgencyid'] ?? 0),
    'requires_status' => $change['approvalstate'] === 'approved'
  ],
  $reference_data
);

if ( isset( $_GET['edit_comment'] ) && is_numeric( $_GET['edit_comment'] ) ) {
  $edit_comment_id = (int)$_GET['edit_comment'];
  $comment_stmt = mysqli_prepare( $con, "SELECT * FROM itsm_cm_changecomments WHERE id = ? AND changeid = ?" );
  mysqli_stmt_bind_param( $comment_stmt, "ii", $edit_comment_id, $change_id );
  mysqli_stmt_execute( $comment_stmt );
  $comment_result = mysqli_stmt_get_result( $comment_stmt );
  $edit_comment = mysqli_fetch_assoc( $comment_result );
  mysqli_stmt_close( $comment_stmt );
}

$form_values = [
  'commentid' => $edit_comment ? (string)$edit_comment['id'] : '',
  'requesttype' => $change['requesttype'],
  'changetype' => $change['changetype'],
  'title' => $change['title'],
  'description' => $change['description'],
  'commenttext' => $edit_comment['commenttext'] ?? '',
  'internalonly' => isset( $edit_comment['internalonly'] ) ? (int)$edit_comment['internalonly'] : 0,
  'customerid' => (string)$change['customerid'],
  'personid' => (string)$change['personid'],
  'personemail' => $seed_validation['person']['email'] ?? $change['personemail'],
  'personphone' => $seed_validation['person']['phone'] ?? $change['personphone'],
  'categoryid' => (string)$change['categoryid'],
  'subcategoryid' => (string)$change['subcategoryid'],
  'assetid' => (string)$change['assetid'],
  'assettype' => $seed_validation['asset']['typename'] ?? '',
  'operatorgroupid' => (string)$change['operatorgroupid'],
  'operatorid' => (string)$change['operatorid'],
  'coordinatorid' => (string)$change['coordinatorid'],
  'statusid' => (string)$change['statusid'],
  'statusready' => isset( $seed_validation['status']['ready'] ) ? (int)$seed_validation['status']['ready'] : 0,
  'statusclosed' => isset( $seed_validation['status']['closed'] ) ? (int)$seed_validation['status']['closed'] : 0,
  'impactid' => (string)($change['impactid'] ?? ''),
  'urgencyid' => (string)($change['urgencyid'] ?? ''),
  'priorityid' => (string)($change['priorityid'] ?? ''),
  'priorityname' => priority_name_by_id( $reference_data, $change['priorityid'] ?? null ),
  'applied_template_id' => '',
  'template_used' => (string)($change['template_used'] ?? '')
];

$activity_values = [ 'title' => '', 'description' => '', 'operatorgroupid' => '', 'operatorid' => '', 'statusid' => '' ];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['add_task_link'] ) && !isset( $_POST['manual_mail_rule_id'] ) ) {
  form_presence_redirect_if_stale( $con, 'change', $change_id, $_POST['presence_token'] ?? '', 'edit_change.php?id=' . $change_id );
  $action = $_POST['change_action'] ?? 'save';

  $form_values = [
    'commentid' => $_POST['commentid'] ?? '',
    'requesttype' => $_POST['requesttype'] ?? $change['requesttype'],
    'changetype' => $_POST['changetype'] ?? $change['changetype'],
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
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'operatorid' => $_POST['operatorid'] ?? '',
    'coordinatorid' => $_POST['coordinatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? '',
    'statusready' => 0,
    'statusclosed' => 0,
    'impactid' => $_POST['impactid'] ?? '',
    'urgencyid' => $_POST['urgencyid'] ?? '',
    'priorityid' => '',
    'priorityname' => '',
    'applied_template_id' => $_POST['applied_template_id'] ?? '',
    'template_used' => ( $change['template_used'] ?? '' ) !== '' ? (string)$change['template_used'] : ( $_POST['applied_template_id'] ?? '' )
  ];

  $requires_status = $change['approvalstate'] === 'approved';
  $validation = change_validate_form(
    [
      'requesttype' => $form_values['requesttype'],
      'changetype' => $form_values['changetype'],
      'title' => $form_values['title'],
      'customerid' => (int)$form_values['customerid'],
      'personid' => (int)$form_values['personid'],
      'categoryid' => (int)$form_values['categoryid'],
      'subcategoryid' => (int)$form_values['subcategoryid'],
      'assetid' => (int)$form_values['assetid'],
      'operatorgroupid' => (int)$form_values['operatorgroupid'],
      'operatorid' => (int)$form_values['operatorid'],
      'coordinatorid' => (int)$form_values['coordinatorid'],
      'statusid' => $requires_status ? (int)$form_values['statusid'] : 0,
      'impactid' => (int)$form_values['impactid'],
      'urgencyid' => (int)$form_values['urgencyid'],
      'requires_status' => $requires_status
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
  $form_values['priorityid'] = $validation['priority']['priorityid'] ? (string)$validation['priority']['priorityid'] : '';
  $form_values['priorityname'] = priority_name_by_id( $reference_data, $validation['priority']['priorityid'] );
  if ( $validation['status'] ) {
    $form_values['statusready'] = (int)$validation['status']['ready'];
    $form_values['statusclosed'] = (int)$validation['status']['closed'];
  }

  if ( $action === 'approve' ) {
    if ( $form_values['requesttype'] === 'simple' && (int)$operator_context['simplechange'] === 0 ) {
      $errors[] = 'Je hebt geen rechten om een eenvoudige wijziging goed te keuren.';
    }
    if ( $form_values['requesttype'] === 'extended' && (int)$operator_context['extchange'] === 0 ) {
      $errors[] = 'Je hebt geen rechten om een uitgebreide wijziging goed te keuren.';
    }
  }

  if ( $action === 'add_activity' ) {
    $activity_values = [
      'title' => trim( $_POST['activity_title'] ?? '' ),
      'description' => trim( $_POST['activity_description'] ?? '' ),
      'operatorgroupid' => $_POST['activity_operatorgroupid'] ?? '',
      'operatorid' => $_POST['activity_operatorid'] ?? '',
      'statusid' => $_POST['activity_statusid'] ?? ''
    ];
  }
  $errors = array_merge( $errors, attachment_upload_errors() );
  if ( $action === 'add_activity' ) {
    $errors = array_merge( $errors, attachment_upload_errors( 'activity_attachment' ) );
  }
  $presence_check = form_presence_check_before_save( $con, 'change', $change_id, $_POST['presence_token'] ?? '' );
  if ( !$presence_check['ok'] ) {
    $errors[] = $presence_check['message'];
  }

  if ( empty( $errors ) ) {
    $customer_id = (int)$validation['customer']['id'];
    $person_id = (int)$validation['person']['id'];
    $category_id = (int)$validation['category']['id'];
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $asset_id = $validation['asset'] ? (int)$validation['asset']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $form_values['requesttype'] === 'simple' && $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $coordinator_id = $form_values['requesttype'] === 'extended' && $validation['coordinator'] ? (int)$validation['coordinator']['id'] : null;
    $old_status_id = (int)($change['statusid'] ?? 0);
    $status_id = $validation['status'] ? (int)$validation['status']['id'] : null;
    $impact_id = $validation['priority']['impactid'];
    $urgency_id = $validation['priority']['urgencyid'];
    $priority_id = $validation['priority']['priorityid'];
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $template_used = $form_values['template_used'] !== '' ? (int)$form_values['template_used'] : null;
    $approval_state = $change['approvalstate'];
    $closed = (int)$change['closed'];

    if ( $action === 'approve' ) {
      $approval_state = 'approved';
      $closed = 0;
      if ( !$status_id ) {
        $status_id = change_default_status_id( $reference_data['statuses'] );
      }
    } elseif ( $action === 'reject' ) {
      $approval_state = 'rejected';
      $closed = 1;
      $status_id = null;
    }

    if ( $action === 'add_activity' ) {
      if ( $form_values['requesttype'] !== 'extended' ) {
        $errors[] = 'Wijzigingsactiviteiten zijn alleen beschikbaar op uitgebreide wijzigingen.';
      } else {
        $activity_validation = change_validate_activity_form(
          [
            'title' => $activity_values['title'],
            'operatorgroupid' => (int)$activity_values['operatorgroupid'],
            'operatorid' => (int)$activity_values['operatorid'],
            'statusid' => (int)$activity_values['statusid']
          ],
          $reference_data
        );
        $errors = array_merge( $errors, $activity_validation['errors'] );

        if ( empty( $errors ) ) {
          $activity_group_id = $activity_validation['group'] ? (int)$activity_validation['group']['id'] : null;
          $activity_operator_id = $activity_validation['operator'] ? (int)$activity_validation['operator']['id'] : null;
          $activity_status_id = (int)$activity_validation['status']['id'];
          $created_by = (int)$operator_context['id'];
          $activity_number = change_generate_activity_number( $con );
          $activity_stmt = mysqli_prepare( $con, "
                    INSERT INTO itsm_cm_changeactivities (
                        activitynumber, changeid, title, description, operatorgroupid, operatorid, statusid, createdby
                    ) VALUES (?,?,?,?,?,?,?,?)
                " );
          mysqli_stmt_bind_param(
            $activity_stmt,
            "sissiiii",
            $activity_number,
            $change_id,
            $activity_values['title'],
            $activity_values['description'],
            $activity_group_id,
            $activity_operator_id,
            $activity_status_id,
            $created_by
          );
          mysqli_stmt_execute( $activity_stmt );
          $activity_id = mysqli_insert_id( $con );
          attachment_save_upload( $con, 'changeactivity', $activity_id, $created_by, 0, null, null, 'activity_attachment' );
          task_log_add( $con, 'change', $change_id, 'activity_created', 'Wijzigingsactiviteit ' . $activity_number . ' aangemaakt.', $created_by );
          task_log_add( $con, 'changeactivity', $activity_id, 'created', 'Wijzigingsactiviteit aangemaakt vanuit wijziging #' . $change_id . '.', $created_by );
          form_presence_mark_saved( $con, 'change', $change_id, (int)$operator_context['id'] );
          header( 'Location: edit_change.php?id=' . $change_id );
          exit;
        }
      }
    } elseif ( empty( $errors ) ) {
      $update_stmt = mysqli_prepare( $con, "
                UPDATE itsm_cm_changes SET
                    requesttype = ?,
                    approvalstate = ?,
                    changetype = ?,
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
                    coordinatorid = ?,
                    statusid = ?,
                    impactid = ?,
                    urgencyid = ?,
                    priorityid = ?,
                    template_used = ?,
                    closed = ?
                WHERE id = ?
            " );
      mysqli_stmt_bind_param(
        $update_stmt,
        "sssssiissiiiiiiiiiiiii",
        $form_values['requesttype'],
        $approval_state,
        $form_values['changetype'],
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
        $operator_id,
        $coordinator_id,
        $status_id,
        $impact_id,
        $urgency_id,
        $priority_id,
        $template_used,
        $closed,
        $change_id
      );

      if ( !mysqli_stmt_execute( $update_stmt ) ) {
        $errors[] = 'Wijziging bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
      } else {
        form_presence_mark_saved( $con, 'change', $change_id, (int)$operator_context['id'] );
        task_log_add( $con, 'change', $change_id, 'updated', 'Wijziging opgeslagen.', (int)$operator_context['id'] );
        if ( $approval_state !== $change['approvalstate'] ) {
          $approval_action = $approval_state === 'approved' ? 'approved' : ( $approval_state === 'rejected' ? 'rejected' : 'updated' );
          $approval_message = $approval_state === 'approved' ? 'Wijzigingsaanvraag goedgekeurd.' : ( $approval_state === 'rejected' ? 'Wijzigingsaanvraag afgewezen en gesloten.' : 'Goedkeuringsstatus gewijzigd naar ' . $approval_state . '.' );
          task_log_add( $con, 'change', $change_id, $approval_action, $approval_message, (int)$operator_context['id'], $change['approvalstate'], $approval_state );
        }
        task_log_field_changes(
          $con,
          'change',
          $change_id,
          [
            'requesttype' => $change['requesttype'],
            'approvalstate' => $change['approvalstate'],
            'changetype' => $change['changetype'],
            'title' => $change['title'],
            'description' => $change['description'],
            'customerid' => $change['customerid'],
            'personid' => $change['personid'],
            'categoryid' => $change['categoryid'],
            'subcategoryid' => $change['subcategoryid'],
            'assetid' => $change['assetid'],
            'operatorgroupid' => $change['operatorgroupid'],
            'operatorid' => $change['operatorid'],
            'coordinatorid' => $change['coordinatorid'],
            'impactid' => $change['impactid'] ?? null,
            'urgencyid' => $change['urgencyid'] ?? null,
            'priorityid' => $change['priorityid'] ?? null
          ],
          [
            'requesttype' => $form_values['requesttype'],
            'approvalstate' => $approval_state,
            'changetype' => $form_values['changetype'],
            'title' => $form_values['title'],
            'description' => $form_values['description'],
            'customerid' => $customer_id,
            'personid' => $person_id,
            'categoryid' => $category_id,
            'subcategoryid' => $subcategory_id,
            'assetid' => $asset_id,
            'operatorgroupid' => $group_id,
            'operatorid' => $operator_id,
            'coordinatorid' => $coordinator_id,
            'impactid' => $impact_id,
            'urgencyid' => $urgency_id,
            'priorityid' => $priority_id
          ],
          [
            'requesttype' => 'Wijzigingssoort',
            'approvalstate' => 'Goedkeuringsstatus',
            'changetype' => 'Type',
            'title' => 'Titel',
            'description' => 'Omschrijving',
            'customerid' => 'Klant',
            'personid' => 'Persoon',
            'categoryid' => 'Categorie',
            'subcategoryid' => 'Subcategorie',
            'assetid' => 'Object ID',
            'operatorgroupid' => 'Behandelaarsgroep',
            'operatorid' => 'Behandelaar',
            'coordinatorid' => 'Coordinator',
            'impactid' => t('Impact'),
            'urgencyid' => t('Urgency'),
            'priorityid' => t('Priority')
          ],
          (int)$operator_context['id']
        );
        if ( !empty( $form_values['commentid'] ) || $form_values['commenttext'] !== '' || attachment_uploaded_file_available() ) {
          $attachment_comment_id = null;
          if ( !empty( $form_values['commentid'] ) && is_numeric( $form_values['commentid'] ) ) {
            $comment_id = (int)$form_values['commentid'];
            $old_comment_text = '';
            $old_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_cm_changecomments WHERE id = ? AND changeid = ?" );
            mysqli_stmt_bind_param( $old_comment_stmt, "ii", $comment_id, $change_id );
            mysqli_stmt_execute( $old_comment_stmt );
            mysqli_stmt_bind_result( $old_comment_stmt, $old_comment_text );
            mysqli_stmt_fetch( $old_comment_stmt );
            mysqli_stmt_close( $old_comment_stmt );

            $attachment_comment_id = $comment_id;
            $comment_stmt = mysqli_prepare( $con, "
              UPDATE itsm_cm_changecomments
              SET commenttext = ?, internalonly = ?
              WHERE id = ? AND changeid = ?
            " );
            mysqli_stmt_bind_param(
              $comment_stmt,
              "siii",
              $form_values['commenttext'],
              $form_values['internalonly'],
              $comment_id,
              $change_id
            );
          } else {
            $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly) VALUES (?,?,NULL,?,?)" );
            $comment_operator_id = (int)$operator_context['id'];
            $comment_text = $form_values['commenttext'] !== '' ? $form_values['commenttext'] : 'Bijlage toegevoegd.';
            mysqli_stmt_bind_param( $comment_stmt, "iisi", $change_id, $comment_operator_id, $comment_text, $form_values['internalonly'] );
          }

          mysqli_stmt_execute( $comment_stmt );
          if ( empty( $attachment_comment_id ) ) {
            $attachment_comment_id = mysqli_insert_id( $con );
            task_log_add( $con, 'change', $change_id, 'updated', 'Commentaar toegevoegd: "' . task_log_text_snippet( $comment_text ) . '".', (int)$operator_context['id'], null, task_log_text_snippet( $comment_text ) );
          } else {
            task_log_add( $con, 'change', $change_id, 'updated', 'Commentaar bijgewerkt van "' . task_log_text_snippet( $old_comment_text ?? '' ) . '" naar "' . task_log_text_snippet( $form_values['commenttext'] ) . '".', (int)$operator_context['id'], task_log_text_snippet( $old_comment_text ?? '' ), task_log_text_snippet( $form_values['commenttext'] ) );
          }
          mysqli_stmt_close( $comment_stmt );
          attachment_save_upload( $con, 'change', $change_id, (int)$operator_context['id'], $form_values['internalonly'], 'changecomment', $attachment_comment_id );
        }
        if ( $status_id !== null ) {
          mail_process_status_change( $con, 'change', $change_id, $old_status_id, (int)$status_id, (int)$operator_context['id'] );
          task_log_status_change( $con, 'change', $change_id, $old_status_id, (int)$status_id, (int)$operator_context['id'] );
        }

        $applied_template = change_find_by_id( $reference_data['templates'], (int)$form_values['applied_template_id'] );
        if ( $applied_template && $form_values['requesttype'] === 'extended' && ($applied_template['changerequesttype'] ?? '') === 'extended' ) {
          change_copy_template_activities_to_change( $con, (int)$applied_template['id'], $change_id, (int)$operator_context['id'] );
        }
        header( 'Location: edit_change.php?id=' . $change_id );
        exit;
      }
    }
  }
}

$comments_result = mysqli_query( $con, "
    SELECT
      c.*,
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name,
      CONCAT(p.lastname, ', ', p.firstname) AS person_name
    FROM itsm_cm_changecomments c
    LEFT JOIN itsm_ob_operators o ON c.operatorid = o.id
    LEFT JOIN itsm_ob_persons p ON c.personid = p.id
    WHERE c.changeid = " . $change_id . "
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
$attachments = attachment_load_for_task( $con, 'change', $change_id );
$attachments_by_comment = attachment_group_by_comment( $attachments );
$attachments_html = attachment_render_as_comments( $attachments );

$activities = [];
if ( $change['requesttype'] === 'extended' ) {
  $activities_result = mysqli_query( $con, "
      SELECT a.*, s.name AS status_name, g.groupname, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
      FROM itsm_cm_changeactivities a
      LEFT JOIN itsm_core_status s ON a.statusid = s.id
      LEFT JOIN itsm_ob_operatorgroups g ON a.operatorgroupid = g.id
      LEFT JOIN itsm_ob_operators o ON a.operatorid = o.id
      WHERE a.changeid = " . $change_id . "
      ORDER BY a.activitynumber ASC, a.id ASC
  " );
  while ( $row = mysqli_fetch_assoc( $activities_result ) ) {
    $activities[] = $row;
  }
}

$page_title = change_approval_state_label( $change ) . ' ' . change_format_display_number( $change );
$tab_title = change_format_display_number( $change );
$tab_subtitle = change_approval_state_label( $change );
$list_back_url = change_get_list_back_url( 'changes.php?section=changes&view=all' );
$show_status_block = $change['approvalstate'] === 'approved';
$show_history = true;
$show_activities = $change['requesttype'] === 'extended';
$action_buttons = [];
if ( $change['approvalstate'] === 'request' ) {
  $action_buttons[] = [ 'value' => 'approve', 'label' => t('Goedkeuren'), 'class' => 'btn-success' ];
  $action_buttons[] = [ 'value' => 'reject', 'label' => t('Afwijzen'), 'class' => 'btn-danger', 'formnovalidate' => true ];
}
$action_buttons[] = [ 'value' => 'save', 'label' => 'Opslaan', 'class' => 'btn-primary' ];
$links_html = task_render_links_section( task_load_links( $con, 'change', $change_id, 'secure' ) );
$task_logs_html = task_log_render_tab( task_log_load( $con, 'change', $change_id ) );
$mail_tab_html = mail_render_manual_tab( mail_load_manual_rules( $con, 'change' ), $mail_messages );
?>
<?php require_once(__DIR__ . '/include/change_form.php'); ?>
