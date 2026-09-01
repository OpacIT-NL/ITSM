<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/kb_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}

$visibility = $_GET['visibility'] ?? 'all';
$public_only = $visibility === 'public';
$items = kb_load_all_items( $con, $public_only );
$tree = kb_build_tree( $items );
$module_back_url = 'kb-menu.php';
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php require_once(__DIR__ . '/include/module_links.php'); ?>
  <h1><?= htmlspecialchars($public_only ? t('Publieke kennisitems') : t('Alle kennisitems')) ?></h1>
  <div class="inline-link-row">
    <a href="new_kb_item.php"><?= htmlspecialchars(t('Nieuw kennisitem')) ?></a>
    <?php if ( $public_only ): ?>
    <a href="kb_items.php"><?= htmlspecialchars(t('Toon alle kennisitems')) ?></a>
    <?php else: ?>
    <a href="kb_items.php?visibility=public"><?= htmlspecialchars(t('Toon alleen publieke kennisitems')) ?></a>
    <?php endif; ?>
  </div>
  <div class="kb-tree-wrap">
    <?= kb_render_tree( $tree, 'secure' ) ?>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
