<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/kb_helpers.php' );
require_once( __DIR__ . '/include/markdown_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  die( 'Invalid ID' );
}

$item_id = (int)$_GET['id'];
$all_items = kb_load_all_items( $con );
$item = kb_find_item_by_id( $all_items, $item_id );
if ( !$item ) {
  die( 'Kennisitem niet gevonden' );
}

$children = array_values( array_filter( $all_items, function( $row ) use ( $item_id ) {
  return isset( $row['parentid'] ) && (int)$row['parentid'] === $item_id;
} ) );
$breadcrumbs = kb_build_breadcrumbs( $all_items, $item_id );
$list_back_url = 'kb_items.php';
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php require_once(__DIR__ . '/include/back_links.php'); ?>
  <h1><?= htmlspecialchars($item['title']) ?></h1>

  <?php if ( !empty( $breadcrumbs ) ): ?>
  <p class="info-note">
    Pad:
    <?php foreach ( $breadcrumbs as $index => $crumb ): ?>
    <?php if ( $index > 0 ): ?> / <?php endif; ?>
    <a class="task-inline-link" href="view_kb_item.php?id=<?= (int)$crumb['id'] ?>"><?= htmlspecialchars($crumb['title']) ?></a>
    <?php endforeach; ?>
  </p>
  <?php endif; ?>

  <div class="inline-link-row" style="margin: 14px 0;">
    <a href="edit_kb_item.php?id=<?= $item_id ?>">Bewerken</a>
    <a href="new_kb_item.php?parentid=<?= $item_id ?>">Nieuw subitem</a>
    <?php if ( (int)$item['publicaccess'] === 1 ): ?>
    <a href="../public/view_kb_item.php?id=<?= $item_id ?>">Open publieke weergave</a>
    <?php endif; ?>
  </div>

  <div class="form-card form-card-wide">
    <div class="inline-link-row" style="margin-bottom: 12px;">
      <span class="kb-visibility-badge<?= (int)$item['publicaccess'] === 1 ? ' is-public' : '' ?>">
        <?= (int)$item['publicaccess'] === 1 ? 'Publiek zichtbaar' : 'Alleen secure zichtbaar' ?>
      </span>
      <?php if ( !empty( $item['creator_firstname'] ) || !empty( $item['creator_lastname'] ) ): ?>
      <span class="info-note">Aangemaakt door <?= htmlspecialchars(trim(($item['creator_firstname'] ?? '') . ' ' . ($item['creator_lastname'] ?? ''))) ?></span>
      <?php endif; ?>
    </div>
    <div class="kb-markdown"><?= markdown_to_html( $item['content'] ) ?></div>
  </div>

  <?php if ( !empty( $children ) ): ?>
  <div class="form-card form-card-wide" style="margin-top: 20px;">
    <h2>Subitems</h2>
    <ul class="kb-tree">
      <?php foreach ( $children as $child ): ?>
      <li>
        <a class="task-inline-link" href="view_kb_item.php?id=<?= (int)$child['id'] ?>"><?= htmlspecialchars($child['title']) ?></a>
        <span class="kb-visibility-badge<?= (int)$child['publicaccess'] === 1 ? ' is-public' : '' ?>">
          <?= (int)$child['publicaccess'] === 1 ? 'Publiek' : 'Intern' ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
