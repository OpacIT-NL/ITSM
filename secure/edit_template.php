<?php
session_start();
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );

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
$groups = mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_operatorgroups ORDER BY groupname ASC" )->fetch_all( MYSQLI_ASSOC );
$operators_rows = mysqli_query( $con, "SELECT id, firstname, lastname FROM itsm_ob_operators ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC );
$statuses = mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'CHANGE' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
$op_links = mysqli_query( $con, "SELECT groupid, operatorid FROM itsm_ob_opgrouplinks" )->fetch_all( MYSQLI_ASSOC );
$errors = [];

$form_values = [
  'name' => $template['name'],
  'type' => $template['type'],
  'changerequesttype' => $template['changerequesttype'] ?? '',
  'categoryid' => (string)$template['categoryid'],
  'subcategoryid' => (string)$template['subcategoryid'],
  'description' => $template['description'],
  'commenttext' => $template['commenttext']
];

$activity_values = [
  'title' => '',
  'description' => '',
  'operatorgroupid' => '',
  'operatorid' => '',
  'statusid' => ''
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  if ( isset( $_POST['manage_activities'] ) ) {
    $activity_values = [
      'title' => trim( $_POST['new_activity_title'] ?? '' ),
      'description' => trim( $_POST['new_activity_description'] ?? '' ),
      'operatorgroupid' => $_POST['new_activity_operatorgroupid'] ?? '',
      'operatorid' => $_POST['new_activity_operatorid'] ?? '',
      'statusid' => $_POST['new_activity_statusid'] ?? ''
    ];

    if ( $form_values['type'] !== 'CHANGE' || $form_values['changerequesttype'] !== 'extended' ) {
      $errors[] = 'Sjabloonactiviteiten zijn alleen beschikbaar voor uitgebreide wijzigingen.';
    } else {
      $activity_validation = change_validate_activity_form(
        [
          'title' => $activity_values['title'],
          'operatorgroupid' => (int)$activity_values['operatorgroupid'],
          'operatorid' => (int)$activity_values['operatorid'],
          'statusid' => (int)$activity_values['statusid']
        ],
        [
          'groups' => $groups,
          'operators' => $operators_rows,
          'statuses' => $statuses,
          'op_links' => $op_links
        ]
      );

      $errors = array_merge( $errors, $activity_validation['errors'] );

      if ( empty( $errors ) ) {
        $activity_group_id = $activity_validation['group'] ? (int)$activity_validation['group']['id'] : null;
        $activity_operator_id = $activity_validation['operator'] ? (int)$activity_validation['operator']['id'] : null;
        $activity_status_id = (int)$activity_validation['status']['id'];

        $activity_stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_core_templateactivities (
                templateid, title, description, operatorgroupid, operatorid, statusid
            ) VALUES (?,?,?,?,?,?)
        " );
        mysqli_stmt_bind_param(
          $activity_stmt,
          "issiii",
          $template_id,
          $activity_values['title'],
          $activity_values['description'],
          $activity_group_id,
          $activity_operator_id,
          $activity_status_id
        );
        mysqli_stmt_execute( $activity_stmt );
        mysqli_stmt_close( $activity_stmt );

        header( 'Location: edit_template.php?id=' . $template_id );
        exit;
      }
    }
  } else {
    $form_values = [
      'name' => trim( $_POST['name'] ?? '' ),
      'type' => $_POST['type'] ?? 'INCIDENT',
      'changerequesttype' => $_POST['changerequesttype'] ?? '',
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
      $update_stmt = mysqli_prepare( $con, "
          UPDATE itsm_core_templates
          SET name = ?, type = ?, changerequesttype = ?, categoryid = ?, subcategoryid = ?, description = ?, commenttext = ?
          WHERE id = ?
      " );
      mysqli_stmt_bind_param(
        $update_stmt,
        "sssiissi",
        $form_values['name'],
        $form_values['type'],
        $form_values['changerequesttype'],
        $category_id,
        $subcategory_id,
        $form_values['description'],
        $form_values['commenttext'],
        $template_id
      );
      mysqli_stmt_execute( $update_stmt );
      mysqli_stmt_close( $update_stmt );

      header( 'Location: set-templates.php?type=' . urlencode( $form_values['type'] ) );
      exit;
    }
  }
}

$template_activities = [];
if ( $form_values['type'] === 'CHANGE' && $form_values['changerequesttype'] === 'extended' ) {
  $template_activities_result = mysqli_query( $con, "
      SELECT a.*, g.groupname, CONCAT(o.lastname, ', ', o.firstname) AS operator_name, s.name AS status_name
      FROM itsm_core_templateactivities a
      LEFT JOIN itsm_ob_operatorgroups g ON a.operatorgroupid = g.id
      LEFT JOIN itsm_ob_operators o ON a.operatorid = o.id
      LEFT JOIN itsm_core_status s ON a.statusid = s.id
      WHERE a.templateid = " . $template_id . "
      ORDER BY a.id ASC
  " );
  while ( $row = mysqli_fetch_assoc( $template_activities_result ) ) {
    $template_activities[] = $row;
  }
}

$categories_json = json_encode( $categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
$subcategories_json = json_encode( $subcategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <a href='javascript:history.back(1)'>Ga terug</a>
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
        <div class="form-group" id="template_requesttype_group" style="display: none;">
          <label>Wijzigingssoort</label>
          <select name="changerequesttype" id="template_changerequesttype">
            <option value="">Selecteer wijzigingssoort</option>
            <option value="simple" <?= $form_values['changerequesttype'] === 'simple' ? 'selected' : '' ?>>Eenvoudige Wijziging</option>
            <option value="extended" <?= $form_values['changerequesttype'] === 'extended' ? 'selected' : '' ?>>Uitgebreide Wijziging</option>
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

  <?php if ( $form_values['type'] === 'CHANGE' && $form_values['changerequesttype'] === 'extended' ): ?>
  <br>
  <div class="content" style="padding: 0;">
    <h2>Sjabloonactiviteiten</h2>
    <div class="results">
      <table border="0" class="results" style="width: 100%;">
        <thead>
          <tr>
            <th style="text-align: start;">Titel</th>
            <th style="text-align: start;">Groep</th>
            <th style="text-align: start;">Behandelaar</th>
            <th style="text-align: start;">Status</th>
            <th style="text-align: start;">Actie</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( empty( $template_activities ) ): ?>
          <tr>
            <td colspan="5">Nog geen sjabloonactiviteiten.</td>
          </tr>
          <?php else: ?>
          <?php foreach ( $template_activities as $row ): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['groupname'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['operator_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['status_name'] ?? '') ?></td>
            <td><a class="btn" href="edit_template_activity.php?id=<?= htmlspecialchars((string)$row['id']) ?>">Open activiteit</a></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <br>
    <div class="form-wrapper">
      <form method="post" class="form-card">
        <input type="hidden" name="manage_activities" value="1">
        <div class="form-grid">
          <h3>Nieuwe sjabloonactiviteit</h3>
          <div class="form-group">
            <label>Titel</label>
            <input type="text" name="new_activity_title" value="<?= htmlspecialchars($activity_values['title']) ?>">
          </div>
          <div class="form-group">
            <label>Omschrijving</label>
            <textarea name="new_activity_description"><?= htmlspecialchars($activity_values['description']) ?></textarea>
          </div>
          <div class="form-group">
            <label>Behandelaarsgroep</label>
            <select name="new_activity_operatorgroupid">
              <option value="">Geen groep</option>
              <?php foreach ( $groups as $group ): ?>
              <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= (string)$activity_values['operatorgroupid'] === (string)$group['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($group['groupname']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Behandelaar</label>
            <select name="new_activity_operatorid">
              <option value="">Geen behandelaar</option>
              <?php foreach ( $operators_rows as $operator_row ): ?>
              <option value="<?= htmlspecialchars((string)$operator_row['id']) ?>" <?= (string)$activity_values['operatorid'] === (string)$operator_row['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($operator_row['lastname'] . ', ' . $operator_row['firstname']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="new_activity_statusid">
              <option value="">Selecteer een status</option>
              <?php foreach ( $statuses as $status ): ?>
              <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$activity_values['statusid'] === (string)$status['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($status['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary">Activiteit toevoegen</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
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
