<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
require_once( __DIR__ . '/../secure/include/change_helpers.php' );
require_once( __DIR__ . '/../secure/include/mail_helpers.php' );

$person = ssp_require_login( $con );
if ( !ssp_person_has_group_name( $person, 'SSP_InfraShop' ) ) {
  header( 'Location: index.php' );
  exit;
}

$reference_data = ssp_change_reference_data( $con, $person );
$default_operator_id = ssp_get_default_operator_id( $con );
$default_group_id = ssp_default_operator_group_id( $con );
$errors = [];
$infra_group_id = ssp_find_group_id_by_name( $person, 'SSP_InfraShop' );

$templates = array_values( array_filter(
  $reference_data['templates'],
  function( $template ) use ( $infra_group_id ) {
    return ssp_template_has_variables( $template ) && (int)( $template['persongroupid'] ?? 0 ) === $infra_group_id;
  }
) );

$template_id = isset( $_GET['template'] ) ? (int)$_GET['template'] : 0;
$selected_template = $template_id > 0 ? ssp_find_by_id( $templates, $template_id ) : null;
$template_variables = $selected_template ? ssp_extract_template_variables( $selected_template ) : [];
$page_key = 'infra_shop';
$page_title = 'InfraShop';
$page_intro = 'Welkom op de InfraShop. Hier kan je virtuele machines, netwerkwijzigingen en nog veel meer aanvragen.';

$form_values = [
  'variables' => []
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $template_id = (int)( $_POST['templateid'] ?? 0 );
  $selected_template = ssp_find_by_id( $templates, $template_id );
  $template_variables = $selected_template ? ssp_extract_template_variables( $selected_template ) : [];
  $posted_variables = $_POST['template_values'] ?? [];
  if ( is_array( $posted_variables ) ) {
    foreach ( $posted_variables as $key => $value ) {
      $form_values['variables'][ (string)$key ] = trim( (string)$value );
    }
  }

  if ( !$selected_template ) {
    $errors[] = 'Selecteer een geldig InfraShop-formulier.';
  }
  if ( $default_operator_id === 0 ) {
    $errors[] = 'Er is geen behandelaar beschikbaar om deze wijzigingsaanvraag te registreren.';
  }

  foreach ( $template_variables as $variable ) {
    if ( trim( $form_values['variables'][ $variable ] ?? '' ) === '' ) {
      $errors[] = ssp_template_variable_label( $variable ) . ' is verplicht.';
    }
  }
  $errors = array_merge( $errors, ssp_attachment_upload_errors() );

  if ( empty( $errors ) && $selected_template ) {
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
    $asset_id = null;
    $operator_group_id = $default_group_id > 0 ? $default_group_id : null;
    $operator_id = null;
    $coordinator_id = null;
    $status_id = null;
    $template_used = (int)$selected_template['id'];
    $closed = 0;

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_cm_changes (
                changenumber, requesttype, approvalstate, changetype, title, description, customerid, personid, personemail, personphone,
                categoryid, subcategoryid, assetid, operatorgroupid, operatorid, coordinatorid, statusid, template_used, closed, createdby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        " );
    mysqli_stmt_bind_param(
      $stmt,
      "ssssssiissiiiiiiiiii",
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
      $template_used,
      $closed,
      $default_operator_id
    );

    if ( mysqli_stmt_execute( $stmt ) ) {
      $change_id = mysqli_insert_id( $con );
      mysqli_stmt_close( $stmt );
      task_log_add( $con, 'change', $change_id, 'created', 'Wijzigingsaanvraag aangemaakt via InfraShop.', null );

      if ( trim( $commenttext ) !== '' || ssp_attachment_uploaded_file_available() ) {
        $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly) VALUES (?,NULL,?,?,0)" );
        $comment_text = trim( $commenttext ) !== '' ? $commenttext : 'Bijlage toegevoegd.';
        mysqli_stmt_bind_param( $comment_stmt, "iis", $change_id, $person['id'], $comment_text );
        mysqli_stmt_execute( $comment_stmt );
        $comment_id = mysqli_insert_id( $con );
        ssp_attachment_save_upload( $con, 'change', $change_id, (int)$person['id'], 'changecomment', $comment_id );
        mysqli_stmt_close( $comment_stmt );
      }

      if ( $requesttype === 'extended' ) {
        change_copy_template_activities_to_change( $con, (int)$selected_template['id'], $change_id, $default_operator_id );
      }
      mail_process_ticket_created( $con, 'change', $change_id );

      header( 'Location: view_change.php?id=' . $change_id );
      exit;
    }

    $errors[] = 'Wijzigingsaanvraag opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    mysqli_stmt_close( $stmt );
  }
}

