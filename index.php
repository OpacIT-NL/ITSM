<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>OpacIT ITSM</title>
<link href="include/style.css" rel="stylesheet" type="text/css">
</head>
<?php require_once(__DIR__ . '/version.php'); ?>
<body class="login-page">
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
</body>
</html>