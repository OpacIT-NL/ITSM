<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );
if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Sliding idle timeout check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION[ 'name' ];
$message = '';
$form_error = '';
$languages = itsm_available_languages();
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE id = ? AND username = ? LIMIT 1" );
$session_operator_id = (int)$_SESSION['id'];
mysqli_stmt_bind_param( $stmt, "is", $session_operator_id, $logged_in_user );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );
mysqli_stmt_close( $stmt );
if ( !$operator ) {
  itsm_destroy_session();
  header( 'Location: login.php' );
  exit;
}

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
    header( 'Location: profile.php?saved=1' );
    exit;
  }

  if ( $form_action === 'password' ) {
    $current_password = (string)( $_POST['current_password'] ?? '' );
    $new_password = (string)( $_POST['password'] ?? '' );
    $password_confirm = (string)( $_POST['password_confirm'] ?? '' );
    if ( !password_verify( $current_password, (string)$operator['password'] ) ) {
      $form_error = 'Het huidige wachtwoord is niet correct.';
    } elseif ( $new_password !== $password_confirm ) {
      $form_error = 'De nieuwe wachtwoorden komen niet overeen.';
    } else {
      $form_error = itsm_password_policy_error( $new_password );
    }

    if ( $form_error === '' ) {
      $hashed = password_hash( $new_password, PASSWORD_DEFAULT );
      $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_operators 
        SET password = ? 
        WHERE id = ?
      " );

      mysqli_stmt_bind_param( $stmt, "si", $hashed, $session_operator_id );
      if ( !mysqli_stmt_execute( $stmt ) ) {
        itsm_fail( 'profile_password_update_failed', mysqli_stmt_error( $stmt ) );
      }
      mysqli_stmt_close( $stmt );
      session_regenerate_id( true );
      itsm_refresh_session_security_fingerprint( $con );
      header( 'Location: profile.php?saved=1' );
      exit;
    }
  }
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
  <?php if ( $form_error !== '' ): ?>
  <div class="error"><?= htmlspecialchars($form_error) ?></div>
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
          <label>Huidig wachtwoord</label>
          <input type="password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="form-group">
          <label><?= htmlspecialchars(t('profile.new_password')) ?></label>
          <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" required placeholder="<?= htmlspecialchars(t('profile.new_password')) ?>">
        </div>
        <div class="form-group">
          <label>Herhaal nieuw wachtwoord</label>
          <input type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="128" required>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= htmlspecialchars(t('profile.save_password')) ?></button>
        </div>
      </form>
    </article>
  </section>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
