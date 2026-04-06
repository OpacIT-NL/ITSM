<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$reference_data = ssp_change_reference_data( $con, $person );
$default_operator_id = ssp_get_default_operator_id( $con );
$errors = [];

$form_values = [
  'requesttype' => 'simple',
  'changetype' => 'standard',
  'templateid' => '',
  'title' => '',
  'description' => '',
  'commenttext' => '',
  'categoryid' => '',
  'subcategoryid' => '',
  'assetid' => ''
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'requesttype' => $_POST['requesttype'] ?? 'simple',
    'changetype' => $_POST['changetype'] ?? 'standard',
    'templateid' => $_POST['templateid'] ?? '',
    'title' => trim( $_POST['title'] ?? '' ),
    'description' => trim( $_POST['description'] ?? '' ),
    'commenttext' => trim( $_POST['commenttext'] ?? '' ),
    'categoryid' => $_POST['categoryid'] ?? '',
    'subcategoryid' => $_POST['subcategoryid'] ?? '',
    'assetid' => $_POST['assetid'] ?? ''
  ];

  $category = ssp_find_by_id( $reference_data['categories'], (int)$form_values['categoryid'] );
  $subcategory = $form_values['subcategoryid'] !== '' ? ssp_find_by_id( $reference_data['subcategories'], (int)$form_values['subcategoryid'] ) : null;
  $asset = $form_values['assetid'] !== '' ? ssp_find_by_id( $reference_data['assets'], (int)$form_values['assetid'] ) : null;
  $template = $form_values['templateid'] !== '' ? ssp_find_by_id( $reference_data['templates'], (int)$form_values['templateid'] ) : null;

  if ( isset( $_POST['apply_template'] ) && $template ) {
    $form_values['title'] = $template['name'];
    $form_values['description'] = $template['description'] ?? '';
    $form_values['commenttext'] = $template['commenttext'] ?? '';
    if ( !empty( $template['changerequesttype'] ) ) {
      $form_values['requesttype'] = $template['changerequesttype'];
    }
  } else {
    if ( !in_array( $form_values['requesttype'], [ 'simple', 'extended' ], true ) ) {
      $errors[] = 'Selecteer een geldige wijzigingssoort.';
    }
    if ( !in_array( $form_values['changetype'], [ 'standard', 'normal', 'normalcab', 'emergency' ], true ) ) {
      $errors[] = 'Selecteer een geldig wijzigingstype.';
    }
    if ( $form_values['requesttype'] !== 'extended' && $form_values['changetype'] === 'normalcab' ) {
      $errors[] = 'Normale CAB Wijziging is alleen mogelijk bij een uitgebreide wijziging.';
    }
    if ( $form_values['title'] === '' ) {
      $errors[] = 'Titel is verplicht.';
    }
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
    if ( $default_operator_id === 0 ) {
      $errors[] = 'Er is geen behandelaar beschikbaar om deze wijzigingsaanvraag te registreren.';
    }
    if ( $template ) {
      if ( (int)$template['categoryid'] !== (int)$category['id'] ) {
        $errors[] = 'Het gekozen sjabloon hoort niet bij de gekozen categorie.';
      }
      if ( $subcategory && $template['subcategoryid'] !== null && (int)$template['subcategoryid'] !== (int)$subcategory['id'] ) {
        $errors[] = 'Het gekozen sjabloon hoort niet bij de gekozen subcategorie.';
      }
    }

    if ( empty( $errors ) ) {
      $change_number = ssp_change_generate_number( $con );
      $customer_id = (int)$person['customerid'];
      $person_id = (int)$person['id'];
      $person_email = $person['email'] ?? '';
      $person_phone = $person['phone'] ?? '';
      $subcategory_id = $subcategory ? (int)$subcategory['id'] : null;
      $asset_id = $asset ? (int)$asset['id'] : null;
      $operator_group_id = null;
      $operator_id = null;
      $coordinator_id = null;
      $status_id = null;
      $closed = 0;
      $approval_state = 'request';

      $stmt = mysqli_prepare( $con, "
              INSERT INTO itsm_cm_changes (
                  changenumber, requesttype, approvalstate, changetype, title, description, customerid, personid, personemail, personphone,
                  categoryid, subcategoryid, assetid, operatorgroupid, operatorid, coordinatorid, statusid, closed, createdby
              ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
          " );
      mysqli_stmt_bind_param(
        $stmt,
        "ssssssiissiiiiiiiii",
        $change_number,
        $form_values['requesttype'],
        $approval_state,
        $form_values['changetype'],
        $form_values['title'],
        $form_values['description'],
        $customer_id,
        $person_id,
        $person_email,
        $person_phone,
        $category['id'],
        $subcategory_id,
        $asset_id,
        $operator_group_id,
        $operator_id,
        $coordinator_id,
        $status_id,
        $closed,
        $default_operator_id
      );

      if ( mysqli_stmt_execute( $stmt ) ) {
        $change_id = mysqli_insert_id( $con );
        mysqli_stmt_close( $stmt );

        if ( $form_values['commenttext'] !== '' ) {
          $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly) VALUES (?,NULL,?,?,0)" );
          mysqli_stmt_bind_param( $comment_stmt, "iis", $change_id, $person['id'], $form_values['commenttext'] );
          mysqli_stmt_execute( $comment_stmt );
          mysqli_stmt_close( $comment_stmt );
        }

        header( 'Location: view_change.php?id=' . $change_id );
        exit;
      }

      $errors[] = 'Wijzigingsaanvraag opslaan mislukt: ' . mysqli_stmt_error( $stmt );
      mysqli_stmt_close( $stmt );
    }
  }
}

