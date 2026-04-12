<?php

require_once( __DIR__ . '/template_helpers.php' );
require_once( __DIR__ . '/priority_helpers.php' );

function incident_get_operator_context( $con, $logged_in_user ) {
  $stmt = mysqli_prepare( $con, "
        SELECT id, firstname, lastname, firstlineincidents, secondlineincidents, groups
        FROM itsm_ob_operators
        WHERE username = ?
    " );
  mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $operator = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  if ( !$operator ) {
    die( "Behandelaar niet gevonden" );
  }

  return $operator;
}

function incident_require_firstline_authorization( $con, $logged_in_user ) {
  $sql2 = "SELECT firstlineincidents FROM itsm_ob_operators WHERE username = ?";
  $result2 = mysqli_prepare( $con, $sql2 );
  mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
  mysqli_stmt_execute( $result2 );
  mysqli_stmt_bind_result( $result2, $operators );
  mysqli_stmt_fetch( $result2 );
  mysqli_stmt_close( $result2 );
  if ( $operators == 0 ) {
    header( "Location: modules.php" );
    exit();
  }
}

function incident_require_access( $operator ) {
  if ( (int)$operator['firstlineincidents'] === 0 && (int)$operator['secondlineincidents'] === 0 ) {
    header( "Location: modules.php" );
    exit();
  }
}

function incident_mode_map() {
  return [
    'firstline' => t( 'incident.mode.firstline' ),
    'secondline' => t( 'incident.mode.secondline' ),
    'major' => t( 'incident.mode.major' )
  ];
}

function incident_normalize_mode( $mode ) {
  $modes = incident_mode_map();
  return array_key_exists( $mode, $modes ) ? $mode : 'firstline';
}

function incident_mode_label( $mode ) {
  $modes = incident_mode_map();
  return $modes[ incident_normalize_mode( $mode ) ];
}

function incident_format_display_number( $incident ) {
  if ( !empty( $incident['incidentnumber'] ) ) {
    return $incident['incidentnumber'];
  }

  return '#' . $incident['id'];
}

function incident_store_list_location() {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  $_SESSION['incident_list_back_url'] = 'incidents.php' . ( $query !== '' ? '?' . $query : '' );
}

function incident_get_list_back_url( $fallback = 'im_menu.php' ) {
  if ( !empty( $_SESSION['incident_list_back_url'] ) ) {
    return $_SESSION['incident_list_back_url'];
  }

  return $fallback;
}

function incident_generate_number( $con ) {
  $prefix = 'I' . date( 'ym' );
  $like_prefix = $prefix . ' %';

  $stmt = mysqli_prepare( $con, "
        SELECT incidentnumber
        FROM itsm_im_incidents
        WHERE incidentnumber LIKE ?
        ORDER BY incidentnumber DESC
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['incidentnumber'] ) ) {
    $last_sequence = (int)substr( $row['incidentnumber'], -4 );
    $next_number = $last_sequence + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function incident_load_reference_data( $con ) {
  $customers = mysqli_query( $con, "SELECT id, din, name FROM itsm_ob_customers ORDER BY din ASC, name ASC" )->fetch_all( MYSQLI_ASSOC );
  $persons = mysqli_query( $con, "SELECT id, customerid, firstname, lastname, email, phone FROM itsm_ob_persons ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC );
  $categories = mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'INCIDENT' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
  $subcategories = mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
  $assets = mysqli_query( $con, "
        SELECT a.id, a.objectid, t.type AS typename
        FROM itsm_am_assets a
        LEFT JOIN itsm_am_types t ON a.type = t.id
        ORDER BY a.objectid ASC
    " )->fetch_all( MYSQLI_ASSOC );
  $groups = mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_operatorgroups ORDER BY groupname ASC" )->fetch_all( MYSQLI_ASSOC );
  $operators = mysqli_query( $con, "SELECT id, firstname, lastname FROM itsm_ob_operators ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC );
  $op_links = mysqli_query( $con, "SELECT groupid, operatorid FROM itsm_ob_opgrouplinks" )->fetch_all( MYSQLI_ASSOC );
  $statuses = mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'INCIDENT' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
  $templates = mysqli_query( $con, "
        SELECT id, name, type, categoryid, subcategoryid, description, commenttext
        FROM itsm_core_templates
        WHERE type = 'INCIDENT'
        ORDER BY name ASC
    " )->fetch_all( MYSQLI_ASSOC );
  $templates = itsm_operator_template_filter( $templates );
  $major_incidents = mysqli_query( $con, "
        SELECT i.id, i.incidentnumber, i.title
        FROM itsm_im_incidents i
        LEFT JOIN itsm_core_status s ON i.statusid = s.id
        WHERE i.incidenttype = 'major'
          AND IFNULL(s.closed, 0) = 0
        ORDER BY i.id DESC
    " )->fetch_all( MYSQLI_ASSOC );

  return priority_merge_reference_data( $con, [
    'customers' => $customers,
    'persons' => $persons,
    'categories' => $categories,
    'subcategories' => $subcategories,
    'assets' => $assets,
    'groups' => $groups,
    'operators' => $operators,
    'op_links' => $op_links,
    'statuses' => $statuses,
    'templates' => $templates,
    'major_incidents' => $major_incidents
  ] );
}

function incident_find_by_id( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function incident_find_operator_group_match( $links, $group_id, $operator_id ) {
  foreach ( $links as $link ) {
    if ( (int)$link['groupid'] === (int)$group_id && (int)$link['operatorid'] === (int)$operator_id ) {
      return true;
    }
  }

  return false;
}

function incident_default_status_id( $statuses ) {
  foreach ( $statuses as $status ) {
    if ( (int)$status['closed'] === 0 ) {
      return (int)$status['id'];
    }
  }

  return isset( $statuses[0] ) ? (int)$statuses[0]['id'] : 0;
}

function incident_default_group_id( $groups, $default_name = 'Servicedesk' ) {
  foreach ( $groups as $group ) {
    if ( strcasecmp( (string)( $group['groupname'] ?? '' ), $default_name ) === 0 ) {
      return (int)$group['id'];
    }
  }

  return 0;
}

function incident_find_major_incident( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function incident_validate_form( $data, $reference_data ) {
  $errors = [];

  $customer = incident_find_by_id( $reference_data['customers'], $data['customerid'] );
  $person = incident_find_by_id( $reference_data['persons'], $data['personid'] );
  $category = incident_find_by_id( $reference_data['categories'], $data['categoryid'] );
  $subcategory = $data['subcategoryid'] ? incident_find_by_id( $reference_data['subcategories'], $data['subcategoryid'] ) : null;
  $asset = $data['assetid'] ? incident_find_by_id( $reference_data['assets'], $data['assetid'] ) : null;
  $group = $data['operatorgroupid'] ? incident_find_by_id( $reference_data['groups'], $data['operatorgroupid'] ) : null;
  $operator = $data['operatorid'] ? incident_find_by_id( $reference_data['operators'], $data['operatorid'] ) : null;
  $status = incident_find_by_id( $reference_data['statuses'], $data['statusid'] );
  $major_incident = $data['majorincidentid'] ? incident_find_major_incident( $reference_data['major_incidents'], $data['majorincidentid'] ) : null;
  $priority = priority_validate_selection( $data, $reference_data );
  $errors = array_merge( $errors, $priority['errors'] );

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
  if ( !$status ) {
    $errors[] = 'Selecteer een geldige status.';
  }
  if ( !empty( $data['current_incident_id'] ) && (int)$data['majorincidentid'] === (int)$data['current_incident_id'] ) {
    $errors[] = 'Een incident kan niet aan zichzelf gekoppeld worden als major.';
  }
  if ( $data['majorincidentid'] && !$major_incident ) {
    $errors[] = 'Selecteer een geldig major incident.';
  }
  if ( $data['incidenttype'] === 'major' && $data['majorincidentid'] ) {
    $errors[] = 'Een major incident kan niet aan een ander major incident gekoppeld worden.';
  }
  if ( $data['operatorid'] ) {
    if ( !$operator ) {
      $errors[] = 'Selecteer een geldige behandelaar.';
    } elseif ( $group && !incident_find_operator_group_match( $reference_data['op_links'], $group['id'], $operator['id'] ) ) {
      $errors[] = 'De behandelaar hoort niet bij de gekozen groep.';
    }
  }
  if ( trim( $data['title'] ) === '' ) {
    $errors[] = 'Titel is verplicht.';
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
    'status' => $status,
    'major_incident' => $major_incident,
    'priority' => $priority
  ];
}

function incident_collect_group_ids_for_operator( $con, $operator_id ) {
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

function incident_mode_transition_options( $current_mode ) {
  if ( $current_mode === 'firstline' ) {
    return [ 'secondline', 'major' ];
  }

  return [];
}
