<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/../secure/include/news_helpers.php' );
require_once( __DIR__ . '/include/password_reset_helpers.php' );

$token = (string)( $_POST['token'] ?? $_GET['token'] ?? '' );
$reset = $token !== '' ? ssp_reset_load_by_token( $con, $token ) : null;
$error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  if ( !$reset ) {
    $error = 'Deze resetlink is ongeldig of verlopen.';
  } else {
    $password = (string)( $_POST['password'] ?? '' );
    $password_confirm = (string)( $_POST['password_confirm'] ?? '' );

    if ( strlen( $password ) < 8 ) {
      $error = 'Gebruik een wachtwoord van minimaal 8 tekens.';
    } elseif ( $password !== $password_confirm ) {
      $error = 'De wachtwoorden komen niet overeen.';
    } else {
      $password_hash = password_hash( $password, PASSWORD_DEFAULT );
      if ( ssp_reset_update_password( $con, (int)$reset['personid'], $password_hash ) && ssp_reset_mark_used( $con, (int)$reset['resetid'] ) ) {
        header( 'Location: login.php?reset_done=1' );
        exit();
      }
      $error = 'Het wachtwoord kon niet worden gewijzigd. Probeer het opnieuw.';
    }
  }
}
?>
<html>
<head>
<meta charset="utf-8">
<title>ITSM SelfService - Wachtwoord resetten</title>
<link href="include/login.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<body class="login-page">
<?= news_render_banners( news_fetch_items( $con, 'login', 10 ) ) ?>
<div class="login-box">
  <h1>ITSM SelfService</h1>
  <h2>Nieuw wachtwoord</h2>
  <?php if ( !$reset && $error === '' ): ?>
  <p class="error">Deze resetlink is ongeldig of verlopen.</p>
  <p><a href="forgot_password.php" class="login-helper-link">Nieuwe resetlink aanvragen</a></p>
  <?php else: ?>
  <?php if ( $error !== '' ): ?>
  <p class="error"><?= htmlspecialchars( $error ) ?></p>
  <?php endif; ?>
  <?php if ( $reset ): ?>
  <p class="login-hint">Stel een nieuw wachtwoord in voor <?= htmlspecialchars( $reset['email'] ) ?>.</p>
  <form action="reset_password.php" method="post" class="login-form">
    <input type="hidden" name="token" value="<?= htmlspecialchars( $token ) ?>">
    <div class="input-group"> <i class="fas fa-lock"></i>
      <input type="password" name="password" placeholder="Nieuw wachtwoord" required minlength="8">
    </div>
    <div class="input-group"> <i class="fas fa-lock"></i>
      <input type="password" name="password_confirm" placeholder="Herhaal wachtwoord" required minlength="8">
    </div>
    <button type="submit" class="login-button">Wachtwoord wijzigen</button>
  </form>
  <?php endif; ?>
  <p><a href="login.php" class="login-helper-link">Terug naar inloggen</a></p>
  <?php endif; ?>
</div>
<?php itsm_render_local_datetime_script(); ?>
</body>
</html>
