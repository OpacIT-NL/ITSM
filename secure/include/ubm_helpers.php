<?php

function ubm_get_operator_context( $con, $logged_in_user ) {
  $stmt = mysqli_prepare( $con, "
        SELECT id, firstname, lastname, ubm, groups
        FROM itsm_ob_operators
        WHERE username = ?
    " );
  mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $operator = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$operator ) {
    die( 'Behandelaar niet gevonden' );
  }

  return $operator;
}

function ubm_require_access( $operator ) {
  if ( (int)( $operator['ubm'] ?? 0 ) === 0 ) {
    header( 'Location: modules.php' );
    exit();
  }
}

function ubm_type_map() {
  return [
    'initiative' => 'Initiative',
    'epic' => 'Epic',
    'feature' => 'Feature',
    'story' => 'Story',
    'subtask' => 'Subtask'
  ];
}

function ubm_type_label( $type ) {
  $map = ubm_type_map();
  return $map[$type] ?? $type;
}

function ubm_type_prefix_map() {
  return [
    'initiative' => 'INI',
    'epic' => 'EPI',
    'feature' => 'FEA',
    'story' => 'STR',
    'subtask' => 'SUB'
  ];
}

function ubm_type_prefix( $type ) {
  $map = ubm_type_prefix_map();
  return $map[$type] ?? 'TSK';
}

