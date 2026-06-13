<?php
session_start();

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
  session_unset();
  session_destroy();
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
      c.title AS change_title
    FROM itsm_cm_changeactivities a
    INNER JOIN itsm_cm_changes c ON a.changeid = c.id
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
$presence_error = form_presence_flash_error();
if ( $presence_error !== '' ) {
  $errors[] = $presence_error;
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

$form_values = [
  'title' => $activity['title'],
  'description' => $activity['description'],
  'operatorgroupid' => (string)$activity['operatorgroupid'],
  'operatorid' => (string)$activity['operatorid'],
  'statusid' => (string)$activity['statusid']
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['add_task_link'] ) ) {
  form_presence_redirect_if_stale( $con, 'changeactivity', $activity_id, $_POST['presence_token'] ?? '', 'edit_change_activity.php?id=' . $activity_id );
  $old_activity = $activity;
  $old_status_id = (int)$activity['statusid'];
  $form_values = [
    'title' => trim( $_POST['title'] ?? '' ),
    'description' => trim( $_POST['description'] ?? '' ),
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? ''
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
      header( 'Location: edit_change_activity.php?id=' . $activity_id );
      exit;
    }
  }
}

$groups_json = json_encode( $reference_data['groups'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$operators_json = json_encode( $reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$op_links_json = json_encode( $reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$attachments = attachment_load_for_task( $con, 'changeactivity', $activity_id );
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
  <div class="form-wrapper">
    <div class="form-card">
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
  <div class="form-wrapper">
    <form method="post" enctype="multipart/form-data" class="form-card">
      <div class="form-grid">
        <p>Wijziging: <a href="edit_change.php?id=<?= htmlspecialchars((string)$activity['change_id']) ?>"><?= htmlspecialchars(change_format_display_number($activity)) ?> - <?= htmlspecialchars($activity['change_title']) ?></a></p>

        <div class="form-group">
          <label>Titel</label>
          <input type="text" name="title" value="<?= htmlspecialchars($form_values['title']) ?>" required>
        </div>

        <div class="form-group">
          <label>Omschrijving</label>
        <textarea name="description" required><?= htmlspecialchars($form_values['description']) ?></textarea>
        </div>

        <?php attachment_render_upload_field(); ?>

        <div class="form-group">
          <label>Behandelaarsgroep</label>
          <input type="hidden" name="operatorgroupid" id="activity_operatorgroup_id" value="<?= htmlspecialchars((string)$form_values['operatorgroupid']) ?>">
          <input type="text" id="activity_operatorgroup_lookup" list="groups_list" autocomplete="off">
          <datalist id="groups_list"></datalist>
        </div>

        <div class="form-group">
          <label>Behandelaar</label>
          <label class="assign-to-me-row">
            <input type="hidden" name="operatorid" id="activity_operator_id" value="<?= htmlspecialchars((string)$form_values['operatorid']) ?>">
            <input type="text" id="activity_operator_lookup" list="activity_operators_list" autocomplete="off">
            <button type="button" id="activity_assign_to_me_button" class="assign-to-me-button" title="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>" aria-label="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>"><i class="fa-solid fa-user"></i></button>
            <datalist id="activity_operators_list"></datalist>
          </label>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="statusid" required>
            <option value="">Selecteer een status</option>
            <?php foreach ( $reference_data['statuses'] as $status ): ?>
            <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$form_values['statusid'] === (string)$status['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($status['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Opslaan</button>
        </div>
      </div>
    </form>
  </div>
  <div class="form-wrapper">
    <div class="form-card">
      <?php if ( !empty( $attachments_html ) ): ?>
      <h3>Bijlagen</h3>
      <?= $attachments_html ?>
      <?php endif; ?>
    </div>
  </div>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="links">
    <div class="form-wrapper">
      <div class="form-card">
        <form method="post">
          <?= task_render_links_section( task_load_links( $con, 'changeactivity', $activity_id, 'secure' ) ) ?>
        </form>
      </div>
    </div>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="log">
    <div class="form-wrapper">
      <div class="form-card">
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
