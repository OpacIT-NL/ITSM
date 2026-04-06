<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );

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
  'operatorgroupid' => '',
  'operatorid' => '',
  'statusid' => (string)$default_status_id,
  'statusready' => 0,
  'statusclosed' => 0
];

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
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? '',
    'statusready' => 0,
    'statusclosed' => 0
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
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $created_by = (int)$operator_context['id'];
    $incident_number = incident_generate_number( $con );

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_im_incidents (
                incidentnumber, incidenttype, majorincidentid, title, description, customerid, personid, personemail, personphone,
                categoryid, subcategoryid, assetid, operatorgroupid, operatorid, statusid, createdby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        " );
    mysqli_stmt_bind_param(
      $stmt,
      "ssissiissiiiiiii",
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
      $created_by
    );

    if ( !mysqli_stmt_execute( $stmt ) ) {
      $errors[] = 'Incident opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    } else {
      $incident_id = mysqli_insert_id( $con );

      if ( $form_values['commenttext'] !== '' ) {
        $comment_stmt = mysqli_prepare( $con, "
                    INSERT INTO itsm_im_incidentcomments (
                        incidentid, operatorid, personid, commenttext, internalonly
                    ) VALUES (?, ?, NULL, ?, ?)
                " );
        mysqli_stmt_bind_param(
          $comment_stmt,
          "iisi",
          $incident_id,
          $created_by,
          $form_values['commenttext'],
          $form_values['internalonly']
        );
        mysqli_stmt_execute( $comment_stmt );
      }

      header( 'Location: edit_incident.php?id=' . $incident_id );
      exit;
    }
  }
}

$page_title = incident_mode_label( $mode ) . ' aanmaken';
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
