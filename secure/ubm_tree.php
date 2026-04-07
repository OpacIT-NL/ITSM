<?php
session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/ubm_helpers.php' );

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

$logged_in_user = $_SESSION['name'];
$operator_context = ubm_get_operator_context( $con, $logged_in_user );
ubm_require_access( $operator_context );

$items = ubm_load_tree_items( $con );
$children_by_parent = ubm_group_tree_by_parent( $items );
$initiatives = $children_by_parent[0] ?? [];
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <center>
    <h1>UBM Overzicht per Initiative</h1>
  </center>

  <div class="form-wrapper">
    <div class="form-card form-card-wide">
      <p class="info-note">Dit overzicht toont de volledige UBM-structuur per initiative: epic, feature, story en subtask.</p>
      <div class="form-actions">
        <a class="btn-primary" href="new_ubm_item.php?type=initiative">Nieuwe Initiative</a>
      </div>
    </div>
  </div>

  <div class="ubm-tree-overview">
    <?php if ( empty( $initiatives ) ): ?>
    <div class="form-wrapper">
      <div class="form-card form-card-wide">
        <p>Nog geen initiatives.</p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ( $initiatives as $initiative ): ?>
    <section class="ubm-tree-initiative">
      <div class="ubm-tree-initiative-head">
        <div>
          <h2><a href="edit_ubm_item.php?id=<?= (int)$initiative['id'] ?>"><?= htmlspecialchars($initiative['title']) ?></a></h2>
          <div class="ubm-tree-meta">
            <?php if ( !empty( $initiative['status_name'] ) ): ?><span><?= htmlspecialchars($initiative['status_name']) ?></span><?php endif; ?>
            <?php if ( !empty( $initiative['groupname'] ) ): ?><span><?= htmlspecialchars($initiative['groupname']) ?></span><?php endif; ?>
            <?php if ( !empty( $initiative['operator_name'] ) ): ?><span><?= htmlspecialchars($initiative['operator_name']) ?></span><?php endif; ?>
          </div>
        </div>
        <a class="btn-primary" href="new_ubm_item.php?parentid=<?= (int)$initiative['id'] ?>&type=epic">Nieuwe Epic</a>
      </div>
      <?= ubm_render_tree_nodes( $children_by_parent, (int)$initiative['id'] ) ?>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
