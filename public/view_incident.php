<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
require_once( __DIR__ . '/../secure/include/task_log_helpers.php' );
require_once( __DIR__ . '/../secure/include/task_helpers.php' );

$person = ssp_require_login( $con );
$is_manager = ssp_person_is_manager( $person );
$incident_id = isset( $_GET['id'] ) ? (int)$_GET['id'] : 0;
if ( $incident_id === 0 && !empty( $_GET['tasknumber'] ) ) {
  $task = task_find_by_number( $con, $_GET['tasknumber'], 'public' );
  if ( $task && $task['type'] === 'incident' ) {
    $incident_id = (int)$task['id'];
  }
}
$errors = [];

$incident = ssp_load_incident_for_person( $con, $person, $incident_id );

if ( !$incident ) {
  header( 'Location: incidents.php?access_denied=1' );
  exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $commenttext = trim( $_POST['commenttext'] ?? '' );

  if ( $commenttext === '' && !ssp_attachment_uploaded_file_available() ) {
    $errors[] = 'Commentaar of bijlage is verplicht.';
  }
  $errors = array_merge( $errors, ssp_attachment_upload_errors() );

  if ( empty( $errors ) ) {
    $comment_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_im_incidentcomments (incidentid, operatorid, personid, commenttext, internalonly)
        VALUES (?,NULL,?,?,0)
    " );
    $commenttext_for_save = $commenttext !== '' ? $commenttext : 'Bijlage toegevoegd.';
    mysqli_stmt_bind_param( $comment_stmt, "iis", $incident_id, $person['id'], $commenttext_for_save );

    if ( mysqli_stmt_execute( $comment_stmt ) ) {
      $comment_id = mysqli_insert_id( $con );
      ssp_attachment_save_upload( $con, 'incident', $incident_id, (int)$person['id'], 'incidentcomment', $comment_id );
      task_log_add( $con, 'incident', $incident_id, 'updated', 'Reactie toegevoegd via Self Service Portal: "' . task_log_text_snippet( $commenttext_for_save ) . '".', null, null, task_log_text_snippet( $commenttext_for_save ) );
      mysqli_stmt_close( $comment_stmt );
      header( 'Location: view_incident.php?id=' . $incident_id );
      exit;
    }

    $errors[] = itsm_error_reference( 'public_incident_comment_insert_failed', mysqli_stmt_error( $comment_stmt ) );
    mysqli_stmt_close( $comment_stmt );
  }
}

