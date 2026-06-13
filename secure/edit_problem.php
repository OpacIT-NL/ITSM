<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/problem_helpers.php' );
require_once( __DIR__ . '/include/task_helpers.php' );
require_once( __DIR__ . '/include/mail_helpers.php' );
require_once( __DIR__ . '/include/attachment_helpers.php' );
require_once( __DIR__ . '/include/form_presence_helpers.php' );
require_once( __DIR__ . '/include/task_log_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
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
    mail_send_manual_rule( $con, $manual_mail_rule_id, 'problem', $problem_id, (int)$operator_context['id'] )
  ];
  header( 'Location: edit_problem.php?id=' . $problem_id );
  exit;
}

if ( isset( $_POST['delete_comment_id'] ) && is_numeric( $_POST['delete_comment_id'] ) ) {
  $delete_comment_id = (int)$_POST['delete_comment_id'];
  $deleted_comment_text = '';
  $deleted_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_pm_problemcomments WHERE id = ? AND problemid = ?" );
  mysqli_stmt_bind_param( $deleted_comment_stmt, "ii", $delete_comment_id, $problem_id );
  mysqli_stmt_execute( $deleted_comment_stmt );
  mysqli_stmt_bind_result( $deleted_comment_stmt, $deleted_comment_text );
  mysqli_stmt_fetch( $deleted_comment_stmt );
  mysqli_stmt_close( $deleted_comment_stmt );
  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_pm_problemcomments WHERE id = ? AND problemid = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "ii", $delete_comment_id, $problem_id );
  mysqli_stmt_execute( $delete_stmt );
  task_log_add( $con, 'problem', $problem_id, 'updated', 'Commentaar verwijderd: "' . task_log_text_snippet( $deleted_comment_text ) . '".', (int)$operator_context['id'], task_log_text_snippet( $deleted_comment_text ), null );
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
      task_log_add( $con, 'problem', $problem_id, 'link_created', 'Link toegevoegd: ' . $relation . ' ' . $target['type'] . ' #' . (int)$target['id'] . '.', (int)$operator_context['id'] );
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
    'statusid' => (int)$problem['statusid'],
    'impactid' => (int)($problem['impactid'] ?? 0),
    'urgencyid' => (int)($problem['urgencyid'] ?? 0)
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
  'impactid' => (string)($problem['impactid'] ?? ''),
  'urgencyid' => (string)($problem['urgencyid'] ?? ''),
  'priorityid' => (string)($problem['priorityid'] ?? ''),
  'priorityname' => priority_name_by_id( $reference_data, $problem['priorityid'] ?? null )
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['delete_comment_id'] ) && !isset( $_POST['add_task_link'] ) && !isset( $_POST['manual_mail_rule_id'] ) ) {
  form_presence_redirect_if_stale( $con, 'problem', $problem_id, $_POST['presence_token'] ?? '', 'edit_problem.php?id=' . $problem_id );
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
    'impactid' => $_POST['impactid'] ?? '',
    'urgencyid' => $_POST['urgencyid'] ?? '',
    'priorityid' => '',
    'priorityname' => ''
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
      'statusid' => (int)$form_values['statusid'],
      'impactid' => (int)$form_values['impactid'],
      'urgencyid' => (int)$form_values['urgencyid']
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
  $presence_check = form_presence_check_before_save( $con, 'problem', $problem_id, $_POST['presence_token'] ?? '' );
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
    $operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $old_status_id = (int)$problem['statusid'];
    $status_id = (int)$validation['status']['id'];
    $impact_id = $validation['priority']['impactid'];
    $urgency_id = $validation['priority']['urgencyid'];
    $priority_id = $validation['priority']['priorityid'];
    $person_email = $validation['person']['email'] ?? '';
    $person_phone = $validation['person']['phone'] ?? '';

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
                impactid = ?,
                urgencyid = ?,
                priorityid = ?
            WHERE id = ?
        " );
    mysqli_stmt_bind_param(
      $update_stmt,
      "ssiissiiiiiiiiii",
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
      $impact_id,
      $urgency_id,
      $priority_id,
      $problem_id
    );

    if ( !mysqli_stmt_execute( $update_stmt ) ) {
      $errors[] = 'Probleem bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
    } else {
      form_presence_mark_saved( $con, 'problem', $problem_id, (int)$operator_context['id'] );
      task_log_add( $con, 'problem', $problem_id, 'updated', 'Problem opgeslagen.', (int)$operator_context['id'] );
      task_log_field_changes(
        $con,
        'problem',
        $problem_id,
        [
          'title' => $problem['title'],
          'description' => $problem['description'],
          'customerid' => $problem['customerid'],
          'personid' => $problem['personid'],
          'categoryid' => $problem['categoryid'],
          'subcategoryid' => $problem['subcategoryid'],
          'assetid' => $problem['assetid'],
          'operatorgroupid' => $problem['operatorgroupid'],
          'operatorid' => $problem['operatorid'],
          'impactid' => $problem['impactid'] ?? null,
          'urgencyid' => $problem['urgencyid'] ?? null,
          'priorityid' => $problem['priorityid'] ?? null
        ],
        [
          'title' => $form_values['title'],
          'description' => $form_values['description'],
          'customerid' => $customer_id,
          'personid' => $person_id,
          'categoryid' => $category_id,
          'subcategoryid' => $subcategory_id,
          'assetid' => $asset_id,
          'operatorgroupid' => $group_id,
          'operatorid' => $operator_id,
          'impactid' => $impact_id,
          'urgencyid' => $urgency_id,
          'priorityid' => $priority_id
        ],
        [
          'title' => 'Titel',
          'description' => 'Omschrijving',
          'customerid' => 'Klant',
          'personid' => 'Persoon',
          'categoryid' => 'Categorie',
          'subcategoryid' => 'Subcategorie',
          'assetid' => 'Object ID',
          'operatorgroupid' => 'Behandelaarsgroep',
          'operatorid' => 'Behandelaar',
          'impactid' => t('Impact'),
          'urgencyid' => t('Urgency'),
          'priorityid' => t('Priority')
        ],
        (int)$operator_context['id']
      );
      if ( $form_values['commenttext'] !== '' || attachment_uploaded_file_available() ) {
        $attachment_comment_id = null;
        if ( !empty( $form_values['commentid'] ) && is_numeric( $form_values['commentid'] ) ) {
          $comment_id = (int)$form_values['commentid'];
          $old_comment_text = '';
          $old_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_pm_problemcomments WHERE id = ? AND problemid = ?" );
          mysqli_stmt_bind_param( $old_comment_stmt, "ii", $comment_id, $problem_id );
          mysqli_stmt_execute( $old_comment_stmt );
          mysqli_stmt_bind_result( $old_comment_stmt, $old_comment_text );
          mysqli_stmt_fetch( $old_comment_stmt );
          mysqli_stmt_close( $old_comment_stmt );
          $attachment_comment_id = $comment_id;
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
          $comment_text = $form_values['commenttext'] !== '' ? $form_values['commenttext'] : 'Bijlage toegevoegd.';
          mysqli_stmt_bind_param( $comment_stmt, "iisi", $problem_id, $comment_operator_id, $comment_text, $form_values['internalonly'] );
        }
        mysqli_stmt_execute( $comment_stmt );
        if ( empty( $attachment_comment_id ) ) {
          $attachment_comment_id = mysqli_insert_id( $con );
          task_log_add( $con, 'problem', $problem_id, 'updated', 'Commentaar toegevoegd: "' . task_log_text_snippet( $comment_text ) . '".', (int)$operator_context['id'], null, task_log_text_snippet( $comment_text ) );
        } else {
          task_log_add( $con, 'problem', $problem_id, 'updated', 'Commentaar bijgewerkt van "' . task_log_text_snippet( $old_comment_text ?? '' ) . '" naar "' . task_log_text_snippet( $form_values['commenttext'] ) . '".', (int)$operator_context['id'], task_log_text_snippet( $old_comment_text ?? '' ), task_log_text_snippet( $form_values['commenttext'] ) );
        }
        attachment_save_upload( $con, 'problem', $problem_id, (int)$operator_context['id'], $form_values['internalonly'], 'problemcomment', $attachment_comment_id );
      }
      mail_process_status_change( $con, 'problem', $problem_id, $old_status_id, $status_id, (int)$operator_context['id'] );
      task_log_status_change( $con, 'problem', $problem_id, $old_status_id, $status_id, (int)$operator_context['id'] );

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
$attachments = attachment_load_for_task( $con, 'problem', $problem_id );
$attachments_by_comment = attachment_group_by_comment( $attachments );
$attachments_html = attachment_render_as_comments( $attachments );

$page_title = 'Probleem ' . problem_format_display_number( $problem );
$tab_title = problem_format_display_number( $problem );
$tab_subtitle = 'Problem';
$submit_label = 'Probleem opslaan';
$show_history = true;
$list_back_url = problem_get_list_back_url( 'problems.php?view=open' );
$action_links = [
  [ 'href' => 'new_change.php?source_type=problem&source_id=' . $problem_id, 'label' => 'Wijziging aanmaken' ],
  [ 'href' => 'incidents.php?view=all&problem_target=' . $problem_id, 'label' => 'Incidenten koppelen' ]
];
$links_html = task_render_links_section( task_load_links( $con, 'problem', $problem_id, 'secure' ) );
$task_logs_html = task_log_render_tab( task_log_load( $con, 'problem', $problem_id ) );
$mail_tab_html = mail_render_manual_tab( mail_load_manual_rules( $con, 'problem' ), $mail_messages );
?>
<?php require_once(__DIR__ . '/include/problem_form.php'); ?>
