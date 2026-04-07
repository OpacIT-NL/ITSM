<?php
require_once( __DIR__ . '/task_helpers.php' );
$customers_json = json_encode( $reference_data['customers'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$persons_json = json_encode( $reference_data['persons'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$subcategories_json = json_encode( $reference_data['subcategories'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$operators_json = json_encode( $reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$op_links_json = json_encode( $reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
?>
<?php require_once(__DIR__ . '/../nav/nav.php'); ?>
<div class="content">
  <?php require(__DIR__ . '/back_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars($page_title) ?></h1>
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

  <form method="post" class="incident-layout">
    <div class="incident-column">
      <div class="incident-card incident-left-card">
        <div class="form-grid">
          <div class="form-group">
            <label class="incident-meta-label">Klant</label>
            <label>
              <input type="hidden" name="customerid" id="customer_id" value="<?= htmlspecialchars((string)$form_values['customerid']) ?>">
              <input type="text" id="customer_lookup" list="customers_list" autocomplete="off" required>
              <datalist id="customers_list"></datalist>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Persoon</label>
            <label>
              <input type="hidden" name="personid" id="person_id" value="<?= htmlspecialchars((string)$form_values['personid']) ?>">
              <input type="text" id="person_lookup" list="persons_list" autocomplete="off" required>
              <datalist id="persons_list"></datalist>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">E-mail</label>
            <label><input type="text" id="person_email" class="incident-readonly" value="<?= htmlspecialchars($form_values['personemail']) ?>" readonly></label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Telefoonnummer</label>
            <label><input type="text" id="person_phone" class="incident-readonly" value="<?= htmlspecialchars($form_values['personphone']) ?>" readonly></label>
          </div>

          <hr>

          <div class="form-group">
            <label class="incident-meta-label">Categorie</label>
            <label>
              <select name="categoryid" id="category_id" required>
                <option value="">Selecteer een categorie</option>
                <?php foreach ( $reference_data['categories'] as $category ): ?>
                <option value="<?= htmlspecialchars((string)$category['id']) ?>" <?= (string)$form_values['categoryid'] === (string)$category['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($category['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Subcategorie</label>
            <label><select name="subcategoryid" id="subcategory_id"><option value="">Selecteer een subcategorie</option></select></label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Object ID</label>
            <label>
              <select name="assetid" id="asset_id">
                <option value="">Selecteer een object</option>
                <?php foreach ( $reference_data['assets'] as $asset ): ?>
                <option value="<?= htmlspecialchars((string)$asset['id']) ?>" data-type="<?= htmlspecialchars($asset['typename'] ?? '') ?>" <?= (string)$form_values['assetid'] === (string)$asset['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($asset['objectid']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Type</label>
            <label><input type="text" id="asset_type" class="incident-readonly" value="<?= htmlspecialchars($form_values['assettype']) ?>" readonly></label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Status</label>
            <label>
              <select name="statusid" id="status_id" required>
                <option value="">Selecteer een status</option>
                <?php foreach ( $reference_data['statuses'] as $status ): ?>
                <option value="<?= htmlspecialchars((string)$status['id']) ?>" data-ready="<?= htmlspecialchars((string)$status['ready']) ?>" data-closed="<?= htmlspecialchars((string)$status['closed']) ?>" <?= (string)$form_values['statusid'] === (string)$status['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($status['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Gereed</label>
            <label><div><input type="checkbox" id="status_ready_display" <?= !empty($form_values['statusready']) ? 'checked' : '' ?> disabled></div></label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Afgemeld</label>
            <label><div><input type="checkbox" id="status_closed_display" <?= !empty($form_values['statusclosed']) ? 'checked' : '' ?> disabled></div></label>
          </div>

          <hr>

          <div class="form-group">
            <label class="incident-meta-label">Behandelaarsgroep</label>
            <label>
              <select name="operatorgroupid" id="operatorgroup_id">
                <option value="">Selecteer een groep</option>
                <?php foreach ( $reference_data['groups'] as $group ): ?>
                <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= (string)$form_values['operatorgroupid'] === (string)$group['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($group['groupname']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Behandelaar</label>
            <label><select name="operatorid" id="operator_id"><option value="">Selecteer een behandelaar</option></select></label>
          </div>
        </div>
      </div>
    </div>

    <div class="incident-column">
      <div class="incident-card incident-main-card">
        <div class="form-grid">
          <div class="form-actions">
            <?php if ( !empty( $action_links ) ): ?>
            <?php foreach ( $action_links as $action_link ): ?>
            <a href="<?= htmlspecialchars($action_link['href']) ?>" class="btn-primary"><?= htmlspecialchars($action_link['label']) ?></a>
            <?php endforeach; ?>
            <?php endif; ?>
            <button type="submit" class="btn-primary"><?= htmlspecialchars($submit_label) ?></button>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Korte titel</label>
            <label><input type="text" name="title" class="incident-title-input" value="<?= htmlspecialchars($form_values['title']) ?>" required></label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Omschrijving</label>
            <label><textarea name="description" required><?= htmlspecialchars($form_values['description']) ?></textarea></label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Commentaar</label>
            <label>
              <input type="hidden" name="commentid" value="<?= htmlspecialchars((string)$form_values['commentid']) ?>">
              <textarea name="commenttext"><?= htmlspecialchars($form_values['commenttext']) ?></textarea>
            </label>
          </div>

          <div class="form-group">
            <label><input type="checkbox" name="internalonly" <?= !empty($form_values['internalonly']) ? 'checked' : '' ?>> Niet voor klant</label>
          </div>

          <?php if ( $show_history ): ?>
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
              <div class="form-actions">
                <a href="edit_problem.php?id=<?= htmlspecialchars((string)$problem_id) ?>&edit_comment=<?= htmlspecialchars((string)$comment['id']) ?>">Commentaar bewerken</a>
                <button type="submit" name="delete_comment_id" value="<?= htmlspecialchars((string)$comment['id']) ?>" class="btn-danger" formnovalidate>Commentaar verwijderen</button>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ( !empty( $links_html ) ): ?>
          <?= $links_html ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
const customers = <?= $customers_json ?>;
const persons = <?= $persons_json ?>;
const subcategories = <?= $subcategories_json ?>;
const operators = <?= $operators_json ?>;
const opLinks = <?= $op_links_json ?>;
const currentSubcategoryId = <?= json_encode((string)$form_values['subcategoryid']) ?>;
const currentOperatorId = <?= json_encode((string)$form_values['operatorid']) ?>;

function customerLabel(row) { return `${row.din || row.id} - ${row.name}`; }
function personLabel(row) { return row.email ? `${row.lastname}, ${row.firstname} (${row.email})` : `${row.lastname}, ${row.firstname}`; }
function operatorLabel(row) { return `${row.lastname}, ${row.firstname}`; }

function currentCustomerPersons() {
  const customerId = document.getElementById('customer_id').value;
  return persons.filter((row) => String(row.customerid) === String(customerId));
}

function refreshCustomerOptions() {
  const list = document.getElementById('customers_list');
  list.innerHTML = '';
  customers.forEach((row) => {
    const option = document.createElement('option');
    option.value = customerLabel(row);
    list.appendChild(option);
  });
  const current = customers.find((row) => String(row.id) === String(document.getElementById('customer_id').value));
  document.getElementById('customer_lookup').value = current ? customerLabel(current) : '';
}

function refreshPersonOptions(resetSelection) {
  const list = document.getElementById('persons_list');
  const lookup = document.getElementById('person_lookup');
  const rows = currentCustomerPersons();
  list.innerHTML = '';
  rows.forEach((row) => {
    const option = document.createElement('option');
    option.value = personLabel(row);
    list.appendChild(option);
  });
  if (resetSelection) {
    document.getElementById('person_id').value = '';
    lookup.value = '';
    document.getElementById('person_email').value = '';
    document.getElementById('person_phone').value = '';
    return;
  }
  const current = rows.find((row) => String(row.id) === String(document.getElementById('person_id').value));
  lookup.value = current ? personLabel(current) : '';
  document.getElementById('person_email').value = current ? (current.email || '') : '';
  document.getElementById('person_phone').value = current ? (current.phone || '') : '';
}

function refreshSubcategories() {
  const categoryId = document.getElementById('category_id').value;
  const select = document.getElementById('subcategory_id');
  select.innerHTML = '<option value="">Selecteer een subcategorie</option>';
  subcategories.filter((row) => String(row.parent) === String(categoryId)).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    if (String(row.id) === String(currentSubcategoryId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}

function refreshOperators() {
  const groupId = document.getElementById('operatorgroup_id').value;
  const operatorIds = groupId ? opLinks.filter((row) => String(row.groupid) === String(groupId)).map((row) => String(row.operatorid)) : operators.map((row) => String(row.id));
  const select = document.getElementById('operator_id');
  select.innerHTML = '<option value="">Selecteer een behandelaar</option>';
  operators.filter((row) => operatorIds.includes(String(row.id))).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = operatorLabel(row);
    if (String(row.id) === String(currentOperatorId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}

document.getElementById('customer_lookup').addEventListener('change', () => {
  const match = customers.find((row) => customerLabel(row) === document.getElementById('customer_lookup').value);
  document.getElementById('customer_id').value = match ? String(match.id) : '';
  refreshPersonOptions(true);
});

document.getElementById('person_lookup').addEventListener('change', () => {
  const match = currentCustomerPersons().find((row) => personLabel(row) === document.getElementById('person_lookup').value);
  document.getElementById('person_id').value = match ? String(match.id) : '';
  document.getElementById('person_email').value = match ? (match.email || '') : '';
  document.getElementById('person_phone').value = match ? (match.phone || '') : '';
});

document.getElementById('category_id').addEventListener('change', refreshSubcategories);
document.getElementById('operatorgroup_id').addEventListener('change', refreshOperators);
document.getElementById('asset_id').addEventListener('change', () => {
  const selected = document.getElementById('asset_id').selectedOptions[0];
  document.getElementById('asset_type').value = selected ? (selected.dataset.type || '') : '';
});
document.getElementById('status_id').addEventListener('change', () => {
  const selected = document.getElementById('status_id').selectedOptions[0];
  document.getElementById('status_ready_display').checked = selected ? selected.dataset.ready === '1' : false;
  document.getElementById('status_closed_display').checked = selected ? selected.dataset.closed === '1' : false;
});

refreshCustomerOptions();
refreshPersonOptions(false);
refreshSubcategories();
refreshOperators();
const selectedAsset = document.getElementById('asset_id').selectedOptions[0];
document.getElementById('asset_type').value = selectedAsset ? (selectedAsset.dataset.type || '') : '';
const selectedStatus = document.getElementById('status_id').selectedOptions[0];
document.getElementById('status_ready_display').checked = selectedStatus ? selectedStatus.dataset.ready === '1' : false;
document.getElementById('status_closed_display').checked = selectedStatus ? selectedStatus.dataset.closed === '1' : false;
</script>

<?php require_once(__DIR__ . '/../nav/end.php'); ?>
