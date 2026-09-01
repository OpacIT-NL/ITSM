<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/../secure/include/news_helpers.php' );
require_once( __DIR__ . '/include/password_reset_helpers.php' );

$sent = isset( $_GET['sent'] );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $email = trim( (string)( $_POST['email'] ?? '' ) );
  $rate_limited = itsm_auth_rate_limited( $con, 'password_reset', $email, 3, 20, 60 * 60 );
  itsm_auth_record_attempt( $con, 'password_reset', $email );
  if ( !$rate_limited && filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
    $person = ssp_reset_find_person_by_email( $con, $email );
    if ( $person ) {
      try {
        $token_data = ssp_reset_create_token( $con, (int)$person['id'], 60 );
        if ( $token_data ) {
          if ( !ssp_reset_send_mail( $person, $token_data ) ) {
            throw new RuntimeException( 'Password reset email delivery failed.' );
          }
        }
      } catch ( Throwable $exception ) {
        $trace_id = itsm_trace_id();
        itsm_log_trace( $trace_id, 'password_reset_request_failed', $exception->getMessage(), $exception );
        // Keep the response generic so account existence is never exposed.
      }
    }
  }
  header( 'Location: forgot_password.php?sent=1' );
  exit();
}
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(t('ITSM SelfService - Wachtwoord vergeten')) ?></title>
<link href="include/login.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<body class="login-page">
<?= news_render_banners( news_fetch_items( $con, 'login', 10 ) ) ?>
<div class="login-box">
  <h1><?= htmlspecialchars(t('ITSM SelfService')) ?></h1>
  <h2><?= htmlspecialchars(t('Wachtwoord vergeten')) ?></h2>
  <?php if ( $sent ): ?>
  <p class="success">Als dit e-mailadres bekend is voor SelfService, ontvang je binnen enkele minuten een resetlink.</p>
  <p><a href="login.php" class="login-helper-link">Terug naar inloggen</a></p>
  <?php else: ?>
  <p class="login-hint">Vul je e-mailadres in. We sturen dan een beveiligde link waarmee je je wachtwoord opnieuw kunt instellen.</p>
  <form action="forgot_password.php" method="post" class="login-form">
    <div class="input-group"> <i class="fas fa-envelope"></i>
      <input type="email" name="email" placeholder="<?= htmlspecialchars(t('E-mail')) ?>" required>
    </div>
    <button type="submit" class="login-button"><?= htmlspecialchars(t('Resetlink versturen')) ?></button>
  </form>
  <p><a href="login.php" class="login-helper-link">Terug naar inloggen</a></p>
  <?php endif; ?>
</div>
<?php itsm_render_local_datetime_script(); ?>
</body>
</html>
