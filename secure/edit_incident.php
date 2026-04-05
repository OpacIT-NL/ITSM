<?php
session_start();
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
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

$reference_data = incident_load_reference_data( $con );
$errors = [];
$edit_comment = null;

if ( isset( $_POST['delete_comment_id'] ) && is_numeric( $_POST['delete_comment_id'] ) ) {
  $delete_comment_id = (int)$_POST['delete_comment_id'];
  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_im_incidentcomments WHERE id = ? AND incidentid = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "ii", $delete_comment_id, $incident_id );
  mysqli_stmt_execute( $delete_stmt );
  header( 'Location: edit_incident.php?id=' . $incident_id );
  exit;
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
  'statusclosed' => isset( $validation_seed['status']['closed'] ) ? (int)$validation_seed['status']['closed'] : 0
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
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
    'statusclosed' => 0
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
                statusid = ?
            WHERE id = ?
        " );
    mysqli_stmt_bind_param(
      $update_stmt,
      "sissiissiiiiiii",
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
      $incident_id
    );

    if ( !mysqli_stmt_execute( $update_stmt ) ) {
      $errors[] = 'Incident bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
    } else {
      if ( $form_values['commenttext'] !== '' ) {
        if ( !empty( $form_values['commentid'] ) && is_numeric( $form_values['commentid'] ) ) {
          $comment_id = (int)$form_values['commentid'];
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
                        incidentid, operatorid, commenttext, internalonly
                    ) VALUES (?,?,?,?)
                " );
          $operator_id_for_comment = (int)$operator_context['id'];
          mysqli_stmt_bind_param(
            $comment_stmt,
            "iisi",
            $incident_id,
            $operator_id_for_comment,
            $form_values['commenttext'],
            $form_values['internalonly']
          );
        }
        mysqli_stmt_execute( $comment_stmt );
      }

      header( 'Location: edit_incident.php?id=' . $incident_id );
      exit;
    }
  }
}

$comments_result = mysqli_query( $con, "
    SELECT c.*, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_im_incidentcomments c
    LEFT JOIN itsm_ob_operators o ON c.operatorid = o.id
    WHERE c.incidentid = " . $incident_id . "
    ORDER BY c.createdat DESC, c.id DESC
" );
$comments = [];
while ( $row = mysqli_fetch_assoc( $comments_result ) ) {
  $row['operator_name'] = $row['operator_name'] ?: 'Onbekend';
  $comments[] = $row;
}

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
$submit_label = 'Incident opslaan';
$show_history = true;
$show_linked_incidents = $incident['incidenttype'] === 'major';
$mode_label = incident_mode_label( $new_mode ?? $incident['incidenttype'] );
$back_url = incident_get_list_back_url( 'incidents.php?view=all' );
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
$action_buttons[] = [ 'value' => 'save', 'label' => 'Opslaan' ];
?>
<?php require_once(__DIR__ . '/include/incident_form.php'); ?>
