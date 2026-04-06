<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );

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
  'applied_template_id' => ''
];

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
    'applied_template_id' => $_POST['applied_template_id'] ?? ''
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

  if ( empty( $errors ) ) {
    $customer_id = (int)$validation['customer']['id'];
    $person_id = (int)$validation['person']['id'];
    $category_id = (int)$validation['category']['id'];
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $asset_id = $validation['asset'] ? (int)$validation['asset']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $form_values['requesttype'] === 'simple' && $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $coordinator_id = $form_values['requesttype'] === 'extended' && $validation['coordinator'] ? (int)$validation['coordinator']['id'] : null;
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $created_by = (int)$operator_context['id'];
    $change_number = change_generate_number( $con );

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_cm_changes (
                changenumber, requesttype, approvalstate, changetype, title, description, customerid, personid, personemail, personphone,
                categoryid, subcategoryid, assetid, operatorgroupid, operatorid, coordinatorid, statusid, closed, createdby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        " );
    $approval_state = 'request';
    $status_id = null;
    $closed = 0;
    mysqli_stmt_bind_param(
      $stmt,
      "ssssssiissiiiiiiiii",
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
      $closed,
      $created_by
    );

    if ( !mysqli_stmt_execute( $stmt ) ) {
      $errors[] = 'Wijzigingsaanvraag opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    } else {
      $change_id = mysqli_insert_id( $con );
      if ( $form_values['commenttext'] !== '' ) {
        $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly) VALUES (?,?,NULL,?,?)" );
        mysqli_stmt_bind_param( $comment_stmt, "iisi", $change_id, $created_by, $form_values['commenttext'], $form_values['internalonly'] );
        mysqli_stmt_execute( $comment_stmt );
        mysqli_stmt_close( $comment_stmt );
      }

      $applied_template = change_find_by_id( $reference_data['templates'], (int)$form_values['applied_template_id'] );
      if ( $applied_template && $form_values['requesttype'] === 'extended' && ($applied_template['changerequesttype'] ?? '') === 'extended' ) {
        change_copy_template_activities_to_change( $con, (int)$applied_template['id'], $change_id, $created_by );
      }
      header( 'Location: edit_change.php?id=' . $change_id );
      exit;
    }
  }
}

$page_title = 'Wijzigingsaanvraag aanmaken';
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
