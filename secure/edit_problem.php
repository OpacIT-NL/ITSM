<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/problem_helpers.php' );
require_once( __DIR__ . '/include/task_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  die( 'Invalid ID' );
}

$logged_in_user = $_SESSION['name'];
$operator_context = problem_get_operator_context( $con, $logged_in_user );
problem_require_access( $operator_context );
$problem_id = (int)$_GET['id'];

$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_pm_problems WHERE id = ?" );
mysqli_stmt_bind_param( $stmt, "i", $problem_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$problem = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$problem ) {
  die( 'Probleem niet gevonden' );
}

$reference_data = problem_load_reference_data( $con );
$errors = [];
$edit_comment = null;

if ( isset( $_POST['delete_comment_id'] ) && is_numeric( $_POST['delete_comment_id'] ) ) {
  $delete_comment_id = (int)$_POST['delete_comment_id'];
  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_pm_problemcomments WHERE id = ? AND problemid = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "ii", $delete_comment_id, $problem_id );
  mysqli_stmt_execute( $delete_stmt );
  header( 'Location: edit_problem.php?id=' . $problem_id );
  exit;
}
if ( isset( $_POST['delete_link_id'] ) && is_numeric( $_POST['delete_link_id'] ) ) {
  task_delete_link( $con, (int)$_POST['delete_link_id'] );
  header( 'Location: edit_problem.php?id=' . $problem_id );
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
    } elseif ( $target['type'] === 'problem' && (int)$target['id'] === $problem_id ) {
      $errors[] = 'Een problem kan niet aan zichzelf gekoppeld worden.';
    } else {
      task_create_link( $con, 'problem', $problem_id, $relation, $target['type'], (int)$target['id'], (int)$operator_context['id'] );
      header( 'Location: edit_problem.php?id=' . $problem_id );
      exit;
    }
  }
}

if ( isset( $_GET['edit_comment'] ) && is_numeric( $_GET['edit_comment'] ) ) {
  $comment_id = (int)$_GET['edit_comment'];
  $comment_stmt = mysqli_prepare( $con, "SELECT * FROM itsm_pm_problemcomments WHERE id = ? AND problemid = ?" );
  mysqli_stmt_bind_param( $comment_stmt, "ii", $comment_id, $problem_id );
  mysqli_stmt_execute( $comment_stmt );
  $comment_result = mysqli_stmt_get_result( $comment_stmt );
  $edit_comment = mysqli_fetch_assoc( $comment_result );
  mysqli_stmt_close( $comment_stmt );
}

$validation_seed = problem_validate_form(
  [
    'title' => $problem['title'],
    'customerid' => (int)$problem['customerid'],
    'personid' => (int)$problem['personid'],
    'categoryid' => (int)$problem['categoryid'],
    'subcategoryid' => (int)$problem['subcategoryid'],
    'assetid' => (int)$problem['assetid'],
    'operatorgroupid' => (int)$problem['operatorgroupid'],
    'operatorid' => (int)$problem['operatorid'],
    'statusid' => (int)$problem['statusid']
  ],
  $reference_data
);

