<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );
require_once( __DIR__ . '/include/task_helpers.php' );
require_once( __DIR__ . '/include/attachment_helpers.php' );
require_once( __DIR__ . '/include/mail_helpers.php' );
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

$logged_in_user = $_SESSION[ 'name' ];
change_require_request_authorization( $con, $logged_in_user );
$operator_context = change_get_operator_context( $con, $logged_in_user );
change_require_access( $operator_context );

$reference_data = change_load_reference_data( $con );
$default_group_id = change_default_group_id( $reference_data['groups'] );
$errors = [];
$source_type = $_GET['source_type'] ?? '';
$source_id = isset( $_GET['source_id'] ) && is_numeric( $_GET['source_id'] ) ? (int)$_GET['source_id'] : 0;

$form_values = [
  'commentid' => '',
  'requesttype' => 'simple',
  'changetype' => 'standard',
  'title' => '',
  'description' => '',
  'commenttext' => '',
  'internalonly' => 0,
  'customerid' => '',
  'personid' => '',
  'personemail' => '',
  'personphone' => '',
  'categoryid' => '',
  'subcategoryid' => '',
  'assetid' => '',
  'assettype' => '',
  'operatorgroupid' => $default_group_id > 0 ? (string)$default_group_id : '',
  'operatorid' => '',
  'coordinatorid' => '',
  'statusid' => '',
  'statusready' => 0,
  'statusclosed' => 0,
  'impactid' => '',
  'urgencyid' => '',
  'priorityid' => '',
  'priorityname' => '',
  'applied_template_id' => '',
  'template_used' => ''
];

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' && $source_id > 0 && in_array( $source_type, [ 'incident', 'problem', 'ubm' ], true ) ) {
  $source_item = task_prefill_from_source( $con, $source_type, $source_id );
  if ( $source_item ) {
    $form_values['title'] = $source_item['title'] ?? '';
    $form_values['description'] = $source_item['description'] ?? '';
    $form_values['customerid'] = (string)($source_item['customerid'] ?? '');
    $form_values['personid'] = (string)($source_item['personid'] ?? '');
    $form_values['personemail'] = $source_item['personemail'] ?? '';
    $form_values['personphone'] = $source_item['personphone'] ?? '';
    $form_values['categoryid'] = (string)($source_item['categoryid'] ?? '');
    $form_values['subcategoryid'] = (string)($source_item['subcategoryid'] ?? '');
    $form_values['assetid'] = (string)($source_item['assetid'] ?? '');
  }
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
  $prefill_customer_id = isset( $_GET['customerid'] ) && is_numeric( $_GET['customerid'] ) ? (int)$_GET['customerid'] : 0;
  $prefill_person_id = isset( $_GET['personid'] ) && is_numeric( $_GET['personid'] ) ? (int)$_GET['personid'] : 0;
  if ( $prefill_customer_id > 0 ) {
    $form_values['customerid'] = (string)$prefill_customer_id;
  }
  if ( $prefill_person_id > 0 ) {
    $form_values['personid'] = (string)$prefill_person_id;
    foreach ( $reference_data['persons'] as $person_row ) {
      if ( (int)$person_row['id'] === $prefill_person_id && (int)$person_row['customerid'] === $prefill_customer_id ) {
        $form_values['personemail'] = $person_row['email'] ?? '';
        $form_values['personphone'] = $person_row['phone'] ?? '';
        break;
      }
    }
  }
  if ( isset( $_GET['title'] ) ) {
    $form_values['title'] = trim( (string)$_GET['title'] );
  }
  if ( isset( $_GET['description'] ) ) {
    $form_values['description'] = trim( (string)$_GET['description'] );
  }
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'commentid' => '',
    'requesttype' => $_POST['requesttype'] ?? 'simple',
    'changetype' => $_POST['changetype'] ?? 'standard',
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
    'operatorgroupid' => ($_POST['operatorgroupid'] ?? '') !== '' ? $_POST['operatorgroupid'] : ( $default_group_id > 0 ? (string)$default_group_id : '' ),
    'operatorid' => $_POST['operatorid'] ?? '',
    'coordinatorid' => $_POST['coordinatorid'] ?? '',
    'statusid' => '',
    'statusready' => 0,
    'statusclosed' => 0,
    'impactid' => $_POST['impactid'] ?? '',
    'urgencyid' => $_POST['urgencyid'] ?? '',
    'priorityid' => '',
    'priorityname' => '',
    'applied_template_id' => $_POST['applied_template_id'] ?? '',
    'template_used' => $_POST['applied_template_id'] ?? ''
  ];

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
      'statusid' => 0,
      'impactid' => (int)$form_values['impactid'],
      'urgencyid' => (int)$form_values['urgencyid'],
      'requires_status' => false
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
  $errors = array_merge( $errors, attachment_upload_errors() );

  if ( empty( $errors ) ) {
    $customer_id = (int)$validation['customer']['id'];
    $person_id = (int)$validation['person']['id'];
    $category_id = (int)$validation['category']['id'];
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $asset_id = $validation['asset'] ? (int)$validation['asset']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $form_values['requesttype'] === 'simple' && $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $coordinator_id = $form_values['requesttype'] === 'extended' && $validation['coordinator'] ? (int)$validation['coordinator']['id'] : null;
    $impact_id = $validation['priority']['impactid'];
    $urgency_id = $validation['priority']['urgencyid'];
    $priority_id = $validation['priority']['priorityid'];
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $created_by = (int)$operator_context['id'];
    $change_number = change_generate_number( $con );

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_cm_changes (
                changenumber, requesttype, approvalstate, changetype, title, description, customerid, personid, personemail, personphone,
                categoryid, subcategoryid, assetid, operatorgroupid, operatorid, coordinatorid, statusid, impactid, urgencyid, priorityid, template_used, closed, createdby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        " );
    $approval_state = 'request';
    $status_id = null;
    $template_used = ($form_values['applied_template_id'] ?? '') !== '' ? (int)$form_values['applied_template_id'] : null;
    $closed = 0;
    mysqli_stmt_bind_param(
      $stmt,
      "ssssssiissiiiiiiiiiiiii",
      $change_number,
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
      $created_by
    );

    if ( !mysqli_stmt_execute( $stmt ) ) {
      $errors[] = 'Wijzigingsaanvraag opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    } else {
      $change_id = mysqli_insert_id( $con );
      task_log_add( $con, 'change', $change_id, 'created', 'Wijzigingsaanvraag aangemaakt.', $created_by );
      if ( $form_values['commenttext'] !== '' || attachment_uploaded_file_available() ) {
        $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly) VALUES (?,?,NULL,?,?)" );
        $comment_text = $form_values['commenttext'] !== '' ? $form_values['commenttext'] : 'Bijlage toegevoegd.';
        mysqli_stmt_bind_param( $comment_stmt, "iisi", $change_id, $created_by, $comment_text, $form_values['internalonly'] );
        mysqli_stmt_execute( $comment_stmt );
        $comment_id = mysqli_insert_id( $con );
        mysqli_stmt_close( $comment_stmt );
        attachment_save_upload( $con, 'change', $change_id, $created_by, $form_values['internalonly'], 'changecomment', $comment_id );
      }

      $applied_template = change_find_by_id( $reference_data['templates'], (int)$form_values['applied_template_id'] );
      if ( $applied_template && $form_values['requesttype'] === 'extended' && ($applied_template['changerequesttype'] ?? '') === 'extended' ) {
        change_copy_template_activities_to_change( $con, (int)$applied_template['id'], $change_id, $created_by );
      }
      if ( $source_id > 0 && in_array( $source_type, [ 'incident', 'problem', 'ubm' ], true ) ) {
        task_create_link( $con, 'change', $change_id, 'Afgeleid van', $source_type, $source_id, $created_by );
        task_log_add( $con, 'change', $change_id, 'link_created', 'Link toegevoegd: Afgeleid van ' . $source_type . ' #' . $source_id . '.', $created_by );
      }
      mail_process_ticket_created( $con, 'change', $change_id, $created_by );
      header( 'Location: edit_change.php?id=' . $change_id );
      exit;
    }
  }
}

$page_title = 'Wijzigingsaanvraag aanmaken';
$tab_title = t( 'common.new' );
$tab_subtitle = 'Wijzigingsaanvraag';
$list_back_url = change_get_list_back_url( 'changes.php?section=requests&view=all' );
$show_status_block = false;
$show_history = false;
$show_activities = false;
$comments = [];
$activities = [];
$activity_values = [ 'title' => '', 'description' => '', 'operatorgroupid' => '', 'operatorid' => '', 'statusid' => '' ];
$action_buttons = [
  [ 'value' => 'save', 'label' => 'Wijzigingsaanvraag opslaan', 'class' => 'btn-primary' ]
];
?>
<?php require_once(__DIR__ . '/include/change_form.php'); ?>
