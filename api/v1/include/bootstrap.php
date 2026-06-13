<?php

require_once( __DIR__ . '/../../../include/api_token_helpers.php' );

function api_db_connect() {
  $config = parse_ini_file( __DIR__ . '/../../../../config/sql.ini' );
  $con = mysqli_connect( $config['servername'], $config['username'], $config['password'], $config['dbname'] );
  if ( $con === false ) {
    api_error( 500, 'database_connection_failed', mysqli_connect_error() );
  }
  return $con;
}

function api_json( $data, $status = 200 ) {
  http_response_code( $status );
  header( 'Content-Type: application/json; charset=utf-8' );
  echo json_encode( $data, JSON_UNESCAPED_SLASHES );
  exit;
}

function api_error( $status, $code, $message = '' ) {
  api_json( [
    'error' => [
      'code' => $code,
      'message' => $message !== '' ? $message : $code
    ]
  ], $status );
}

function api_get_bearer_token() {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
  if ( $header === '' && function_exists( 'apache_request_headers' ) ) {
    $headers = apache_request_headers();
    $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  }
  if ( !preg_match( '/^Bearer\s+(.+)$/i', $header, $matches ) ) {
    return '';
  }
  return trim( $matches[1] );
}

function api_authenticate( $con ) {
  itsm_ensure_api_token_table( $con );
  $token = api_get_bearer_token();
  if ( $token === '' ) {
    api_error( 401, 'missing_bearer_token', 'Use Authorization: Bearer <token>.' );
  }

  $prefix = itsm_api_token_prefix( $token );
  $stmt = mysqli_prepare( $con, "
    SELECT
      t.id AS tokenid,
      t.token_hash,
      t.expiresat,
      o.*
    FROM itsm_api_tokens t
    INNER JOIN itsm_ob_operators o ON o.id = t.operatorid
    WHERE t.active = 1
      AND t.token_prefix = ?
      AND o.allowlogin = 1
  " );
  mysqli_stmt_bind_param( $stmt, 's', $prefix );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    if ( !empty( $row['expiresat'] ) && strtotime( $row['expiresat'] ) < time() ) {
      continue;
    }
    if ( password_verify( $token, $row['token_hash'] ) ) {
      $update = mysqli_prepare( $con, "UPDATE itsm_api_tokens SET lastusedat = NOW() WHERE id = ?" );
      mysqli_stmt_bind_param( $update, 'i', $row['tokenid'] );
      mysqli_stmt_execute( $update );
      mysqli_stmt_close( $update );
      unset( $row['token_hash'], $row['password'] );
      return $row;
    }
  }
  mysqli_stmt_close( $stmt );
  api_error( 401, 'invalid_bearer_token', 'The bearer token is invalid or expired.' );
}

function api_request_json() {
  $raw = file_get_contents( 'php://input' );
  if ( trim( $raw ) === '' ) {
    return [];
  }
  $data = json_decode( $raw, true );
  if ( !is_array( $data ) ) {
    api_error( 400, 'invalid_json', 'Request body must be a JSON object.' );
  }
  return $data;
}

