<?php

function change_get_operator_context( $con, $logged_in_user ) {
  $stmt = mysqli_prepare( $con, "
        SELECT id, firstname, lastname, reqforchange, simplechange, extchange, groups
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

function change_require_request_authorization( $con, $logged_in_user ) {
  $stmt = mysqli_prepare( $con, "SELECT reqforchange FROM itsm_ob_operators WHERE username = ?" );
  mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $allowed );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  if ( (int)$allowed === 0 ) {
    header( "Location: modules.php" );
    exit();
  }
}

function change_require_access( $operator ) {
  if ( (int)$operator['reqforchange'] === 0 && (int)$operator['simplechange'] === 0 && (int)$operator['extchange'] === 0 ) {
    header( "Location: modules.php" );
    exit();
  }
}

function change_request_type_map() {
  return [
    'simple' => 'Eenvoudige Wijziging',
    'extended' => 'Uitgebreide Wijziging'
  ];
}

function change_request_type_label( $type ) {
  $map = change_request_type_map();
  return $map[ $type ] ?? $map['simple'];
}

function change_format_display_number( $change ) {
  if ( !empty( $change['changenumber'] ) ) {
    return $change['changenumber'];
  }

  return '#' . $change['id'];
}

function change_format_activity_number( $activity ) {
  if ( !empty( $activity['activitynumber'] ) ) {
    return $activity['activitynumber'];
  }

  return '#' . $activity['id'];
}

function change_generate_number( $con ) {
  $prefix = 'W' . date( 'ym' );
  $like_prefix = $prefix . ' %';

  $stmt = mysqli_prepare( $con, "
        SELECT changenumber
        FROM itsm_cm_changes
        WHERE changenumber LIKE ?
        ORDER BY changenumber DESC
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['changenumber'] ) ) {
    $last_sequence = (int)substr( $row['changenumber'], -4 );
    $next_number = $last_sequence + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function change_generate_activity_number( $con ) {
  $prefix = 'WA' . date( 'ym' );
  $like_prefix = $prefix . ' %';

  $stmt = mysqli_prepare( $con, "
        SELECT activitynumber
        FROM itsm_cm_changeactivities
        WHERE activitynumber LIKE ?
        ORDER BY activitynumber DESC
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['activitynumber'] ) ) {
    $last_sequence = (int)substr( $row['activitynumber'], -4 );
    $next_number = $last_sequence + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function change_type_options() {
  return [
    'standard' => 'Standaard Wijziging',
    'normal' => 'Normale Wijziging',
    'normalcab' => 'Normale CAB Wijziging',
    'emergency' => 'Noodwijziging'
  ];
}

function change_type_label( $type ) {
  $map = change_type_options();
  return $map[ $type ] ?? $type;
}

function change_approval_state_label( $change ) {
  if ( $change['approvalstate'] === 'request' ) {
    return 'Wijzigingsaanvraag';
  }
  if ( $change['approvalstate'] === 'approved' ) {
    return change_request_type_label( $change['requesttype'] );
  }
  if ( $change['approvalstate'] === 'rejected' ) {
    return 'Afgewezen wijzigingsaanvraag';
  }

  return 'Wijziging';
}

function change_store_list_location() {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  $_SESSION['change_list_back_url'] = 'changes.php' . ( $query !== '' ? '?' . $query : '' );
}

function change_get_list_back_url( $fallback = 'cm-menu.php' ) {
  if ( !empty( $_SESSION['change_list_back_url'] ) ) {
    return $_SESSION['change_list_back_url'];
  }

  return $fallback;
}

function change_default_status_id( $statuses ) {
  foreach ( $statuses as $status ) {
    if ( (int)$status['closed'] === 0 ) {
      return (int)$status['id'];
    }
  }

  return isset( $statuses[0] ) ? (int)$statuses[0]['id'] : 0;
}

function change_find_by_id( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function change_find_operator_group_match( $links, $group_id, $operator_id ) {
  foreach ( $links as $link ) {
    if ( (int)$link['groupid'] === (int)$group_id && (int)$link['operatorid'] === (int)$operator_id ) {
      return true;
    }
  }

  return false;
}

function change_collect_group_ids_for_operator( $con, $operator_id ) {
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

function change_load_reference_data( $con ) {
  $customers = mysqli_query( $con, "SELECT id, din, name FROM itsm_ob_customers ORDER BY din ASC, name ASC" )->fetch_all( MYSQLI_ASSOC );
  $persons = mysqli_query( $con, "SELECT id, customerid, firstname, lastname, email, phone FROM itsm_ob_persons ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC );
  $categories = mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'CHANGE' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
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
  $statuses = mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'CHANGE' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
  $templates = mysqli_query( $con, "
        SELECT id, name, type, changerequesttype, categoryid, subcategoryid, description, commenttext
        FROM itsm_core_templates
        WHERE type = 'CHANGE'
        ORDER BY name ASC
    " )->fetch_all( MYSQLI_ASSOC );
  $template_activities = mysqli_query( $con, "
        SELECT id, templateid, title, description, operatorgroupid, operatorid, statusid
        FROM itsm_core_templateactivities
        ORDER BY id ASC
    " )->fetch_all( MYSQLI_ASSOC );

  return [
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
    'template_activities' => $template_activities
  ];
}

function change_validate_form( $data, $reference_data ) {
  $errors = [];

  $customer = change_find_by_id( $reference_data['customers'], $data['customerid'] );
  $person = change_find_by_id( $reference_data['persons'], $data['personid'] );
  $category = change_find_by_id( $reference_data['categories'], $data['categoryid'] );
  $subcategory = $data['subcategoryid'] ? change_find_by_id( $reference_data['subcategories'], $data['subcategoryid'] ) : null;
  $asset = $data['assetid'] ? change_find_by_id( $reference_data['assets'], $data['assetid'] ) : null;
  $group = $data['operatorgroupid'] ? change_find_by_id( $reference_data['groups'], $data['operatorgroupid'] ) : null;
  $operator = $data['operatorid'] ? change_find_by_id( $reference_data['operators'], $data['operatorid'] ) : null;
  $coordinator = $data['coordinatorid'] ? change_find_by_id( $reference_data['operators'], $data['coordinatorid'] ) : null;
  $status = $data['statusid'] ? change_find_by_id( $reference_data['statuses'], $data['statusid'] ) : null;

  if ( !array_key_exists( $data['requesttype'], change_request_type_map() ) ) {
    $errors[] = 'Selecteer een geldige wijzigingssoort.';
  }
  if ( !array_key_exists( $data['changetype'], change_type_options() ) ) {
    $errors[] = 'Selecteer een geldig wijzigingstype.';
  }
  if ( $data['requesttype'] !== 'extended' && $data['changetype'] === 'normalcab' ) {
    $errors[] = 'Normale CAB Wijziging is alleen toegestaan bij een uitgebreide wijziging.';
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
  if ( trim( $data['title'] ) === '' ) {
    $errors[] = 'Titel is verplicht.';
  }

  if ( !empty( $data['requires_status'] ) && !$status ) {
    $errors[] = 'Selecteer een geldige status.';
  }

  if ( $data['requesttype'] === 'extended' ) {
    if ( $data['coordinatorid'] && !$coordinator ) {
      $errors[] = 'Selecteer een geldige coordinator.';
    } elseif ( $group && $coordinator && !change_find_operator_group_match( $reference_data['op_links'], $group['id'], $coordinator['id'] ) ) {
      $errors[] = 'De coordinator hoort niet bij de gekozen groep.';
    }
  } else {
    if ( $data['operatorid'] && !$operator ) {
      $errors[] = 'Selecteer een geldige behandelaar.';
    } elseif ( $group && $operator && !change_find_operator_group_match( $reference_data['op_links'], $group['id'], $operator['id'] ) ) {
      $errors[] = 'De behandelaar hoort niet bij de gekozen groep.';
    }
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
    'coordinator' => $coordinator,
    'status' => $status
  ];
}

function change_validate_activity_form( $data, $reference_data ) {
  $errors = [];

  $group = $data['operatorgroupid'] ? change_find_by_id( $reference_data['groups'], $data['operatorgroupid'] ) : null;
  $operator = $data['operatorid'] ? change_find_by_id( $reference_data['operators'], $data['operatorid'] ) : null;
  $status = $data['statusid'] ? change_find_by_id( $reference_data['statuses'], $data['statusid'] ) : null;

  if ( trim( $data['title'] ) === '' ) {
    $errors[] = 'Titel van de wijzigingsactiviteit is verplicht.';
  }
  if ( $data['operatorgroupid'] && !$group ) {
    $errors[] = 'Selecteer een geldige behandelaarsgroep voor de activiteit.';
  }
  if ( $data['operatorid'] && !$operator ) {
    $errors[] = 'Selecteer een geldige behandelaar voor de activiteit.';
  } elseif ( $group && $operator && !change_find_operator_group_match( $reference_data['op_links'], $group['id'], $operator['id'] ) ) {
    $errors[] = 'De behandelaar van de activiteit hoort niet bij de gekozen groep.';
  }
  if ( !$status ) {
    $errors[] = 'Selecteer een geldige status voor de activiteit.';
  }

  return [
    'errors' => $errors,
    'group' => $group,
    'operator' => $operator,
    'status' => $status
  ];
}

function change_copy_template_activities_to_change( $con, $template_id, $change_id, $created_by ) {
  $stmt = mysqli_prepare( $con, "
        SELECT title, description, operatorgroupid, operatorid, statusid
        FROM itsm_core_templateactivities
        WHERE templateid = ?
        ORDER BY id ASC
    " );
  mysqli_stmt_bind_param( $stmt, "i", $template_id );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );

  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $activity_number = change_generate_activity_number( $con );
    $insert_stmt = mysqli_prepare( $con, "
            INSERT INTO itsm_cm_changeactivities (
                activitynumber, changeid, title, description, operatorgroupid, operatorid, statusid, createdby
            ) VALUES (?,?,?,?,?,?,?,?)
        " );
    $group_id = $row['operatorgroupid'] !== null ? (int)$row['operatorgroupid'] : null;
    $operator_id = $row['operatorid'] !== null ? (int)$row['operatorid'] : null;
    $status_id = $row['statusid'] !== null ? (int)$row['statusid'] : null;
    mysqli_stmt_bind_param(
      $insert_stmt,
      "sissiiii",
      $activity_number,
      $change_id,
      $row['title'],
      $row['description'],
      $group_id,
      $operator_id,
      $status_id,
      $created_by
    );
    mysqli_stmt_execute( $insert_stmt );
    mysqli_stmt_close( $insert_stmt );
  }

  mysqli_stmt_close( $stmt );
}