ssp_page_title( 'Wijziging aanvragen' );
ssp_render_header( $person, 'new_change' );
?>
<section class="ssp-page-head">
  <div>
    <h2>Wijziging aanvragen</h2>
    <p>Dien een nieuwe wijzigingsaanvraag in. Sjablonen helpen je om terugkerende aanvragen sneller op te bouwen.</p>
  </div>
</section>

<?php if ( !empty( $errors ) ): ?>
<div class="ssp-error">
  <?= htmlspecialchars(implode(' ', $errors)) ?>
</div>
<?php endif; ?>

<section class="ssp-form-grid">
  <article class="ssp-form-card">
    <h3>Jouw gegevens</h3>
    <dl class="ssp-summary-list">
      <div><dt>Klant</dt><dd><?= htmlspecialchars($person['customer_name'] ?? '') ?></dd></div>
      <div><dt>Persoon</dt><dd><?= htmlspecialchars(trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))) ?></dd></div>
      <div><dt>E-mail</dt><dd><?= htmlspecialchars($person['email'] ?? '') ?></dd></div>
      <div><dt>Telefoon</dt><dd><?= htmlspecialchars($person['phone'] ?? '') ?></dd></div>
    </dl>
  </article>

  <article class="ssp-form-card">
    <form method="post" class="ssp-form-stack">
      <div class="ssp-field">
        <label>Wijzigingssoort</label>
        <div class="ssp-form-actions" style="gap:10px;">
          <label class="ssp-checkbox"><input type="radio" name="requesttype" value="simple"<?= $form_values['requesttype'] === 'simple' ? ' checked' : '' ?>> Eenvoudige Wijziging</label>
          <label class="ssp-checkbox"><input type="radio" name="requesttype" value="extended"<?= $form_values['requesttype'] === 'extended' ? ' checked' : '' ?>> Uitgebreide Wijziging</label>
        </div>
      </div>
      <div class="ssp-field">
        <label for="changetype">Type</label>
        <select id="changetype" name="changetype">
          <option value="standard"<?= $form_values['changetype'] === 'standard' ? ' selected' : '' ?>>Standaard Wijziging</option>
          <option value="normal"<?= $form_values['changetype'] === 'normal' ? ' selected' : '' ?>>Normale Wijziging</option>
          <option value="normalcab"<?= $form_values['changetype'] === 'normalcab' ? ' selected' : '' ?>>Normale CAB Wijziging</option>
          <option value="emergency"<?= $form_values['changetype'] === 'emergency' ? ' selected' : '' ?>>Noodwijziging</option>
        </select>
      </div>
      <div class="ssp-field">
        <label for="categoryid">Categorie</label>
        <select id="categoryid" name="categoryid" required>
          <option value="">Selecteer categorie</option>
          <?php foreach ( $reference_data['categories'] as $category ): ?>
          <option value="<?= (int)$category['id'] ?>"<?= (string)$form_values['categoryid'] === (string)$category['id'] ? ' selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ssp-field">
        <label for="subcategoryid">Subcategorie</label>
        <select id="subcategoryid" name="subcategoryid">
          <option value="">Geen subcategorie</option>
          <?php foreach ( $reference_data['subcategories'] as $subcategory ): ?>
          <option value="<?= (int)$subcategory['id'] ?>" data-parent="<?= (int)$subcategory['parent'] ?>"<?= (string)$form_values['subcategoryid'] === (string)$subcategory['id'] ? ' selected' : '' ?>><?= htmlspecialchars($subcategory['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ssp-field">
        <label for="templateid">Sjabloon</label>
        <select id="templateid" name="templateid">
          <option value="">Geen sjabloon</option>
          <?php foreach ( $reference_data['templates'] as $template ): ?>
          <option value="<?= (int)$template['id'] ?>" data-category="<?= (int)$template['categoryid'] ?>" data-subcategory="<?= htmlspecialchars((string)($template['subcategoryid'] ?? '')) ?>" data-requesttype="<?= htmlspecialchars($template['changerequesttype'] ?? '') ?>"<?= (string)$form_values['templateid'] === (string)$template['id'] ? ' selected' : '' ?>><?= htmlspecialchars($template['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ssp-form-actions">
        <button class="ssp-button-secondary" type="submit" name="apply_template" value="1"><i class="fa-solid fa-wand-magic-sparkles"></i> Sjabloon toepassen</button>
      </div>
      <div class="ssp-field">
        <label for="title">Korte titel</label>
        <input id="title" name="title" type="text" value="<?= htmlspecialchars($form_values['title']) ?>" required>
      </div>
      <div class="ssp-field">
        <label for="assetid">Object</label>
        <select id="assetid" name="assetid">
          <option value="">Geen object</option>
          <?php foreach ( $reference_data['assets'] as $asset ): ?>
          <option value="<?= (int)$asset['id'] ?>"<?= (string)$form_values['assetid'] === (string)$asset['id'] ? ' selected' : '' ?>><?= htmlspecialchars($asset['objectid'] . (($asset['typename'] ?? '') !== '' ? ' - ' . $asset['typename'] : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ssp-field">
        <label for="description">Omschrijving</label>
        <textarea id="description" name="description"><?= htmlspecialchars($form_values['description']) ?></textarea>
      </div>
      <div class="ssp-field">
        <label for="commenttext">Aanvullend commentaar</label>
        <textarea id="commenttext" name="commenttext"><?= htmlspecialchars($form_values['commenttext']) ?></textarea>
      </div>
      <div class="ssp-form-actions">
        <button class="ssp-button" type="submit"><i class="fa-solid fa-floppy-disk"></i> Wijzigingsaanvraag opslaan</button>
      </div>
    </form>
  </article>
</section>

<script>
const changeCategory = document.getElementById('categoryid');
const changeSubcategory = document.getElementById('subcategoryid');
const changeTemplate = document.getElementById('templateid');
const requestTypeRadios = document.querySelectorAll('input[name="requesttype"]');
const changeType = document.getElementById('changetype');

function currentRequestType() {
  const checked = document.querySelector('input[name="requesttype"]:checked');
  return checked ? checked.value : 'simple';
}

function filterChangeSubcategories() {
  const categoryId = changeCategory.value;
  Array.from(changeSubcategory.options).forEach((option) => {
    if (!option.dataset.parent) {
      option.hidden = false;
      return;
    }
    option.hidden = categoryId !== '' && option.dataset.parent !== categoryId;
    if (option.hidden && option.selected) {
      changeSubcategory.value = '';
    }
  });
}

function filterTemplates() {
  const categoryId = changeCategory.value;
  const subcategoryId = changeSubcategory.value;
  const requestType = currentRequestType();
  Array.from(changeTemplate.options).forEach((option) => {
    if (!option.dataset.category) {
      option.hidden = false;
      return;
    }
    const categoryMatches = categoryId !== '' && option.dataset.category === categoryId;
    const subcategoryMatches = !option.dataset.subcategory || option.dataset.subcategory === '' || option.dataset.subcategory === subcategoryId;
    const requestMatches = !option.dataset.requesttype || option.dataset.requesttype === requestType;
    option.hidden = !(categoryMatches && subcategoryMatches && requestMatches);
    if (option.hidden && option.selected) {
      changeTemplate.value = '';
    }
  });
}

function syncChangeTypeAvailability() {
  if (currentRequestType() !== 'extended' && changeType.value === 'normalcab') {
    changeType.value = 'normal';
  }
  Array.from(changeType.options).forEach((option) => {
    option.hidden = option.value === 'normalcab' && currentRequestType() !== 'extended';
  });
}

changeCategory.addEventListener('change', () => {
  filterChangeSubcategories();
  filterTemplates();
});
changeSubcategory.addEventListener('change', filterTemplates);
requestTypeRadios.forEach((radio) => radio.addEventListener('change', () => {
  syncChangeTypeAvailability();
  filterTemplates();
}));

filterChangeSubcategories();
syncChangeTypeAvailability();
filterTemplates();
</script>
<?php ssp_render_footer(); ?>
