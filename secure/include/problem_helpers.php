<?php

function problem_get_operator_context( $con, $logged_in_user ) {
  $stmt = mysqli_prepare( $con, "
        SELECT id, firstname, lastname, problems, groups
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

function problem_require_access( $operator ) {
  if ( (int)( $operator['problems'] ?? 0 ) === 0 ) {
    header( 'Location: modules.php' );
    exit();
  }
}

function problem_format_display_number( $problem ) {
  if ( !empty( $problem['problemnumber'] ) ) {
    return $problem['problemnumber'];
  }

  return '#' . $problem['id'];
}

function problem_store_list_location() {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  $_SESSION['problem_list_back_url'] = 'problems.php' . ( $query !== '' ? '?' . $query : '' );
}

function problem_get_list_back_url( $fallback = 'pm-menu.php' ) {
  if ( !empty( $_SESSION['problem_list_back_url'] ) ) {
    return $_SESSION['problem_list_back_url'];
  }

  return $fallback;
}

function problem_generate_number( $con ) {
  $prefix = 'P' . date( 'ym' );
  $like_prefix = $prefix . ' %';

  $stmt = mysqli_prepare( $con, "
        SELECT problemnumber
        FROM itsm_pm_problems
        WHERE problemnumber LIKE ?
        ORDER BY problemnumber DESC
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['problemnumber'] ) ) {
    $next_number = (int)substr( $row['problemnumber'], -4 ) + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function problem_load_reference_data( $con ) {
  return [
    'customers' => mysqli_query( $con, "SELECT id, din, name FROM itsm_ob_customers ORDER BY din ASC, name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'persons' => mysqli_query( $con, "SELECT id, customerid, firstname, lastname, email, phone FROM itsm_ob_persons ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC ),
    'categories' => mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'PROBLEM' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'subcategories' => mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'assets' => mysqli_query( $con, "
        SELECT a.id, a.objectid, t.type AS typename
        FROM itsm_am_assets a
        LEFT JOIN itsm_am_types t ON a.type = t.id
        ORDER BY a.objectid ASC
    " )->fetch_all( MYSQLI_ASSOC ),
    'groups' => mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_operatorgroups ORDER BY groupname ASC" )->fetch_all( MYSQLI_ASSOC ),
    'operators' => mysqli_query( $con, "SELECT id, firstname, lastname FROM itsm_ob_operators ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC ),
    'op_links' => mysqli_query( $con, "SELECT groupid, operatorid FROM itsm_ob_opgrouplinks" )->fetch_all( MYSQLI_ASSOC ),
    'statuses' => mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'PROBLEM' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'templates' => mysqli_query( $con, "
        SELECT id, name, type, categoryid, subcategoryid, description, commenttext
        FROM itsm_core_templates
        WHERE type = 'PROBLEM'
        ORDER BY name ASC
    " )->fetch_all( MYSQLI_ASSOC )
  ];
}

function problem_find_by_id( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function problem_find_operator_group_match( $links, $group_id, $operator_id ) {
  foreach ( $links as $link ) {
    if ( (int)$link['groupid'] === (int)$group_id && (int)$link['operatorid'] === (int)$operator_id ) {
      return true;
    }
  }

  return false;
}

function problem_default_status_id( $statuses ) {
  foreach ( $statuses as $status ) {
    if ( (int)$status['closed'] === 0 ) {
      return (int)$status['id'];
    }
  }

  return isset( $statuses[0] ) ? (int)$statuses[0]['id'] : 0;
}

function problem_default_group_id( $groups, $default_name = 'Servicedesk' ) {
  foreach ( $groups as $group ) {
    if ( strcasecmp( (string)( $group['groupname'] ?? '' ), $default_name ) === 0 ) {
      return (int)$group['id'];
    }
  }

  return 0;
}

function problem_collect_group_ids_for_operator( $con, $operator_id ) {
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

function problem_validate_form( $data, $reference_data ) {
  $errors = [];

  $customer = problem_find_by_id( $reference_data['customers'], $data['customerid'] );
  $person = problem_find_by_id( $reference_data['persons'], $data['personid'] );
  $category = problem_find_by_id( $reference_data['categories'], $data['categoryid'] );
  $subcategory = $data['subcategoryid'] ? problem_find_by_id( $reference_data['subcategories'], $data['subcategoryid'] ) : null;
  $asset = $data['assetid'] ? problem_find_by_id( $reference_data['assets'], $data['assetid'] ) : null;
  $group = $data['operatorgroupid'] ? problem_find_by_id( $reference_data['groups'], $data['operatorgroupid'] ) : null;
  $operator = $data['operatorid'] ? problem_find_by_id( $reference_data['operators'], $data['operatorid'] ) : null;
  $status = problem_find_by_id( $reference_data['statuses'], $data['statusid'] );

  if ( trim( $data['title'] ) === '' ) {
    $errors[] = 'Titel is verplicht.';
  }
  if ( !$customer ) {
    $errors[] = 'Selecteer een geldige klant.';
  }
  if ( !$person ) {
    $errors[] = 'Selecteer een geldige persoon.';
  } elseif ( $customer && (int)$person['customerid'] !== (int)$customer['id'] ) {
    $errors[] = 'De geselecteerde persoon hoort niet bij de gekozen klant.';
  }
  if ( !$category ) {
    $errors[] = 'Selecteer een geldige categorie.';
  }
  if ( $data['subcategoryid'] && !$subcategory ) {
    $errors[] = 'Selecteer een geldige subcategorie.';
  } elseif ( $subcategory && $category && (int)$subcategory['parent'] !== (int)$category['id'] ) {
    $errors[] = 'De subcategorie hoort niet bij de gekozen categorie.';
  }
  if ( $data['assetid'] && !$asset ) {
    $errors[] = 'Selecteer een geldig object.';
  }
  if ( $data['operatorgroupid'] && !$group ) {
    $errors[] = 'Selecteer een geldige behandelaarsgroep.';
  }
  if ( $data['operatorid'] ) {
    if ( !$operator ) {
      $errors[] = 'Selecteer een geldige behandelaar.';
    } elseif ( $group && !problem_find_operator_group_match( $reference_data['op_links'], $group['id'], $operator['id'] ) ) {
      $errors[] = 'De behandelaar hoort niet bij de gekozen groep.';
    }
  }
  if ( !$status ) {
    $errors[] = 'Selecteer een geldige status.';
  }

  return [
    'errors' => $errors,
    'customer' => $customer,
    'person' => $person,
    'category' => $category,
    'subcategory' => $subcategory,
    'asset' => $asset,
    'group' => $group,
    'operator' => $operator,
    'status' => $status
  ];
}
