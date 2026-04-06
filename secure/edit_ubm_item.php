<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/ubm_helpers.php' );

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
  $parent_result = mysqli_query( $con, "SELECT id, itemtype, title FROM itsm_ubm_items WHERE id = " . (int)$item['parentid'] . " LIMIT 1" );
  $parent_item = mysqli_fetch_assoc( $parent_result ) ?: null;
}

$errors = [];
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $item['title'] = trim( $_POST['title'] ?? '' );
  $item['description'] = trim( $_POST['description'] ?? '' );
  $item['operatorgroupid'] = $_POST['operatorgroupid'] ?? '';
  $item['operatorid'] = $_POST['operatorid'] ?? '';
  $item['statusid'] = $_POST['statusid'] ?? '';

  $validation = ubm_validate_form(
    [
      'parentid' => (int)$item['parentid'],
      'itemtype' => $item['itemtype'],
      'title' => $item['title'],
      'operatorgroupid' => (int)$item['operatorgroupid'],
      'operatorid' => (int)$item['operatorid'],
      'statusid' => (int)$item['statusid']
    ],
    $reference_data,
    $parent_item
  );
  $errors = $validation['errors'];

  if ( empty( $errors ) ) {
    $group_id = $validation['group'] ? (int)$validation['group']['id'] : null;
    $operator_id = $validation['operator'] ? (int)$validation['operator']['id'] : null;
    $status_id = (int)$validation['status']['id'];

    $update_stmt = mysqli_prepare( $con, "
            UPDATE itsm_ubm_items
            SET title = ?, description = ?, operatorgroupid = ?, operatorid = ?, statusid = ?
            WHERE id = ?
        " );
    mysqli_stmt_bind_param( $update_stmt, "ssiiii", $item['title'], $item['description'], $group_id, $operator_id, $status_id, $item_id );
    if ( mysqli_stmt_execute( $update_stmt ) ) {
      header( 'Location: edit_ubm_item.php?id=' . $item_id );
      exit;
    }
    $errors[] = 'UBM-item bijwerken mislukt: ' . mysqli_stmt_error( $update_stmt );
  }
}

$children_result = mysqli_query( $con, "
    SELECT u.id, u.itemtype, u.title, s.name AS status_name
    FROM itsm_ubm_items u
    LEFT JOIN itsm_core_status s ON u.statusid = s.id
    WHERE u.parentid = " . $item_id . "
    ORDER BY u.title ASC, u.id ASC
" );
$children = [];
while ( $row = mysqli_fetch_assoc( $children_result ) ) {
  $children[] = $row;
}
$allowed_children = ubm_allowed_child_types( $item['itemtype'] );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $list_back_url = ubm_get_list_back_url( 'ubm-menu.php' ); require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars(ubm_type_label($item['itemtype'])) ?>: <?= htmlspecialchars($item['title']) ?></h1>
  </center>
  <?php if ( !empty( $errors ) ): ?>
  <div class="form-wrapper"><div class="form-card"><?php foreach ( $errors as $error ): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div></div><br>
  <?php endif; ?>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post">
        <?php if ( $parent_item ): ?>
        <p>Bovenliggend item: <?= htmlspecialchars(ubm_type_label($parent_item['itemtype'])) ?> - <?= htmlspecialchars($parent_item['title']) ?></p>
        <?php endif; ?>
        <label>Titel</label>
        <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
        <br><br>
        <label>Omschrijving</label>
        <textarea name="description"><?= htmlspecialchars($item['description']) ?></textarea>
        <br><br>
        <label>Team</label>
        <select name="operatorgroupid" id="operatorgroup_id">
          <option value="">Selecteer een team</option>
          <?php foreach ( $reference_data['groups'] as $group ): ?>
          <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= (string)$item['operatorgroupid'] === (string)$group['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['groupname']) ?></option>
          <?php endforeach; ?>
        </select>
        <br><br>
        <label>Behandelaar</label>
        <select name="operatorid" id="operator_id"><option value="">Selecteer een behandelaar</option></select>
        <br><br>
        <label>Status</label>
        <select name="statusid" required>
          <option value="">Selecteer een status</option>
          <?php foreach ( $reference_data['statuses'] as $status ): ?>
          <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$item['statusid'] === (string)$status['id'] ? 'selected' : '' ?>><?= htmlspecialchars($status['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <br><br>
        <div class="form-actions">
          <?php if ( !empty( $allowed_children ) ): ?>
          <a href="new_ubm_item.php?parentid=<?= htmlspecialchars((string)$item_id) ?>&type=<?= htmlspecialchars($allowed_children[0]) ?>">Nieuw child-item</a>
          <a href="ubm_items.php?parent=<?= htmlspecialchars((string)$item_id) ?>">Child-items bekijken</a>
          <?php endif; ?>
          <button type="submit">Opslaan</button>
        </div>
      </form>
    </div>
  </div>
  <br>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Laag</th>
          <th style="text-align: start;">Titel</th>
          <th style="text-align: start;">Status</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php if ( empty( $children ) ): ?>
        <tr><td colspan="4">Nog geen child-items.</td></tr>
        <?php else: ?>
        <?php foreach ( $children as $child ): ?>
        <tr>
          <td><?= htmlspecialchars(ubm_type_label($child['itemtype'])) ?></td>
          <td><?= htmlspecialchars($child['title']) ?></td>
          <td><?= htmlspecialchars($child['status_name'] ?? '') ?></td>
          <td class="tblaction"><a class="btn" href="edit_ubm_item.php?id=<?= htmlspecialchars((string)$child['id']) ?>">Open item</a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
const editUbmOperators = <?= json_encode($reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const editUbmOpLinks = <?= json_encode($reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const editUbmCurrentOperatorId = <?= json_encode((string)$item['operatorid']) ?>;
function editUbmOperatorLabel(row) { return `${row.lastname}, ${row.firstname}`; }
function refreshEditUbmOperators() {
  const groupId = document.getElementById('operatorgroup_id').value;
  const select = document.getElementById('operator_id');
  const operatorIds = groupId ? editUbmOpLinks.filter((row) => String(row.groupid) === String(groupId)).map((row) => String(row.operatorid)) : editUbmOperators.map((row) => String(row.id));
  select.innerHTML = '<option value="">Selecteer een behandelaar</option>';
  editUbmOperators.filter((row) => operatorIds.includes(String(row.id))).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = editUbmOperatorLabel(row);
    if (String(row.id) === String(editUbmCurrentOperatorId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}
document.getElementById('operatorgroup_id').addEventListener('change', refreshEditUbmOperators);
refreshEditUbmOperators();
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
