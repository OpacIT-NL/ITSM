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

$logged_in_user = $_SESSION['name'];
$operator_context = problem_get_operator_context( $con, $logged_in_user );
problem_require_access( $operator_context );

$reference_data = problem_load_reference_data( $con );
$default_status_id = problem_default_status_id( $reference_data['statuses'] );
$default_group_id = problem_default_group_id( $reference_data['groups'] );
$errors = [];
$comments = [];
$source_type = $_GET['source_type'] ?? '';
$source_id = isset( $_GET['source_id'] ) && is_numeric( $_GET['source_id'] ) ? (int)$_GET['source_id'] : 0;

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
  'operatorgroupid' => $default_group_id > 0 ? (string)$default_group_id : '',
  'operatorid' => '',
  'statusid' => (string)$default_status_id,
  'statusready' => 0,
  'statusclosed' => 0,
  'applied_template_id' => '',
  'template_used' => ''
];

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' && $source_id > 0 && $source_type === 'incident' ) {
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
    'operatorgroupid' => ($_POST['operatorgroupid'] ?? '') !== '' ? $_POST['operatorgroupid'] : ( $default_group_id > 0 ? (string)$default_group_id : '' ),
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? '',
    'statusready' => 0,
    'statusclosed' => 0,
    'applied_template_id' => $_POST['applied_template_id'] ?? '',
    'template_used' => $_POST['applied_template_id'] ?? ''
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
    $created_by = (int)$operator_context['id'];
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';
    $problem_number = problem_generate_number( $con );

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_pm_problems (
                problemnumber, title, description, customerid, personid, personemail, personphone,
                categoryid, subcategoryid, assetid, operatorgroupid, operatorid, statusid, template_used, createdby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        " );
    $template_used = ($form_values['applied_template_id'] ?? '') !== '' ? (int)$form_values['applied_template_id'] : null;
    mysqli_stmt_bind_param(
      $stmt,
      "sssiissiiiiiiii",
      $problem_number,
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
      $created_by
    );

    if ( !mysqli_stmt_execute( $stmt ) ) {
      $errors[] = 'Probleem opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    } else {
      $problem_id = mysqli_insert_id( $con );
      if ( $form_values['commenttext'] !== '' ) {
        $comment_stmt = mysqli_prepare( $con, "
                    INSERT INTO itsm_pm_problemcomments (problemid, operatorid, commenttext, internalonly)
                    VALUES (?, ?, ?, ?)
                " );
        mysqli_stmt_bind_param( $comment_stmt, "iisi", $problem_id, $created_by, $form_values['commenttext'], $form_values['internalonly'] );
        mysqli_stmt_execute( $comment_stmt );
      }
      if ( $source_id > 0 && $source_type === 'incident' ) {
        task_create_link( $con, 'problem', $problem_id, 'Afgeleid van', 'incident', $source_id, $created_by );
      }

      header( 'Location: edit_problem.php?id=' . $problem_id );
      exit;
    }
  }
}

$page_title = 'Probleem aanmaken';
$submit_label = 'Probleem opslaan';
$show_history = false;
$problem_id = 0;
$list_back_url = problem_get_list_back_url( 'problems.php?view=open' );
?>
<?php require_once(__DIR__ . '/include/problem_form.php'); ?>
