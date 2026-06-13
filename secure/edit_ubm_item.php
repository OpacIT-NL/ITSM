<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/ubm_helpers.php' );
require_once( __DIR__ . '/include/task_helpers.php' );
require_once( __DIR__ . '/include/attachment_helpers.php' );
require_once( __DIR__ . '/include/form_presence_helpers.php' );
require_once( __DIR__ . '/include/task_log_helpers.php' );

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
$operator_context = ubm_get_operator_context( $con, $logged_in_user );
ubm_require_access( $operator_context );
$item_id = (int)$_GET['id'];

$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ubm_items WHERE id = ?" );
mysqli_stmt_bind_param( $stmt, "i", $item_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$item = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$item ) {
  die( 'UBM-item niet gevonden' );
}

$reference_data = ubm_load_reference_data( $con );
$parent_item = null;
if ( !empty( $item['parentid'] ) ) {
  $parent_result = mysqli_query( $con, "SELECT id, ubmnumber, itemtype, title FROM itsm_ubm_items WHERE id = " . (int)$item['parentid'] . " LIMIT 1" );
  $parent_item = mysqli_fetch_assoc( $parent_result ) ?: null;
}

$errors = [];
$presence_error = form_presence_flash_error();
if ( $presence_error !== '' ) {
  $errors[] = $presence_error;
}
if ( isset( $_POST['delete_comment_id'] ) && is_numeric( $_POST['delete_comment_id'] ) ) {
  $delete_comment_id = (int)$_POST['delete_comment_id'];
  $deleted_comment_text = '';
  $deleted_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_ubm_itemcomments WHERE id = ? AND ubmitemid = ?" );
  mysqli_stmt_bind_param( $deleted_comment_stmt, "ii", $delete_comment_id, $item_id );
  mysqli_stmt_execute( $deleted_comment_stmt );
  mysqli_stmt_bind_result( $deleted_comment_stmt, $deleted_comment_text );
  mysqli_stmt_fetch( $deleted_comment_stmt );
  mysqli_stmt_close( $deleted_comment_stmt );

  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_ubm_itemcomments WHERE id = ? AND ubmitemid = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "ii", $delete_comment_id, $item_id );
  if ( mysqli_stmt_execute( $delete_stmt ) ) {
    task_log_add( $con, 'ubm', $item_id, 'updated', 'Commentaar verwijderd: "' . task_log_text_snippet( $deleted_comment_text ) . '".', (int)$operator_context['id'], task_log_text_snippet( $deleted_comment_text ), null );
  }
  mysqli_stmt_close( $delete_stmt );

  header( 'Location: edit_ubm_item.php?id=' . $item_id );
  exit;
}
if ( isset( $_POST['delete_link_id'] ) && is_numeric( $_POST['delete_link_id'] ) ) {
  task_delete_link( $con, (int)$_POST['delete_link_id'] );
  header( 'Location: edit_ubm_item.php?id=' . $item_id );
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
    } else {
      task_create_link( $con, 'ubm', $item_id, $relation, $target['type'], (int)$target['id'], (int)$operator_context['id'] );
      task_log_add( $con, 'ubm', $item_id, 'link_created', 'Link toegevoegd: ' . $relation . ' ' . $target['type'] . ' #' . (int)$target['id'] . '.', (int)$operator_context['id'] );
      header( 'Location: edit_ubm_item.php?id=' . $item_id );
      exit;
    }
  }
}
$edit_comment = null;
if ( isset( $_GET['edit_comment'] ) && is_numeric( $_GET['edit_comment'] ) ) {
  $edit_comment_id = (int)$_GET['edit_comment'];
  $comment_stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ubm_itemcomments WHERE id = ? AND ubmitemid = ?" );
  mysqli_stmt_bind_param( $comment_stmt, "ii", $edit_comment_id, $item_id );
  mysqli_stmt_execute( $comment_stmt );
  $comment_result = mysqli_stmt_get_result( $comment_stmt );
  $edit_comment = mysqli_fetch_assoc( $comment_result );
  mysqli_stmt_close( $comment_stmt );
}
$comment_values = [
  'commentid' => $edit_comment ? (string)$edit_comment['id'] : '',
  'commenttext' => $edit_comment['commenttext'] ?? '',
  'internalonly' => 0
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['add_task_link'] ) && !isset( $_POST['delete_comment_id'] ) ) {
  form_presence_redirect_if_stale( $con, 'ubm', $item_id, $_POST['presence_token'] ?? '', 'edit_ubm_item.php?id=' . $item_id );
  $old_item = $item;
  $old_status_id = (int)$item['statusid'];
  $item['title'] = trim( $_POST['title'] ?? '' );
  $item['description'] = trim( $_POST['description'] ?? '' );
  $item['categoryid'] = $_POST['categoryid'] ?? '';
  $item['subcategoryid'] = $_POST['subcategoryid'] ?? '';
  $item['operatorgroupid'] = $_POST['operatorgroupid'] ?? '';
  $item['operatorid'] = $_POST['operatorid'] ?? '';
  $item['statusid'] = $_POST['statusid'] ?? '';
  $comment_values = [
    'commentid' => $_POST['commentid'] ?? '',
    'commenttext' => trim( $_POST['commenttext'] ?? '' ),
    'internalonly' => 0
  ];

  $validation = ubm_validate_form(
    [
      'parentid' => (int)$item['parentid'],
      'itemtype' => $item['itemtype'],
      'title' => $item['title'],
      'categoryid' => (int)$item['categoryid'],
      'subcategoryid' => (int)$item['subcategoryid'],
      'operatorgroupid' => (int)$item['operatorgroupid'],
      'operatorid' => (int)$item['operatorid'],
      'statusid' => (int)$item['statusid']
    ],
    $reference_data,
    $parent_item
  );
  $errors = $validation['errors'];
  $errors = array_merge( $errors, attachment_upload_errors() );
  $presence_check = form_presence_check_before_save( $con, 'ubm', $item_id, $_POST['presence_token'] ?? '' );
  if ( !$presence_check['ok'] ) {
    $errors[] = $presence_check['message'];
  }

  if ( empty( $errors ) ) {
    $category_id = $validation['category'] ? (int)$validation['category']['id'] : null;
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $status_id = (int)$validation['status']['id'];

    $update_stmt = mysqli_prepare( $con, "
            UPDATE itsm_ubm_items
            SET title = ?, description = ?, categoryid = ?, subcategoryid = ?, operatorgroupid = ?, operatorid = ?, statusid = ?
            WHERE id = ?
        " );
    mysqli_stmt_bind_param( $update_stmt, "ssiiiiii", $item['title'], $item['description'], $category_id, $subcategory_id, $group_id, $operator_id, $status_id, $item_id );
    if ( mysqli_stmt_execute( $update_stmt ) ) {
      form_presence_mark_saved( $con, 'ubm', $item_id, (int)$operator_context['id'] );
      task_log_add( $con, 'ubm', $item_id, 'updated', 'UBM-item opgeslagen.', (int)$operator_context['id'] );
      task_log_field_changes(
        $con,
        'ubm',
        $item_id,
        [
          'title' => $old_item['title'],
          'description' => $old_item['description'],
          'categoryid' => $old_item['categoryid'],
          'subcategoryid' => $old_item['subcategoryid'],
          'operatorgroupid' => $old_item['operatorgroupid'],
          'operatorid' => $old_item['operatorid']
        ],
        [
          'title' => $item['title'],
          'description' => $item['description'],
          'categoryid' => $category_id,
          'subcategoryid' => $subcategory_id,
          'operatorgroupid' => $group_id,
          'operatorid' => $operator_id
        ],
        [
          'title' => 'Titel',
          'description' => 'Omschrijving',
          'categoryid' => 'Categorie',
          'subcategoryid' => 'Subcategorie',
          'operatorgroupid' => 'Team',
          'operatorid' => 'Behandelaar'
        ],
        (int)$operator_context['id']
      );
      task_log_status_change( $con, 'ubm', $item_id, $old_status_id, $status_id, (int)$operator_context['id'] );
      if ( $comment_values['commenttext'] !== '' || attachment_uploaded_file_available() ) {
        $attachment_comment_id = null;
        if ( !empty( $comment_values['commentid'] ) && is_numeric( $comment_values['commentid'] ) ) {
          $comment_id = (int)$comment_values['commentid'];
          $old_comment_text = '';
          $old_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_ubm_itemcomments WHERE id = ? AND ubmitemid = ?" );
          mysqli_stmt_bind_param( $old_comment_stmt, "ii", $comment_id, $item_id );
          mysqli_stmt_execute( $old_comment_stmt );
          mysqli_stmt_bind_result( $old_comment_stmt, $old_comment_text );
          mysqli_stmt_fetch( $old_comment_stmt );
          mysqli_stmt_close( $old_comment_stmt );

          $attachment_comment_id = $comment_id;
          $comment_stmt = mysqli_prepare( $con, "UPDATE itsm_ubm_itemcomments SET commenttext = ?, internalonly = ? WHERE id = ? AND ubmitemid = ?" );
          mysqli_stmt_bind_param( $comment_stmt, "siii", $comment_values['commenttext'], $comment_values['internalonly'], $comment_id, $item_id );
        } else {
          $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_ubm_itemcomments (ubmitemid, operatorid, commenttext, internalonly) VALUES (?,?,?,?)" );
          $operator_id_for_comment = (int)$operator_context['id'];
          $comment_text = $comment_values['commenttext'] !== '' ? $comment_values['commenttext'] : 'Bijlage toegevoegd.';
          mysqli_stmt_bind_param( $comment_stmt, "iisi", $item_id, $operator_id_for_comment, $comment_text, $comment_values['internalonly'] );
        }

        mysqli_stmt_execute( $comment_stmt );
        if ( empty( $attachment_comment_id ) ) {
          $attachment_comment_id = mysqli_insert_id( $con );
          task_log_add( $con, 'ubm', $item_id, 'updated', 'Commentaar toegevoegd: "' . task_log_text_snippet( $comment_text ) . '".', (int)$operator_context['id'], null, task_log_text_snippet( $comment_text ) );
        } else {
          task_log_add( $con, 'ubm', $item_id, 'updated', 'Commentaar bijgewerkt van "' . task_log_text_snippet( $old_comment_text ?? '' ) . '" naar "' . task_log_text_snippet( $comment_values['commenttext'] ) . '".', (int)$operator_context['id'], task_log_text_snippet( $old_comment_text ?? '' ), task_log_text_snippet( $comment_values['commenttext'] ) );
        }
        mysqli_stmt_close( $comment_stmt );
        attachment_save_upload( $con, 'ubm', $item_id, (int)$operator_context['id'], $comment_values['internalonly'], 'ubmcomment', $attachment_comment_id );
      }
      header( 'Location: edit_ubm_item.php?id=' . $item_id );
      exit;
    }
    $errors[] = 'UBM-item bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
  }
}

$children_result = mysqli_query( $con, "
    SELECT u.id, u.itemtype, u.title, c.name AS category_name, sub.name AS subcategory_name, s.name AS status_name
    FROM itsm_ubm_items u
    LEFT JOIN itsm_core_category c ON u.categoryid = c.id
    LEFT JOIN itsm_core_subcategory sub ON u.subcategoryid = sub.id
    LEFT JOIN itsm_core_status s ON u.statusid = s.id
    WHERE u.parentid = " . $item_id . "
    ORDER BY u.title ASC, u.id ASC
" );
$children = [];
while ( $row = mysqli_fetch_assoc( $children_result ) ) {
  $children[] = $row;
}
$allowed_children = ubm_allowed_child_types( $item['itemtype'] );
$links_html = task_render_links_section( task_load_links( $con, 'ubm', $item_id, 'secure' ) );
$attachments = attachment_load_for_task( $con, 'ubm', $item_id );
$comments_result = mysqli_query( $con, "
    SELECT c.*, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_ubm_itemcomments c
    LEFT JOIN itsm_ob_operators o ON c.operatorid = o.id
    WHERE c.ubmitemid = " . $item_id . "
    ORDER BY c.createdat DESC, c.id DESC
" );
$comments = [];
while ( $row = mysqli_fetch_assoc( $comments_result ) ) {
  $comments[] = $row;
}
$attachments_by_comment = attachment_group_by_comment( $attachments );
$attachments_html = attachment_render_as_comments( $attachments );
$task_logs_html = task_log_render_tab( task_log_load( $con, 'ubm', $item_id ) );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <span data-tab-title="<?= htmlspecialchars(ubm_format_display_number($item), ENT_QUOTES) ?>" data-tab-subtitle="<?= htmlspecialchars(ubm_type_label($item['itemtype']), ENT_QUOTES) ?>" hidden></span>
  <?php $list_back_url = ubm_get_list_back_url( 'ubm-menu.php' ); require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars(ubm_format_display_number($item)) ?> - <?= htmlspecialchars($item['title']) ?></h1>
  </center>
  <?php if ( !empty( $errors ) ): ?>
  <div class="form-wrapper"><div class="form-card"><?php foreach ( $errors as $error ): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div></div><br>
  <?php endif; ?>
  <div class="ticket-view-tabs caller-card-tabs" role="tablist">
    <button type="button" class="caller-card-tab is-active" data-ticket-view-tab="task" role="tab" aria-selected="true"><?= htmlspecialchars(t('Taak')) ?></button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="children" role="tab" aria-selected="false"><?= htmlspecialchars(t('Onderliggende taken')) ?></button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="links" role="tab" aria-selected="false"><?= htmlspecialchars(t('Links')) ?></button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="log" role="tab" aria-selected="false"><?= htmlspecialchars(t('Audit log')) ?></button>
  </div>
  <form method="post" enctype="multipart/form-data" class="incident-layout ticket-view-panel is-active" data-ticket-view-panel="task">
    <div class="incident-column">
      <div class="incident-card incident-left-card">
        <div class="form-grid">
          <h2 class="incident-section-title"><?= htmlspecialchars(t('Algemeen')) ?></h2>
          <hr>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Laag')) ?></label>
            <label><input type="text" class="incident-readonly" value="<?= htmlspecialchars(ubm_type_label($item['itemtype'])) ?>" readonly></label>
          </div>
          <?php if ( $parent_item ): ?>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Bovenliggend')) ?></label>
            <label><a class="task-inline-link" href="edit_ubm_item.php?id=<?= (int)$parent_item['id'] ?>"><?= htmlspecialchars(ubm_format_display_number($parent_item)) ?> - <?= htmlspecialchars($parent_item['title']) ?></a></label>
          </div>
          <?php endif; ?>
          <hr>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Categorie')) ?></label>
            <label>
              <select name="categoryid" id="category_id">
                <option value=""><?= htmlspecialchars(t('Selecteer een categorie')) ?></option>
                <?php foreach ( $reference_data['categories'] as $category ): ?>
                <option value="<?= htmlspecialchars((string)$category['id']) ?>" <?= (string)$item['categoryid'] === (string)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Subcategorie')) ?></label>
            <label><select name="subcategoryid" id="subcategory_id"><option value=""><?= htmlspecialchars(t('Selecteer een subcategorie')) ?></option></select></label>
          </div>
          <hr>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Team')) ?></label>
            <label>
              <select name="operatorgroupid" id="operatorgroup_id">
                <option value=""><?= htmlspecialchars(t('Selecteer een team')) ?></option>
                <?php foreach ( $reference_data['groups'] as $group ): ?>
                <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= (string)$item['operatorgroupid'] === (string)$group['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['groupname']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Behandelaar')) ?></label>
            <label class="assign-to-me-row">
              <select name="operatorid" id="operator_id"><option value=""><?= htmlspecialchars(t('Selecteer een behandelaar')) ?></option></select>
              <button type="button" id="assign_to_me_button" class="assign-to-me-button" title="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>" aria-label="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>"><i class="fa-solid fa-user"></i></button>
            </label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Status')) ?></label>
            <label>
              <select name="statusid" required>
                <option value=""><?= htmlspecialchars(t('Selecteer een status')) ?></option>
                <?php foreach ( $reference_data['statuses'] as $status ): ?>
                <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$item['statusid'] === (string)$status['id'] ? 'selected' : '' ?>><?= htmlspecialchars($status['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
        </div>
      </div>
    </div>
    <div class="incident-column">
      <div class="incident-card incident-main-card">
        <div class="form-grid">
          <div class="form-actions">
            <?php if ( !empty( $allowed_children ) ): ?>
            <a href="new_ubm_item.php?parentid=<?= htmlspecialchars((string)$item_id) ?>&type=<?= htmlspecialchars($allowed_children[0]) ?>" class="btn-primary"><?= htmlspecialchars(t('Nieuwe onderliggende taak')) ?></a>
            <?php endif; ?>
            <a href="new_change.php?source_type=ubm&source_id=<?= htmlspecialchars((string)$item_id) ?>" class="btn-primary"><?= htmlspecialchars(t('Wijziging aanmaken')) ?></a>
            <button type="submit" class="btn-primary"><?= htmlspecialchars(t('Opslaan')) ?></button>
          </div>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Titel')) ?></label>
            <label><input type="text" name="title" class="incident-title-input" value="<?= htmlspecialchars($item['title']) ?>" required></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Omschrijving')) ?></label>
            <label><textarea name="description"><?= htmlspecialchars($item['description']) ?></textarea></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Commentaar')) ?></label>
            <label>
              <input type="hidden" name="commentid" value="<?= htmlspecialchars((string)$comment_values['commentid']) ?>">
              <textarea name="commenttext"><?= htmlspecialchars($comment_values['commenttext']) ?></textarea>
            </label>
          </div>
          <?php attachment_render_upload_field(); ?>
          <hr>
          <h3><?= htmlspecialchars(t('Commentaarhistorie')) ?></h3>
          <div class="incident-history">
            <?php if ( empty( $comments ) ): ?>
            <p><?= htmlspecialchars(t('Nog geen commentaar.')) ?></p>
            <?php else: ?>
            <?php foreach ( $comments as $comment ): ?>
            <div class="incident-comment">
              <div class="incident-comment-meta">
                <span><?= htmlspecialchars($comment['operator_name'] ?? '') ?></span>
                <span><?= htmlspecialchars($comment['createdat']) ?></span>
              </div>
              <p><?= task_linkify_text($comment['commenttext'], 'secure') ?></p>
              <?php if ( !empty( $attachments_by_comment[(string)$comment['id']] ) ): ?>
              <?= attachment_render_links( $attachments_by_comment[(string)$comment['id']] ) ?>
              <?php endif; ?>
              <div class="form-actions">
                <a href="edit_ubm_item.php?id=<?= htmlspecialchars((string)$item_id) ?>&edit_comment=<?= htmlspecialchars((string)$comment['id']) ?>"><?= htmlspecialchars(t('Commentaar bewerken')) ?></a>
                <button type="submit" name="delete_comment_id" value="<?= htmlspecialchars((string)$comment['id']) ?>" class="btn-danger" formnovalidate onclick="return confirm('<?= htmlspecialchars(t('Weet je zeker dat je dit commentaar wil verwijderen?'), ENT_QUOTES) ?>');"><?= htmlspecialchars(t('Commentaar verwijderen')) ?></button>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <?php if ( !empty( $attachments_html ) ): ?>
          <h3><?= htmlspecialchars(t('Bijlagen')) ?></h3>
          <?= $attachments_html ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </form>
  <div class="ticket-view-panel" data-ticket-view-panel="children">
    <div class="form-wrapper">
      <div class="form-card form-card-wide">
        <div class="form-actions">
          <?php if ( !empty( $allowed_children ) ): ?>
          <a href="new_ubm_item.php?parentid=<?= htmlspecialchars((string)$item_id) ?>&type=<?= htmlspecialchars($allowed_children[0]) ?>" class="btn-primary"><?= htmlspecialchars(t('Nieuwe onderliggende taak')) ?></a>
          <?php endif; ?>
        </div>
        <div class="results incident-results">
          <table border="0" class="results incident-results-table" style="width: 100%;">
            <thead>
              <tr>
                <th style="text-align: start;"><?= htmlspecialchars(t('Laag')) ?></th>
                <th style="text-align: start;"><?= htmlspecialchars(t('Nummer')) ?></th>
                <th style="text-align: start;"><?= htmlspecialchars(t('Titel')) ?></th>
                <th style="text-align: start;"><?= htmlspecialchars(t('Categorie')) ?></th>
                <th style="text-align: start;"><?= htmlspecialchars(t('Subcategorie')) ?></th>
                <th style="text-align: start;"><?= htmlspecialchars(t('Status')) ?></th>
                <th style="text-align: start;"><?= htmlspecialchars(t('Actie')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if ( empty( $children ) ): ?>
              <tr><td colspan="7"><?= htmlspecialchars(t('Nog geen onderliggende taken.')) ?></td></tr>
              <?php else: ?>
              <?php foreach ( $children as $child ): ?>
              <tr>
                <td><?= htmlspecialchars(ubm_type_label($child['itemtype'])) ?></td>
                <td><?= htmlspecialchars(ubm_format_display_number($child)) ?></td>
                <td><?= htmlspecialchars($child['title']) ?></td>
                <td><?= htmlspecialchars($child['category_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($child['subcategory_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($child['status_name'] ?? '') ?></td>
                <td class="tblaction"><a class="btn" href="edit_ubm_item.php?id=<?= htmlspecialchars((string)$child['id']) ?>"><?= htmlspecialchars(t('Open item')) ?></a></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="links">
    <div class="form-wrapper">
      <div class="form-card form-card-wide">
        <form method="post">
          <?= $links_html ?>
        </form>
      </div>
    </div>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="log">
    <div class="form-wrapper">
      <div class="form-card form-card-wide">
        <?= $task_logs_html ?>
      </div>
    </div>
  </div>
</div>
<script>
const editUbmSubcategories = <?= json_encode($reference_data['subcategories'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const editUbmCurrentSubcategoryId = <?= json_encode((string)$item['subcategoryid']) ?>;
const editUbmOperators = <?= json_encode($reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const editUbmOpLinks = <?= json_encode($reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const editUbmCurrentOperatorId = <?= json_encode((string)$item['operatorid']) ?>;
const editUbmLoggedInOperatorId = <?= json_encode((string)($operator_context['id'] ?? '')) ?>;
const editUbmLoggedInOperatorGroupIds = [...new Set(editUbmOpLinks.filter((row) => String(row.operatorid) === String(editUbmLoggedInOperatorId)).map((row) => String(row.groupid)))];
const editUbmFormI18n = {
  selectSubcategory: <?= json_encode(t('Selecteer een subcategorie')) ?>,
  selectOperator: <?= json_encode(t('Selecteer een behandelaar')) ?>
};
document.querySelectorAll('[data-ticket-view-tab]').forEach((tab) => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.ticketViewTab;
    document.querySelectorAll('[data-ticket-view-tab]').forEach((button) => {
      const active = button.dataset.ticketViewTab === target;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-ticket-view-panel]').forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.ticketViewPanel === target);
    });
  });
});
function refreshEditUbmSubcategories() {
  const categoryId = document.getElementById('category_id').value;
  const select = document.getElementById('subcategory_id');
  select.innerHTML = `<option value="">${editUbmFormI18n.selectSubcategory}</option>`;
  editUbmSubcategories.filter((row) => String(row.parent) === String(categoryId)).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    if (String(row.id) === String(editUbmCurrentSubcategoryId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}
function editUbmOperatorLabel(row) { return `${row.lastname}, ${row.firstname}`; }
function refreshEditUbmOperators() {
  const groupId = document.getElementById('operatorgroup_id').value;
  const select = document.getElementById('operator_id');
  const operatorIds = groupId ? editUbmOpLinks.filter((row) => String(row.groupid) === String(groupId)).map((row) => String(row.operatorid)) : editUbmOperators.map((row) => String(row.id));
  select.innerHTML = `<option value="">${editUbmFormI18n.selectOperator}</option>`;
  editUbmOperators.filter((row) => operatorIds.includes(String(row.id))).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = editUbmOperatorLabel(row);
    if (String(row.id) === String(editUbmCurrentOperatorId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
  refreshEditUbmAssignToMeButton();
}
function refreshEditUbmAssignToMeButton() {
  const button = document.getElementById('assign_to_me_button');
  const groupId = String(document.getElementById('operatorgroup_id').value || '');
  if (!button) { return; }
  button.disabled = !editUbmLoggedInOperatorId || (editUbmLoggedInOperatorGroupIds.length !== 1 && !groupId) || (groupId && !editUbmLoggedInOperatorGroupIds.includes(groupId));
}
function assignEditUbmToMe() {
  const groupSelect = document.getElementById('operatorgroup_id');
  if (editUbmLoggedInOperatorGroupIds.length === 1 && !groupSelect.value) {
    groupSelect.value = editUbmLoggedInOperatorGroupIds[0];
    refreshEditUbmOperators();
  }
  const groupId = String(groupSelect.value || '');
  if ((editUbmLoggedInOperatorGroupIds.length !== 1 && !groupId) || (groupId && !editUbmLoggedInOperatorGroupIds.includes(groupId))) { return; }
  const select = document.getElementById('operator_id');
  select.value = String(editUbmLoggedInOperatorId);
  select.dispatchEvent(new Event('change', { bubbles: true }));
  refreshEditUbmAssignToMeButton();
}
document.getElementById('category_id').addEventListener('change', refreshEditUbmSubcategories);
refreshEditUbmSubcategories();
document.getElementById('operatorgroup_id').addEventListener('change', refreshEditUbmOperators);
document.getElementById('assign_to_me_button')?.addEventListener('click', assignEditUbmToMe);
refreshEditUbmOperators();
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
