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
require_once( __DIR__ . '/../version.php' );
// Fetch user permissions
$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: index.php" );
  exit();
}

$message = '';
$default_language = itsm_fetch_setting_value( $con, 'default_language', 'nl_NL' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $default_language = itsm_normalize_language_code( $_POST['default_language'] ?? 'nl_NL' );
  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_core_settings (settingkey, settingvalue)
    VALUES ('default_language', ?)
    ON DUPLICATE KEY UPDATE settingvalue = VALUES(settingvalue)
  " );
  mysqli_stmt_bind_param( $stmt, 's', $default_language );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );
  header( 'Location: set-general.php?saved=1' );
  exit;
}

if ( isset( $_GET['saved'] ) ) {
  $message = t( 'language.saved' );
}

$languages = itsm_available_languages();
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/settings.php'); ?>
<div class="content">
  <div class="module-section">
    <h1><?= htmlspecialchars(t('settings.general.title')) ?></h1>
    <p class="info-note"><?= htmlspecialchars(t('settings.general.description')) ?></p>
    <?php if ( $message !== '' ): ?>
    <p class="info-note"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <div class="form-wrapper record-form-wrapper">
      <div class="form-card record-form-card">
        <form method="post" class="form-grid">
          <div class="form-group">
            <label><?= htmlspecialchars(t('language.default')) ?>:
              <select name="default_language">
                <?php foreach ( $languages as $code => $label ): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $default_language === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <p class="info-note"><?= htmlspecialchars(t('settings.general.language_help')) ?></p>
          <div class="form-actions">
            <button type="submit" class="btn-primary"><?= htmlspecialchars(t('language.save')) ?></button>
          </div>
        </form>
      </div>
    </div>
    <div class="module-grid"> <a href="set-ls-cat.php"> Categoriebeheer </a> <a href="set-ls-status.php"> Statussen </a> </div>
    <br>
    Current Version:
    <?= htmlspecialchars($version) ?>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
