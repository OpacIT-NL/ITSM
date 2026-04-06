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
    'requires_status' => $change['approvalstate'] === 'approved'
  ],
  $reference_data
);

$form_values = [
  'commentid' => '',
  'requesttype' => $change['requesttype'],
  'changetype' => $change['changetype'],
  'title' => $change['title'],
  'description' => $change['description'],
  'commenttext' => '',
  'internalonly' => 0,
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
  'applied_template_id' => ''
];

$activity_values = [ 'title' => '', 'description' => '', 'operatorgroupid' => '', 'operatorid' => '', 'statusid' => '' ];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $action = $_POST['change_action'] ?? 'save';

  $form_values = [
    'commentid' => '',
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
    'applied_template_id' => $_POST['applied_template_id'] ?? ''
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

  if ( empty( $errors ) ) {
    $customer_id = (int)$validation['customer']['id'];
    $person_id = (int)$validation['person']['id'];
    $category_id = (int)$validation['category']['id'];
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $asset_id = $validation['asset'] ? (int)$validation['asset']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $form_values['requesttype'] === 'simple' && $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $coordinator_id = $form_values['requesttype'] === 'extended' && $validation['coordinator'] ? (int)$validation['coordinator']['id'] : null;
    $status_id = $validation['status'] ? (int)$validation['status']['id'] : null;
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
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
                    closed = ?
                WHERE id = ?
            " );
      mysqli_stmt_bind_param(
        $update_stmt,
        "sssssiissiiiiiiiii",
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
        $change_id
      );

      if ( !mysqli_stmt_execute( $update_stmt ) ) {
        $errors[] = 'Wijziging bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
      } else {
        if ( $form_values['commenttext'] !== '' ) {
          $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_cm_changecomments (changeid, operatorid, commenttext, internalonly) VALUES (?,?,?,?)" );
          $comment_operator_id = (int)$operator_context['id'];
          mysqli_stmt_bind_param( $comment_stmt, "iisi", $change_id, $comment_operator_id, $form_values['commenttext'], $form_values['internalonly'] );
          mysqli_stmt_execute( $comment_stmt );
          mysqli_stmt_close( $comment_stmt );
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
    SELECT c.*, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_cm_changecomments c
    LEFT JOIN itsm_ob_operators o ON c.operatorid = o.id
    WHERE c.changeid = " . $change_id . "
    ORDER BY c.createdat DESC, c.id DESC
" );
$comments = [];
while ( $row = mysqli_fetch_assoc( $comments_result ) ) {
  $row['operator_name'] = $row['operator_name'] ?: 'Onbekend';
  $comments[] = $row;
}

$activities = [];
if ( $change['requesttype'] === 'extended' ) {
  $activities_result = mysqli_query( $con, "
      SELECT a.*, s.name AS status_name, g.groupname, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
      FROM itsm_cm_changeactivities a
      LEFT JOIN itsm_core_status s ON a.statusid = s.id
      LEFT JOIN itsm_ob_operatorgroups g ON a.operatorgroupid = g.id
      LEFT JOIN itsm_ob_operators o ON a.operatorid = o.id
      WHERE a.changeid = " . $change_id . "
      ORDER BY a.title ASC, a.id ASC
  " );
  while ( $row = mysqli_fetch_assoc( $activities_result ) ) {
    $activities[] = $row;
  }
}

$page_title = change_approval_state_label( $change ) . ' ' . change_format_display_number( $change );
$back_url = change_get_list_back_url( 'changes.php?view=all' );
$show_status_block = $change['approvalstate'] === 'approved';
$show_history = true;
$show_activities = $change['requesttype'] === 'extended';
$action_buttons = [];
if ( $change['approvalstate'] === 'request' ) {
  $action_buttons[] = [ 'value' => 'approve', 'label' => 'Goedkeuren', 'class' => 'btn-success' ];
  $action_buttons[] = [ 'value' => 'reject', 'label' => 'Afwijzen', 'class' => 'btn-danger', 'formnovalidate' => true ];
}
$action_buttons[] = [ 'value' => 'save', 'label' => 'Opslaan', 'class' => 'btn-primary' ];
?>
<?php require_once(__DIR__ . '/include/change_form.php'); ?>
