<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/news_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION['name'];
news_require_firstline_authorization( $con, $logged_in_user );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1><?= htmlspecialchars(t('Nieuws')) ?></h1>
  <div class="module-grid">
    <a href="new_news.php"><?= htmlspecialchars(t('Nieuw bericht')) ?></a>
    <a href="news.php"><?= htmlspecialchars(t('Alle berichten')) ?></a>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
