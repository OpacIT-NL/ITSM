<?php
require_once( __DIR__ . '/my.php' );
require_once( __DIR__ . '/secure/include/news_helpers.php' );
require_once( __DIR__ . '/version.php' );
$status_items = news_fetch_items( $con, 'login' );
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Status</title>
<link href="include/style.css" rel="stylesheet" type="text/css">
</head>
<body class="login-page">
<div class="login-container">
	
  <div class="login-box status-box">
	  <a href="index.php">Ga terug</a>
    <h1>Actuele statusmeldingen</h1>
    <?php if ( empty( $status_items ) ): ?>
    <p>Er zijn op dit moment geen verstoringen bekend.</p>
    <?php else: ?>
    <div class="status-news-list">
      <?php foreach ( $status_items as $item ): ?>
      <article class="status-news-item <?= htmlspecialchars(news_type_css_class($item['newstype'])) ?>">
        <div class="status-news-meta">
          <strong><?= htmlspecialchars(news_type_label($item['newstype'])) ?></strong>
          <span><?= htmlspecialchars($item['createdat']) ?></span>
        </div>
        <h2><?= htmlspecialchars($item['title']) ?></h2>
        <div class="status-news-body"><?= nl2br(htmlspecialchars($item['message'])) ?></div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <hr>
    <sub>
    <?= htmlspecialchars($version) ?>
    <br>
    ©OpacIT 2026
    </sub>
  </div>
</div>
<?php itsm_render_local_datetime_script(); ?>
</body>
</html>
