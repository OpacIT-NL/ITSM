<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
require_once( __DIR__ . '/../secure/include/kb_helpers.php' );

$person = ssp_require_login( $con );
$items = kb_load_all_items( $con, true );
$tree = kb_build_tree( $items );

ssp_page_title( 'Kennisbank' );
ssp_render_header( $person, 'knowledge' );
?>
<section class="ssp-page-head">
  <div>
    <h2>Kennisbank</h2>
    <p>Zoek door handleidingen, procedures en veelgestelde vragen.</p>
  </div>
</section>

<section class="ssp-panel">
  <?= kb_render_tree( $tree, 'public' ) ?>
</section>
<?php ssp_render_footer(); ?>
