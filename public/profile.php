<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$message = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $preferred_language = itsm_normalize_language_code( $_POST['preferredlanguage'] ?? 'nl_NL' );
  $phone = (string)( $_POST['phone'] ?? '' );

  $stmt = mysqli_prepare( $con, "
    UPDATE itsm_ob_persons
    SET phone = ?, preferredlanguage = ?
    WHERE id = ?
  " );
  mysqli_stmt_bind_param( $stmt, 'ssi', $phone, $preferred_language, $person['id'] );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );
  $_SESSION['preferred_language'] = $preferred_language;

  header( 'Location: profile.php?saved=1' );
  exit;
}

if ( isset( $_GET['saved'] ) ) {
  $message = t( 'ssp.profile.saved' );
}

$person = ssp_require_login( $con );
$languages = itsm_available_languages();
$selected_language = !empty( $person['preferredlanguage'] ) ? $person['preferredlanguage'] : itsm_current_language();

ssp_page_title( t( 'ssp.profile.title' ) );
ssp_render_header( $person, 'profile' );
?>
<section class="ssp-hero">
  <div>
    <span class="ssp-eyebrow"><?= htmlspecialchars(t('nav.profile')) ?></span>
    <h2><?= htmlspecialchars(t('ssp.profile.title')) ?></h2>
    <p><?= htmlspecialchars(t('ssp.profile.subtitle')) ?></p>
  </div>
</section>

<?php if ( $message !== '' ): ?>
<section class="ssp-panel" style="margin-top: 22px;">
  <p class="success"><?= htmlspecialchars($message) ?></p>
</section>
<?php endif; ?>

<section class="ssp-dashboard-grid">
  <article class="ssp-panel">
    <div class="ssp-panel-head">
      <h3><?= htmlspecialchars(t('profile.details')) ?></h3>
    </div>
    <dl class="ssp-summary-list">
      <div><dt>Klant</dt><dd><?= htmlspecialchars($person['customer_name'] ?? '') ?></dd></div>
      <div><dt>Naam</dt><dd><?= htmlspecialchars(trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))) ?></dd></div>
      <div><dt>E-mail</dt><dd><?= htmlspecialchars($person['email'] ?? '') ?></dd></div>
    </dl>
  </article>

  <article class="ssp-panel">
    <div class="ssp-panel-head">
      <h3><?= htmlspecialchars(t('nav.profile')) ?></h3>
    </div>
    <form method="post">
      <div class="ssp-field">
        <label for="phone">Telefoonnummer</label>
        <input id="phone" type="text" name="phone" value="<?= htmlspecialchars($person['phone'] ?? '') ?>">
      </div>
      <div class="ssp-field">
        <label for="preferredlanguage"><?= htmlspecialchars(t('person.preferred_language')) ?></label>
        <select id="preferredlanguage" name="preferredlanguage">
          <?php foreach ( $languages as $code => $label ): ?>
          <option value="<?= htmlspecialchars($code) ?>" <?= $selected_language === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="ssp-button"><?= htmlspecialchars(t('ssp.profile.save')) ?></button>
      </div>
    </form>
  </article>
</section>
<?php ssp_render_footer(); ?>
