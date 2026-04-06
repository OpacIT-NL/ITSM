<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
require_once( __DIR__ . '/../secure/include/kb_helpers.php' );
require_once( __DIR__ . '/../secure/include/markdown_helpers.php' );

$person = ssp_require_login( $con );
if ( !isset( $_GET['id'] ) || !is_numeric( $_GET['id'] ) ) {
  header( 'Location: knowledge.php' );
  exit;
}

$item_id = (int)$_GET['id'];
$items = kb_load_all_items( $con, true );
$item = kb_find_item_by_id( $items, $item_id );
if ( !$item ) {
  header( 'Location: knowledge.php' );
  exit;
}

$children = array_values( array_filter( $items, function( $row ) use ( $item_id ) {
  return isset( $row['parentid'] ) && (int)$row['parentid'] === $item_id;
} ) );
$breadcrumbs = kb_build_breadcrumbs( $items, $item_id );

ssp_page_title( $item['title'] );
ssp_render_header( $person, 'knowledge' );
?>
<section class="ssp-page-head">
  <div>
    <h2><?= htmlspecialchars($item['title']) ?></h2>
    <p>Onderdeel van de kennisbank.</p>
  </div>
  <a class="ssp-ghost-link" href="knowledge.php">Terug naar kennisbank</a>
</section>

<?php if ( !empty( $breadcrumbs ) ): ?>
<section class="ssp-panel" style="margin-bottom: 18px;">
  <div class="kb-breadcrumbs">
    <?php foreach ( $breadcrumbs as $index => $crumb ): ?>
    <?php if ( $index > 0 ): ?><span>/</span><?php endif; ?>
    <a href="view_kb_item.php?id=<?= (int)$crumb['id'] ?>"><?= htmlspecialchars($crumb['title']) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="ssp-detail-card">
  <div class="kb-markdown"><?= markdown_to_html( $item['content'] ) ?></div>
</section>

<?php if ( !empty( $children ) ): ?>
<section class="ssp-panel" style="margin-top: 18px;">
  <div class="ssp-panel-head">
    <h3>Subitems</h3>
  </div>
  <ul class="kb-tree">
    <?php foreach ( $children as $child ): ?>
    <li><a href="view_kb_item.php?id=<?= (int)$child['id'] ?>"><?= htmlspecialchars($child['title']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php ssp_render_footer(); ?>
