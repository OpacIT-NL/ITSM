<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
require_once( __DIR__ . '/../secure/include/change_helpers.php' );

$person = ssp_require_login( $con );
$reference_data = ssp_change_reference_data( $con, $person );
$default_operator_id = ssp_get_default_operator_id( $con );
$errors = [];

$templates = array_values( array_filter(
  $reference_data['templates'],
  function( $template ) {
    return ssp_template_has_variables( $template );
  }
) );

$form_values = [
  'templateid' => '',
  'assetid' => '',
  'variables' => []
];

$selected_template = null;
$template_variables = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_action = $_POST['form_action'] ?? 'submit';
  $form_values['templateid'] = $_POST['templateid'] ?? '';
  $form_values['assetid'] = $_POST['assetid'] ?? '';
  $posted_variables = $_POST['template_values'] ?? [];
  if ( is_array( $posted_variables ) ) {
    foreach ( $posted_variables as $key => $value ) {
      $form_values['variables'][ (string)$key ] = trim( (string)$value );
    }
  }

  $selected_template = ssp_find_by_id( $templates, (int)$form_values['templateid'] );
  if ( $selected_template ) {
    $template_variables = ssp_extract_template_variables( $selected_template );
  }

  if ( !$selected_template ) {
    $errors[] = 'Selecteer een geldig wijzigingssjabloon.';
  }
  if ( $form_action === 'submit' && $default_operator_id === 0 ) {
    $errors[] = 'Er is geen behandelaar beschikbaar om deze wijzigingsaanvraag te registreren.';
  }

  $asset = null;
  if ( $form_action === 'submit' && $form_values['assetid'] !== '' ) {
    $asset = ssp_find_by_id( $reference_data['assets'], (int)$form_values['assetid'] );
    if ( !$asset ) {
      $errors[] = 'Selecteer een geldig object.';
    }
  }

  if ( $form_action === 'submit' && $selected_template ) {
    foreach ( $template_variables as $variable ) {
      if ( trim( $form_values['variables'][ $variable ] ?? '' ) === '' ) {
        $errors[] = ssp_template_variable_label( $variable ) . ' is verplicht.';
      }
    }
  }

  if ( $form_action === 'submit' && empty( $errors ) && $selected_template ) {
    $change_number = ssp_change_generate_number( $con );
    $requesttype = $selected_template['changerequesttype'] ?: 'simple';
    $approval_state = 'request';
    $changetype = 'standard';
    $title = ssp_apply_template_variables( $selected_template['name'], $form_values['variables'] );
    $description = ssp_apply_template_variables( $selected_template['description'] ?? '', $form_values['variables'] );
    $commenttext = ssp_apply_template_variables( $selected_template['commenttext'] ?? '', $form_values['variables'] );
    $customer_id = (int)$person['customerid'];
    $person_id = (int)$person['id'];
    $person_email = $person['email'] ?? '';
    $person_phone = $person['phone'] ?? '';
    $category_id = (int)$selected_template['categoryid'];
    $subcategory_id = !empty( $selected_template['subcategoryid'] ) ? (int)$selected_template['subcategoryid'] : null;
    $asset_id = $asset ? (int)$asset['id'] : null;
    $operator_group_id = null;
    $operator_id = null;
    $coordinator_id = null;
    $status_id = null;
    $closed = 0;

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
      $requesttype,
      $approval_state,
      $changetype,
      $title,
      $description,
      $customer_id,
      $person_id,
      $person_email,
      $person_phone,
      $category_id,
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

      if ( trim( $commenttext ) !== '' ) {
        $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly) VALUES (?,NULL,?,?,0)" );
        mysqli_stmt_bind_param( $comment_stmt, "iis", $change_id, $person['id'], $commenttext );
        mysqli_stmt_execute( $comment_stmt );
        mysqli_stmt_close( $comment_stmt );
      }

      if ( $requesttype === 'extended' ) {
        change_copy_template_activities_to_change( $con, (int)$selected_template['id'], $change_id, $default_operator_id );
      }

      header( 'Location: view_change.php?id=' . $change_id );
      exit;
    }

    $errors[] = 'Wijzigingsaanvraag opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    mysqli_stmt_close( $stmt );
  }
}

