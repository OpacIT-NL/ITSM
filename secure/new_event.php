<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/event_helpers.php' );
require_once( __DIR__ . '/include/attachment_helpers.php' );
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

$logged_in_user = $_SESSION['name'];
$operator_context = event_get_operator_context( $con, $logged_in_user );
event_require_access( $operator_context );
$reference_data = event_load_reference_data( $con );
$errors = [];

$form_values = [
  'categoryid' => '',
  'subcategoryid' => '',
  'assetid' => '',
  'description' => ''
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'categoryid' => $_POST['categoryid'] ?? '',
    'subcategoryid' => $_POST['subcategoryid'] ?? '',
    'assetid' => $_POST['assetid'] ?? '',
    'description' => trim( $_POST['description'] ?? '' )
  ];

  $category = event_find_by_id( $reference_data['categories'], $form_values['categoryid'] );
  $subcategory = $form_values['subcategoryid'] !== '' ? event_find_by_id( $reference_data['subcategories'], $form_values['subcategoryid'] ) : null;
  $asset = $form_values['assetid'] !== '' ? event_find_by_id( $reference_data['assets'], $form_values['assetid'] ) : null;

  if ( !$category ) {
    $errors[] = 'Selecteer een geldige categorie.';
  }
  if ( $form_values['subcategoryid'] !== '' && !$subcategory ) {
    $errors[] = 'Selecteer een geldige subcategorie.';
  } elseif ( $subcategory && $category && (int)$subcategory['parent'] !== (int)$category['id'] ) {
    $errors[] = 'De subcategorie hoort niet bij de gekozen categorie.';
  }
  if ( $form_values['assetid'] !== '' && !$asset ) {
    $errors[] = 'Selecteer een geldig object.';
  }
  if ( $form_values['description'] === '' ) {
    $errors[] = 'Omschrijving is verplicht.';
  }
  $errors = array_merge( $errors, attachment_upload_errors() );

  if ( empty( $errors ) ) {
    $event_number = event_generate_number( $con );
    $created_by = (int)$operator_context['id'];
    $category_id = (int)$category['id'];
    $subcategory_id = $subcategory ? (int)$subcategory['id'] : null;
    $asset_id = $asset ? (int)$asset['id'] : null;

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_em_events (eventnumber, categoryid, subcategoryid, assetid, description, createdby)
            VALUES (?,?,?,?,?,?)
        " );
    mysqli_stmt_bind_param( $stmt, "siiisi", $event_number, $category_id, $subcategory_id, $asset_id, $form_values['description'], $created_by );

    if ( mysqli_stmt_execute( $stmt ) ) {
      $event_id = mysqli_insert_id( $con );
      attachment_save_upload( $con, 'event', $event_id, $created_by, 0 );
      task_log_add( $con, 'event', $event_id, 'created', 'Event aangemaakt.', $created_by );
      header( 'Location: edit_event.php?id=' . $event_id );
      exit;
    }

    $errors[] = 'Event opslaan mislukt: ' . mysqli_stmt_error( $stmt );
  }
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <span data-tab-title="<?= htmlspecialchars(t('Nieuw'), ENT_QUOTES) ?>" data-tab-subtitle="<?= htmlspecialchars(t('Event'), ENT_QUOTES) ?>" hidden></span>
  <?php $list_back_url = event_get_list_back_url( 'events.php?view=open' ); require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Event aanmaken</h1>
  </center>
  <?php if ( !empty( $errors ) ): ?>
  <div class="form-wrapper"><div class="form-card"><?php foreach ( $errors as $error ): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div></div><br>
  <?php endif; ?>
  <div class="form-wrapper">
    <div class="form-card form-card-wide">
      <form method="post" enctype="multipart/form-data">
        <div class="form-grid">
          <div class="form-group">
            <label>Categorie</label>
            <select name="categoryid" id="category_id" required>
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
            <label>Object ID</label>
            <select name="assetid">
              <option value="">Selecteer een object</option>
              <?php foreach ( $reference_data['assets'] as $asset ): ?>
              <option value="<?= htmlspecialchars((string)$asset['id']) ?>" <?= (string)$form_values['assetid'] === (string)$asset['id'] ? 'selected' : '' ?>><?= htmlspecialchars($asset['objectid']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Omschrijving</label>
            <textarea name="description" required><?= htmlspecialchars($form_values['description']) ?></textarea>
          </div>
          <?php attachment_render_upload_field(); ?>
          <div class="form-actions">
            <button type="submit" class="btn-primary">Event opslaan</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
const eventSubcategories = <?= json_encode($reference_data['subcategories'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const eventCurrentSubcategoryId = <?= json_encode((string)$form_values['subcategoryid']) ?>;
const eventFormI18n = {
  selectSubcategory: <?= json_encode(t('Selecteer een subcategorie')) ?>
};
function refreshEventSubcategories() {
  const categoryId = document.getElementById('category_id').value;
  const select = document.getElementById('subcategory_id');
  select.innerHTML = `<option value="">${eventFormI18n.selectSubcategory}</option>`;
  eventSubcategories.filter((row) => String(row.parent) === String(categoryId)).forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    if (String(row.id) === String(eventCurrentSubcategoryId)) {
      option.selected = true;
    }
    select.appendChild(option);
  });
}
document.getElementById('category_id').addEventListener('change', refreshEventSubcategories);
refreshEventSubcategories();
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