ssp_page_title( $page_title );
ssp_render_header( $person, $page_key );

$grouped_templates = [];
foreach ( $templates as $template ) {
  $category = ssp_find_by_id( $reference_data['categories'], (int)$template['categoryid'] );
  $subcategory = !empty( $template['subcategoryid'] ) ? ssp_find_by_id( $reference_data['subcategories'], (int)$template['subcategoryid'] ) : null;
  $category_label = $category['name'] ?? 'Overig';
  $subcategory_label = $subcategory['name'] ?? 'Algemeen';

  if ( !isset( $grouped_templates[ $category_label ] ) ) {
    $grouped_templates[ $category_label ] = [];
  }
  if ( !isset( $grouped_templates[ $category_label ][ $subcategory_label ] ) ) {
    $grouped_templates[ $category_label ][ $subcategory_label ] = [];
  }
  $grouped_templates[ $category_label ][ $subcategory_label ][] = $template;
}
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($page_title) ?></h2>
    <p><?= htmlspecialchars($page_intro) ?></p>
  </div>
  <?php if ( $selected_template ): ?>
  <a class="ssp-ghost-link" href="infra_shop.php">Terug naar formulieren</a>
  <?php endif; ?>
</section>

<?php if ( !empty( $errors ) ): ?>
<div class="ssp-error">
  <?= htmlspecialchars(implode(' ', $errors)) ?>
</div>
<?php endif; ?>

<?php if ( !$selected_template ): ?>
<section class="ssp-panel" style="margin-top: 22px;">
  <?php foreach ( $grouped_templates as $category_label => $subgroups ): ?>
  <div class="ssp-panel-head" style="margin-top: 10px;">
    <h2><?= htmlspecialchars($category_label) ?></h2>
  </div>
  <?php foreach ( $subgroups as $subcategory_label => $items ): ?>
  <div class="ssp-panel-head" style="margin-top: 10px;">
    <h3 style="font-size: 18px;"><?= htmlspecialchars($subcategory_label) ?></h3>
  </div>
  <div class="ssp-quick-actions">
    <?php foreach ( $items as $template ): ?>
    <a class="ssp-quick-link" href="infra_shop.php?template=<?= (int)$template['id'] ?>">
      <i class="fa-solid fa-cart-shopping"></i>
      <span><?= htmlspecialchars($template['name']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php endforeach; ?>
</section>
<?php else: ?>
<section class="ssp-form-grid">
  <article class="ssp-form-card">
    <h3>Jouw gegevens</h3>
    <dl class="ssp-summary-list">
      <div><dt>Klant</dt><dd><?= htmlspecialchars($person['customer_name'] ?? '') ?></dd></div>
      <div><dt>Persoon</dt><dd><?= htmlspecialchars(trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))) ?></dd></div>
      <div><dt>E-mail</dt><dd><?= htmlspecialchars($person['email'] ?? '') ?></dd></div>
      <div><dt>Telefoon</dt><dd><?= htmlspecialchars($person['phone'] ?? '') ?></dd></div>
      <div><dt>Formulier</dt><dd><?= htmlspecialchars($selected_template['name']) ?></dd></div>
    </dl>
  </article>

  <article class="ssp-form-card">
    <form method="post" enctype="multipart/form-data" class="ssp-form-stack">
      <input type="hidden" name="templateid" value="<?= (int)$selected_template['id'] ?>">
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
      <?php ssp_attachment_render_upload_field(); ?>
      <div class="ssp-form-actions">
        <button class="ssp-button" type="submit"><i class="fa-solid fa-floppy-disk"></i> Aanvraag versturen</button>
      </div>
    </form>
  </article>
</section>
<?php endif; ?>
<?php ssp_render_footer(); ?>