if ( !$selected_template && $form_values['templateid'] !== '' ) {
  $selected_template = ssp_find_by_id( $templates, (int)$form_values['templateid'] );
  if ( $selected_template ) {
    $template_variables = ssp_extract_template_variables( $selected_template );
  }
}

ssp_page_title( 'Wijziging aanvragen' );
ssp_render_header( $person, 'new_change' );
?>
<section class="ssp-page-head">
  <div>
    <h2>Wijziging aanvragen</h2>
    <p>Kies een sjabloon. Zodra een sjabloon `%variabelen%` bevat, verandert het automatisch in een selfserviceformulier.</p>
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
      <input type="hidden" name="form_action" id="form_action" value="submit">
      <div class="ssp-field">
        <label for="templateid">Sjabloon</label>
        <select id="templateid" name="templateid" required>
          <option value="">Selecteer sjabloon</option>
          <?php foreach ( $templates as $template ): ?>
          <option value="<?= (int)$template['id'] ?>"<?= (string)$form_values['templateid'] === (string)$template['id'] ? ' selected' : '' ?>>
            <?= htmlspecialchars($template['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ( $selected_template ): ?>
      <div class="ssp-field">
        <label>Sjabloon details</label>
        <div class="ssp-summary-list">
          <div><dt>Categorie</dt><dd><?php
            $category = ssp_find_by_id( $reference_data['categories'], (int)$selected_template['categoryid'] );
            echo htmlspecialchars( $category['name'] ?? '' );
          ?></dd></div>
          <div><dt>Subcategorie</dt><dd><?php
            $subcategory = !empty( $selected_template['subcategoryid'] ) ? ssp_find_by_id( $reference_data['subcategories'], (int)$selected_template['subcategoryid'] ) : null;
            echo htmlspecialchars( $subcategory['name'] ?? '' );
          ?></dd></div>
          <div><dt>Wijzigingssoort</dt><dd><?= htmlspecialchars($selected_template['changerequesttype'] === 'extended' ? 'Uitgebreide Wijziging' : 'Eenvoudige Wijziging') ?></dd></div>
        </div>
      </div>

      <div class="ssp-field">
        <label for="assetid">Object</label>
        <select id="assetid" name="assetid">
          <option value="">Geen object</option>
          <?php foreach ( $reference_data['assets'] as $asset ): ?>
          <option value="<?= (int)$asset['id'] ?>"<?= (string)$form_values['assetid'] === (string)$asset['id'] ? ' selected' : '' ?>>
            <?= htmlspecialchars($asset['objectid'] . (($asset['typename'] ?? '') !== '' ? ' - ' . $asset['typename'] : '')) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php foreach ( $template_variables as $variable ): ?>
      <div class="ssp-field">
        <label for="template_<?= htmlspecialchars($variable) ?>"><?= htmlspecialchars(ssp_template_variable_label($variable)) ?></label>
        <input
          id="template_<?= htmlspecialchars($variable) ?>"
          name="template_values[<?= htmlspecialchars($variable) ?>]"
          type="text"
          value="<?= htmlspecialchars($form_values['variables'][$variable] ?? '') ?>"
          required
        >
      </div>
      <?php endforeach; ?>

      <div class="ssp-field">
        <label>Voorbeeld omschrijving</label>
        <textarea readonly><?= htmlspecialchars(ssp_apply_template_variables($selected_template['description'] ?? '', $form_values['variables'])) ?></textarea>
      </div>

      <?php if ( trim( (string)($selected_template['commenttext'] ?? '') ) !== '' ): ?>
      <div class="ssp-field">
        <label>Voorbeeld commentaar</label>
        <textarea readonly><?= htmlspecialchars(ssp_apply_template_variables($selected_template['commenttext'] ?? '', $form_values['variables'])) ?></textarea>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <div class="ssp-form-actions">
        <button class="ssp-button" type="submit"><i class="fa-solid fa-floppy-disk"></i> Wijzigingsaanvraag opslaan</button>
      </div>
    </form>
  </article>
</section>

<script>
const templateSelect = document.getElementById('templateid');
const formActionField = document.getElementById('form_action');
if (templateSelect) {
  templateSelect.addEventListener('change', () => {
    if (formActionField) {
      formActionField.value = 'load_template';
    }
    templateSelect.form.submit();
  });
}
</script>
<?php ssp_render_footer(); ?>
