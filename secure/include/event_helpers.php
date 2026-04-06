<?php

function event_get_operator_context( $con, $logged_in_user ) {
  $stmt = mysqli_prepare( $con, "
        SELECT id, firstname, lastname, events, groups
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

function event_require_access( $operator ) {
  if ( (int)( $operator['events'] ?? 0 ) === 0 ) {
    header( 'Location: modules.php' );
    exit();
  }
}

function event_store_list_location() {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  $_SESSION['event_list_back_url'] = 'events.php' . ( $query !== '' ? '?' . $query : '' );
}

function event_get_list_back_url( $fallback = 'em-menu.php' ) {
  if ( !empty( $_SESSION['event_list_back_url'] ) ) {
    return $_SESSION['event_list_back_url'];
  }

  return $fallback;
}

function event_generate_number( $con ) {
  $prefix = 'E' . date( 'ym' );
  $like_prefix = $prefix . ' %';

  $stmt = mysqli_prepare( $con, "
        SELECT eventnumber
        FROM itsm_em_events
        WHERE eventnumber LIKE ?
        ORDER BY eventnumber DESC
        LIMIT 1
    " );
  mysqli_stmt_bind_param( $stmt, "s", $like_prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  $next_number = 1;
  if ( $row && !empty( $row['eventnumber'] ) ) {
    $next_number = (int)substr( $row['eventnumber'], -4 ) + 1;
  }

  return sprintf( '%s %04d', $prefix, $next_number );
}

function event_format_display_number( $event ) {
  return !empty( $event['eventnumber'] ) ? $event['eventnumber'] : '#' . $event['id'];
}

function event_load_reference_data( $con ) {
  return [
    'categories' => mysqli_query( $con, "SELECT id, name FROM itsm_core_category WHERE type = 'EVENT' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'subcategories' => mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC ),
    'assets' => mysqli_query( $con, "
        SELECT a.id, a.objectid, t.type AS typename
        FROM itsm_am_assets a
        LEFT JOIN itsm_am_types t ON a.type = t.id
        ORDER BY a.objectid ASC
    " )->fetch_all( MYSQLI_ASSOC ),
    'groups' => mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_operatorgroups ORDER BY groupname ASC" )->fetch_all( MYSQLI_ASSOC ),
    'statuses' => mysqli_query( $con, "SELECT id, name, ready, closed FROM itsm_core_status WHERE type = 'INCIDENT' ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC )
  ];
}

function event_find_by_id( $rows, $id ) {
  foreach ( $rows as $row ) {
    if ( (int)$row['id'] === (int)$id ) {
      return $row;
    }
  }

  return null;
}

function event_default_group_id( $groups, $default_name = 'Servicedesk' ) {
  foreach ( $groups as $group ) {
    if ( strcasecmp( (string)( $group['groupname'] ?? '' ), $default_name ) === 0 ) {
      return (int)$group['id'];
    }
  }

  return 0;
}

function event_default_incident_status_id( $statuses ) {
  foreach ( $statuses as $status ) {
    if ( (int)$status['closed'] === 0 ) {
      return (int)$status['id'];
    }
  }

  return isset( $statuses[0] ) ? (int)$statuses[0]['id'] : 0;
}

function event_build_incident_title( $event ) {
  $description = trim( (string)( $event['description'] ?? '' ) );
  if ( $description !== '' ) {
    $first_line = preg_split( '/\r\n|\r|\n/', $description )[0];
    $first_line = trim( $first_line );
    if ( $first_line !== '' ) {
      return function_exists( 'mb_substr' ) ? mb_substr( $first_line, 0, 255 ) : substr( $first_line, 0, 255 );
    }
  }

  return 'Incident vanuit event ' . event_format_display_number( $event );
}
