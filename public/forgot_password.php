<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/../secure/include/news_helpers.php' );
require_once( __DIR__ . '/include/password_reset_helpers.php' );

$sent = isset( $_GET['sent'] );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $email = trim( (string)( $_POST['email'] ?? '' ) );
  if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
    $person = ssp_reset_find_person_by_email( $con, $email );
    if ( $person ) {
      try {
        $token_data = ssp_reset_create_token( $con, (int)$person['id'], 60 );
        if ( $token_data ) {
          ssp_reset_send_mail( $person, $token_data );
        }
      } catch ( Exception $exception ) {
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
<title>ITSM SelfService - Wachtwoord vergeten</title>
<link href="include/login.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<body class="login-page">
<?= news_render_banners( news_fetch_items( $con, 'login', 10 ) ) ?>
<div class="login-box">
  <h1>ITSM SelfService</h1>
  <h2>Wachtwoord vergeten</h2>
  <?php if ( $sent ): ?>
  <p class="success">Als dit e-mailadres bekend is voor SelfService, ontvang je binnen enkele minuten een resetlink.</p>
  <p><a href="login.php" class="login-helper-link">Terug naar inloggen</a></p>
  <?php else: ?>
  <p class="login-hint">Vul je e-mailadres in. We sturen dan een beveiligde link waarmee je je wachtwoord opnieuw kunt instellen.</p>
  <form action="forgot_password.php" method="post" class="login-form">
    <div class="input-group"> <i class="fas fa-envelope"></i>
      <input type="email" name="email" placeholder="E-mail" required>
    </div>
    <button type="submit" class="login-button">Resetlink versturen</button>
  </form>
  <p><a href="login.php" class="login-helper-link">Terug naar inloggen</a></p>
  <?php endif; ?>
</div>
<?php itsm_render_local_datetime_script(); ?>
</body>
</html>
