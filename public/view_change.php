<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$change_id = isset( $_GET['id'] ) ? (int)$_GET['id'] : 0;
$errors = [];

$stmt = mysqli_prepare( $con, "
    SELECT c.*, cat.name AS category_name, sub.name AS subcategory_name, s.name AS status_name, a.objectid AS asset_objectid, t.type AS asset_type
    FROM itsm_cm_changes c
    LEFT JOIN itsm_core_category cat ON c.categoryid = cat.id
    LEFT JOIN itsm_core_subcategory sub ON c.subcategoryid = sub.id
    LEFT JOIN itsm_core_status s ON c.statusid = s.id
    LEFT JOIN itsm_am_assets a ON c.assetid = a.id
    LEFT JOIN itsm_am_types t ON a.type = t.id
    WHERE c.id = ? AND c.personid = ?
    LIMIT 1
" );
mysqli_stmt_bind_param( $stmt, "ii", $change_id, $person['id'] );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$change = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );

if ( !$change ) {
  header( 'Location: changes.php' );
  exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $commenttext = trim( $_POST['commenttext'] ?? '' );

  if ( $commenttext === '' ) {
    $errors[] = 'Commentaar is verplicht.';
  }

  if ( empty( $errors ) ) {
    $comment_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_cm_changecomments (changeid, operatorid, personid, commenttext, internalonly)
        VALUES (?,NULL,?,?,0)
    " );
    mysqli_stmt_bind_param( $comment_stmt, "iis", $change_id, $person['id'], $commenttext );

    if ( mysqli_stmt_execute( $comment_stmt ) ) {
      mysqli_stmt_close( $comment_stmt );
      header( 'Location: view_change.php?id=' . $change_id );
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
    FROM itsm_cm_changecomments c
    LEFT JOIN itsm_ob_operators op ON c.operatorid = op.id
    LEFT JOIN itsm_ob_persons per ON c.personid = per.id
    WHERE c.changeid = ? AND c.internalonly = 0
    ORDER BY c.createdat DESC, c.id DESC
" );
mysqli_stmt_bind_param( $comments_stmt, "i", $change_id );
mysqli_stmt_execute( $comments_stmt );
$comments_result = mysqli_stmt_get_result( $comments_stmt );

ssp_page_title( 'Wijziging ' . ($change['changenumber'] ?: ('#' . $change['id'])) );
ssp_render_header( $person, 'changes' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($change['changenumber'] ?: ('#' . $change['id'])) ?> - <?= htmlspecialchars($change['title']) ?></h2>
    <p>Volg hier de voortgang van je wijziging.</p>
  </div>
  <a class="ssp-ghost-link" href="changes.php">Terug naar wijzigingen</a>
</section>

<section class="ssp-detail-grid">
  <article class="ssp-detail-card">
    <h3>Details</h3>
    <dl class="ssp-summary-list">
      <div><dt>Fase</dt><dd><?php
        if ( $change['approvalstate'] === 'request' ) {
          echo 'Wijzigingsaanvraag';
        } elseif ( $change['approvalstate'] === 'rejected' ) {
          echo 'Afgewezen';
        } else {
          echo htmlspecialchars( $change['requesttype'] === 'extended' ? 'Uitgebreide Wijziging' : 'Eenvoudige Wijziging' );
        }
      ?></dd></div>
      <div><dt>Type</dt><dd><?= htmlspecialchars($change['changetype']) ?></dd></div>
      <div><dt>Status</dt><dd><?= htmlspecialchars($change['approvalstate'] === 'approved' ? ($change['status_name'] ?? '') : 'Aanvraag') ?></dd></div>
      <div><dt>Categorie</dt><dd><?= htmlspecialchars($change['category_name'] ?? '') ?></dd></div>
      <div><dt>Subcategorie</dt><dd><?= htmlspecialchars($change['subcategory_name'] ?? '') ?></dd></div>
      <div><dt>Object</dt><dd><?= htmlspecialchars($change['asset_objectid'] ?? '') ?></dd></div>
      <div><dt>Type object</dt><dd><?= htmlspecialchars($change['asset_type'] ?? '') ?></dd></div>
      <div><dt>Aangemaakt</dt><dd><?= htmlspecialchars($change['createdat']) ?></dd></div>
      <div><dt>Bijgewerkt</dt><dd><?= htmlspecialchars($change['updatedat']) ?></dd></div>
    </dl>
  </article>

  <article class="ssp-detail-card">
    <h3>Omschrijving</h3>
    <p><?= nl2br(htmlspecialchars($change['description'] ?? '')) ?></p>
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
      <div><?= nl2br(htmlspecialchars($comment['commenttext'])) ?></div>
    </article>
    <?php endwhile; ?>
    <?php if ( mysqli_num_rows( $comments_result ) === 0 ): ?>
    <div class="ssp-empty">Er is nog geen zichtbaar commentaar bij deze wijziging.</div>
    <?php endif; ?>
  </div>
</section>
<?php
mysqli_stmt_close( $comments_stmt );
ssp_render_footer();
?>
