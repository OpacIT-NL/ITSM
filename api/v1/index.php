<?php

require_once( __DIR__ . '/include/bootstrap.php' );

$con = api_db_connect();
$operator = api_authenticate( $con );
$resources = api_resource_maps();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$path = trim( $_SERVER['PATH_INFO'] ?? '', '/' );
$path_parts = $path !== '' ? explode( '/', $path ) : [];
$resource_name = $path_parts[0] ?? ( $_GET['resource'] ?? '' );
$id = $path_parts[1] ?? ( $_GET['id'] ?? null );

if ( $resource_name === '' ) {
  api_json( [
    'api' => 'ITSM API',
    'version' => 'v1',
    'operator' => [
      'id' => (int)$operator['id'],
      'username' => $operator['username'],
      'name' => trim( ( $operator['firstname'] ?? '' ) . ' ' . ( $operator['lastname'] ?? '' ) )
    ],
    'resources' => array_keys( $resources )
  ] );
}

if ( !isset( $resources[$resource_name] ) ) {
  api_error( 404, 'unknown_resource', 'Unknown API resource.' );
}

$resource = $resources[$resource_name];
api_require_resource_access( $operator, $resource );

$table = $resource['table'];
$hidden = $resource['hidden'] ?? [];
$columns = api_table_columns( $con, $table );

if ( $method === 'GET' ) {
  if ( $id !== null && $id !== '' ) {
    $stmt = mysqli_prepare( $con, "SELECT * FROM `$table` WHERE id = ? LIMIT 1" );
    $id_value = (int)$id;
    mysqli_stmt_bind_param( $stmt, 'i', $id_value );
    mysqli_stmt_execute( $stmt );
    $result = mysqli_stmt_get_result( $stmt );
    $row = mysqli_fetch_assoc( $result );
    mysqli_stmt_close( $stmt );
    if ( !$row ) {
      api_error( 404, 'not_found', 'Record not found.' );
    }
    api_json( [ 'data' => api_clean_row( $row, $hidden ) ] );
  }

  $limit = min( max( (int)( $_GET['limit'] ?? 100 ), 1 ), 500 );
  $offset = max( (int)( $_GET['offset'] ?? 0 ), 0 );
  $where = [];
  $values = [];
  foreach ( $_GET as $key => $value ) {
    if ( in_array( $key, [ 'resource', 'id', 'limit', 'offset' ], true ) || !isset( $columns[$key] ) ) {
      continue;
    }
    $where[] = "`$key` = ?";
    $values[] = $value;
  }
  $where_sql = count( $where ) > 0 ? ' WHERE ' . implode( ' AND ', $where ) : '';
  $sql = "SELECT * FROM `$table`$where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset";
  $stmt = mysqli_prepare( $con, $sql );
  api_bind_values( $stmt, $values );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rows = [];
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $rows[] = api_clean_row( $row, $hidden );
  }
  mysqli_stmt_close( $stmt );
  api_json( [ 'data' => $rows, 'limit' => $limit, 'offset' => $offset ] );
}

if ( $method === 'POST' ) {
  $data = api_request_json();
  unset( $data['id'] );
  if ( isset( $columns['createdby'] ) && !isset( $data['createdby'] ) ) {
    $data['createdby'] = (int)$operator['id'];
  }
  if ( $table === 'itsm_ob_operators' && isset( $data['password'] ) ) {
    $data['password'] = password_hash( (string)$data['password'], PASSWORD_DEFAULT );
  }
  $data = array_intersect_key( $data, $columns );
  if ( count( $data ) === 0 ) {
    api_error( 400, 'empty_payload', 'No writable fields were supplied.' );
  }
  $fields = array_keys( $data );
  $field_sql = '`' . implode( '`,`', $fields ) . '`';
  $placeholder_sql = implode( ',', array_fill( 0, count( $fields ), '?' ) );
  $stmt = mysqli_prepare( $con, "INSERT INTO `$table` ($field_sql) VALUES ($placeholder_sql)" );
  api_bind_values( $stmt, array_values( $data ) );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    api_error( 400, 'insert_failed', mysqli_stmt_error( $stmt ) );
  }
  $new_id = mysqli_insert_id( $con );
  mysqli_stmt_close( $stmt );
  api_json( [ 'data' => [ 'id' => $new_id ] ], 201 );
}

if ( $method === 'PUT' || $method === 'PATCH' ) {
  if ( $id === null || $id === '' ) {
    api_error( 400, 'missing_id', 'An id is required for updates.' );
  }
  $data = api_request_json();
  unset( $data['id'] );
  if ( $table === 'itsm_ob_operators' && array_key_exists( 'password', $data ) ) {
    if ( trim( (string)$data['password'] ) === '' ) {
      unset( $data['password'] );
    } else {
      $data['password'] = password_hash( (string)$data['password'], PASSWORD_DEFAULT );
    }
  }
  $data = array_intersect_key( $data, $columns );
  if ( count( $data ) === 0 ) {
    api_error( 400, 'empty_payload', 'No writable fields were supplied.' );
  }
  $sets = [];
  foreach ( array_keys( $data ) as $field ) {
    $sets[] = "`$field` = ?";
  }
  $values = array_values( $data );
  $values[] = (int)$id;
  $stmt = mysqli_prepare( $con, "UPDATE `$table` SET " . implode( ', ', $sets ) . " WHERE id = ?" );
  api_bind_values( $stmt, $values );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    api_error( 400, 'update_failed', mysqli_stmt_error( $stmt ) );
  }
  mysqli_stmt_close( $stmt );
  api_json( [ 'data' => [ 'id' => (int)$id, 'updated' => true ] ] );
}

if ( $method === 'DELETE' ) {
  if ( $id === null || $id === '' ) {
    api_error( 400, 'missing_id', 'An id is required for deletes.' );
  }
  $stmt = mysqli_prepare( $con, "DELETE FROM `$table` WHERE id = ?" );
  $id_value = (int)$id;
  mysqli_stmt_bind_param( $stmt, 'i', $id_value );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    api_error( 400, 'delete_failed', mysqli_stmt_error( $stmt ) );
  }
  $affected = mysqli_stmt_affected_rows( $stmt );
  mysqli_stmt_close( $stmt );
  api_json( [ 'data' => [ 'id' => (int)$id, 'deleted' => $affected > 0 ] ] );
}

api_error( 405, 'method_not_allowed', 'Allowed methods: GET, POST, PUT, PATCH, DELETE.' );

?>