$comments_stmt = mysqli_prepare( $con, "
    SELECT
      c.id,
      c.commenttext,
      c.createdat,
      c.personid,
      c.operatorid,
      op.firstname AS operator_firstname,
      op.lastname AS operator_lastname,
      per.firstname AS person_firstname,
      per.lastname AS person_lastname
    FROM itsm_im_incidentcomments c
    LEFT JOIN itsm_ob_operators op ON c.operatorid = op.id
    LEFT JOIN itsm_ob_persons per ON c.personid = per.id
    WHERE c.incidentid = ? AND c.internalonly = 0
    ORDER BY c.createdat DESC, c.id DESC
" );
mysqli_stmt_bind_param( $comments_stmt, "i", $incident_id );
mysqli_stmt_execute( $comments_stmt );
$comments_result = mysqli_stmt_get_result( $comments_stmt );
$attachments = ssp_attachment_load_for_task( $con, 'incident', $incident_id );
$attachments_by_comment = ssp_attachment_group_by_comment( $attachments );

ssp_page_title( t('Incident') . ' ' . ($incident['incidentnumber'] ?: ('#' . $incident['id'])) );
ssp_render_header( $person, 'incidents' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($incident['incidentnumber'] ?: ('#' . $incident['id'])) ?> - <?= htmlspecialchars($incident['title']) ?></h2>
    <p><?= htmlspecialchars(t('Bekijk de status en communicatie van je melding.')) ?></p>
  </div>
  <a class="ssp-ghost-link" href="incidents.php<?= $is_manager ? '?scope=customer' : '' ?>"><?= htmlspecialchars(t('Terug naar incidenten')) ?></a>
</section>

<section class="ssp-detail-grid">
  <article class="ssp-detail-card">
    <h3><?= htmlspecialchars(t('Details')) ?></h3>
    <dl class="ssp-summary-list">
      <div><dt><?= htmlspecialchars(t('Status')) ?></dt><dd><?= htmlspecialchars($incident['status_name'] ?? '') ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Categorie')) ?></dt><dd><?= htmlspecialchars($incident['category_name'] ?? '') ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Subcategorie')) ?></dt><dd><?= htmlspecialchars($incident['subcategory_name'] ?? '') ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Object')) ?></dt><dd><?= htmlspecialchars($incident['asset_objectid'] ?? '') ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Type')) ?></dt><dd><?= htmlspecialchars($incident['asset_type'] ?? '') ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Aangemaakt')) ?></dt><dd><?= htmlspecialchars($incident['createdat']) ?></dd></div>
      <div><dt><?= htmlspecialchars(t('Bijgewerkt')) ?></dt><dd><?= htmlspecialchars($incident['updatedat']) ?></dd></div>
    </dl>
  </article>

  <article class="ssp-detail-card">
    <h3><?= htmlspecialchars(t('Omschrijving')) ?></h3>
    <p><?= task_linkify_text($incident['description'] ?? '', 'public') ?></p>
  </article>
</section>

<section class="ssp-panel" style="margin-top: 22px;">
  <div class="ssp-panel-head">
    <h3><?= htmlspecialchars(t('Commentaar')) ?></h3>
  </div>
  <?php if ( !empty( $errors ) ): ?>
  <div class="ssp-error">
    <?= htmlspecialchars(implode(' ', $errors)) ?>
  </div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="ssp-form-stack" style="margin-bottom: 18px;">
    <div class="ssp-field">
      <label for="commenttext"><?= htmlspecialchars(t('Nieuwe reactie')) ?></label>
      <textarea id="commenttext" name="commenttext" placeholder="<?= htmlspecialchars(t('Plaats hier je aanvullende informatie of reactie.')) ?>"></textarea>
    </div>
    <?php ssp_attachment_render_upload_field(); ?>
    <div class="ssp-form-actions">
      <button class="ssp-button" type="submit"><i class="fa-solid fa-paper-plane"></i> <?= htmlspecialchars(t('Reactie plaatsen')) ?></button>
    </div>
  </form>
  <div class="ssp-comment-list">
    <?php while ( $comment = mysqli_fetch_assoc( $comments_result ) ): ?>
    <article class="ssp-comment">
      <div class="ssp-comment-meta">
        <strong><?= htmlspecialchars((int)($comment['personid'] ?? 0) > 0 ? (trim(($comment['person_firstname'] ?? '') . ' ' . ($comment['person_lastname'] ?? '')) ?: 'Klant') : (trim(($comment['operator_firstname'] ?? '') . ' ' . ($comment['operator_lastname'] ?? '')) ?: 'Behandelaar')) ?></strong>
        <span><?= htmlspecialchars($comment['createdat']) ?></span>
      </div>
      <div><?= task_linkify_text($comment['commenttext'], 'public') ?></div>
      <?php if ( !empty( $attachments_by_comment[(string)$comment['id']] ) ): ?>
      <?= ssp_attachment_render_links( $attachments_by_comment[(string)$comment['id']] ) ?>
      <?php endif; ?>
    </article>
    <?php endwhile; ?>
    <?php if ( mysqli_num_rows( $comments_result ) === 0 ): ?>
    <div class="ssp-empty">Er is nog geen zichtbaar commentaar bij deze melding.</div>
    <?php endif; ?>
  </div>
</section>
<?php
mysqli_stmt_close( $comments_stmt );
ssp_render_footer();
?>
