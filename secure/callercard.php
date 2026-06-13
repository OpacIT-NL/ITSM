<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );

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
$operator_stmt = mysqli_prepare( $con, "
    SELECT id, firstname, lastname, firstlineincidents, secondlineincidents, reqforchange, simplechange, extchange, persons
    FROM itsm_ob_operators
    WHERE username = ?
    LIMIT 1
" );
mysqli_stmt_bind_param( $operator_stmt, "s", $logged_in_user );
mysqli_stmt_execute( $operator_stmt );
$operator_result = mysqli_stmt_get_result( $operator_stmt );
$operator_context = mysqli_fetch_assoc( $operator_result );
mysqli_stmt_close( $operator_stmt );

if ( !$operator_context ) {
  die( 'Behandelaar niet gevonden' );
}
if ( (int)$operator_context['firstlineincidents'] === 0 && (int)$operator_context['secondlineincidents'] === 0 ) {
  header( 'Location: modules.php' );
  exit;
}

$reference_data = incident_load_reference_data( $con );
$selected_customer_id = isset( $_REQUEST['customerid'] ) && is_numeric( $_REQUEST['customerid'] ) ? (int)$_REQUEST['customerid'] : 0;
$selected_person_id = isset( $_REQUEST['personid'] ) && is_numeric( $_REQUEST['personid'] ) ? (int)$_REQUEST['personid'] : 0;
$active_tab = $_GET['tab'] ?? 'incidents';
$description = trim( (string)( $_REQUEST['description'] ?? '' ) );

$selected_customer = $selected_customer_id > 0 ? incident_find_by_id( $reference_data['customers'], $selected_customer_id ) : null;
$selected_person = $selected_person_id > 0 ? incident_find_by_id( $reference_data['persons'], $selected_person_id ) : null;
if ( $selected_person && $selected_customer && (int)$selected_person['customerid'] !== (int)$selected_customer['id'] ) {
  $selected_person = null;
  $selected_person_id = 0;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && $selected_customer && $selected_person ) {
  $query = http_build_query(
    [
      'customerid' => $selected_customer_id,
      'personid' => $selected_person_id,
      'description' => $description
    ]
  );

  $action = $_POST['caller_action'] ?? '';
  if ( $action === 'new_firstline_incident' ) {
    header( 'Location: new_incident.php?mode=firstline&' . $query );
    exit;
  }
  if ( $action === 'new_secondline_incident' && (int)$operator_context['secondlineincidents'] === 1 ) {
    header( 'Location: new_incident.php?mode=secondline&' . $query );
    exit;
  }
  if ( $action === 'new_change_request' && ((int)$operator_context['reqforchange'] === 1 || (int)$operator_context['simplechange'] === 1 || (int)$operator_context['extchange'] === 1) ) {
    header( 'Location: new_change.php?' . $query );
    exit;
  }
}

$incidents = [];
$changes = [];
$assets = [];

if ( $selected_customer && $selected_person ) {
  $incident_stmt = mysqli_prepare( $con, "
      SELECT i.id, i.incidentnumber, i.title, cat.name AS category_name, s.name AS status_name, i.updatedat
      FROM itsm_im_incidents i
      LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
      LEFT JOIN itsm_core_status s ON i.statusid = s.id
      WHERE i.customerid = ? AND i.personid = ?
      ORDER BY i.updatedat DESC, i.id DESC
      LIMIT 50
  " );
  mysqli_stmt_bind_param( $incident_stmt, "ii", $selected_customer_id, $selected_person_id );
  mysqli_stmt_execute( $incident_stmt );
  $incident_result = mysqli_stmt_get_result( $incident_stmt );
  $incidents = mysqli_fetch_all( $incident_result, MYSQLI_ASSOC );
  mysqli_stmt_close( $incident_stmt );

  $change_stmt = mysqli_prepare( $con, "
      SELECT c.id, c.changenumber, c.title, c.requesttype, c.approvalstate, s.name AS status_name, c.updatedat
      FROM itsm_cm_changes c
      LEFT JOIN itsm_core_status s ON c.statusid = s.id
      WHERE c.customerid = ? AND c.personid = ?
      ORDER BY c.updatedat DESC, c.id DESC
      LIMIT 50
  " );
  mysqli_stmt_bind_param( $change_stmt, "ii", $selected_customer_id, $selected_person_id );
  mysqli_stmt_execute( $change_stmt );
  $change_result = mysqli_stmt_get_result( $change_stmt );
  $changes = mysqli_fetch_all( $change_result, MYSQLI_ASSOC );
  mysqli_stmt_close( $change_stmt );

  $asset_stmt = mysqli_prepare( $con, "
      SELECT a.id, a.objectid, t.type AS typename, s.name AS status_name, a.startdate, a.enddate
      FROM itsm_am_assets a
      LEFT JOIN itsm_am_types t ON a.type = t.id
      LEFT JOIN itsm_core_status s ON a.status = s.id
      WHERE a.owner = ?
      ORDER BY a.objectid ASC, a.id ASC
      LIMIT 50
  " );
  mysqli_stmt_bind_param( $asset_stmt, "i", $selected_person_id );
  mysqli_stmt_execute( $asset_stmt );
  $asset_result = mysqli_stmt_get_result( $asset_stmt );
  $assets = mysqli_fetch_all( $asset_result, MYSQLI_ASSOC );
  mysqli_stmt_close( $asset_stmt );
}

$customers_json = json_encode( $reference_data['customers'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$persons_json = json_encode( $reference_data['persons'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $list_back_url = 'index.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <h1>Aanmelderskaart</h1>

  <div class="form-wrapper caller-card-selector">
    <div class="form-card form-card-wide">
      <form method="get" class="form-grid">
        <div class="incident-grid-two">
          <div class="form-group">
            <label>Klant</label>
            <input type="hidden" name="customerid" id="customer_id" value="<?= htmlspecialchars((string)$selected_customer_id) ?>">
            <div class="combo-box">
              <input type="text" id="customer_lookup" class="combo-input" autocomplete="off" required>
              <button type="button" class="combo-toggle" data-target="customer_lookup" aria-label="<?= htmlspecialchars(t('Toon klanten')) ?>">
                <i class="fa-solid fa-chevron-down"></i>
              </button>
              <div id="customers_list" class="combo-menu"></div>
            </div>
          </div>
          <div class="form-group">
            <label>Persoon</label>
            <input type="hidden" name="personid" id="person_id" value="<?= htmlspecialchars((string)$selected_person_id) ?>">
            <div class="combo-box">
              <input type="text" id="person_lookup" class="combo-input" autocomplete="off" required>
              <button type="button" class="combo-toggle" data-target="person_lookup" aria-label="<?= htmlspecialchars(t('Toon personen')) ?>">
                <i class="fa-solid fa-chevron-down"></i>
              </button>
              <div id="persons_list" class="combo-menu"></div>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Aanmelderskaart openen</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ( $selected_customer && $selected_person ): ?>
  <div class="caller-card-overview">
    <div class="incident-card">
      <h2>Klant gegevens</h2>
      <dl class="caller-card-summary">
        <div><dt>Klant</dt><dd><?= htmlspecialchars($selected_customer['name'] ?? '') ?></dd></div>
        <div><dt>Persoon</dt><dd><?= htmlspecialchars(trim(($selected_person['firstname'] ?? '') . ' ' . ($selected_person['lastname'] ?? ''))) ?></dd></div>
        <div><dt>E-mail</dt><dd><?= htmlspecialchars($selected_person['email'] ?? '') ?></dd></div>
        <div><dt>Telefoon</dt><dd><?= htmlspecialchars($selected_person['phone'] ?? '') ?></dd></div>
      </dl>
    </div>

    <div class="incident-card caller-card-description">
      <form method="post" class="form-grid">
        <input type="hidden" name="customerid" value="<?= htmlspecialchars((string)$selected_customer_id) ?>">
        <input type="hidden" name="personid" value="<?= htmlspecialchars((string)$selected_person_id) ?>">
        <div class="caller-card-actions">
          <?php if ( (int)$operator_context['firstlineincidents'] === 1 ): ?>
          <button type="submit" name="caller_action" value="new_firstline_incident" class="btn-primary">Eerstelijns incident</button>
          <?php endif; ?>
          <?php if ( (int)$operator_context['secondlineincidents'] === 1 ): ?>
          <button type="submit" name="caller_action" value="new_secondline_incident" class="btn-primary">Tweedelijns incident</button>
          <?php endif; ?>
          <?php if ( (int)$operator_context['reqforchange'] === 1 || (int)$operator_context['simplechange'] === 1 || (int)$operator_context['extchange'] === 1 ): ?>
          <button type="submit" name="caller_action" value="new_change_request" class="btn-primary">Wijzigingsaanvraag</button>
          <?php endif; ?>
          <?php if ( (int)$operator_context['persons'] === 1 ): ?>
          <a href="edit_person.php?id=<?= (int)$selected_person_id ?>">Persoon aanpassen</a>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>Omschrijving</label>
          <textarea name="description"><?= htmlspecialchars($description) ?></textarea>
        </div>
      </form>
    </div>
  </div>

  <div class="incident-card caller-card-tabs-wrap">
    <div class="caller-card-tabs" role="tablist" aria-label="<?= htmlspecialchars(t('Aanmelderskaart tabs')) ?>">
      <button type="button" class="caller-card-tab<?= $active_tab === 'incidents' ? ' is-active' : '' ?>" data-tab="incidents">Incidenten</button>
      <button type="button" class="caller-card-tab<?= $active_tab === 'changes' ? ' is-active' : '' ?>" data-tab="changes">Wijzigingen</button>
      <button type="button" class="caller-card-tab<?= $active_tab === 'assets' ? ' is-active' : '' ?>" data-tab="assets">Assets</button>
    </div>

    <div class="caller-card-tabpanel<?= $active_tab === 'incidents' ? ' is-active' : '' ?>" data-tab-panel="incidents">
      <div class="results">
        <table class="incident-results-table caller-card-mini-table">
          <thead>
            <tr>
              <th>Nummer</th>
              <th>Titel</th>
              <th>Categorie</th>
              <th>Status</th>
              <th>Bijgewerkt</th>
              <th>Actie</th>
            </tr>
          </thead>
          <tbody>
            <?php if ( empty( $incidents ) ): ?>
            <tr><td colspan="6">Geen incidenten gevonden.</td></tr>
            <?php else: ?>
            <?php foreach ( $incidents as $incident ): ?>
            <tr>
              <td><?= htmlspecialchars($incident['incidentnumber'] ?: ('#' . $incident['id'])) ?></td>
              <td><?= htmlspecialchars($incident['title']) ?></td>
              <td><?= htmlspecialchars($incident['category_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($incident['status_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($incident['updatedat']) ?></td>
              <td class="tblaction"><a href="edit_incident.php?id=<?= (int)$incident['id'] ?>">Open</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="caller-card-tabpanel<?= $active_tab === 'changes' ? ' is-active' : '' ?>" data-tab-panel="changes">
      <div class="results">
        <table class="incident-results-table caller-card-mini-table">
          <thead>
            <tr>
              <th>Nummer</th>
              <th>Titel</th>
              <th>Soort</th>
              <th>Status</th>
              <th>Bijgewerkt</th>
              <th>Actie</th>
            </tr>
          </thead>
          <tbody>
            <?php if ( empty( $changes ) ): ?>
            <tr><td colspan="6">Geen wijzigingen gevonden.</td></tr>
            <?php else: ?>
            <?php foreach ( $changes as $change ): ?>
            <tr>
              <td><?= htmlspecialchars($change['changenumber'] ?: ('#' . $change['id'])) ?></td>
              <td><?= htmlspecialchars($change['title']) ?></td>
              <td>
                <?php
                if ( ($change['approvalstate'] ?? '') === 'request' ) {
                  echo 'Wijzigingsaanvraag';
                } else {
                  echo htmlspecialchars( ($change['requesttype'] ?? '') === 'extended' ? 'Uitgebreide wijziging' : 'Eenvoudige wijziging' );
                }
                ?>
              </td>
              <td><?= htmlspecialchars(($change['approvalstate'] ?? '') === 'approved' ? ($change['status_name'] ?? '') : 'Aanvraag') ?></td>
              <td><?= htmlspecialchars($change['updatedat']) ?></td>
              <td class="tblaction"><a href="edit_change.php?id=<?= (int)$change['id'] ?>">Open</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="caller-card-tabpanel<?= $active_tab === 'assets' ? ' is-active' : '' ?>" data-tab-panel="assets">
      <div class="results">
        <table class="incident-results-table caller-card-mini-table asset-results-table" data-table-preview="off">
          <thead>
            <tr>
              <th>Object ID</th>
              <th>Type</th>
              <th>Status</th>
              <th>Startdatum</th>
              <th>Einddatum</th>
              <th>Actie</th>
            </tr>
          </thead>
          <tbody>
            <?php if ( empty( $assets ) ): ?>
            <tr><td colspan="6">Geen assets gevonden.</td></tr>
            <?php else: ?>
            <?php foreach ( $assets as $asset ): ?>
            <tr>
              <td><?= htmlspecialchars($asset['objectid'] ?? '') ?></td>
              <td><?= htmlspecialchars($asset['typename'] ?? '') ?></td>
              <td><?= htmlspecialchars($asset['status_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($asset['startdate'] ?? '') ?></td>
              <td><?= htmlspecialchars($asset['enddate'] ?? '') ?></td>
              <td class="tblaction"><a href="edit_asset.php?id=<?= (int)$asset['id'] ?>">Open</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
const callerCustomers = <?= $customers_json ?>;
const callerPersons = <?= $persons_json ?>;

function callerCustomerLabel(row) {
  const din = row.din ? `${row.din} - ` : '';
  return `${din}${row.name}`;
}

function callerPersonLabel(row) {
  return `${row.lastname}, ${row.firstname}`;
}

function callerSetDatalistOptions(listId, rows, labelBuilder) {
  const list = document.getElementById(listId);
  if (!list) {
    return;
  }
  list.innerHTML = '';
  rows.forEach((row) => {
    const option = document.createElement('button');
    option.type = 'button';
    option.className = 'combo-option';
    option.dataset.id = String(row.id);
    option.textContent = labelBuilder(row);
    list.appendChild(option);
  });
}

function callerSetLookupValue(inputId, hiddenId, rows, labelBuilder) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  if (!input || !hidden) {
    return;
  }
  const match = rows.find((row) => String(row.id) === String(hidden.value));
  input.value = match ? labelBuilder(match) : '';
}

function callerCurrentPersons() {
  const customerId = document.getElementById('customer_id').value;
  return callerPersons.filter((row) => String(row.customerid) === String(customerId));
}

function callerRefreshPersons(resetSelection) {
  const rows = callerCurrentPersons();
  callerSetDatalistOptions('persons_list', rows, callerPersonLabel);
  if (resetSelection) {
    document.getElementById('person_id').value = '';
    document.getElementById('person_lookup').value = '';
  } else {
    callerSetLookupValue('person_lookup', 'person_id', rows, callerPersonLabel);
  }
}

function callerResolveLookup(inputId, hiddenId, rowsSource, labelBuilder, onResolved) {
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  const menu = document.getElementById(inputId === 'customer_lookup' ? 'customers_list' : 'persons_list');
  if (!input || !hidden) {
    return;
  }

  const render = (showAll) => {
    const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
    const typed = input.value.trim().toLowerCase();
    const filtered = showAll || typed === ''
      ? rows
      : rows.filter((row) => labelBuilder(row).toLowerCase().includes(typed));

    callerSetDatalistOptions(menu.id, filtered, labelBuilder);
    menu.classList.toggle('is-open', filtered.length > 0);

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

  const resolve = () => {
    const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
    const match = rows.find((row) => labelBuilder(row) === input.value);
    hidden.value = match ? String(match.id) : '';
    if (onResolved) {
      onResolved(match || null);
    }
  };

  input.addEventListener('input', () => {
    hidden.value = '';
    const rows = typeof rowsSource === 'function' ? rowsSource() : rowsSource;
    const typedValue = input.value;
    const typedLower = typedValue.trim().toLowerCase();
    render(false);
    if (!typedLower) {
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
    }
  });

  input.addEventListener('blur', () => {
    window.setTimeout(resolve, 120);
    window.setTimeout(() => menu.classList.remove('is-open'), 150);
  });

  input.addEventListener('focus', () => {
    render(true);
  });

  const toggle = document.querySelector(`.combo-toggle[data-target="${inputId}"]`);
  if (toggle) {
    toggle.addEventListener('click', () => {
      input.focus();
      render(true);
    });
  }
}

callerSetDatalistOptions('customers_list', callerCustomers, callerCustomerLabel);
callerSetLookupValue('customer_lookup', 'customer_id', callerCustomers, callerCustomerLabel);
callerRefreshPersons(false);

callerResolveLookup('customer_lookup', 'customer_id', callerCustomers, callerCustomerLabel, () => {
  callerRefreshPersons(true);
});
callerResolveLookup('person_lookup', 'person_id', callerCurrentPersons, callerPersonLabel);

document.querySelectorAll('.caller-card-tab').forEach((button) => {
  button.addEventListener('click', () => {
    const target = button.dataset.tab;
    document.querySelectorAll('.caller-card-tab').forEach((tab) => tab.classList.toggle('is-active', tab === button));
    document.querySelectorAll('.caller-card-tabpanel').forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.tabPanel === target);
    });
  });
});
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
