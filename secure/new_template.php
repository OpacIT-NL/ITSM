<?php
session_start();
require_once( __DIR__ . '/../my.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  session_unset();
  session_destroy();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION['name'];

$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: index.php" );
  exit();
}

$categories = mysqli_query( $con, "SELECT id, name, type FROM itsm_core_category WHERE type IN ('INCIDENT', 'CHANGE') ORDER BY type ASC, name ASC" )->fetch_all( MYSQLI_ASSOC );
$subcategories = mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
$persongroups = mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_persongroups ORDER BY groupname ASC" )->fetch_all( MYSQLI_ASSOC );
$errors = [];

$form_values = [
  'name' => '',
  'type' => 'INCIDENT',
  'changerequesttype' => '',
  'persongroupid' => '',
  'categoryid' => '',
  'subcategoryid' => '',
  'description' => '',
  'commenttext' => ''
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'name' => trim( $_POST['name'] ?? '' ),
    'type' => $_POST['type'] ?? 'INCIDENT',
    'changerequesttype' => $_POST['changerequesttype'] ?? '',
    'persongroupid' => $_POST['persongroupid'] ?? '',
    'categoryid' => $_POST['categoryid'] ?? '',
    'subcategoryid' => $_POST['subcategoryid'] ?? '',
    'description' => trim( $_POST['description'] ?? '' ),
    'commenttext' => trim( $_POST['commenttext'] ?? '' )
  ];

  $category = null;
  foreach ( $categories as $row ) {
    if ( (int)$row['id'] === (int)$form_values['categoryid'] ) {
      $category = $row;
      break;
    }
  }
  $subcategory = null;
  if ( $form_values['subcategoryid'] !== '' ) {
    foreach ( $subcategories as $row ) {
      if ( (int)$row['id'] === (int)$form_values['subcategoryid'] ) {
        $subcategory = $row;
        break;
      }
    }
  }
  $persongroup = null;
  if ( $form_values['persongroupid'] !== '' ) {
    foreach ( $persongroups as $row ) {
      if ( (int)$row['id'] === (int)$form_values['persongroupid'] ) {
        $persongroup = $row;
        break;
      }
    }
    if ( !$persongroup ) {
      $errors[] = 'Selecteer een geldige persoonsgroep.';
    }
  }

  if ( $form_values['name'] === '' ) {
    $errors[] = 'Naam is verplicht.';
  }
  if ( !in_array( $form_values['type'], [ 'INCIDENT', 'CHANGE' ], true ) ) {
    $errors[] = 'Type is ongeldig.';
  }
  if ( $form_values['type'] === 'CHANGE' && !in_array( $form_values['changerequesttype'], [ 'simple', 'extended' ], true ) ) {
    $errors[] = 'Selecteer een geldige wijzigingssoort.';
  }
  if ( $form_values['type'] !== 'CHANGE' ) {
    $form_values['changerequesttype'] = '';
  }
  if ( !$category ) {
    $errors[] = 'Selecteer een geldige categorie.';
  } elseif ( $category['type'] !== $form_values['type'] ) {
    $errors[] = 'De categorie hoort niet bij het gekozen type.';
  }
  if ( $subcategory && $category && (int)$subcategory['parent'] !== (int)$category['id'] ) {
    $errors[] = 'De subcategorie hoort niet bij de gekozen categorie.';
  }

  if ( empty( $errors ) ) {
    $category_id = (int)$category['id'];
    $subcategory_id = $subcategory ? (int)$subcategory['id'] : null;
    $persongroup_id = $persongroup ? (int)$persongroup['id'] : null;
    $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_core_templates (name, type, changerequesttype, persongroupid, categoryid, subcategoryid, description, commenttext)
        VALUES (?,?,?,?,?,?,?,?)
    " );
    mysqli_stmt_bind_param(
      $stmt,
      "sssiiiss",
      $form_values['name'],
      $form_values['type'],
      $form_values['changerequesttype'],
      $persongroup_id,
      $category_id,
      $subcategory_id,
      $form_values['description'],
      $form_values['commenttext']
    );
    mysqli_stmt_execute( $stmt );
    header( 'Location: set-templates.php?type=' . urlencode( $form_values['type'] ) );
    exit;
  }
}

