<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );
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
incident_require_firstline_authorization( $con, $logged_in_user );
$operator_context = incident_get_operator_context( $con, $logged_in_user );
incident_require_access( $operator_context );

$mode = incident_normalize_mode( $_GET['mode'] ?? 'firstline' );
if ( $mode === 'firstline' && (int)$operator_context['firstlineincidents'] === 0 ) {
  header( 'Location: im_menu.php' );
  exit;
}
if ( in_array( $mode, [ 'secondline', 'major' ], true ) && (int)$operator_context['secondlineincidents'] === 0 ) {
  header( 'Location: im_menu.php' );
  exit;
}

$reference_data = incident_load_reference_data( $con );
$default_status_id = incident_default_status_id( $reference_data['statuses'] );
$default_group_id = incident_default_group_id( $reference_data['groups'] );
$errors = [];
$comments = [];

$form_values = [
  'commentid' => '',
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
  'majorincidentid' => '',
  'operatorgroupid' => $default_group_id > 0 ? (string)$default_group_id : '',
  'operatorid' => '',
  'statusid' => (string)$default_status_id,
  'statusready' => 0,
  'statusclosed' => 0,
  'impactid' => '',
  'urgencyid' => '',
  'priorityid' => '',
  'priorityname' => '',
  'applied_template_id' => '',
  'template_used' => ''
];

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
    'operatorgroupid' => ($_POST['operatorgroupid'] ?? '') !== '' ? $_POST['operatorgroupid'] : ( $default_group_id > 0 ? (string)$default_group_id : '' ),
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? '',
    'statusready' => 0,
    'statusclosed' => 0,
    'impactid' => $_POST['impactid'] ?? '',
    'urgencyid' => $_POST['urgencyid'] ?? '',
    'priorityid' => '',
    'priorityname' => '',
    'applied_template_id' => $_POST['applied_template_id'] ?? '',
    'template_used' => $_POST['applied_template_id'] ?? ''
  ];

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
      'incidenttype' => $mode,
      'current_incident_id' => 0
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

  if ( empty( $errors ) ) {
    $customer_id = (int)$validation['customer']['id'];
    $person_id = (int)$validation['person']['id'];
    $category_id = (int)$validation['category']['id'];
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $asset_id = $validation['asset'] ? (int)$validation['asset']['id'] : null;
    $major_incident_id = $validation['major_incident'] ? (int)$validation['major_incident']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $assigned_operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $status_id = (int)$validation['status']['id'];
    $impact_id = $validation['priority']['impactid'];
    $urgency_id = $validation['priority']['urgencyid'];
    $priority_id = $validation['priority']['priorityid'];
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $created_by = (int)$operator_context['id'];
    $incident_number = incident_generate_number( $con );

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_im_incidents (
                incidentnumber, incidenttype, majorincidentid, title, description, customerid, personid, personemail, personphone,
                categoryid, subcategoryid, assetid, operatorgroupid, operatorid, statusid, impactid, urgencyid, priorityid, template_used, createdby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        " );
    $template_used = ($form_values['applied_template_id'] ?? '') !== '' ? (int)$form_values['applied_template_id'] : null;
    mysqli_stmt_bind_param(
      $stmt,
      "ssissiissiiiiiiiiiii",
      $incident_number,
      $mode,
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
      $created_by
    );

    if ( !mysqli_stmt_execute( $stmt ) ) {
      $errors[] = 'Incident opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    } else {
      $incident_id = mysqli_insert_id( $con );
      task_log_add( $con, 'incident', $incident_id, 'created', incident_mode_label( $mode ) . ' aangemaakt.', $created_by );

      if ( $form_values['commenttext'] !== '' || attachment_uploaded_file_available() ) {
        $comment_stmt = mysqli_prepare( $con, "
                    INSERT INTO itsm_im_incidentcomments (
                        incidentid, operatorid, personid, commenttext, internalonly
                    ) VALUES (?, ?, NULL, ?, ?)
                " );
        $comment_text = $form_values['commenttext'] !== '' ? $form_values['commenttext'] : 'Bijlage toegevoegd.';
        mysqli_stmt_bind_param(
          $comment_stmt,
          "iisi",
          $incident_id,
          $created_by,
          $comment_text,
          $form_values['internalonly']
        );
        mysqli_stmt_execute( $comment_stmt );
        $comment_id = mysqli_insert_id( $con );
        attachment_save_upload( $con, 'incident', $incident_id, $created_by, $form_values['internalonly'], 'incidentcomment', $comment_id );
      }
      mail_process_ticket_created( $con, 'incident', $incident_id );

      header( 'Location: edit_incident.php?id=' . $incident_id );
      exit;
    }
  }
}

$page_titles = [
  'firstline' => t( 'incident.create.firstline' ),
  'secondline' => t( 'incident.create.secondline' ),
  'major' => t( 'incident.create.major' )
];
$page_title = $page_titles[ incident_normalize_mode( $mode ) ] ?? t( 'incident.create.firstline' );
$tab_title = t( 'common.new' );
$tab_subtitle = incident_mode_label( $mode );
$submit_label = 'Incident opslaan';
$show_history = false;
$show_linked_incidents = false;
$linked_incidents = [];
$mode_label = incident_mode_label( $mode );
$list_back_url = incident_get_list_back_url( 'incidents.php?view=all&mode=' . urlencode( $mode ) );
$current_mode = $mode;
$show_major_link_control = $mode !== 'major';
$incident_id = 0;
$action_links = [];
$action_buttons = [
  [
    'value' => 'save',
    'label' => 'Opslaan'
  ]
];
?>
<?php require_once(__DIR__ . '/include/incident_form.php'); ?>
