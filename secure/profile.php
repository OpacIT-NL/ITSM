<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Absolute expiration check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION[ 'name' ];
$message = '';
$languages = itsm_available_languages();
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  $form_action = (string)( $_POST['profile_action'] ?? '' );

  if ( $form_action === 'language' ) {
    $preferred_language = itsm_normalize_language_code( $_POST['preferredlanguage'] ?? 'nl_NL' );
    $language_stmt = mysqli_prepare( $con, "
      UPDATE itsm_ob_operators
      SET preferredlanguage = ?
      WHERE username = ?
    " );
    mysqli_stmt_bind_param( $language_stmt, "ss", $preferred_language, $logged_in_user );
    mysqli_stmt_execute( $language_stmt );
    mysqli_stmt_close( $language_stmt );
    $_SESSION['preferred_language'] = $preferred_language;
  }

  if ( $form_action === 'password' && !empty( $_POST[ 'password' ] ) ) {
    $hashed = password_hash( $_POST[ 'password' ], PASSWORD_DEFAULT );

    $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_operators 
        SET password = ? 
        WHERE username = ?
    " );

    mysqli_stmt_bind_param( $stmt, "ss", $hashed, $logged_in_user );
    mysqli_stmt_execute( $stmt );
  }
  header( 'Location: profile.php?saved=1' );
  exit;
}
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE username=?" );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );
if ( !$operator ) {
  die( "Operator not found" );
}
$selected_language = $operator['preferredlanguage'] ?? itsm_current_language();
if ( isset( $_GET['saved'] ) ) {
  $message = t( 'language.saved' );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <section class="profile-hero">
    <div class="profile-avatar">
      <?= htmlspecialchars(strtoupper(substr((string)$operator['firstname'], 0, 1) . substr((string)$operator['lastname'], 0, 1))) ?>
    </div>
    <div>
      <p class="profile-eyebrow"><?= htmlspecialchars(t('profile.my_account')) ?></p>
      <h1><?= htmlspecialchars(trim(($operator['firstname'] ?? '') . ' ' . ($operator['lastname'] ?? ''))) ?></h1>
      <p><?= htmlspecialchars($operator['email'] ?? '') ?></p>
    </div>
  </section>

  <?php if ( $message !== '' ): ?>
  <div class="profile-success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <section class="profile-grid">
    <article class="profile-card">
      <h2><?= htmlspecialchars(t('profile.details')) ?></h2>
      <dl class="profile-details">
        <div><dt><?= htmlspecialchars(t('Voornaam')) ?></dt><dd><?= htmlspecialchars($operator['firstname']) ?></dd></div>
        <div><dt><?= htmlspecialchars(t('Achternaam')) ?></dt><dd><?= htmlspecialchars($operator['lastname']) ?></dd></div>
        <div><dt><?= htmlspecialchars(t('Gebruikersnaam')) ?></dt><dd><?= htmlspecialchars($operator['username']) ?></dd></div>
        <div><dt><?= htmlspecialchars(t('E-mail')) ?></dt><dd><?= htmlspecialchars($operator['email']) ?></dd></div>
      </dl>
    </article>

    <article class="profile-card">
      <h2><?= htmlspecialchars(t('profile.language')) ?></h2>
      <form method="post" class="profile-password-form">
        <input type="hidden" name="profile_action" value="language">
        <div class="form-group">
          <label><?= htmlspecialchars(t('operator.preferred_language')) ?></label>
          <select name="preferredlanguage">
            <?php foreach ( $languages as $code => $label ): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= $selected_language === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= htmlspecialchars(t('profile.save_language')) ?></button>
        </div>
      </form>
    </article>

    <article class="profile-card">
      <h2><?= htmlspecialchars(t('profile.security')) ?></h2>
      <p class="info-note"><?= htmlspecialchars(t('profile.password_info')) ?></p>
      <form method="post" class="profile-password-form">
        <input type="hidden" name="profile_action" value="password">
        <div class="form-group">
          <label><?= htmlspecialchars(t('profile.new_password')) ?></label>
          <input type="password" name="password" autocomplete="new-password" placeholder="<?= htmlspecialchars(t('profile.new_password')) ?>">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= htmlspecialchars(t('profile.save_password')) ?></button>
        </div>
      </form>
    </article>
  </section>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
