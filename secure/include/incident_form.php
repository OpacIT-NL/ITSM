<?php
require_once( __DIR__ . '/task_helpers.php' );
$customers_json = json_encode( $reference_data['customers'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$persons_json = json_encode( $reference_data['persons'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$categories_json = json_encode( $reference_data['categories'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$subcategories_json = json_encode( $reference_data['subcategories'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$assets_json = json_encode( $reference_data['assets'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$groups_json = json_encode( $reference_data['groups'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$operators_json = json_encode( $reference_data['operators'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$op_links_json = json_encode( $reference_data['op_links'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$templates_json = json_encode( $reference_data['templates'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$major_incidents_json = json_encode( $reference_data['major_incidents'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$show_template_actions = empty( $form_values['template_used'] );
?>
<?php require_once(__DIR__ . '/../nav/nav.php'); ?>
<div class="content">
  <a href='javascript:history.back(1)'>Ga terug</a>
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
          <h2 class="incident-section-title">Algemeen</h2>

          <hr>

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
            <label>
              <input type="text" id="person_email" class="incident-readonly" value="<?= htmlspecialchars($form_values['personemail']) ?>" readonly>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Telefoonnummer</label>
            <label>
              <input type="text" id="person_phone" class="incident-readonly" value="<?= htmlspecialchars($form_values['personphone']) ?>" readonly>
            </label>
          </div>

          <hr>

          <div class="form-group">
            <label class="incident-meta-label">Categorie</label>
            <label>
              <input type="hidden" name="categoryid" id="category_id" value="<?= htmlspecialchars((string)$form_values['categoryid']) ?>">
              <div class="combo-box">
                <input type="text" id="category_lookup" class="combo-input" autocomplete="off" required>
                <button type="button" class="combo-toggle" data-target="category_lookup" aria-label="Toon categorieen">
                  <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div id="categories_list" class="combo-menu"></div>
              </div>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Subcategorie</label>
            <label>
              <input type="hidden" name="subcategoryid" id="subcategory_id" value="<?= htmlspecialchars((string)$form_values['subcategoryid']) ?>">
              <div class="combo-box">
                <input type="text" id="subcategory_lookup" class="combo-input" autocomplete="off">
                <button type="button" class="combo-toggle" data-target="subcategory_lookup" aria-label="Toon subcategorieen">
                  <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div id="subcategories_list" class="combo-menu"></div>
              </div>
            </label>
          </div>

          <hr>

          <div class="form-group">
            <label class="incident-meta-label">Object ID</label>
            <label>
              <input type="hidden" name="assetid" id="asset_id" value="<?= htmlspecialchars((string)$form_values['assetid']) ?>">
              <div class="combo-box">
                <input type="text" id="asset_lookup" class="combo-input" autocomplete="off">
                <button type="button" class="combo-toggle" data-target="asset_lookup" aria-label="Toon objecten">
                  <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div id="assets_list" class="combo-menu"></div>
              </div>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Type</label>
            <label>
              <input type="text" id="asset_type" class="incident-readonly" value="<?= htmlspecialchars($form_values['assettype']) ?>" readonly>
            </label>
          </div>

         

          <?php if ( $show_major_link_control ): ?>
          <div class="form-group">
            <label class="incident-meta-label">Major incident</label>
            <label>
              <input type="hidden" name="majorincidentid" id="majorincident_id" value="<?= htmlspecialchars((string)$form_values['majorincidentid']) ?>">
              <input type="text" id="majorincident_lookup" list="major_incidents_list" autocomplete="off">
              <datalist id="major_incidents_list"></datalist>
            </label>
          </div>
          <?php endif; ?>

          <hr>

          <div class="form-group">
            <label class="incident-meta-label">Behandelaarsgroep</label>
            <label>
              <input type="hidden" name="operatorgroupid" id="operatorgroup_id" value="<?= htmlspecialchars((string)$form_values['operatorgroupid']) ?>">
              <input type="text" id="operatorgroup_lookup" list="groups_list" autocomplete="off">
              <datalist id="groups_list"></datalist>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Behandelaar</label>
            <label>
              <input type="hidden" name="operatorid" id="operator_id" value="<?= htmlspecialchars((string)$form_values['operatorid']) ?>">
              <input type="text" id="operator_lookup" list="operators_list" autocomplete="off">
              <datalist id="operators_list"></datalist>
            </label>
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
            <label>
              <div>
                <input type="checkbox" id="status_ready_display" <?= !empty($form_values['statusready']) ? 'checked' : '' ?> disabled>
              </div>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Afgemeld</label>
            <label>
              <div>
                <input type="checkbox" id="status_closed_display" <?= !empty($form_values['statusclosed']) ? 'checked' : '' ?> disabled>
              </div>
            </label>
          </div>
          <input type="hidden" name="incidenttype" value="<?= htmlspecialchars($current_mode) ?>">
        </div>
      </div>
    </div>

    <div class="incident-column">
      <div class="incident-card incident-main-card">
        <div class="form-grid">
          <?php if ( !empty( $action_links ) || !empty( $action_buttons ) ): ?>
          <div class="form-actions">
            <?php foreach ( $action_links as $action_link ): ?>
            <a href="<?= htmlspecialchars($action_link['href']) ?>" class="btn-primary"><?= htmlspecialchars($action_link['label']) ?></a>
            <?php endforeach; ?>
            <?php foreach ( $action_buttons as $action_button ): ?>
            <button type="submit" name="incident_action" value="<?= htmlspecialchars($action_button['value']) ?>" class="btn-primary" <?= !empty($action_button['formnovalidate']) ? 'formnovalidate' : '' ?>>
              <?= htmlspecialchars($action_button['label']) ?>
            </button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="form-group">
            <label class="incident-meta-label">Korte titel</label>
            <label>
              <input type="text" name="title" class="incident-title-input" value="<?= htmlspecialchars($form_values['title']) ?>" required>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Omschrijving</label>
            <label>
              <textarea name="description" required><?= htmlspecialchars($form_values['description']) ?></textarea>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label">Commentaar</label>
            <label>
              <input type="hidden" name="commentid" value="<?= htmlspecialchars((string)$form_values['commentid']) ?>">
              <textarea name="commenttext"><?= htmlspecialchars($form_values['commenttext']) ?></textarea>
            </label>
          </div>

          <?php if ( $show_template_actions ): ?>
          <div class="form-actions" id="template_actions">
            <input type="hidden" name="applied_template_id" id="applied_template_id" value="<?= htmlspecialchars((string)($form_values['applied_template_id'] ?? '')) ?>">
            <select id="template_select">
              <option value="">Selecteer sjabloon</option>
            </select>
            <button type="button" id="apply_template_button" class="btn-primary">Sjabloon toepassen</button>
          </div>
          <?php endif; ?>

          <div class="form-group">
            <label>
              <input type="checkbox" name="internalonly" <?= !empty($form_values['internalonly']) ? 'checked' : '' ?>>
              Niet voor klant
            </label>
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
              <?php if ( (int)$comment['internalonly'] === 1 ): ?>
              <span class="incident-badge">Niet voor klant</span>
              <?php else: ?>
              <span class="incident-badge">Klant zichtbaar</span>
              <?php endif; ?>
              <p><?= task_linkify_text($comment['commenttext'], 'secure') ?></p>
              <div class="form-actions">
                <a href="edit_incident.php?id=<?= htmlspecialchars((string)$incident_id) ?>&edit_comment=<?= htmlspecialchars((string)$comment['id']) ?>">Commentaar bewerken</a>
                <button type="submit" name="delete_comment_id" value="<?= htmlspecialchars((string)$comment['id']) ?>" class="btn-danger" formnovalidate onclick="return confirm('Weet je zeker dat je dit commentaar wil verwijderen?');">Commentaar verwijderen</button>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ( $show_linked_incidents ): ?>
          <hr>
          <h3>Gekoppelde incidenten</h3>
          <div class="incident-history">
            <?php if ( empty( $linked_incidents ) ): ?>
            <p>Geen gekoppelde incidenten.</p>
            <?php else: ?>
            <?php foreach ( $linked_incidents as $linked_incident ): ?>
            <div class="incident-comment">
              <div class="incident-comment-meta">
                <span><?= htmlspecialchars(incident_format_display_number($linked_incident)) ?> <?= htmlspecialchars(incident_mode_label($linked_incident['incidenttype'])) ?></span>
                <span><?= htmlspecialchars($linked_incident['status_name']) ?></span>
              </div>
              <p><?= task_linkify_text($linked_incident['title'], 'secure') ?></p>
              <a href="edit_incident.php?id=<?= htmlspecialchars((string)$linked_incident['id']) ?>">Open gekoppeld incident</a>
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
const categories = <?= $categories_json ?>;
const subcategories = <?= $subcategories_json ?>;
const assets = <?= $assets_json ?>;
const groups = <?= $groups_json ?>;
const operators = <?= $operators_json ?>;
const opLinks = <?= $op_links_json ?>;
const templates = <?= $templates_json ?>;
const majorIncidents = <?= $major_incidents_json ?>;

function customerLabel(row) {
  return `${row.din || row.id} - ${row.name}`;
}

function personLabel(row) {
  return row.email ? `${row.lastname}, ${row.firstname} (${row.email})` : `${row.lastname}, ${row.firstname}`;
}

function categoryLabel(row) {
  return row.name;
}

function assetLabel(row) {
  return row.objectid;
}

function groupLabel(row) {
  return row.groupname;
}

function operatorLabel(row) {
  return `${row.lastname}, ${row.firstname}`;
}

function majorIncidentLabel(row) {
  return `${row.incidentnumber || ('#' + row.id)} - ${row.title}`;
}

function setDatalistOptions(listId, rows, labelBuilder) {
  const list = document.getElementById(listId);
  if (!list) {
    return;
  }

  if (list.tagName === 'DATALIST') {
    list.innerHTML = '';
    rows.forEach((row) => {
      const option = document.createElement('option');
      option.value = labelBuilder(row);
      list.appendChild(option);
    });
    return;
  }

  list.innerHTML = '';
  rows.forEach((row) => {
    const option = document.createElement('button');
    option.type = 'button';
    option.className = 'combo-option';
    option.textContent = labelBuilder(row);
    option.dataset.id = String(row.id);
    list.appendChild(option);
  });
}

function setLookupValue(inputId, hiddenId, rows, labelBuilder) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  if (!input || !hidden) {
    return;
  }

  const current = rows.find((row) => String(row.id) === String(hidden.value));
  input.value = current ? labelBuilder(current) : '';
}

function resolveLookup(inputId, hiddenId, rowsSource, labelBuilder, onResolved) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  if (!input || !hidden) {
    return;
  }

  const resolve = () => {
    const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
    const match = rows.find((row) => labelBuilder(row) === input.value);
    hidden.value = match ? String(match.id) : '';
    if (onResolved) {
      onResolved(match);
    }
  };

  input.addEventListener('input', resolve);
  input.addEventListener('change', resolve);
  input.addEventListener('blur', resolve);
}

function initComboBox(inputId, hiddenId, rowsSource, labelBuilder, onResolved) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  const menu = document.getElementById(input.getAttribute('list'));
  if (!input || !hidden || !menu) {
    return;
  }

  const render = (showAll = false) => {
    const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
    const term = input.value.trim().toLowerCase();
    const filteredRows = showAll || term === ''
      ? rows
      : rows.filter((row) => labelBuilder(row).toLowerCase().includes(term));

    setDatalistOptions(menu.id, filteredRows, labelBuilder);
    menu.classList.toggle('is-open', filteredRows.length > 0);

    Array.from(menu.querySelectorAll('.combo-option')).forEach((option) => {
      option.addEventListener('click', () => {
        const match = rows.find((row) => String(row.id) === option.dataset.id);
        input.value = match ? labelBuilder(match) : '';
        hidden.value = match ? String(match.id) : '';
        menu.classList.remove('is-open');
        if (onResolved) {
          onResolved(match || null);
        }
      });
    });
  };

  const applyAutocomplete = () => {
    const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
    const typedValue = input.value;
    const typedLower = typedValue.trim().toLowerCase();

    if (typedLower === '') {
      hidden.value = '';
      if (onResolved) {
        onResolved(null);
      }
      return;
    }

    const prefixMatch = rows.find((row) => labelBuilder(row).toLowerCase().startsWith(typedLower));
    if (prefixMatch) {
      const fullLabel = labelBuilder(prefixMatch);
      input.value = fullLabel;
      input.setSelectionRange(typedValue.length, fullLabel.length);
      hidden.value = String(prefixMatch.id);
      if (onResolved) {
        onResolved(prefixMatch);
      }
      return;
    }

    const exactMatch = rows.find((row) => labelBuilder(row) === typedValue);
    hidden.value = exactMatch ? String(exactMatch.id) : '';
    if (onResolved) {
      onResolved(exactMatch || null);
    }
  };

  input.addEventListener('focus', () => render(true));
  input.addEventListener('input', () => {
    hidden.value = '';
    render(false);
    applyAutocomplete();
  });
  input.addEventListener('blur', () => {
    window.setTimeout(() => {
      menu.classList.remove('is-open');
      const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
      const match = rows.find((row) => labelBuilder(row) === input.value);
      hidden.value = match ? String(match.id) : '';
      if (onResolved) {
        onResolved(match || null);
      }
    }, 150);
  });

  const toggle = document.querySelector(`.combo-toggle[data-target="${inputId}"]`);
  if (toggle) {
    toggle.addEventListener('click', () => {
      input.focus();
      render(true);
    });
  }
}

function refreshPersonFields(person) {
  document.getElementById('person_email').value = person ? (person.email || '') : '';
  document.getElementById('person_phone').value = person ? (person.phone || '') : '';
}

function refreshAssetType(asset) {
  document.getElementById('asset_type').value = asset ? (asset.typename || '') : '';
}

function currentCustomerPersons() {
  const customerId = document.getElementById('customer_id').value;
  return persons.filter((row) => String(row.customerid) === String(customerId));
}

function currentCategorySubcategories() {
  const categoryId = document.getElementById('category_id').value;
  return subcategories.filter((row) => String(row.parent) === String(categoryId));
}

function currentGroupOperators() {
  const groupId = document.getElementById('operatorgroup_id').value;
  if (!groupId) {
    return operators;
  }

  const operatorIds = opLinks
    .filter((row) => String(row.groupid) === String(groupId))
    .map((row) => String(row.operatorid));

  return operators.filter((row) => operatorIds.includes(String(row.id)));
}

function refreshPersons(resetSelection) {
  const rows = currentCustomerPersons();
  setDatalistOptions('persons_list', rows, personLabel);
  if (resetSelection) {
    document.getElementById('person_id').value = '';
    document.getElementById('person_lookup').value = '';
    refreshPersonFields(null);
  } else {
    setLookupValue('person_lookup', 'person_id', rows, personLabel);
    const current = rows.find((row) => String(row.id) === document.getElementById('person_id').value);
    refreshPersonFields(current || null);
  }
}

function refreshSubcategories(resetSelection) {
  const rows = currentCategorySubcategories();
  setDatalistOptions('subcategories_list', rows, categoryLabel);
  if (resetSelection) {
    document.getElementById('subcategory_id').value = '';
    document.getElementById('subcategory_lookup').value = '';
  } else {
    setLookupValue('subcategory_lookup', 'subcategory_id', rows, categoryLabel);
  }
}

function refreshOperators(resetSelection) {
  const rows = currentGroupOperators();
  setDatalistOptions('operators_list', rows, operatorLabel);
  if (resetSelection) {
    document.getElementById('operator_id').value = '';
    document.getElementById('operator_lookup').value = '';
  } else {
    setLookupValue('operator_lookup', 'operator_id', rows, operatorLabel);
  }
}

function currentTemplates() {
  const categoryId = document.getElementById('category_id').value;
  const subcategoryId = document.getElementById('subcategory_id').value;
  return templates.filter((row) => {
    if (String(row.categoryid) !== String(categoryId)) {
      return false;
    }
    if (!subcategoryId) {
      return !row.subcategoryid || String(row.subcategoryid) === '';
    }
    return String(row.subcategoryid || '') === String(subcategoryId);
  });
}

function refreshTemplateSelect() {
  const templateSelect = document.getElementById('template_select');
  if (!templateSelect) {
    return;
  }

  const rows = currentTemplates();
  templateSelect.innerHTML = '<option value="">Selecteer sjabloon</option>';
  rows.forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    templateSelect.appendChild(option);
  });
}

setDatalistOptions('customers_list', customers, customerLabel);
setDatalistOptions('groups_list', groups, groupLabel);
setDatalistOptions('major_incidents_list', majorIncidents, majorIncidentLabel);

setLookupValue('customer_lookup', 'customer_id', customers, customerLabel);
setLookupValue('category_lookup', 'category_id', categories, categoryLabel);
setLookupValue('asset_lookup', 'asset_id', assets, assetLabel);
setLookupValue('operatorgroup_lookup', 'operatorgroup_id', groups, groupLabel);
setLookupValue('majorincident_lookup', 'majorincident_id', majorIncidents, majorIncidentLabel);
refreshPersons(false);
refreshSubcategories(false);
refreshOperators(false);
refreshTemplateSelect();
setLookupValue('operator_lookup', 'operator_id', currentGroupOperators(), operatorLabel);
refreshAssetType(assets.find((row) => String(row.id) === document.getElementById('asset_id').value) || null);

resolveLookup('customer_lookup', 'customer_id', customers, customerLabel, () => {
  refreshPersons(true);
});
resolveLookup('person_lookup', 'person_id', currentCustomerPersons, personLabel, (match) => {
  refreshPersonFields(match || null);
});
resolveLookup('operatorgroup_lookup', 'operatorgroup_id', groups, groupLabel, () => {
  refreshOperators(true);
});
resolveLookup('operator_lookup', 'operator_id', currentGroupOperators, operatorLabel);
resolveLookup('majorincident_lookup', 'majorincident_id', majorIncidents, majorIncidentLabel);
document.getElementById('category_lookup').setAttribute('list', 'categories_list');
document.getElementById('subcategory_lookup').setAttribute('list', 'subcategories_list');
document.getElementById('asset_lookup').setAttribute('list', 'assets_list');
initComboBox('category_lookup', 'category_id', categories, categoryLabel, () => {
  refreshSubcategories(true);
  refreshTemplateSelect();
});
initComboBox('subcategory_lookup', 'subcategory_id', currentCategorySubcategories, categoryLabel, () => {
  refreshTemplateSelect();
});
initComboBox('asset_lookup', 'asset_id', assets, assetLabel, (match) => {
  refreshAssetType(match || null);
});

function refreshStatusFlags() {
  const select = document.getElementById('status_id');
  const readyDisplay = document.getElementById('status_ready_display');
  const closedDisplay = document.getElementById('status_closed_display');
  if (!select || !readyDisplay || !closedDisplay) {
    return;
  }

  const option = select.options[select.selectedIndex];
  const ready = option ? option.getAttribute('data-ready') === '1' : false;
  const closed = option ? option.getAttribute('data-closed') === '1' : false;
  readyDisplay.checked = ready;
  closedDisplay.checked = closed;
}

const statusSelect = document.getElementById('status_id');
if (statusSelect) {
  refreshStatusFlags();
  statusSelect.addEventListener('change', refreshStatusFlags);
}

const applyTemplateButton = document.getElementById('apply_template_button');
if (applyTemplateButton) {
  applyTemplateButton.addEventListener('click', () => {
    const templateSelect = document.getElementById('template_select');
    const templateActions = document.getElementById('template_actions');
    const appliedTemplateField = document.getElementById('applied_template_id');
    const selected = templates.find((row) => String(row.id) === String(templateSelect.value));
    if (!selected) {
      return;
    }

    const descriptionField = document.querySelector('textarea[name="description"]');
    const commentField = document.querySelector('textarea[name="commenttext"]');
    const titleField = document.querySelector('input[name="title"]');
    if (titleField) {
      titleField.value = selected.name || '';
    }
    if (descriptionField) {
      descriptionField.value = selected.description || '';
    }
    if (commentField) {
      commentField.value = selected.commenttext || '';
    }
    if (appliedTemplateField) {
      appliedTemplateField.value = String(selected.id || '');
    }
    if (templateActions) {
      templateActions.style.display = 'none';
    }
  });
}
</script>

<?php require_once(__DIR__ . '/../nav/end.php'); ?>
