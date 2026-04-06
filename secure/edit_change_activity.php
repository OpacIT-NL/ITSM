<?php
session_start();
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );

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

$form_values = [
  'title' => $activity['title'],
  'description' => $activity['description'],
  'operatorgroupid' => (string)$activity['operatorgroupid'],
  'operatorid' => (string)$activity['operatorid'],
  'statusid' => (string)$activity['statusid']
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
  $errors = $validation['errors'];

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
      header( 'Location: edit_change_activity.php?id=' . $activity_id );
      exit;
    }
  }
}

$groups_json = json_encode( $reference_data['groups'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$operators_json = json_encode( $reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$op_links_json = json_encode( $reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <a href="edit_change.php?id=<?= htmlspecialchars((string)$activity['change_id']) ?>">Ga terug</a>
  <center>
    <h1>Wijzigingsactiviteit - <?= htmlspecialchars($activity['title']) ?></h1>
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

  <div class="form-wrapper">
    <form method="post" class="form-card">
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

        <div class="form-group">
          <label>Behandelaarsgroep</label>
          <input type="hidden" name="operatorgroupid" id="activity_operatorgroup_id" value="<?= htmlspecialchars((string)$form_values['operatorgroupid']) ?>">
          <input type="text" id="activity_operatorgroup_lookup" list="groups_list" autocomplete="off">
          <datalist id="groups_list"></datalist>
        </div>

        <div class="form-group">
          <label>Behandelaar</label>
          <input type="hidden" name="operatorid" id="activity_operator_id" value="<?= htmlspecialchars((string)$form_values['operatorid']) ?>">
          <input type="text" id="activity_operator_lookup" list="activity_operators_list" autocomplete="off">
          <datalist id="activity_operators_list"></datalist>
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
</div>

<script>
const groups = <?= $groups_json ?>;
const operators = <?= $operators_json ?>;
const opLinks = <?= $op_links_json ?>;

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
</script>

<?php require_once(__DIR__ . '/nav/end.php'); ?>