function ubm_generate_number( $con, $itemtype ) {
  $prefix = ubm_type_prefix( $itemtype ) . date( 'ym' );
  $like_prefix = $prefix . ' %';

  $stmt = mysqli_prepare( $con, "
        SELECT ubmnumber
        FROM itsm_ubm_items
        WHERE ubmnumber LIKE ?
        ORDER BY ubmnumber DESC
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['ubmnumber'] ) ) {
    $next_number = (int)substr( $row['ubmnumber'], -4 ) + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function ubm_format_display_number( $item ) {
  return !empty( $item['ubmnumber'] ) ? $item['ubmnumber'] : ubm_type_prefix( $item['itemtype'] ?? '' ) . ' #' . (int)$item['id'];
}

function ubm_allowed_child_types( $parent_type ) {
  $order = array_keys( ubm_type_map() );
  $index = array_search( $parent_type, $order, true );
  if ( $index === false || $index >= count( $order ) - 1 ) {
    return [];
  }

  return [ $order[$index + 1] ];
}

function ubm_store_list_location() {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  $_SESSION['ubm_list_back_url'] = 'ubm_items.php' . ( $query !== '' ? '?' . $query : '' );
}

function ubm_get_list_back_url( $fallback = 'ubm-menu.php' ) {
  if ( !empty( $_SESSION['ubm_list_back_url'] ) ) {
    return $_SESSION['ubm_list_back_url'];
  }

  return $fallback;
}

function ubm_load_reference_data( $con ) {
  return [
    'groups' => mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_operatorgroups ORDER BY groupname ASC" )->fetch_all( MYSQLI_ASSOC ),
    'operators' => mysqli_query( $con, "SELECT id, firstname, lastname FROM itsm_ob_operators ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC ),
    'op_links' => mysqli_query( $con, "SELECT groupid, operatorid FROM itsm_ob_opgrouplinks" )->fetch_all( MYSQLI_ASSOC ),
    'statuses' => mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'UBM' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'categories' => mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'UBM' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'subcategories' => mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC )
  ];
}

function ubm_find_by_id( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function ubm_find_operator_group_match( $links, $group_id, $operator_id ) {
  foreach ( $links as $link ) {
    if ( (int)$link['groupid'] === (int)$group_id && (int)$link['operatorid'] === (int)$operator_id ) {
      return true;
    }
  }

  return false;
}

function ubm_default_status_id( $statuses ) {
  foreach ( $statuses as $status ) {
    if ( (int)$status['closed'] === 0 ) {
      return (int)$status['id'];
    }
  }

  return isset( $statuses[0] ) ? (int)$statuses[0]['id'] : 0;
}

function ubm_collect_group_ids_for_operator( $con, $operator_id ) {
  $stmt = mysqli_prepare( $con, "SELECT groupid FROM itsm_ob_opgrouplinks WHERE operatorid = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $operator_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $group_ids = [];
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $group_ids[] = (int)$row['groupid'];
  }
  mysqli_stmt_close( $stmt );

  return $group_ids;
}

function ubm_load_tree_items( $con ) {
  $items = [];
  $result = mysqli_query( $con, "
    SELECT
      u.*,
      c.name AS category_name,
      sub.name AS subcategory_name,
      s.name AS status_name,
      IFNULL(s.closed, 0) AS status_closed,
      g.groupname,
      CONCAT(o.lastname, ', ', o.firstname) AS operator_name
    FROM itsm_ubm_items u
    LEFT JOIN itsm_core_category c ON u.categoryid = c.id
    LEFT JOIN itsm_core_subcategory sub ON u.subcategoryid = sub.id
    LEFT JOIN itsm_core_status s ON u.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups g ON u.operatorgroupid = g.id
    LEFT JOIN itsm_ob_operators o ON u.operatorid = o.id
    ORDER BY FIELD(u.itemtype, 'initiative', 'epic', 'feature', 'story', 'subtask'), u.title ASC, u.id ASC
  " );

  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $items[] = $row;
  }

  return $items;
}

function ubm_group_tree_by_parent( $items ) {
  $children = [];
  foreach ( $items as $item ) {
    $parent_id = !empty( $item['parentid'] ) ? (int)$item['parentid'] : 0;
    if ( !isset( $children[$parent_id] ) ) {
      $children[$parent_id] = [];
    }
    $children[$parent_id][] = $item;
  }

  return $children;
}

function ubm_render_tree_nodes( $children_by_parent, $parent_id = 0 ) {
  if ( empty( $children_by_parent[$parent_id] ) ) {
    return '';
  }

  ob_start();
  ?>
  <ul class="ubm-tree-list">
    <?php foreach ( $children_by_parent[$parent_id] as $item ): ?>
    <li>
      <div class="ubm-tree-node <?= (int)($item['status_closed'] ?? 0) === 1 ? 'is-closed' : '' ?>">
        <div class="ubm-tree-node-head">
          <a href="edit_ubm_item.php?id=<?= (int)$item['id'] ?>"><?= htmlspecialchars(ubm_format_display_number($item)) ?> - <?= htmlspecialchars($item['title']) ?></a>
          <span class="ubm-tree-type"><?= htmlspecialchars(ubm_type_label($item['itemtype'])) ?></span>
        </div>
        <div class="ubm-tree-meta">
          <?php if ( !empty( $item['status_name'] ) ): ?><span><?= htmlspecialchars($item['status_name']) ?></span><?php endif; ?>
          <?php if ( !empty( $item['groupname'] ) ): ?><span><?= htmlspecialchars($item['groupname']) ?></span><?php endif; ?>
          <?php if ( !empty( $item['operator_name'] ) ): ?><span><?= htmlspecialchars($item['operator_name']) ?></span><?php endif; ?>
          <?php if ( !empty( $item['category_name'] ) ): ?><span><?= htmlspecialchars($item['category_name']) ?></span><?php endif; ?>
          <?php if ( !empty( $item['subcategory_name'] ) ): ?><span><?= htmlspecialchars($item['subcategory_name']) ?></span><?php endif; ?>
        </div>
      </div>
      <?= ubm_render_tree_nodes( $children_by_parent, (int)$item['id'] ) ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php
  return ob_get_clean();
}

function ubm_validate_form( $data, $reference_data, $parent_item = null ) {
  $errors = [];
  $status = ubm_find_by_id( $reference_data['statuses'], $data['statusid'] );
  $group = $data['operatorgroupid'] ? ubm_find_by_id( $reference_data['groups'], $data['operatorgroupid'] ) : null;
  $operator = $data['operatorid'] ? ubm_find_by_id( $reference_data['operators'], $data['operatorid'] ) : null;
  $category = $data['categoryid'] ? ubm_find_by_id( $reference_data['categories'], $data['categoryid'] ) : null;
  $subcategory = $data['subcategoryid'] ? ubm_find_by_id( $reference_data['subcategories'], $data['subcategoryid'] ) : null;

  if ( !array_key_exists( $data['itemtype'], ubm_type_map() ) ) {
    $errors[] = 'Selecteer een geldig type.';
  }
  if ( trim( $data['title'] ) === '' ) {
    $errors[] = 'Titel is verplicht.';
  }
  if ( !$status ) {
    $errors[] = 'Selecteer een geldige status.';
  }
  if ( $data['categoryid'] && !$category ) {
    $errors[] = 'Selecteer een geldige categorie.';
  }
  if ( $data['subcategoryid'] && !$subcategory ) {
    $errors[] = 'Selecteer een geldige subcategorie.';
  } elseif ( $category && $subcategory && (int)$subcategory['parent'] !== (int)$category['id'] ) {
    $errors[] = 'De subcategorie hoort niet bij de gekozen categorie.';
  }
  if ( $data['operatorgroupid'] && !$group ) {
    $errors[] = 'Selecteer een geldige groep.';
  }
  if ( $data['operatorid'] && !$operator ) {
    $errors[] = 'Selecteer een geldige behandelaar.';
  } elseif ( $group && $operator && !ubm_find_operator_group_match( $reference_data['op_links'], $group['id'], $operator['id'] ) ) {
    $errors[] = 'De behandelaar hoort niet bij de gekozen groep.';
  }
  if ( $parent_item ) {
    $allowed_children = ubm_allowed_child_types( $parent_item['itemtype'] );
    if ( !in_array( $data['itemtype'], $allowed_children, true ) ) {
      $errors[] = 'Onder ' . ubm_type_label( $parent_item['itemtype'] ) . ' mag alleen ' . implode( ', ', array_map( 'ubm_type_label', $allowed_children ) ) . ' worden aangemaakt.';
    }
  } elseif ( $data['parentid'] ) {
    $errors[] = 'Ouderitem niet gevonden.';
  } elseif ( $data['itemtype'] !== 'initiative' ) {
    $errors[] = 'Alleen een Initiative mag zonder bovenliggend item worden aangemaakt.';
  }

  return [
    'errors' => $errors,
    'status' => $status,
    'category' => $category,
    'subcategory' => $subcategory,
    'group' => $group,
    'operator' => $operator
  ];
}
