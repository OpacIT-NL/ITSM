<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );
require_once( __DIR__ . '/include/task_helpers.php' );
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
$operator_context = change_get_operator_context( $con, $logged_in_user );
change_require_access( $operator_context );
$activity_id = (int)$_GET['id'];

$stmt = mysqli_prepare( $con, "
    SELECT
      a.*,
      c.id AS change_id,
      c.changenumber,
      c.requesttype,
      c.approvalstate,
      c.changetype,
      c.title AS change_title,
      c.description AS change_description,
      c.personemail,
      c.personphone,
      c.createdat AS change_createdat,
      c.updatedat AS change_updatedat,
      cat.name AS category_name,
      sub.name AS subcategory_name,
      asset.objectid AS asset_objectid,
      at.type AS asset_type,
      cust.name AS customer_name,
      cust.din AS customer_din,
      CONCAT(p.lastname, ', ', p.firstname) AS person_name,
      cg.groupname AS change_group_name,
      CONCAT(co.lastname, ', ', co.firstname) AS change_operator_name,
      s.name AS change_status_name,
      imp.name AS impact_name,
      urg.name AS urgency_name,
      prio.name AS priority_name
    FROM itsm_cm_changeactivities a
    INNER JOIN itsm_cm_changes c ON a.changeid = c.id
    LEFT JOIN itsm_core_category cat ON c.categoryid = cat.id
    LEFT JOIN itsm_core_subcategory sub ON c.subcategoryid = sub.id
    LEFT JOIN itsm_am_assets asset ON c.assetid = asset.id
    LEFT JOIN itsm_am_types at ON asset.type = at.id
    LEFT JOIN itsm_ob_customers cust ON c.customerid = cust.id
    LEFT JOIN itsm_ob_persons p ON c.personid = p.id
    LEFT JOIN itsm_ob_operatorgroups cg ON c.operatorgroupid = cg.id
    LEFT JOIN itsm_ob_operators co ON c.operatorid = co.id
    LEFT JOIN itsm_core_status s ON c.statusid = s.id
    LEFT JOIN itsm_core_impacts imp ON c.impactid = imp.id
    LEFT JOIN itsm_core_urgencies urg ON c.urgencyid = urg.id
    LEFT JOIN itsm_core_priorities prio ON c.priorityid = prio.id
    WHERE a.id = ?
" );
mysqli_stmt_bind_param( $stmt, "i", $activity_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$activity = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$activity ) {
  die( 'Wijzigingsactiviteit niet gevonden' );
}

$reference_data = change_load_reference_data( $con );
$errors = [];
$edit_comment = null;
$presence_error = form_presence_flash_error();
if ( $presence_error !== '' ) {
  $errors[] = $presence_error;
}

if ( isset( $_POST['delete_comment_id'] ) && is_numeric( $_POST['delete_comment_id'] ) ) {
  $delete_comment_id = (int)$_POST['delete_comment_id'];
  $deleted_comment_text = '';
  $deleted_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_cm_changeactivitycomments WHERE id = ? AND changeactivityid = ?" );
  mysqli_stmt_bind_param( $deleted_comment_stmt, "ii", $delete_comment_id, $activity_id );
  mysqli_stmt_execute( $deleted_comment_stmt );
  mysqli_stmt_bind_result( $deleted_comment_stmt, $deleted_comment_text );
  mysqli_stmt_fetch( $deleted_comment_stmt );
  mysqli_stmt_close( $deleted_comment_stmt );

  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_cm_changeactivitycomments WHERE id = ? AND changeactivityid = ?" );
  mysqli_stmt_bind_param( $delete_stmt, "ii", $delete_comment_id, $activity_id );
  mysqli_stmt_execute( $delete_stmt );
  mysqli_stmt_close( $delete_stmt );

  task_log_add( $con, 'changeactivity', $activity_id, 'updated', 'Commentaar verwijderd: "' . task_log_text_snippet( $deleted_comment_text ) . '".', (int)$operator_context['id'], task_log_text_snippet( $deleted_comment_text ), null );
  header( 'Location: edit_change_activity.php?id=' . $activity_id );
  exit;
}

if ( isset( $_POST['delete_link_id'] ) && is_numeric( $_POST['delete_link_id'] ) ) {
  task_delete_link( $con, (int)$_POST['delete_link_id'] );
  header( 'Location: edit_change_activity.php?id=' . $activity_id );
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
    } elseif ( $target['type'] === 'changeactivity' && (int)$target['id'] === $activity_id ) {
      $errors[] = 'Een wijzigingsactiviteit kan niet aan zichzelf gekoppeld worden.';
    } else {
      task_create_link( $con, 'changeactivity', $activity_id, $relation, $target['type'], (int)$target['id'], (int)$operator_context['id'] );
      task_log_add( $con, 'changeactivity', $activity_id, 'link_created', 'Link toegevoegd: ' . $relation . ' ' . $target['type'] . ' #' . (int)$target['id'] . '.', (int)$operator_context['id'] );
      header( 'Location: edit_change_activity.php?id=' . $activity_id );
      exit;
    }
  }
}