function api_resource_maps() {
  return [
    'assets' => [ 'table' => 'itsm_am_assets', 'permission' => 'assets' ],
    'asset_types' => [ 'table' => 'itsm_am_types', 'permission' => 'assets' ],
    'asset_fields' => [ 'table' => 'itsm_am_fields', 'permission' => 'assets' ],
    'asset_configurations' => [ 'table' => 'itsm_am_configurations', 'permission' => 'assets' ],
    'asset_configuration_assets' => [ 'table' => 'itsm_am_configurationassets', 'permission' => 'assets' ],
    'asset_connection_types' => [ 'table' => 'itsm_am_connectiontypes', 'permission' => 'assets' ],
    'asset_connections' => [ 'table' => 'itsm_am_assetconnections', 'permission' => 'assets' ],
    'asset_configuration_templates' => [ 'table' => 'itsm_am_configurationtemplates', 'permission' => 'assets' ],
    'incidents' => [ 'table' => 'itsm_im_incidents', 'permission' => [ 'firstlineincidents', 'secondlineincidents' ] ],
    'incident_comments' => [ 'table' => 'itsm_im_incidentcomments', 'permission' => [ 'firstlineincidents', 'secondlineincidents' ] ],
    'changes' => [ 'table' => 'itsm_cm_changes', 'permission' => [ 'reqforchange', 'simplechange', 'extchange' ] ],
    'change_comments' => [ 'table' => 'itsm_cm_changecomments', 'permission' => [ 'reqforchange', 'simplechange', 'extchange' ] ],
    'change_activities' => [ 'table' => 'itsm_cm_changeactivities', 'permission' => [ 'reqforchange', 'simplechange', 'extchange' ] ],
    'change_activity_comments' => [ 'table' => 'itsm_cm_changeactivitycomments', 'permission' => [ 'reqforchange', 'simplechange', 'extchange' ] ],
    'problems' => [ 'table' => 'itsm_pm_problems', 'permission' => 'problems' ],
    'problem_comments' => [ 'table' => 'itsm_pm_problemcomments', 'permission' => 'problems' ],
    'events' => [ 'table' => 'itsm_em_events', 'permission' => 'events' ],
    'ubm_items' => [ 'table' => 'itsm_ubm_items', 'permission' => 'ubm' ],
    'ubm_comments' => [ 'table' => 'itsm_ubm_itemcomments', 'permission' => 'ubm' ],
    'knowledge_items' => [ 'table' => 'itsm_km_items', 'permission' => 'isadmin' ],
    'news' => [ 'table' => 'itsm_core_news', 'permission' => 'isadmin' ],
    'customers' => [ 'table' => 'itsm_ob_customers', 'permission' => 'customers' ],
    'persons' => [ 'table' => 'itsm_ob_persons', 'permission' => 'persons' ],
    'person_groups' => [ 'table' => 'itsm_ob_persongroups', 'permission' => 'groups' ],
    'person_group_links' => [ 'table' => 'itsm_ob_persongrouplinks', 'permission' => 'groups' ],
    'operators' => [ 'table' => 'itsm_ob_operators', 'permission' => 'operators', 'hidden' => [ 'password' ] ],
    'operator_groups' => [ 'table' => 'itsm_ob_operatorgroups', 'permission' => 'groups' ],
    'operator_group_links' => [ 'table' => 'itsm_ob_opgrouplinks', 'permission' => 'groups' ],
    'buildings' => [ 'table' => 'itsm_ob_buildings', 'permission' => 'buildings' ],
    'suppliers' => [ 'table' => 'itsm_ob_suppliers', 'permission' => 'suppliers' ],
    'categories' => [ 'table' => 'itsm_core_category', 'permission' => 'isadmin' ],
    'subcategories' => [ 'table' => 'itsm_core_subcategory', 'permission' => 'isadmin' ],
    'statuses' => [ 'table' => 'itsm_core_status', 'permission' => 'isadmin' ],
    'impacts' => [ 'table' => 'itsm_core_impacts', 'permission' => 'isadmin' ],
    'urgencies' => [ 'table' => 'itsm_core_urgencies', 'permission' => 'isadmin' ],
    'priorities' => [ 'table' => 'itsm_core_priorities', 'permission' => 'isadmin' ],
    'priority_matrix' => [ 'table' => 'itsm_core_prioritymatrix', 'permission' => 'isadmin' ],
    'templates' => [ 'table' => 'itsm_core_templates', 'permission' => 'isadmin' ],
    'template_activities' => [ 'table' => 'itsm_core_templateactivities', 'permission' => 'isadmin' ],
    'task_links' => [ 'table' => 'itsm_core_tasklinks', 'permission' => 'isadmin' ],
    'task_logs' => [ 'table' => 'itsm_core_tasklogs', 'permission' => 'isadmin' ],
    'mail_rules' => [ 'table' => 'itsm_core_mailrules', 'permission' => 'isadmin' ],
    'imap_rules' => [ 'table' => 'itsm_core_imap_rules', 'permission' => 'isadmin' ],
    'settings' => [ 'table' => 'itsm_core_settings', 'permission' => 'isadmin' ]
  ];
}

function api_require_resource_access( $operator, $resource ) {
  if ( (int)( $operator['isadmin'] ?? 0 ) === 1 ) {
    return;
  }
  $permissions = is_array( $resource['permission'] ) ? $resource['permission'] : [ $resource['permission'] ];
  foreach ( $permissions as $permission ) {
    if ( (int)( $operator[$permission] ?? 0 ) === 1 ) {
      return;
    }
  }
  api_error( 403, 'forbidden', 'The token operator does not have access to this resource.' );
}

function api_table_columns( $con, $table ) {
  $columns = [];
  $result = mysqli_query( $con, "DESCRIBE `$table`" );
  if ( !$result ) {
    api_error( 500, 'resource_describe_failed', mysqli_error( $con ) );
  }
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $columns[$row['Field']] = $row;
  }
  return $columns;
}

function api_clean_row( $row, $hidden ) {
  foreach ( $hidden as $field ) {
    unset( $row[$field] );
  }
  return $row;
}

function api_bind_values( $stmt, $values ) {
  if ( count( $values ) === 0 ) {
    return;
  }
  $types = str_repeat( 's', count( $values ) );
  $refs = [];
  foreach ( $values as $key => $value ) {
    $refs[$key] = &$values[$key];
  }
  mysqli_stmt_bind_param( $stmt, $types, ...$refs );
}

?>
