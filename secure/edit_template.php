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
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  die( 'Invalid ID' );
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

$template_id = (int)$_GET['id'];
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_core_templates WHERE id = ?" );
mysqli_stmt_bind_param( $stmt, "i", $template_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$template = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );
if ( !$template ) {
  die( 'Sjabloon niet gevonden' );
}

$categories = mysqli_query( $con, "SELECT id, name, type FROM itsm_core_category WHERE type IN ('INCIDENT', 'CHANGE') ORDER BY type ASC, name ASC" )->fetch_all( MYSQLI_ASSOC );
$subcategories = mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
$errors = [];

$form_values = [
  'name' => $template['name'],
  'type' => $template['type'],
  'categoryid' => (string)$template['categoryid'],
  'subcategoryid' => (string)$template['subcategoryid'],
  'description' => $template['description'],
  'commenttext' => $template['commenttext']
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'name' => trim( $_POST['name'] ?? '' ),
    'type' => $_POST['type'] ?? 'INCIDENT',
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

  if ( $form_values['name'] === '' ) {
    $errors[] = 'Naam is verplicht.';
  }
  if ( !in_array( $form_values['type'], [ 'INCIDENT', 'CHANGE' ], true ) ) {
    $errors[] = 'Type is ongeldig.';
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
    $update_stmt = mysqli_prepare( $con, "
        UPDATE itsm_core_templates
        SET name = ?, type = ?, categoryid = ?, subcategoryid = ?, description = ?, commenttext = ?
        WHERE id = ?
    " );
    mysqli_stmt_bind_param(
      $update_stmt,
      "ssiissi",
      $form_values['name'],
      $form_values['type'],
      $category_id,
      $subcategory_id,
      $form_values['description'],
      $form_values['commenttext'],
      $template_id
    );
    mysqli_stmt_execute( $update_stmt );
    header( 'Location: set-templates.php?type=' . urlencode( $form_values['type'] ) );
    exit;
  }
}

$categories_json = json_encode( $categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$subcategories_json = json_encode( $subcategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <a href="set-templates.php">Ga terug</a>
  <center>
    <h1>Sjabloon bewerken</h1>
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

function refreshTemplateCategories() {
  const type = document.getElementById('template_type').value;
  const categorySelect = document.getElementById('template_category');
  const filtered = categories.filter((row) => row.type === type);
  categorySelect.innerHTML = '<option value="">Selecteer categorie</option>';
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

function refreshTemplateSubcategories(resetSelection) {
  const categoryId = document.getElementById('template_category').value;
  const subcategorySelect = document.getElementById('template_subcategory');
  const filtered = subcategories.filter((row) => String(row.parent) === String(categoryId));
  subcategorySelect.innerHTML = '<option value="">Geen subcategorie</option>';
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
  refreshTemplateCategories();
  refreshTemplateSubcategories(true);
});
document.getElementById('template_category').addEventListener('change', () => {
  refreshTemplateSubcategories(true);
});

refreshTemplateCategories();
refreshTemplateSubcategories(false);
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