if ( isset( $_GET['edit_comment'] ) && is_numeric( $_GET['edit_comment'] ) ) {
  $edit_comment_id = (int)$_GET['edit_comment'];
  $comment_stmt = mysqli_prepare( $con, "SELECT * FROM itsm_cm_changeactivitycomments WHERE id = ? AND changeactivityid = ?" );
  mysqli_stmt_bind_param( $comment_stmt, "ii", $edit_comment_id, $activity_id );
  mysqli_stmt_execute( $comment_stmt );
  $comment_result = mysqli_stmt_get_result( $comment_stmt );
  $edit_comment = mysqli_fetch_assoc( $comment_result );
  mysqli_stmt_close( $comment_stmt );
}

$form_values = [
  'title' => $activity['title'],
  'description' => $activity['description'],
  'operatorgroupid' => (string)$activity['operatorgroupid'],
  'operatorid' => (string)$activity['operatorid'],
  'statusid' => (string)$activity['statusid'],
  'commentid' => $edit_comment ? (string)$edit_comment['id'] : '',
  'commenttext' => $edit_comment['commenttext'] ?? '',
  'internalonly' => isset( $edit_comment['internalonly'] ) ? (int)$edit_comment['internalonly'] : 1
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['add_task_link'] ) && !isset( $_POST['delete_comment_id'] ) ) {
  form_presence_redirect_if_stale( $con, 'changeactivity', $activity_id, $_POST['presence_token'] ?? '', 'edit_change_activity.php?id=' . $activity_id );
  $old_activity = $activity;
  $old_status_id = (int)$activity['statusid'];
  $form_values = [
    'title' => trim( $_POST['title'] ?? '' ),
    'description' => trim( $_POST['description'] ?? '' ),
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? '',
    'commentid' => $_POST['commentid'] ?? '',
    'commenttext' => trim( $_POST['commenttext'] ?? '' ),
    'internalonly' => isset( $_POST['internalonly'] ) ? 1 : 0
  ];

  $validation = change_validate_activity_form(
    [
      'title' => $form_values['title'],
      'operatorgroupid' => (int)$form_values['operatorgroupid'],
      'operatorid' => (int)$form_values['operatorid'],
      'statusid' => (int)$form_values['statusid']
    ],
    $reference_data
  );
  $errors = $validation['errors'];
  $errors = array_merge( $errors, attachment_upload_errors() );
  $presence_check = form_presence_check_before_save( $con, 'changeactivity', $activity_id, $_POST['presence_token'] ?? '' );
  if ( !$presence_check['ok'] ) {
    $errors[] = $presence_check['message'];
  }

  if ( empty( $errors ) ) {
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $status_id = (int)$validation['status']['id'];

    $update_stmt = mysqli_prepare( $con, "
        UPDATE itsm_cm_changeactivities SET
          title = ?,
          description = ?,
          operatorgroupid = ?,
          operatorid = ?,
          statusid = ?
        WHERE id = ?
    " );
    mysqli_stmt_bind_param(
      $update_stmt,
      "ssiiii",
      $form_values['title'],
      $form_values['description'],
      $group_id,
      $operator_id,
      $status_id,
      $activity_id
    );

    if ( !mysqli_stmt_execute( $update_stmt ) ) {
      $errors[] = 'Wijzigingsactiviteit bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
    } else {
      attachment_save_upload( $con, 'changeactivity', $activity_id, (int)$operator_context['id'], 0 );
      form_presence_mark_saved( $con, 'changeactivity', $activity_id, (int)$operator_context['id'] );
      task_log_add( $con, 'changeactivity', $activity_id, 'updated', 'Wijzigingsactiviteit opgeslagen.', (int)$operator_context['id'] );
      task_log_field_changes(
        $con,
        'changeactivity',
        $activity_id,
        [
          'title' => $old_activity['title'],
          'description' => $old_activity['description'],
          'operatorgroupid' => $old_activity['operatorgroupid'],
          'operatorid' => $old_activity['operatorid']
        ],
        [
          'title' => $form_values['title'],
          'description' => $form_values['description'],
          'operatorgroupid' => $group_id,
          'operatorid' => $operator_id
        ],
        [
          'title' => 'Titel',
          'description' => 'Omschrijving',
          'operatorgroupid' => 'Behandelaarsgroep',
          'operatorid' => 'Behandelaar'
        ],
        (int)$operator_context['id']
      );
      task_log_status_change( $con, 'changeactivity', $activity_id, $old_status_id, $status_id, (int)$operator_context['id'] );
      if ( !empty( $form_values['commentid'] ) || $form_values['commenttext'] !== '' || attachment_uploaded_file_available() ) {
        $attachment_comment_id = null;
        if ( !empty( $form_values['commentid'] ) && is_numeric( $form_values['commentid'] ) ) {
          $comment_id = (int)$form_values['commentid'];
          $old_comment_text = '';
          $old_comment_stmt = mysqli_prepare( $con, "SELECT commenttext FROM itsm_cm_changeactivitycomments WHERE id = ? AND changeactivityid = ?" );
          mysqli_stmt_bind_param( $old_comment_stmt, "ii", $comment_id, $activity_id );
          mysqli_stmt_execute( $old_comment_stmt );
          mysqli_stmt_bind_result( $old_comment_stmt, $old_comment_text );
          mysqli_stmt_fetch( $old_comment_stmt );
          mysqli_stmt_close( $old_comment_stmt );
          $attachment_comment_id = $comment_id;

          $comment_stmt = mysqli_prepare( $con, "
              UPDATE itsm_cm_changeactivitycomments
              SET commenttext = ?, internalonly = ?
              WHERE id = ? AND changeactivityid = ?
          " );
          mysqli_stmt_bind_param( $comment_stmt, "siii", $form_values['commenttext'], $form_values['internalonly'], $comment_id, $activity_id );
        } else {
          $comment_stmt = mysqli_prepare( $con, "
              INSERT INTO itsm_cm_changeactivitycomments (changeactivityid, operatorid, commenttext, internalonly)
              VALUES (?, ?, ?, ?)
          " );
          $comment_operator_id = (int)$operator_context['id'];
          $comment_text = $form_values['commenttext'] !== '' ? $form_values['commenttext'] : 'Bijlage toegevoegd.';
          mysqli_stmt_bind_param( $comment_stmt, "iisi", $activity_id, $comment_operator_id, $comment_text, $form_values['internalonly'] );
        }

        mysqli_stmt_execute( $comment_stmt );
        if ( empty( $attachment_comment_id ) ) {
          $attachment_comment_id = mysqli_insert_id( $con );
          task_log_add( $con, 'changeactivity', $activity_id, 'updated', 'Commentaar toegevoegd: "' . task_log_text_snippet( $comment_text ) . '".', (int)$operator_context['id'], null, task_log_text_snippet( $comment_text ) );
        } else {
          task_log_add( $con, 'changeactivity', $activity_id, 'updated', 'Commentaar bijgewerkt van "' . task_log_text_snippet( $old_comment_text ?? '' ) . '" naar "' . task_log_text_snippet( $form_values['commenttext'] ) . '".', (int)$operator_context['id'], task_log_text_snippet( $old_comment_text ?? '' ), task_log_text_snippet( $form_values['commenttext'] ) );
        }
        mysqli_stmt_close( $comment_stmt );
        attachment_save_upload( $con, 'changeactivity', $activity_id, (int)$operator_context['id'], $form_values['internalonly'], 'changeactivitycomment', $attachment_comment_id );
      }
      header( 'Location: edit_change_activity.php?id=' . $activity_id );
      exit;
    }
  }
}

$comments = [];
$comments_result = mysqli_query( $con, "
    SELECT c.*, CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_cm_changeactivitycomments c
    LEFT JOIN itsm_ob_operators o ON c.operatorid = o.id
    WHERE c.changeactivityid = " . $activity_id . "
    ORDER BY c.createdat DESC, c.id DESC
" );
if ( $comments_result ) {
  while ( $row = mysqli_fetch_assoc( $comments_result ) ) {
    $row['operator_name'] = $row['operator_name'] ?: 'Onbekend';
    $comments[] = $row;
  }
}

$groups_json = json_encode( $reference_data['groups'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$operators_json = json_encode( $reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$op_links_json = json_encode( $reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$attachments = attachment_load_for_task( $con, 'changeactivity', $activity_id );
$attachments_by_comment = attachment_group_by_comment( $attachments );
$attachments_html = attachment_render_as_comments( $attachments );
$task_logs_html = task_log_render_tab( task_log_load( $con, 'changeactivity', $activity_id ) );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <span data-tab-title="<?= htmlspecialchars(change_format_activity_number($activity), ENT_QUOTES) ?>" data-tab-subtitle="<?= htmlspecialchars(t('Wijzigingsactiviteit'), ENT_QUOTES) ?>" hidden></span>
  <?php $list_back_url = 'change_activities.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Wijzigingsactiviteit <?= htmlspecialchars(change_format_activity_number($activity)) ?></h1>
  </center>

  <?php if ( !empty( $errors ) ): ?>
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card form-card-wide">
      <?php foreach ( $errors as $error ): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
  <br>
  <?php endif; ?>

  <div class="ticket-view-tabs caller-card-tabs" role="tablist">
    <button type="button" class="caller-card-tab is-active" data-ticket-view-tab="task" role="tab" aria-selected="true">Taak</button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="links" role="tab" aria-selected="false">Links</button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="log" role="tab" aria-selected="false">Audit log</button>
  </div>

  <div class="ticket-view-panel is-active" data-ticket-view-panel="task">
    <form method="post" enctype="multipart/form-data" class="incident-layout">
      <div class="incident-column">
        <div class="incident-card incident-left-card">
          <div class="form-grid">
            <h2 class="incident-section-title">Wijziging</h2>
            <hr>
            <div class="form-group">
              <label class="incident-meta-label">Nummer</label>
              <label><a href="edit_change.php?id=<?= htmlspecialchars((string)$activity['change_id']) ?>"><?= htmlspecialchars(change_format_display_number($activity)) ?></a></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Titel</label>
              <label><?= htmlspecialchars($activity['change_title']) ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Type</label>
              <label><?= htmlspecialchars(change_approval_state_label($activity)) ?> / <?= htmlspecialchars(change_type_label($activity['changetype'] ?? '')) ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Klant</label>
              <label><?= htmlspecialchars(trim(($activity['customer_din'] ?? '') . ' - ' . ($activity['customer_name'] ?? ''), ' -')) ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Aanmelder</label>
              <label><?= htmlspecialchars($activity['person_name'] ?: trim(($activity['personemail'] ?? '') . ' ' . ($activity['personphone'] ?? ''))) ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Object ID</label>
              <label><?= htmlspecialchars(trim(($activity['asset_objectid'] ?? '') . ( !empty($activity['asset_type']) ? ' (' . $activity['asset_type'] . ')' : '' ))) ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Categorie</label>
              <label><?= htmlspecialchars(trim(($activity['category_name'] ?? '') . ' / ' . ($activity['subcategory_name'] ?? ''), ' /')) ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Prioriteit</label>
              <label><?= htmlspecialchars(trim(($activity['impact_name'] ?? '') . ' / ' . ($activity['urgency_name'] ?? '') . ' / ' . ($activity['priority_name'] ?? ''), ' /')) ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Wijzigingsstatus</label>
              <label><?= htmlspecialchars($activity['change_status_name'] ?? '') ?></label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Wijzigingsteam</label>
              <label><?= htmlspecialchars(trim(($activity['change_group_name'] ?? '') . ' / ' . ($activity['change_operator_name'] ?? ''), ' /')) ?></label>
            </div>
            <hr>
            <h2 class="incident-section-title">Algemeen</h2>
            <hr>
            <div class="form-group">
              <label class="incident-meta-label">Behandelaarsgroep</label>
              <label>
                <input type="hidden" name="operatorgroupid" id="activity_operatorgroup_id" value="<?= htmlspecialchars((string)$form_values['operatorgroupid']) ?>">
                <input type="text" id="activity_operatorgroup_lookup" list="groups_list" autocomplete="off">
                <datalist id="groups_list"></datalist>
              </label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Behandelaar</label>
              <label class="assign-to-me-row">
                <input type="hidden" name="operatorid" id="activity_operator_id" value="<?= htmlspecialchars((string)$form_values['operatorid']) ?>">
                <input type="text" id="activity_operator_lookup" list="activity_operators_list" autocomplete="off">
                <button type="button" id="activity_assign_to_me_button" class="assign-to-me-button" title="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>" aria-label="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>"><i class="fa-solid fa-user"></i></button>
                <datalist id="activity_operators_list"></datalist>
              </label>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Status</label>
              <label>
                <select name="statusid" required>
                  <option value="">Selecteer een status</option>
                  <?php foreach ( $reference_data['statuses'] as $status ): ?>
                  <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$form_values['statusid'] === (string)$status['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status['name']) ?>
                  </option>
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
            <input type="text" name="title" class="incident-title-input" value="<?= htmlspecialchars($form_values['title']) ?>" required>
            <div class="form-group">
              <label class="incident-meta-label">Omschrijving</label>
              <textarea name="description" required><?= htmlspecialchars($form_values['description']) ?></textarea>
            </div>
            <div class="form-group">
              <label class="incident-meta-label">Commentaar</label>
              <label>
                <input type="hidden" name="commentid" value="<?= htmlspecialchars((string)$form_values['commentid']) ?>">
                <textarea name="commenttext"><?= htmlspecialchars($form_values['commenttext']) ?></textarea>
              </label>
            </div>
            <div class="form-group">
              <label>
                <input type="checkbox" name="internalonly" <?= !empty($form_values['internalonly']) ? 'checked' : '' ?>>
                Niet voor klant
              </label>
            </div>
            <?php attachment_render_upload_field(); ?>
            <hr>
            <h3>Commentaarhistorie</h3>
            <div class="incident-history">
              <?php if ( empty( $comments ) ): ?>
              <p>Nog geen commentaar.</p>
              <?php else: ?>
              <?php foreach ( $comments as $comment ): ?>
              <div class="incident-comment">
                <div class="incident-comment-meta">
                  <span><?= htmlspecialchars($comment['operator_name']) ?></span>
                  <span><?= htmlspecialchars($comment['createdat']) ?></span>
                </div>
                <span class="incident-badge"><?= (int)$comment['internalonly'] === 1 ? 'Niet voor klant' : 'Klant zichtbaar' ?></span>
                <p><?= task_linkify_text($comment['commenttext'], 'secure') ?></p>
                <?php if ( !empty( $attachments_by_comment[(string)$comment['id']] ) ): ?>
                <?= attachment_render_links( $attachments_by_comment[(string)$comment['id']] ) ?>
                <?php endif; ?>
                <div class="form-actions">
                  <a href="edit_change_activity.php?id=<?= htmlspecialchars((string)$activity_id) ?>&edit_comment=<?= htmlspecialchars((string)$comment['id']) ?>">Commentaar bewerken</a>
                  <button type="submit" name="delete_comment_id" value="<?= htmlspecialchars((string)$comment['id']) ?>" class="btn-danger" formnovalidate onclick="return confirm('Weet je zeker dat je dit commentaar wil verwijderen?');">Commentaar verwijderen</button>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary">Opslaan</button>
            </div>
          </div>
        </div>
      </div>
    </form>
    <?php if ( !empty( $attachments_html ) ): ?>
  <div class="ticket-support-grid">
    <div class="form-card form-card-wide">
      <h3>Bijlagen</h3>
      <?= $attachments_html ?>
    </div>
  </div>
    <?php endif; ?>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="links">
    <div class="form-wrapper record-form-wrapper">
      <div class="form-card form-card-wide">
        <form method="post">
          <?= task_render_links_section( task_load_links( $con, 'changeactivity', $activity_id, 'secure' ) ) ?>
        </form>
      </div>
    </div>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="log">
    <div class="form-wrapper record-form-wrapper">
      <div class="form-card form-card-wide">
        <?= $task_logs_html ?>
      </div>
    </div>
  </div>
</div>

<script>
const groups = <?= $groups_json ?>;
const operators = <?= $operators_json ?>;
const opLinks = <?= $op_links_json ?>;
const currentOperatorId = <?= json_encode((string)($operator_context['id'] ?? '')) ?>;
const currentOperatorGroupIds = [...new Set(opLinks.filter((row) => String(row.operatorid) === String(currentOperatorId)).map((row) => String(row.groupid)))];

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

function groupLabel(row) { return row.groupname; }
function operatorLabel(row) { return `${row.lastname}, ${row.firstname}`; }

function setDatalistOptions(listId, rows, labelBuilder) {
  const list = document.getElementById(listId);
  if (!list) { return; }
  list.innerHTML = '';
  rows.forEach((row) => {
    const option = document.createElement('option');
    option.value = labelBuilder(row);
    list.appendChild(option);
  });
}

function setLookupValue(inputId, hiddenId, rows, labelBuilder) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  if (!input || !hidden) { return; }
  const current = rows.find((row) => String(row.id) === String(hidden.value));
  input.value = current ? labelBuilder(current) : '';
}

function currentGroupOperators() {
  const groupId = document.getElementById('activity_operatorgroup_id').value;
  if (!groupId) { return operators; }
  const operatorIds = opLinks.filter((row) => String(row.groupid) === String(groupId)).map((row) => String(row.operatorid));
  return operators.filter((row) => operatorIds.includes(String(row.id)));
}

function refreshOperators(resetSelection) {
  const rows = currentGroupOperators();
  setDatalistOptions('activity_operators_list', rows, operatorLabel);
  if (resetSelection) {
    document.getElementById('activity_operator_id').value = '';
    document.getElementById('activity_operator_lookup').value = '';
  } else {
    setLookupValue('activity_operator_lookup', 'activity_operator_id', rows, operatorLabel);
  }
  refreshAssignToMeButton();
}

function refreshAssignToMeButton() {
  const button = document.getElementById('activity_assign_to_me_button');
  const groupId = String(document.getElementById('activity_operatorgroup_id').value || '');
  if (!button) { return; }
  button.disabled = !currentOperatorId || (currentOperatorGroupIds.length !== 1 && !groupId) || (groupId && !currentOperatorGroupIds.includes(groupId));
}

function assignToMe() {
  const groupField = document.getElementById('activity_operatorgroup_id');
  const groupLookup = document.getElementById('activity_operatorgroup_lookup');
  if (currentOperatorGroupIds.length === 1 && !groupField.value) {
    groupField.value = currentOperatorGroupIds[0];
    setLookupValue('activity_operatorgroup_lookup', 'activity_operatorgroup_id', groups, groupLabel);
    refreshOperators(false);
  }
  const groupId = String(groupField.value || '');
  if ((currentOperatorGroupIds.length !== 1 && !groupId) || (groupId && !currentOperatorGroupIds.includes(groupId))) { return; }
  const match = operators.find((row) => String(row.id) === String(currentOperatorId));
  if (!match) { return; }
  document.getElementById('activity_operator_id').value = String(match.id);
  document.getElementById('activity_operator_lookup').value = operatorLabel(match);
  document.getElementById('activity_operator_id').dispatchEvent(new Event('change', { bubbles: true }));
  document.getElementById('activity_operator_lookup').dispatchEvent(new Event('input', { bubbles: true }));
  refreshAssignToMeButton();
}

function resolveLookup(inputId, hiddenId, rowsSource, labelBuilder, onResolved) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  if (!input || !hidden) { return; }
  const resolve = () => {
    const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
    const match = rows.find((row) => labelBuilder(row) === input.value);
    hidden.value = match ? String(match.id) : '';
    if (onResolved) { onResolved(match || null); }
  };
  input.addEventListener('input', resolve);
  input.addEventListener('change', resolve);
  input.addEventListener('blur', resolve);
}

setDatalistOptions('groups_list', groups, groupLabel);
setLookupValue('activity_operatorgroup_lookup', 'activity_operatorgroup_id', groups, groupLabel);
refreshOperators(false);
resolveLookup('activity_operatorgroup_lookup', 'activity_operatorgroup_id', groups, groupLabel, () => refreshOperators(true));
resolveLookup('activity_operator_lookup', 'activity_operator_id', currentGroupOperators, operatorLabel);
document.getElementById('activity_assign_to_me_button')?.addEventListener('click', assignToMe);
refreshAssignToMeButton();
</script>

<?php require_once(__DIR__ . '/nav/end.php'); ?>
