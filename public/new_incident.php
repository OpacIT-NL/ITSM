<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
require_once( __DIR__ . '/../secure/include/mail_helpers.php' );

$person = ssp_require_login( $con );
$reference_data = ssp_incident_reference_data( $con, $person );
$default_status_id = ssp_default_status_id( $reference_data['statuses'] );
$default_operator_id = ssp_get_default_operator_id( $con );
$default_group_id = ssp_default_operator_group_id( $con );
$errors = [];
$default_category = !empty( $reference_data['categories'] ) ? $reference_data['categories'][0] : null;

$form_values = [
  'title' => '',
  'description' => '',
  'commenttext' => ''
];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $form_values = [
    'title' => trim( $_POST['title'] ?? '' ),
    'description' => trim( $_POST['description'] ?? '' ),
    'commenttext' => trim( $_POST['commenttext'] ?? '' )
  ];
  $category = $default_category;

  if ( $form_values['title'] === '' ) {
    $errors[] = 'Titel is verplicht.';
  }
  if ( !$category ) {
    $errors[] = 'Er is geen incidentcategorie beschikbaar voor selfservice meldingen.';
  }
  if ( $default_status_id === 0 ) {
    $errors[] = 'Er is geen incidentstatus beschikbaar.';
  }
  if ( $default_operator_id === 0 ) {
    $errors[] = 'Er is geen behandelaar beschikbaar om deze melding te registreren.';
  }
  $errors = array_merge( $errors, ssp_attachment_upload_errors() );

  if ( empty( $errors ) ) {
    $incident_number = ssp_incident_generate_number( $con );
    $subcategory_id = null;
    $asset_id = null;
    $customer_id = (int)$person['customerid'];
    $person_id = (int)$person['id'];
    $person_email = $person['email'] ?? '';
    $person_phone = $person['phone'] ?? '';
    $mode = 'firstline';
    $major_incident_id = null;
    $group_id = $default_group_id > 0 ? $default_group_id : null;
    $operator_id = null;

    $stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_im_incidents (
                incidentnumber, incidenttype, majorincidentid, title, description, customerid, personid, personemail, personphone,
                categoryid, subcategoryid, assetid, operatorgroupid, operatorid, statusid, createdby
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        " );
    mysqli_stmt_bind_param(
      $stmt,
      "ssissiissiiiiiii",
      $incident_number,
      $mode,
      $major_incident_id,
      $form_values['title'],
      $form_values['description'],
      $customer_id,
      $person_id,
      $person_email,
      $person_phone,
      $category['id'],
      $subcategory_id,
      $asset_id,
      $group_id,
      $operator_id,
      $default_status_id,
      $default_operator_id
    );

    if ( mysqli_stmt_execute( $stmt ) ) {
      $incident_id = mysqli_insert_id( $con );
      mysqli_stmt_close( $stmt );
      task_log_add( $con, 'incident', $incident_id, 'created', 'Incident aangemaakt via Self Service Portal.', null );

      if ( $form_values['commenttext'] !== '' || ssp_attachment_uploaded_file_available() ) {
        $comment_stmt = mysqli_prepare( $con, "INSERT INTO itsm_im_incidentcomments (incidentid, operatorid, personid, commenttext, internalonly) VALUES (?,NULL,?,?,0)" );
        $comment_text = $form_values['commenttext'] !== '' ? $form_values['commenttext'] : 'Bijlage toegevoegd.';
        mysqli_stmt_bind_param( $comment_stmt, "iis", $incident_id, $person['id'], $comment_text );
        mysqli_stmt_execute( $comment_stmt );
        $comment_id = mysqli_insert_id( $con );
        ssp_attachment_save_upload( $con, 'incident', $incident_id, (int)$person['id'], 'incidentcomment', $comment_id );
        mysqli_stmt_close( $comment_stmt );
      }
      mail_process_ticket_created( $con, 'incident', $incident_id );

      header( 'Location: view_incident.php?id=' . $incident_id );
      exit;
    }

    $errors[] = 'Incident opslaan mislukt: ' . mysqli_stmt_error( $stmt );
    mysqli_stmt_close( $stmt );
  }
}

ssp_page_title( t( 'Incident melden' ) );
ssp_render_header( $person, 'new_incident' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars(t('Incident melden')) ?></h2>
    <p><?= htmlspecialchars(t('Maak een nieuwe melding aan. De servicedesk vult classificatie en object later aan.')) ?></p>
  </div>
</section>

<?php if ( !empty( $errors ) ): ?>
<div class="ssp-error">
  <?= htmlspecialchars(implode(' ', $errors)) ?>
</div>
<?php endif; ?>

<section class="ssp-form-grid">
  <article class="ssp-form-card">
    <h3><?= htmlspecialchars(t('Jouw gegevens')) ?></h3>
    <dl class="ssp-summary-list">
      <div><dt><?= htmlspecialchars(t('Klant')) ?></dt><dd><?= htmlspecialchars($person['customer_name'] ?? '') ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Persoon')) ?></dt><dd><?= htmlspecialchars(trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))) ?></dd></div>
      <div><dt><?= htmlspecialchars(t('E-mail')) ?></dt><dd><?= htmlspecialchars($person['email'] ?? '') ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Telefoon')) ?></dt><dd><?= htmlspecialchars($person['phone'] ?? '') ?></dd></div>
    </dl>
  </article>

  <article class="ssp-form-card">
    <form method="post" enctype="multipart/form-data" class="ssp-form-stack">
      <div class="ssp-field">
        <label for="title"><?= htmlspecialchars(t('Korte titel')) ?></label>
        <input id="title" name="title" type="text" value="<?= htmlspecialchars($form_values['title']) ?>" required>
      </div>
      <div class="ssp-field">
        <label for="description"><?= htmlspecialchars(t('Omschrijving')) ?></label>
        <textarea id="description" name="description"><?= htmlspecialchars($form_values['description']) ?></textarea>
      </div>
      <div class="ssp-field">
        <label for="commenttext"><?= htmlspecialchars(t('Aanvullend commentaar')) ?></label>
        <textarea id="commenttext" name="commenttext"><?= htmlspecialchars($form_values['commenttext']) ?></textarea>
      </div>
      <?php ssp_attachment_render_upload_field(); ?>
      <div class="ssp-form-actions">
        <button class="ssp-button" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(t('Incident opslaan')) ?></button>
      </div>
    </form>
  </article>
</section>
<?php ssp_render_footer(); ?>
