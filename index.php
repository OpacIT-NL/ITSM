<?php
require_once( __DIR__ . '/my.php' );
require_once( __DIR__ . '/secure/include/news_helpers.php' );
require_once( __DIR__ . '/version.php' );
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ITSM</title>
<link href="include/style.css" rel="stylesheet" type="text/css">
</head>
<body class="login-page">
<?= news_render_banners( news_fetch_items( $con, 'login', 10 ) ) ?>
<div class="login-container">
  <div class="login-box">
    <h1>OpacIT ITSM</h1>
    <a href="/public" class="login-link">Self Service Portaal</a> <a href="/secure" class="login-link">Behandelaarsportaal</a>
    <hr>
    <sub>
    <?= htmlspecialchars($version) ?>
    <br>
    ©OpacIT 2026 </sub> </div>
</div>
<?php itsm_render_local_datetime_script(); ?>
</body>
</html>
