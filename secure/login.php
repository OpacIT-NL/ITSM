<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ITSM Behandelaar</title>
<link href="include/login.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<?php require_once(__DIR__ . '/../my.php'); ?>
<?php require_once(__DIR__ . '/include/news_helpers.php'); ?>
<body class="login-page">
<?= news_render_banners( news_fetch_items( $con, 'login', 10 ) ) ?>
<div class="login-box">
  <h1>ITSM Behandelaar</h1>
  <h2>Inloggen</h2>
  <?php if (isset($_GET['expired'])): ?>
  <p class="error">Je sessie is verlopen. Log opnieuw in.</p>
  <?php endif; ?>
  <?php if (isset($_GET['incorrect'])): ?>
  <p class="error">Gebruikersnaam of wachtwoord is incorrect.</p>
  <?php endif; ?>
  <form action="authenticate.php" method="post" class="login-form">
    <div class="input-group"> <i class="fas fa-user"></i>
      <input type="text" name="username" placeholder="Gebruikersnaam" required>
    </div>
    <div class="input-group"> <i class="fas fa-lock"></i>
      <input type="password" name="password" placeholder="Wachtwoord" required>
    </div>
    <button type="submit" class="login-button">Login</button>
  </form>
</div>
<?php itsm_render_local_datetime_script(); ?>
</body>
</html>
