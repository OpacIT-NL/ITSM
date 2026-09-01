<?php
require_once( __DIR__ . '/task_helpers.php' );
require_once( __DIR__ . '/attachment_helpers.php' );
require_once( __DIR__ . '/task_log_helpers.php' );
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
$impacts_json = json_encode( $reference_data['impacts'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$urgencies_json = json_encode( $reference_data['urgencies'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$priorities_json = json_encode( $reference_data['priorities'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$priority_matrix_json = json_encode( $reference_data['priority_matrix'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$current_operator_id_json = json_encode( (string)( $operator_context['id'] ?? '' ) );
$show_template_actions = empty( $form_values['template_used'] );
?>
<?php require_once(__DIR__ . '/../nav/nav.php'); ?>
<div class="content">
  <?php if ( !empty( $tab_title ) ): ?>
  <span data-tab-title="<?= htmlspecialchars($tab_title, ENT_QUOTES) ?>" data-tab-subtitle="<?= htmlspecialchars($tab_subtitle ?? '', ENT_QUOTES) ?>" hidden></span>
  <?php endif; ?>
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

  <?php if ( !empty( $task_logs_html ) || !empty( $links_html ) || !empty( $mail_tab_html ) ): ?>
  <div class="ticket-view-tabs caller-card-tabs" role="tablist">
    <button type="button" class="caller-card-tab is-active" data-ticket-view-tab="task" role="tab" aria-selected="true"><?= htmlspecialchars(t('Taak')) ?></button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="links" role="tab" aria-selected="false"><?= htmlspecialchars(t('Links')) ?></button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="mail" role="tab" aria-selected="false"><?= htmlspecialchars(t('E-mail')) ?></button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="log" role="tab" aria-selected="false"><?= htmlspecialchars(t('Audit log')) ?></button>
  </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="incident-layout ticket-view-panel is-active" data-ticket-view-panel="task">
    <div class="incident-column">
      <div class="incident-card incident-left-card">
        <div class="form-grid">
          <h2 class="incident-section-title"><?= htmlspecialchars(t('Algemeen')) ?></h2>

          <hr>

          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Klant')) ?></label>
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
                <button type="button" class="combo-toggle" data-target="category_lookup" aria-label="<?= htmlspecialchars(t('Toon categorieen')) ?>">
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
                <button type="button" class="combo-toggle" data-target="subcategory_lookup" aria-label="<?= htmlspecialchars(t('Toon subcategorieen')) ?>">
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
                <button type="button" class="combo-toggle" data-target="asset_lookup" aria-label="<?= htmlspecialchars(t('Toon objecten')) ?>">
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
            <label class="incident-meta-label"><?= htmlspecialchars(t('Major incident')) ?></label>
            <label>
              <input type="hidden" name="majorincidentid" id="majorincident_id" value="<?= htmlspecialchars((string)$form_values['majorincidentid']) ?>">
              <input type="text" id="majorincident_lookup" list="major_incidents_list" autocomplete="off">
              <datalist id="major_incidents_list"></datalist>
            </label>
          </div>
          <?php endif; ?>

          <hr>

          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Impact')) ?></label>
            <label>
              <select name="impactid" id="impact_id">
                <option value=""><?= htmlspecialchars(t('Selecteer impact')) ?></option>
                <?php foreach ( $reference_data['impacts'] ?? [] as $impact ): ?>
                <option value="<?= htmlspecialchars((string)$impact['id']) ?>" <?= (string)($form_values['impactid'] ?? '') === (string)$impact['id'] ? 'selected' : '' ?>><?= htmlspecialchars($impact['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Urgency')) ?></label>
            <label>
              <select name="urgencyid" id="urgency_id">
                <option value=""><?= htmlspecialchars(t('Selecteer urgency')) ?></option>
                <?php foreach ( $reference_data['urgencies'] ?? [] as $urgency ): ?>
                <option value="<?= htmlspecialchars((string)$urgency['id']) ?>" <?= (string)($form_values['urgencyid'] ?? '') === (string)$urgency['id'] ? 'selected' : '' ?>><?= htmlspecialchars($urgency['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="form-group">
            <label class="incident-meta-label"><?= htmlspecialchars(t('Priority')) ?></label>
            <label>
              <input type="hidden" name="priorityid" id="priority_id" value="<?= htmlspecialchars((string)($form_values['priorityid'] ?? '')) ?>">
              <input type="text" id="priority_display" class="incident-readonly" value="<?= htmlspecialchars((string)($form_values['priorityname'] ?? '')) ?>" readonly>
            </label>
          </div>

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
            <label class="assign-to-me-row">
              <input type="hidden" name="operatorid" id="operator_id" value="<?= htmlspecialchars((string)$form_values['operatorid']) ?>">
              <input type="text" id="operator_lookup" list="operators_list" autocomplete="off">
              <button type="button" id="assign_to_me_button" class="assign-to-me-button" title="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>" aria-label="<?= htmlspecialchars(t('Aan mij toewijzen')) ?>"><i class="fa-solid fa-user"></i></button>
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

          <?php if (isset($incident_id)): $time_task_type='incident'; $time_task_id=$incident_id; require(__DIR__.'/time_entry_widget.php'); endif; ?>

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

          <?php attachment_render_upload_field(); ?>

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
              <?php if ( !empty( $attachments_by_comment[(string)$comment['id']] ) ): ?>
              <?= attachment_render_links( $attachments_by_comment[(string)$comment['id']] ) ?>
              <?php endif; ?>
              <div class="form-actions">
                <a href="edit_incident.php?id=<?= htmlspecialchars((string)$incident_id) ?>&edit_comment=<?= htmlspecialchars((string)$comment['id']) ?>"><?= htmlspecialchars(t('Commentaar bewerken')) ?></a>
                <button type="submit" name="delete_comment_id" value="<?= htmlspecialchars((string)$comment['id']) ?>" class="btn-danger" formnovalidate onclick="return confirm('<?= htmlspecialchars(t('Weet je zeker dat je dit commentaar wil verwijderen?'), ENT_QUOTES) ?>');"><?= htmlspecialchars(t('Commentaar verwijderen')) ?></button>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ( !empty( $attachments_html ) ): ?>
          <h3>Bijlagen</h3>
          <?= $attachments_html ?>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </form>
  <?php if ( !empty( $links_html ) ): ?>
  <div class="ticket-view-panel" data-ticket-view-panel="links">
    <div class="form-wrapper">
      <div class="form-card form-card-wide">
        <form method="post">
          <?= $links_html ?>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ( !empty( $mail_tab_html ) ): ?>
  <div class="ticket-view-panel" data-ticket-view-panel="mail">
    <div class="form-wrapper">
      <div class="form-card form-card-wide">
        <?= $mail_tab_html ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ( !empty( $task_logs_html ) ): ?>
  <div class="ticket-view-panel" data-ticket-view-panel="log">
    <div class="form-wrapper">
      <div class="form-card form-card-wide">
        <?= $task_logs_html ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
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
const impacts = <?= $impacts_json ?>;
const urgencies = <?= $urgencies_json ?>;
const priorities = <?= $priorities_json ?>;
const priorityMatrix = <?= $priority_matrix_json ?>;
const currentOperatorId = <?= $current_operator_id_json ?>;
const incidentFormI18n = {
  selectTemplate: <?= json_encode(t('Selecteer sjabloon')) ?>
};
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
  refreshAssignToMeButton();
}

function refreshAssignToMeButton() {
  const button = document.getElementById('assign_to_me_button');
  const groupField = document.getElementById('operatorgroup_id');
  if (!button) { return; }
  const selectedGroupId = groupField ? String(groupField.value || '') : '';
  button.disabled = !currentOperatorId || (currentOperatorGroupIds.length !== 1 && !selectedGroupId) || (selectedGroupId && !currentOperatorGroupIds.includes(selectedGroupId));
}

function refreshPriority() {
  const impactId = document.getElementById('impact_id')?.value || '';
  const urgencyId = document.getElementById('urgency_id')?.value || '';
  const priorityField = document.getElementById('priority_id');
  const priorityDisplay = document.getElementById('priority_display');
  if (!priorityField || !priorityDisplay) { return; }
  const match = priorityMatrix.find((row) => String(row.impactid) === String(impactId) && String(row.urgencyid) === String(urgencyId));
  priorityField.value = match ? String(match.priorityid) : '';
  priorityDisplay.value = match ? (match.priorityname || '') : '';
}

function assignToMe() {
  const groupField = document.getElementById('operatorgroup_id');
  const operatorField = document.getElementById('operator_id');
  const operatorLookup = document.getElementById('operator_lookup');
  if (!currentOperatorId || !operatorField || !operatorLookup) { return; }
  if (currentOperatorGroupIds.length === 1 && groupField && !groupField.value) {
    groupField.value = currentOperatorGroupIds[0];
    setLookupValue('operatorgroup_lookup', 'operatorgroup_id', groups, groupLabel);
    refreshOperators(false);
  }
  const selectedGroupId = groupField ? String(groupField.value || '') : '';
  if ((currentOperatorGroupIds.length !== 1 && !selectedGroupId) || (selectedGroupId && !currentOperatorGroupIds.includes(selectedGroupId))) { return; }
  const match = operators.find((row) => String(row.id) === String(currentOperatorId));
  if (!match) { return; }
  operatorField.value = String(match.id);
  operatorLookup.value = operatorLabel(match);
  operatorField.dispatchEvent(new Event('change', { bubbles: true }));
  operatorLookup.dispatchEvent(new Event('input', { bubbles: true }));
  refreshAssignToMeButton();
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
  templateSelect.innerHTML = '<option value="">' + incidentFormI18n.selectTemplate + '</option>';
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
  refreshAssignToMeButton();
});
resolveLookup('operator_lookup', 'operator_id', currentGroupOperators, operatorLabel);
document.getElementById('assign_to_me_button')?.addEventListener('click', assignToMe);
refreshAssignToMeButton();
document.getElementById('impact_id')?.addEventListener('change', refreshPriority);
document.getElementById('urgency_id')?.addEventListener('change', refreshPriority);
refreshPriority();
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