$form_values = [
  'commentid' => $edit_comment ? (string)$edit_comment['id'] : '',
  'title' => $problem['title'],
  'description' => $problem['description'],
  'commenttext' => $edit_comment['commenttext'] ?? '',
  'internalonly' => isset( $edit_comment['internalonly'] ) ? (int)$edit_comment['internalonly'] : 0,
  'customerid' => (string)$problem['customerid'],
  'personid' => (string)$problem['personid'],
  'personemail' => $validation_seed['person']['email'] ?? $problem['personemail'],
  'personphone' => $validation_seed['person']['phone'] ?? $problem['personphone'],
  'categoryid' => (string)$problem['categoryid'],
  'subcategoryid' => (string)$problem['subcategoryid'],
  'assetid' => (string)$problem['assetid'],
  'assettype' => $validation_seed['asset']['typename'] ?? '',
  'operatorgroupid' => (string)$problem['operatorgroupid'],
  'operatorid' => (string)$problem['operatorid'],
  'statusid' => (string)$problem['statusid'],
  'statusready' => isset( $validation_seed['status']['ready'] ) ? (int)$validation_seed['status']['ready'] : 0,
  'statusclosed' => isset( $validation_seed['status']['closed'] ) ? (int)$validation_seed['status']['closed'] : 0,
  'applied_template_id' => '',
  'template_used' => (string)($problem['template_used'] ?? '')
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['delete_comment_id'] ) && !isset( $_POST['add_task_link'] ) ) {
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
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? '',
    'statusready' => 0,
    'statusclosed' => 0,
    'applied_template_id' => $_POST['applied_template_id'] ?? '',
    'template_used' => ( $problem['template_used'] ?? '' ) !== '' ? (string)$problem['template_used'] : ( $_POST['applied_template_id'] ?? '' )
  ];

  $validation = problem_validate_form(
    [
      'title' => $form_values['title'],
      'customerid' => (int)$form_values['customerid'],
      'personid' => (int)$form_values['personid'],
      'categoryid' => (int)$form_values['categoryid'],
      'subcategoryid' => (int)$form_values['subcategoryid'],
      'assetid' => (int)$form_values['assetid'],
      'operatorgroupid' => (int)$form_values['operatorgroupid'],
      'operatorid' => (int)$form_values['operatorid'],
      'statusid' => (int)$form_values['statusid']
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
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $status_id = (int)$validation['status']['id'];
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $template_used = $form_values['template_used'] !== '' ? (int)$form_values['template_used'] : null;

    $update_stmt = mysqli_prepare( $con, "
            UPDATE itsm_pm_problems SET
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
                template_used = ?
            WHERE id = ?
        " );
    mysqli_stmt_bind_param(
      $update_stmt,
      "ssiissiiiiiiii",
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
      $status_id,
      $template_used,
      $problem_id
    );

    if ( !mysqli_stmt_execute( $update_stmt ) ) {
      $errors[] = 'Probleem bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
    } else {
      if ( $form_values['commenttext'] !== '' ) {
        if ( !empty( $form_values['commentid'] ) && is_numeric( $form_values['commentid'] ) ) {
          $comment_id = (int)$form_values['commentid'];
          $comment_stmt = mysqli_prepare( $con, "
                    UPDATE itsm_pm_problemcomments
                    SET commenttext = ?, internalonly = ?
                    WHERE id = ? AND problemid = ?
                " );
          mysqli_stmt_bind_param( $comment_stmt, "siii", $form_values['commenttext'], $form_values['internalonly'], $comment_id, $problem_id );
        } else {
          $comment_stmt = mysqli_prepare( $con, "
                    INSERT INTO itsm_pm_problemcomments (problemid, operatorid, commenttext, internalonly)
                    VALUES (?, ?, ?, ?)
                " );
          $comment_operator_id = (int)$operator_context['id'];
          mysqli_stmt_bind_param( $comment_stmt, "iisi", $problem_id, $comment_operator_id, $form_values['commenttext'], $form_values['internalonly'] );
        }
        mysqli_stmt_execute( $comment_stmt );
      }

      header( 'Location: edit_problem.php?id=' . $problem_id );
      exit;
    }
  }
}

$comments = [];
$comments_result = mysqli_query( $con, "
    SELECT c.*, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_pm_problemcomments c
    LEFT JOIN itsm_ob_operators o ON c.operatorid = o.id
    WHERE c.problemid = " . $problem_id . "
    ORDER BY c.createdat DESC, c.id DESC
" );
while ( $row = mysqli_fetch_assoc( $comments_result ) ) {
  $comments[] = $row;
}

$page_title = 'Probleem ' . problem_format_display_number( $problem );
$submit_label = 'Probleem opslaan';
$show_history = true;
$list_back_url = problem_get_list_back_url( 'problems.php?view=open' );
$action_links = [
  [ 'href' => 'new_change.php?source_type=problem&source_id=' . $problem_id, 'label' => 'Wijziging aanmaken' ],
  [ 'href' => 'incidents.php?view=all&problem_target=' . $problem_id, 'label' => 'Incidenten koppelen' ]
];
$links_html = task_render_links_section( task_load_links( $con, 'problem', $problem_id, 'secure' ) );
?>
<?php require_once(__DIR__ . '/include/problem_form.php'); ?>
