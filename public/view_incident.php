<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
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

$stmt = mysqli_prepare( $con, "
    SELECT i.*, cat.name AS category_name, sub.name AS subcategory_name, s.name AS status_name, a.objectid AS asset_objectid, t.type AS asset_type
    FROM itsm_im_incidents i
    LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
    LEFT JOIN itsm_core_subcategory sub ON i.subcategoryid = sub.id
    LEFT JOIN itsm_core_status s ON i.statusid = s.id
    LEFT JOIN itsm_am_assets a ON i.assetid = a.id
    LEFT JOIN itsm_am_types t ON a.type = t.id
    WHERE i.id = ? AND (
      i.personid = ?
      OR (? = 1 AND i.customerid = ?)
    )
    LIMIT 1
" );
$manager_flag = $is_manager ? 1 : 0;
$customer_id = (int)$person['customerid'];
mysqli_stmt_bind_param( $stmt, "iiii", $incident_id, $person['id'], $manager_flag, $customer_id );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$incident = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$incident ) {
  header( 'Location: incidents.php' );
  exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $commenttext = trim( $_POST['commenttext'] ?? '' );

  if ( $commenttext === '' ) {
    $errors[] = 'Commentaar is verplicht.';
  }

  if ( empty( $errors ) ) {
    $comment_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_im_incidentcomments (incidentid, operatorid, personid, commenttext, internalonly)
        VALUES (?,NULL,?,?,0)
    " );
    mysqli_stmt_bind_param( $comment_stmt, "iis", $incident_id, $person['id'], $commenttext );

    if ( mysqli_stmt_execute( $comment_stmt ) ) {
      mysqli_stmt_close( $comment_stmt );
      header( 'Location: view_incident.php?id=' . $incident_id );
      exit;
    }

    $errors[] = 'Commentaar opslaan mislukt: ' . mysqli_stmt_error( $comment_stmt );
    mysqli_stmt_close( $comment_stmt );
  }
}

$comments_stmt = mysqli_prepare( $con, "
    SELECT
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

ssp_page_title( 'Incident ' . ($incident['incidentnumber'] ?: ('#' . $incident['id'])) );
ssp_render_header( $person, 'incidents' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($incident['incidentnumber'] ?: ('#' . $incident['id'])) ?> - <?= htmlspecialchars($incident['title']) ?></h2>
    <p>Bekijk de status en communicatie van je melding.</p>
  </div>
  <a class="ssp-ghost-link" href="incidents.php<?= $is_manager ? '?scope=customer' : '' ?>">Terug naar incidenten</a>
</section>

<section class="ssp-detail-grid">
  <article class="ssp-detail-card">
    <h3>Details</h3>
    <dl class="ssp-summary-list">
      <div><dt>Status</dt><dd><?= htmlspecialchars($incident['status_name'] ?? '') ?></dd></div>
      <div><dt>Categorie</dt><dd><?= htmlspecialchars($incident['category_name'] ?? '') ?></dd></div>
      <div><dt>Subcategorie</dt><dd><?= htmlspecialchars($incident['subcategory_name'] ?? '') ?></dd></div>
      <div><dt>Object</dt><dd><?= htmlspecialchars($incident['asset_objectid'] ?? '') ?></dd></div>
      <div><dt>Type</dt><dd><?= htmlspecialchars($incident['asset_type'] ?? '') ?></dd></div>
      <div><dt>Aangemaakt</dt><dd><?= htmlspecialchars($incident['createdat']) ?></dd></div>
      <div><dt>Bijgewerkt</dt><dd><?= htmlspecialchars($incident['updatedat']) ?></dd></div>
    </dl>
  </article>

  <article class="ssp-detail-card">
    <h3>Omschrijving</h3>
    <p><?= task_linkify_text($incident['description'] ?? '', 'public') ?></p>
  </article>
</section>

<section class="ssp-panel" style="margin-top: 22px;">
  <div class="ssp-panel-head">
    <h3>Commentaar</h3>
  </div>
  <?php if ( !empty( $errors ) ): ?>
  <div class="ssp-error">
    <?= htmlspecialchars(implode(' ', $errors)) ?>
  </div>
  <?php endif; ?>
  <form method="post" class="ssp-form-stack" style="margin-bottom: 18px;">
    <div class="ssp-field">
      <label for="commenttext">Nieuwe reactie</label>
      <textarea id="commenttext" name="commenttext" placeholder="Plaats hier je aanvullende informatie of reactie."></textarea>
    </div>
    <div class="ssp-form-actions">
      <button class="ssp-button" type="submit"><i class="fa-solid fa-paper-plane"></i> Reactie plaatsen</button>
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
