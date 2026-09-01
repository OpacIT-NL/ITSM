<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );
require_once( __DIR__ . '/include/attachment_helpers.php' );
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
if ( !isset( $_GET['changeid'] ) || !is_numeric( $_GET['changeid'] ) ) {
  die( 'Invalid ID' );
}

$logged_in_user = $_SESSION['name'];
$operator_context = change_get_operator_context( $con, $logged_in_user );
change_require_access( $operator_context );
$change_id = (int)$_GET['changeid'];

$stmt = mysqli_prepare( $con, "
    SELECT
      c.id,
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
    FROM itsm_cm_changes c
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
    WHERE c.id = ?
" );
mysqli_stmt_bind_param( $stmt, "i", $change_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$change = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$change ) {
  die( 'Wijziging niet gevonden' );
}
if ( $change['requesttype'] !== 'extended' ) {
  die( 'Wijzigingsactiviteiten zijn alleen beschikbaar op uitgebreide wijzigingen.' );
}

$reference_data = change_load_reference_data( $con );
$errors = [];
$form_values = [
  'title' => '',
  'description' => '',
  'operatorgroupid' => '',
  'operatorid' => '',
  'statusid' => ''
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
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
  $errors = array_merge( $validation['errors'], attachment_upload_errors() );

  if ( empty( $errors ) ) {
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $status_id = (int)$validation['status']['id'];
    $created_by = (int)$operator_context['id'];
    $activity_number = change_generate_activity_number( $con );

    $activity_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_cm_changeactivities
            (activitynumber, changeid, title, description, operatorgroupid, operatorid, statusid, createdby)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    " );
    mysqli_stmt_bind_param(
      $activity_stmt,
      "sissiiii",
      $activity_number,
      $change_id,
      $form_values['title'],
      $form_values['description'],
      $group_id,
      $operator_id,
      $status_id,
      $created_by
    );
    mysqli_stmt_execute( $activity_stmt );
    $activity_id = mysqli_insert_id( $con );
    mysqli_stmt_close( $activity_stmt );

    attachment_save_upload( $con, 'changeactivity', $activity_id, $created_by, 0 );
    task_log_add( $con, 'change', $change_id, 'activity_created', 'Wijzigingsactiviteit ' . $activity_number . ' aangemaakt.', $created_by );
    task_log_add( $con, 'changeactivity', $activity_id, 'created', 'Wijzigingsactiviteit aangemaakt vanuit wijziging #' . $change_id . '.', $created_by );
    header( 'Location: edit_change_activity.php?id=' . $activity_id );
    exit;
  }
}

$groups_json = json_encode( $reference_data['groups'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$operators_json = json_encode( $reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$op_links_json = json_encode( $reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <span data-tab-title="<?= htmlspecialchars(t('Nieuwe wijzigingsactiviteit'), ENT_QUOTES) ?>" data-tab-subtitle="<?= htmlspecialchars(change_format_display_number($change), ENT_QUOTES) ?>" hidden></span>
  <?php $list_back_url = 'edit_change.php?id=' . $change_id; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Nieuwe wijzigingsactiviteit</h1>
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

  <form method="post" enctype="multipart/form-data" class="incident-layout">
    <div class="incident-column">
      <div class="incident-card incident-left-card">
        <div class="form-grid">
          <h2 class="incident-section-title">Wijziging</h2>
          <hr>
          <div class="form-group">
            <label class="incident-meta-label">Nummer</label>
            <label><a href="edit_change.php?id=<?= htmlspecialchars((string)$change['change_id']) ?>"><?= htmlspecialchars(change_format_display_number($change)) ?></a></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Titel</label>
            <label><?= htmlspecialchars($change['change_title']) ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Type</label>
            <label><?= htmlspecialchars(change_approval_state_label($change)) ?> / <?= htmlspecialchars(change_type_label($change['changetype'] ?? '')) ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Klant</label>
            <label><?= htmlspecialchars(trim(($change['customer_din'] ?? '') . ' - ' . ($change['customer_name'] ?? ''), ' -')) ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Aanmelder</label>
            <label><?= htmlspecialchars($change['person_name'] ?: trim(($change['personemail'] ?? '') . ' ' . ($change['personphone'] ?? ''))) ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Object ID</label>
            <label><?= htmlspecialchars(trim(($change['asset_objectid'] ?? '') . ( !empty($change['asset_type']) ? ' (' . $change['asset_type'] . ')' : '' ))) ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Categorie</label>
            <label><?= htmlspecialchars(trim(($change['category_name'] ?? '') . ' / ' . ($change['subcategory_name'] ?? ''), ' /')) ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Prioriteit</label>
            <label><?= htmlspecialchars(trim(($change['impact_name'] ?? '') . ' / ' . ($change['urgency_name'] ?? '') . ' / ' . ($change['priority_name'] ?? ''), ' /')) ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Wijzigingsstatus</label>
            <label><?= htmlspecialchars($change['change_status_name'] ?? '') ?></label>
          </div>
          <div class="form-group">
            <label class="incident-meta-label">Wijzigingsteam</label>
            <label><?= htmlspecialchars(trim(($change['change_group_name'] ?? '') . ' / ' . ($change['change_operator_name'] ?? ''), ' /')) ?></label>
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
          <input type="text" name="title" class="incident-title-input" value="<?= htmlspecialchars($form_values['title']) ?>" required placeholder="Titel">
          <div class="form-group">
            <label class="incident-meta-label">Omschrijving</label>
            <textarea name="description" required><?= htmlspecialchars($form_values['description']) ?></textarea>
          </div>
          <?php attachment_render_upload_field(); ?>
          <div class="form-actions">
            <button type="submit" class="btn-primary">Aanmaken</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
const groups = <?= $groups_json ?>;
const operators = <?= $operators_json ?>;
const opLinks = <?= $op_links_json ?>;
const currentOperatorId = <?= json_encode((string)($operator_context['id'] ?? '')) ?>;
const currentOperatorGroupIds = [...new Set(opLinks.filter((row) => String(row.operatorid) === String(currentOperatorId)).map((row) => String(row.groupid)))];

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
