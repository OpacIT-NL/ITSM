<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/ubm_helpers.php' );
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

$logged_in_user = $_SESSION['name'];
$operator_context = ubm_get_operator_context( $con, $logged_in_user );
ubm_require_access( $operator_context );

$reference_data = ubm_load_reference_data( $con );
$default_status_id = ubm_default_status_id( $reference_data['statuses'] );
$parent_id = isset( $_GET['parentid'] ) && is_numeric( $_GET['parentid'] ) ? (int)$_GET['parentid'] : 0;
$parent_item = null;
if ( $parent_id > 0 ) {
  $parent_result = mysqli_query( $con, "SELECT id, ubmnumber, itemtype, title FROM itsm_ubm_items WHERE id = " . $parent_id . " LIMIT 1" );
  $parent_item = mysqli_fetch_assoc( $parent_result ) ?: null;
}

$allowed_types = $parent_item ? ubm_allowed_child_types( $parent_item['itemtype'] ) : [ 'initiative' ];
$initial_type = $_GET['type'] ?? ( $allowed_types[0] ?? 'initiative' );
if ( !in_array( $initial_type, $allowed_types, true ) ) {
  $initial_type = $allowed_types[0] ?? 'initiative';
}

$form_values = [
  'parentid' => $parent_item ? (string)$parent_item['id'] : '',
  'itemtype' => $initial_type,
  'title' => '',
  'description' => '',
  'categoryid' => '',
  'subcategoryid' => '',
  'operatorgroupid' => '',
  'operatorid' => '',
  'statusid' => (string)$default_status_id
];
$errors = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'parentid' => $_POST['parentid'] ?? '',
    'itemtype' => $_POST['itemtype'] ?? $initial_type,
    'title' => trim( $_POST['title'] ?? '' ),
    'description' => trim( $_POST['description'] ?? '' ),
    'categoryid' => $_POST['categoryid'] ?? '',
    'subcategoryid' => $_POST['subcategoryid'] ?? '',
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'operatorid' => $_POST['operatorid'] ?? '',
    'statusid' => $_POST['statusid'] ?? ''
  ];

  $validation = ubm_validate_form(
    [
      'parentid' => (int)$form_values['parentid'],
      'itemtype' => $form_values['itemtype'],
      'title' => $form_values['title'],
      'categoryid' => (int)$form_values['categoryid'],
      'subcategoryid' => (int)$form_values['subcategoryid'],
      'operatorgroupid' => (int)$form_values['operatorgroupid'],
      'operatorid' => (int)$form_values['operatorid'],
      'statusid' => (int)$form_values['statusid']
    ],
    $reference_data,
    $parent_item
  );
  $errors = $validation['errors'];
  $errors = array_merge( $errors, attachment_upload_errors() );

  if ( empty( $errors ) ) {
    $ubm_number = ubm_generate_number( $con, $form_values['itemtype'] );
    $status_id = (int)$validation['status']['id'];
    $category_id = $validation['category'] ? (int)$validation['category']['id'] : null;
    $subcategory_id = $validation['subcategory'] ? (int)$validation['subcategory']['id'] : null;
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $created_by = (int)$operator_context['id'];
    $parent_bind = $parent_item ? (int)$parent_item['id'] : null;

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_ubm_items (ubmnumber, parentid, itemtype, title, description, categoryid, subcategoryid, operatorgroupid, operatorid, statusid, createdby)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        " );
    mysqli_stmt_bind_param( $stmt, "sisssiiiiii", $ubm_number, $parent_bind, $form_values['itemtype'], $form_values['title'], $form_values['description'], $category_id, $subcategory_id, $group_id, $operator_id, $status_id, $created_by );
    if ( mysqli_stmt_execute( $stmt ) ) {
      $item_id = mysqli_insert_id( $con );
      attachment_save_upload( $con, 'ubm', $item_id, $created_by, 0 );
      task_log_add( $con, 'ubm', $item_id, 'created', ubm_type_label( $form_values['itemtype'] ) . ' aangemaakt.', $created_by );
      header( 'Location: edit_ubm_item.php?id=' . $item_id );
      exit;
    }
    $errors[] = 'UBM-item opslaan mislukt: ' . mysqli_stmt_error( $stmt );
  }
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <span data-tab-title="<?= htmlspecialchars(t('Nieuw'), ENT_QUOTES) ?>" data-tab-subtitle="<?= htmlspecialchars(ubm_type_label($form_values['itemtype']), ENT_QUOTES) ?>" hidden></span>
  <?php $list_back_url = ubm_get_list_back_url( 'ubm-menu.php' ); require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>UBM-item aanmaken</h1>
  </center>
  <?php if ( !empty( $errors ) ): ?>
  <div class="form-wrapper record-form-wrapper"><div class="form-card record-form-card"><?php foreach ( $errors as $error ): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div></div><br>
  <?php endif; ?>
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card form-card-wide record-form-card">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="parentid" value="<?= htmlspecialchars($form_values['parentid']) ?>">
        <?php if ( $parent_item ): ?>
        <p class="info-note">Bovenliggend item: <a class="task-inline-link" href="edit_ubm_item.php?id=<?= (int)$parent_item['id'] ?>"><?= htmlspecialchars(ubm_format_display_number($parent_item)) ?> - <?= htmlspecialchars($parent_item['title']) ?></a></p>
        <?php endif; ?>
        <div class="form-grid">
          <div class="form-group">
            <label>Laag</label>
            <select name="itemtype" required>
              <?php foreach ( $allowed_types as $type ): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $form_values['itemtype'] === $type ? 'selected' : '' ?>><?= htmlspecialchars(ubm_type_label($type)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Titel</label>
            <input type="text" name="title" value="<?= htmlspecialchars($form_values['title']) ?>" required>
          </div>
          <div class="form-group">
            <label>Omschrijving</label>
            <textarea name="description"><?= htmlspecialchars($form_values['description']) ?></textarea>
          </div>
          <?php attachment_render_upload_field(); ?>
          <div class="form-group">
            <label>Categorie</label>
            <select name="categoryid" id="category_id">
              <option value="">Selecteer een categorie</option>
              <?php foreach ( $reference_data['categories'] as $category ): ?>
              <option value="<?= htmlspecialchars((string)$category['id']) ?>" <?= (string)$form_values['categoryid'] === (string)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Subcategorie</label>
            <select name="subcategoryid" id="subcategory_id"><option value=""><?= htmlspecialchars(t('Selecteer een subcategorie')) ?></option></select>
          </div>
          <div class="form-group">
            <label>Team</label>
            <select name="operatorgroupid" id="operatorgroup_id">
              <option value="">Selecteer een team</option>
              <?php foreach ( $reference_data['groups'] as $group ): ?>
              <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= (string)$form_values['operatorgroupid'] === (string)$group['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['groupname']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Behandelaar</label>
            <label class="assign-to-me-row">
              <select name="operatorid" id="operator_id"><option value=""><?= htmlspecialchars(t('Selecteer een behandelaar')) ?></option></select>
              <button type="button" id="assign_to_me_button" class="assign-to-me-button" title="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>" aria-label="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>"><i class="fa-solid fa-user"></i></button>
            </label>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="statusid" required>
              <option value="">Selecteer een status</option>
              <?php foreach ( $reference_data['statuses'] as $status ): ?>
              <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$form_values['statusid'] === (string)$status['id'] ? 'selected' : '' ?>><?= htmlspecialchars($status['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary">UBM-item opslaan</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
const ubmSubcategories = <?= json_encode($reference_data['subcategories'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const ubmCurrentSubcategoryId = <?= json_encode((string)$form_values['subcategoryid']) ?>;
const ubmOperators = <?= json_encode($reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const ubmOpLinks = <?= json_encode($reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const ubmCurrentOperatorId = <?= json_encode((string)$form_values['operatorid']) ?>;
const ubmLoggedInOperatorId = <?= json_encode((string)($operator_context['id'] ?? '')) ?>;
const ubmLoggedInOperatorGroupIds = [...new Set(ubmOpLinks.filter((row) => String(row.operatorid) === String(ubmLoggedInOperatorId)).map((row) => String(row.groupid)))];
const ubmFormI18n = {
  selectSubcategory: <?= json_encode(t('Selecteer een subcategorie')) ?>,
  selectOperator: <?= json_encode(t('Selecteer een behandelaar')) ?>
};
function refreshUbmSubcategories() {
  const categoryId = document.getElementById('category_id').value;
  const select = document.getElementById('subcategory_id');
  select.innerHTML = `<option value="">${ubmFormI18n.selectSubcategory}</option>`;
  ubmSubcategories.filter((row) => String(row.parent) === String(categoryId)).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    if (String(row.id) === String(ubmCurrentSubcategoryId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}
function ubmOperatorLabel(row) { return `${row.lastname}, ${row.firstname}`; }
function refreshUbmOperators() {
  const groupId = document.getElementById('operatorgroup_id').value;
  const select = document.getElementById('operator_id');
  const operatorIds = groupId ? ubmOpLinks.filter((row) => String(row.groupid) === String(groupId)).map((row) => String(row.operatorid)) : ubmOperators.map((row) => String(row.id));
  select.innerHTML = `<option value="">${ubmFormI18n.selectOperator}</option>`;
  ubmOperators.filter((row) => operatorIds.includes(String(row.id))).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = ubmOperatorLabel(row);
    if (String(row.id) === String(ubmCurrentOperatorId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
  refreshUbmAssignToMeButton();
}
function refreshUbmAssignToMeButton() {
  const button = document.getElementById('assign_to_me_button');
  const groupId = String(document.getElementById('operatorgroup_id').value || '');
  if (!button) { return; }
  button.disabled = !ubmLoggedInOperatorId || (ubmLoggedInOperatorGroupIds.length !== 1 && !groupId) || (groupId && !ubmLoggedInOperatorGroupIds.includes(groupId));
}
function assignUbmToMe() {
  const groupSelect = document.getElementById('operatorgroup_id');
  if (ubmLoggedInOperatorGroupIds.length === 1 && !groupSelect.value) {
    groupSelect.value = ubmLoggedInOperatorGroupIds[0];
    refreshUbmOperators();
  }
  const groupId = String(groupSelect.value || '');
  if ((ubmLoggedInOperatorGroupIds.length !== 1 && !groupId) || (groupId && !ubmLoggedInOperatorGroupIds.includes(groupId))) { return; }
  const select = document.getElementById('operator_id');
  select.value = String(ubmLoggedInOperatorId);
  select.dispatchEvent(new Event('change', { bubbles: true }));
  refreshUbmAssignToMeButton();
}
document.getElementById('category_id').addEventListener('change', refreshUbmSubcategories);
refreshUbmSubcategories();
document.getElementById('operatorgroup_id').addEventListener('change', refreshUbmOperators);
document.getElementById('assign_to_me_button')?.addEventListener('click', assignUbmToMe);
refreshUbmOperators();
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
