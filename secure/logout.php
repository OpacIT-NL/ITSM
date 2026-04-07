<?php
session_start();
session_destroy();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Uitloggen</title>
</head>
<body>
<script>
try {
  const prefixes = [
    'itsm_secure_tab_draft_v1:',
    'itsm_secure_tab_scroll_v1:',
    'itsm_secure_presence_token_v1:'
  ];
  sessionStorage.removeItem('itsm_secure_tabs_v1');
  sessionStorage.removeItem('itsm_secure_active_tab_key_v1');
  Object.keys(sessionStorage).forEach((key) => {
    if (prefixes.some((prefix) => key.startsWith(prefix))) {
      sessionStorage.removeItem(key);
    }
  });
} catch (error) {}
window.location.href = '../index.php';
</script>
<noscript><a href="../index.php">Terug naar startpagina</a></noscript>
</body>
</html>
