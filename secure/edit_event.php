<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/event_helpers.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );
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
$operator_context = event_get_operator_context( $con, $logged_in_user );
event_require_access( $operator_context );
$event_id = (int)$_GET['id'];

$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_em_events WHERE id = ?" );
mysqli_stmt_bind_param( $stmt, "i", $event_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$event = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$event ) {
  die( 'Event niet gevonden' );
}

$reference_data = event_load_reference_data( $con );
$errors = [];
$presence_error = form_presence_flash_error();
if ( $presence_error !== '' ) {
  $errors[] = $presence_error;
}

if ( isset( $_POST['delete_link_id'] ) && is_numeric( $_POST['delete_link_id'] ) ) {
  task_delete_link( $con, (int)$_POST['delete_link_id'] );
  header( 'Location: edit_event.php?id=' . $event_id );
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
    } elseif ( $target['type'] === 'event' && (int)$target['id'] === $event_id ) {
      $errors[] = 'Een event kan niet aan zichzelf gekoppeld worden.';
    } else {
      task_create_link( $con, 'event', $event_id, $relation, $target['type'], (int)$target['id'], (int)$operator_context['id'] );
      task_log_add( $con, 'event', $event_id, 'link_created', 'Link toegevoegd: ' . $relation . ' ' . $target['type'] . ' #' . (int)$target['id'] . '.', (int)$operator_context['id'] );
      header( 'Location: edit_event.php?id=' . $event_id );
      exit;
    }
  }
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['add_task_link'] ) ) {
  form_presence_redirect_if_stale( $con, 'event', $event_id, $_POST['presence_token'] ?? '', 'edit_event.php?id=' . $event_id );
  $action = $_POST['event_action'] ?? 'save';
  $category = event_find_by_id( $reference_data['categories'], $_POST['categoryid'] ?? '' );
  $subcategory = ( $_POST['subcategoryid'] ?? '' ) !== '' ? event_find_by_id( $reference_data['subcategories'], $_POST['subcategoryid'] ?? '' ) : null;
  $asset = ( $_POST['assetid'] ?? '' ) !== '' ? event_find_by_id( $reference_data['assets'], $_POST['assetid'] ?? '' ) : null;
  $description = trim( $_POST['description'] ?? '' );

  if ( !$category ) {
    $errors[] = 'Selecteer een geldige categorie.';
  }
  if ( ( $_POST['subcategoryid'] ?? '' ) !== '' && !$subcategory ) {
    $errors[] = 'Selecteer een geldige subcategorie.';
  } elseif ( $subcategory && $category && (int)$subcategory['parent'] !== (int)$category['id'] ) {
    $errors[] = 'De subcategorie hoort niet bij de gekozen categorie.';
  }
  if ( ( $_POST['assetid'] ?? '' ) !== '' && !$asset ) {
    $errors[] = 'Selecteer een geldig object.';
  }
  if ( $description === '' ) {
    $errors[] = 'Omschrijving is verplicht.';
  }
  $errors = array_merge( $errors, attachment_upload_errors() );
  $presence_check = form_presence_check_before_save( $con, 'event', $event_id, $_POST['presence_token'] ?? '' );
  if ( !$presence_check['ok'] ) {
    $errors[] = $presence_check['message'];
  }

  if ( empty( $errors ) ) {
    $category_id = (int)$category['id'];
    $subcategory_id = $subcategory ? (int)$subcategory['id'] : null;
    $asset_id = $asset ? (int)$asset['id'] : null;

    $update_stmt = mysqli_prepare( $con, "
            UPDATE itsm_em_events
            SET categoryid = ?, subcategoryid = ?, assetid = ?, description = ?
            WHERE id = ?
        " );
    mysqli_stmt_bind_param( $update_stmt, "iiisi", $category_id, $subcategory_id, $asset_id, $description, $event_id );
    mysqli_stmt_execute( $update_stmt );
    attachment_save_upload( $con, 'event', $event_id, (int)$operator_context['id'], 0 );
    form_presence_mark_saved( $con, 'event', $event_id, (int)$operator_context['id'] );
    task_log_add( $con, 'event', $event_id, 'updated', 'Event opgeslagen.', (int)$operator_context['id'] );
    task_log_field_changes(
      $con,
      'event',
      $event_id,
      [
        'categoryid' => $event['categoryid'],
        'subcategoryid' => $event['subcategoryid'],
        'assetid' => $event['assetid'],
        'description' => $event['description']
      ],
      [
        'categoryid' => $category_id,
        'subcategoryid' => $subcategory_id,
        'assetid' => $asset_id,
        'description' => $description
      ],
      [
        'categoryid' => 'Categorie',
        'subcategoryid' => 'Subcategorie',
        'assetid' => 'Object ID',
        'description' => 'Omschrijving'
      ],
      (int)$operator_context['id']
    );

    if ( $action === 'close' ) {
      $close_stmt = mysqli_prepare( $con, "UPDATE itsm_em_events SET acknowledged = 1, closed = 1 WHERE id = ?" );
      mysqli_stmt_bind_param( $close_stmt, "i", $event_id );
      mysqli_stmt_execute( $close_stmt );
      task_log_add( $con, 'event', $event_id, 'closed', 'Event bevestigd en gesloten.', (int)$operator_context['id'] );
      header( 'Location: edit_event.php?id=' . $event_id );
      exit;
    }

    if ( $action === 'create_incident' && (int)$event['incidentid'] === 0 ) {
      $incident_reference = incident_load_reference_data( $con );
      $group_id = event_default_group_id( $incident_reference['groups'] );
      $status_id = event_default_incident_status_id( $incident_reference['statuses'] );
      $incident_number = incident_generate_number( $con );
      $title = event_build_incident_title( [ 'description' => $description, 'eventnumber' => $event['eventnumber'], 'id' => $event_id ] );
      $incident_type = 'firstline';
      $created_by = (int)$operator_context['id'];
      $empty_string = '';
      $null_customer = null;
      $null_person = null;
      $null_operator = null;

      $incident_stmt = mysqli_prepare( $con, "
                INSERT INTO itsm_im_incidents (
                    incidentnumber, incidenttype, majorincidentid, title, description, customerid, personid, personemail, personphone,
                    categoryid, subcategoryid, assetid, operatorgroupid, operatorid, statusid, createdby
                ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            " );
      mysqli_stmt_bind_param(
        $incident_stmt,
        "ssssiissiiiiiii",
        $incident_number,
        $incident_type,
        $title,
        $description,
        $null_customer,
        $null_person,
        $empty_string,
        $empty_string,
        $category_id,
        $subcategory_id,
        $asset_id,
        $group_id,
        $null_operator,
        $status_id,
        $created_by
      );

      if ( mysqli_stmt_execute( $incident_stmt ) ) {
        $incident_id = mysqli_insert_id( $con );
        $link_stmt = mysqli_prepare( $con, "UPDATE itsm_em_events SET incidentid = ? WHERE id = ?" );
        mysqli_stmt_bind_param( $link_stmt, "ii", $incident_id, $event_id );
        mysqli_stmt_execute( $link_stmt );
        task_log_add( $con, 'event', $event_id, 'incident_created', 'Incident ' . $incident_number . ' aangemaakt vanuit event.', (int)$operator_context['id'] );
        task_log_add( $con, 'incident', $incident_id, 'created', 'Incident aangemaakt vanuit event ' . event_format_display_number( $event ) . '.', (int)$operator_context['id'] );
        header( 'Location: edit_incident.php?id=' . $incident_id );
        exit;
      }

      $errors[] = 'Incident aanmaken vanuit event mislukt: ' . mysqli_stmt_error( $incident_stmt );
    } else {
      header( 'Location: edit_event.php?id=' . $event_id );
      exit;
    }
  }

  $event['categoryid'] = $_POST['categoryid'] ?? '';
  $event['subcategoryid'] = $_POST['subcategoryid'] ?? '';
  $event['assetid'] = $_POST['assetid'] ?? '';
  $event['description'] = $description;
}
$attachments = attachment_load_for_task( $con, 'event', $event_id );
$attachments_html = attachment_render_as_comments( $attachments );
$task_logs_html = task_log_render_tab( task_log_load( $con, 'event', $event_id ) );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <span data-tab-title="<?= htmlspecialchars(event_format_display_number($event), ENT_QUOTES) ?>" data-tab-subtitle="<?= htmlspecialchars(t('Event'), ENT_QUOTES) ?>" hidden></span>
  <?php $list_back_url = event_get_list_back_url( 'events.php?view=open' ); require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Event <?= htmlspecialchars(event_format_display_number($event)) ?></h1>
  </center>
  <?php if ( !empty( $errors ) ): ?>
  <div class="form-wrapper record-form-wrapper"><div class="form-card record-form-card"><?php foreach ( $errors as $error ): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div></div><br>
  <?php endif; ?>
  <div class="ticket-view-tabs caller-card-tabs" role="tablist">
    <button type="button" class="caller-card-tab is-active" data-ticket-view-tab="task" role="tab" aria-selected="true">Taak</button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="links" role="tab" aria-selected="false">Links</button>
    <button type="button" class="caller-card-tab" data-ticket-view-tab="log" role="tab" aria-selected="false">Audit log</button>
  </div>
  <div class="ticket-view-panel is-active" data-ticket-view-panel="task">
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card form-card-wide record-form-card">
      <form method="post" enctype="multipart/form-data">
        <div class="form-grid">
          <div class="form-group">
            <label>Categorie</label>
            <select name="categoryid" id="category_id" required>
              <option value="">Selecteer een categorie</option>
              <?php foreach ( $reference_data['categories'] as $category ): ?>
              <option value="<?= htmlspecialchars((string)$category['id']) ?>" <?= (string)$event['categoryid'] === (string)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Subcategorie</label>
            <select name="subcategoryid" id="subcategory_id"><option value=""><?= htmlspecialchars(t('Selecteer een subcategorie')) ?></option></select>
          </div>
          <div class="form-group">
            <label>Object ID</label>
            <select name="assetid">
              <option value="">Selecteer een object</option>
              <?php foreach ( $reference_data['assets'] as $asset ): ?>
              <option value="<?= htmlspecialchars((string)$asset['id']) ?>" <?= (string)$event['assetid'] === (string)$asset['id'] ? 'selected' : '' ?>><?= htmlspecialchars($asset['objectid']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Omschrijving</label>
            <textarea name="description" required><?= htmlspecialchars($event['description']) ?></textarea>
          </div>
          <?php attachment_render_upload_field(); ?>
          <?php if ( (int)$event['incidentid'] > 0 ): ?>
          <p class="info-note">Gekoppeld incident ID: <?= htmlspecialchars((string)$event['incidentid']) ?></p>
          <?php endif; ?>
          <div class="form-actions">
            <?php if ( (int)$event['incidentid'] === 0 && (int)$event['closed'] === 0 ): ?>
            <button type="submit" name="event_action" value="create_incident" class="btn-primary">Incident maken van event</button>
            <?php endif; ?>
            <?php if ( (int)$event['closed'] === 0 ): ?>
            <button type="submit" name="event_action" value="close" class="btn-danger">Bevestigen en sluiten</button>
            <?php endif; ?>
            <button type="submit" name="event_action" value="save" class="btn-primary">Opslaan</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card form-card-wide record-form-card">
      <?php if ( !empty( $attachments_html ) ): ?>
      <h3>Bijlagen</h3>
      <?= $attachments_html ?>
      <?php endif; ?>
    </div>
  </div>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="links">
    <div class="form-wrapper record-form-wrapper">
      <div class="form-card form-card-wide record-form-card">
        <form method="post">
          <?= task_render_links_section( task_load_links( $con, 'event', $event_id, 'secure' ) ) ?>
        </form>
      </div>
    </div>
  </div>
  <div class="ticket-view-panel" data-ticket-view-panel="log">
    <div class="form-wrapper record-form-wrapper">
      <div class="form-card form-card-wide record-form-card">
        <?= $task_logs_html ?>
      </div>
    </div>
  </div>
</div>
<script>
const editEventSubcategories = <?= json_encode($reference_data['subcategories'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const currentEditEventSubcategoryId = <?= json_encode((string)$event['subcategoryid']) ?>;
const editEventFormI18n = {
  selectSubcategory: <?= json_encode(t('Selecteer een subcategorie')) ?>
};
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
function refreshEditEventSubcategories() {
  const categoryId = document.getElementById('category_id').value;
  const select = document.getElementById('subcategory_id');
  select.innerHTML = `<option value="">${editEventFormI18n.selectSubcategory}</option>`;
  editEventSubcategories.filter((row) => String(row.parent) === String(categoryId)).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    if (String(row.id) === String(currentEditEventSubcategoryId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}
document.getElementById('category_id').addEventListener('change', refreshEditEventSubcategories);
refreshEditEventSubcategories();
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
