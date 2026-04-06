<?php

function kb_load_all_items( $con, $public_only = false ) {
  $sql = "
    SELECT i.*, p.title AS parent_title, o.firstname AS creator_firstname, o.lastname AS creator_lastname
    FROM itsm_km_items i
    LEFT JOIN itsm_km_items p ON i.parentid = p.id
    LEFT JOIN itsm_ob_operators o ON i.createdby = o.id
  ";

  if ( $public_only ) {
    $sql .= " WHERE i.publicaccess = 1";
  }

  $sql .= " ORDER BY COALESCE(i.parentid, 0) ASC, i.title ASC, i.id ASC";
  $result = mysqli_query( $con, $sql );

  return $result ? mysqli_fetch_all( $result, MYSQLI_ASSOC ) : [];
}

function kb_find_item_by_id( $items, $id ) {
  foreach ( $items as $item ) {
    if ( (int)$item['id'] === (int)$id ) {
      return $item;
    }
  }

  return null;
}

function kb_build_tree( $items, $parent_id = null ) {
  $branch = [];

  foreach ( $items as $item ) {
    $item_parent = isset( $item['parentid'] ) && $item['parentid'] !== null ? (int)$item['parentid'] : null;
    $wanted_parent = $parent_id !== null ? (int)$parent_id : null;
    if ( $item_parent !== $wanted_parent ) {
      continue;
    }

    $item['children'] = kb_build_tree( $items, (int)$item['id'] );
    $branch[] = $item;
  }

  return $branch;
}

function kb_flatten_tree_options( $tree, $level = 0, $exclude_id = 0 ) {
  $options = [];

  foreach ( $tree as $item ) {
    if ( (int)$item['id'] !== (int)$exclude_id ) {
      $options[] = [
        'id' => (int)$item['id'],
        'label' => str_repeat( '- ', $level ) . $item['title']
      ];
    }

    if ( !empty( $item['children'] ) ) {
      $options = array_merge( $options, kb_flatten_tree_options( $item['children'], $level + 1, $exclude_id ) );
    }
  }

  return $options;
}

function kb_is_descendant( $items, $candidate_parent_id, $current_id ) {
  $lookup = [];
  foreach ( $items as $item ) {
    $lookup[ (int)$item['id'] ] = $item;
  }

  $cursor = (int)$candidate_parent_id;
  while ( $cursor > 0 && isset( $lookup[ $cursor ] ) ) {
    if ( $cursor === (int)$current_id ) {
      return true;
    }
    $next = $lookup[ $cursor ]['parentid'] ?? null;
    $cursor = $next !== null ? (int)$next : 0;
  }

  return false;
}

function kb_render_tree( $tree, $context = 'secure' ) {
  if ( empty( $tree ) ) {
    return '<div class="kb-empty">Nog geen kennisitems beschikbaar.</div>';
  }

  ob_start();
  ?>
  <div class="kb-tree-columns">
    <?php foreach ( $tree as $item ): ?>
    <div class="kb-tree-branch">
      <?= kb_render_tree_node( $item, $context ) ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
}

function kb_render_tree_node( $item, $context = 'secure' ) {
  $view_url = $context === 'public'
    ? 'view_kb_item.php?id=' . (int)$item['id']
    : 'view_kb_item.php?id=' . (int)$item['id'];

  ob_start();
  ?>
  <div class="kb-node">
    <div class="kb-node-head">
      <a class="kb-node-link" href="<?= htmlspecialchars($view_url) ?>"><?= htmlspecialchars($item['title']) ?></a>
      <span class="kb-visibility-badge<?= (int)$item['publicaccess'] === 1 ? ' is-public' : '' ?>">
        <?= (int)$item['publicaccess'] === 1 ? 'Publiek' : 'Intern' ?>
      </span>
    </div>
    <?php if ( !empty( $item['children'] ) ): ?>
    <ul class="kb-tree">
      <?php foreach ( $item['children'] as $child ): ?>
      <li><?= kb_render_tree_node( $child, $context ) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

function kb_build_breadcrumbs( $items, $item_id ) {
  $lookup = [];
  foreach ( $items as $item ) {
    $lookup[ (int)$item['id'] ] = $item;
  }

  $breadcrumbs = [];
  $cursor = (int)$item_id;
  while ( $cursor > 0 && isset( $lookup[ $cursor ] ) ) {
    array_unshift( $breadcrumbs, $lookup[ $cursor ] );
    $parent_id = $lookup[ $cursor ]['parentid'] ?? null;
    $cursor = $parent_id !== null ? (int)$parent_id : 0;
  }

  return $breadcrumbs;
}