$categories_json = json_encode( $categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$subcategories_json = json_encode( $subcategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $list_back_url = 'set-templates.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Nieuw sjabloon</h1>
  </center>
  <?php foreach ( $errors as $error ): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>
  <div class="form-wrapper">
    <form method="post" class="form-card">
      <div class="form-grid">
        <div class="form-group">
          <label>Naam</label>
          <input type="text" name="name" value="<?= htmlspecialchars($form_values['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type" id="template_type" required>
            <option value="INCIDENT" <?= $form_values['type'] === 'INCIDENT' ? 'selected' : '' ?>>INCIDENT</option>
            <option value="CHANGE" <?= $form_values['type'] === 'CHANGE' ? 'selected' : '' ?>>CHANGE</option>
          </select>
        </div>
        <div class="form-group" id="template_requesttype_group" style="display: none;">
          <label>Wijzigingssoort</label>
          <select name="changerequesttype" id="template_changerequesttype">
            <option value="">Selecteer wijzigingssoort</option>
            <option value="simple" <?= $form_values['changerequesttype'] === 'simple' ? 'selected' : '' ?>>Eenvoudige Wijziging</option>
            <option value="extended" <?= $form_values['changerequesttype'] === 'extended' ? 'selected' : '' ?>>Uitgebreide Wijziging</option>
          </select>
        </div>
        <div class="form-group">
          <label>Persoonsgroep autorisatie</label>
          <select name="persongroupid">
            <option value="">Geen beperking</option>
            <?php foreach ( $persongroups as $persongroup ): ?>
            <option value="<?= htmlspecialchars((string)$persongroup['id']) ?>" <?= (string)$form_values['persongroupid'] === (string)$persongroup['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($persongroup['groupname']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Categorie</label>
          <select name="categoryid" id="template_category" required></select>
        </div>
        <div class="form-group">
          <label>Subcategorie</label>
          <select name="subcategoryid" id="template_subcategory">
            <option value="">Geen subcategorie</option>
          </select>
        </div>
        <div class="form-group">
          <label>Omschrijving</label>
          <textarea name="description"><?= htmlspecialchars($form_values['description']) ?></textarea>
        </div>
        <div class="form-group">
          <label>Commentaar</label>
          <textarea name="commenttext"><?= htmlspecialchars($form_values['commenttext']) ?></textarea>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Opslaan</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
const categories = <?= $categories_json ?>;
const subcategories = <?= $subcategories_json ?>;
const selectedCategoryId = <?= json_encode((string)$form_values['categoryid']) ?>;
const selectedSubcategoryId = <?= json_encode((string)$form_values['subcategoryid']) ?>;
const templateFormI18n = {
  selectCategory: <?= json_encode(t('Selecteer categorie')) ?>,
  noSubcategory: <?= json_encode(t('Geen subcategorie')) ?>
};

function refreshTemplateCategories() {
  const type = document.getElementById('template_type').value;
  const categorySelect = document.getElementById('template_category');
  const filtered = categories.filter((row) => row.type === type);
  categorySelect.innerHTML = `<option value="">${templateFormI18n.selectCategory}</option>`;
  filtered.forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    if (String(row.id) === selectedCategoryId) {
      option.selected = true;
    }
    categorySelect.appendChild(option);
  });
}

function refreshTemplateRequestType() {
  const type = document.getElementById('template_type').value;
  const group = document.getElementById('template_requesttype_group');
  const select = document.getElementById('template_changerequesttype');
  const isChange = type === 'CHANGE';
  group.style.display = isChange ? 'flex' : 'none';
  if (!isChange) {
    select.value = '';
  }
}

function refreshTemplateSubcategories(resetSelection) {
  const categoryId = document.getElementById('template_category').value;
  const subcategorySelect = document.getElementById('template_subcategory');
  const filtered = subcategories.filter((row) => String(row.parent) === String(categoryId));
  subcategorySelect.innerHTML = `<option value="">${templateFormI18n.noSubcategory}</option>`;
  filtered.forEach((row) => {
    const option = document.createElement('option');
    option.value = String(row.id);
    option.textContent = row.name;
    if (!resetSelection && String(row.id) === selectedSubcategoryId) {
      option.selected = true;
    }
    subcategorySelect.appendChild(option);
  });
}

document.getElementById('template_type').addEventListener('change', () => {
  refreshTemplateRequestType();
  refreshTemplateCategories();
  refreshTemplateSubcategories(true);
});
document.getElementById('template_category').addEventListener('change', () => {
  refreshTemplateSubcategories(true);
});

refreshTemplateRequestType();
refreshTemplateCategories();
refreshTemplateSubcategories(false);
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
