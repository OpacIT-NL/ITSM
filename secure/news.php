<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

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
news_require_firstline_authorization( $con, $logged_in_user, 'news-menu.php' );
$items = news_fetch_items( $con, 'all' );
$module_back_url = 'news-menu.php';
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php require_once(__DIR__ . '/include/module_links.php'); ?>
  <h1><?= htmlspecialchars(t('Nieuwsbeheer')) ?></h1>
  <div class="inline-link-row">
    <a href="new_news.php"><?= htmlspecialchars(t('Nieuw bericht')) ?></a>
  </div>
  <div class="results">
    <table border="0" class="results incident-results-table" data-preview-comments="off" data-preview-description-label="<?= htmlspecialchars(t('Bericht'), ENT_QUOTES) ?>" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;"><?= htmlspecialchars(t('Type')) ?></th>
          <th style="text-align: start;"><?= htmlspecialchars(t('Titel')) ?></th>
          <th style="text-align: start;">SelfService</th>
          <th style="text-align: start;"><?= htmlspecialchars(t('Behandelaars Home')) ?></th>
          <th style="text-align: start;"><?= htmlspecialchars(t('Inlogpagina')) ?></th>
          <th style="text-align: start;"><?= htmlspecialchars(t('Aangemaakt')) ?></th>
          <th style="text-align: start;"><?= htmlspecialchars(t('Actie')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ( empty( $items ) ): ?>
        <tr><td colspan="7"><?= htmlspecialchars(t('Nog geen nieuwsberichten.')) ?></td></tr>
        <?php else: ?>
        <?php foreach ( $items as $item ): ?>
        <tr data-table-open-url="edit_news.php?id=<?= (int)$item['id'] ?>" data-preview-description="<?= htmlspecialchars((string)($item['message'] ?? ''), ENT_QUOTES) ?>" data-preview-comments="">
          <td><?= htmlspecialchars(news_type_label($item['newstype'])) ?></td>
          <td><?= htmlspecialchars($item['title']) ?></td>
          <td><?= htmlspecialchars((int)$item['showssp'] === 1 ? t('Ja') : t('Nee')) ?></td>
          <td><?= htmlspecialchars((int)$item['showoperatorhome'] === 1 ? t('Ja') : t('Nee')) ?></td>
          <td><?= htmlspecialchars((int)$item['showlogin'] === 1 ? t('Ja') : t('Nee')) ?></td>
          <td><?= htmlspecialchars($item['createdat']) ?></td>
          <td class="tblaction"><a href="edit_news.php?id=<?= (int)$item['id'] ?>"><?= htmlspecialchars(t('Open bericht')) ?></a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
