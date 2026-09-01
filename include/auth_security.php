<?php

function itsm_auth_ensure_throttle_table( $con ) {
  static $ensured = false;
  if ( $ensured ) {
    return;
  }
  $sql = "
    CREATE TABLE IF NOT EXISTS itsm_core_auth_attempts (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      scope VARCHAR(32) NOT NULL,
      identifierhash CHAR(64) NOT NULL,
      iphash CHAR(64) NOT NULL,
      createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY auth_attempt_lookup (scope, identifierhash, createdat),
      KEY auth_attempt_ip_lookup (scope, iphash, createdat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ";
  if ( !mysqli_query( $con, $sql ) ) {
    itsm_fail( 'auth_throttle_schema_failed', mysqli_error( $con ) );
  }
  $ensured = true;
}

function itsm_auth_identifier_hash( $identifier ) {
  return hash( 'sha256', strtolower( trim( (string)$identifier ) ) );
}

function itsm_auth_ip_hash() {
  return hash( 'sha256', (string)( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
}

function itsm_auth_rate_limited( $con, $scope, $identifier, $identity_limit, $ip_limit, $window_seconds ) {
  itsm_auth_ensure_throttle_table( $con );
  $identifier_hash = itsm_auth_identifier_hash( $identifier );
  $ip_hash = itsm_auth_ip_hash();
  $window_seconds = max( 60, (int)$window_seconds );
  $cutoff = date( 'Y-m-d H:i:s', time() - $window_seconds );

  $stmt = mysqli_prepare( $con, "
    SELECT
      SUM(CASE WHEN identifierhash = ? THEN 1 ELSE 0 END) AS identity_attempts,
      SUM(CASE WHEN iphash = ? THEN 1 ELSE 0 END) AS ip_attempts
    FROM itsm_core_auth_attempts
    WHERE scope = ? AND createdat >= ?
  " );
  if ( !$stmt ) {
    itsm_fail( 'auth_throttle_prepare_failed', mysqli_error( $con ) );
  }
  mysqli_stmt_bind_param( $stmt, 'ssss', $identifier_hash, $ip_hash, $scope, $cutoff );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    itsm_fail( 'auth_throttle_query_failed', mysqli_stmt_error( $stmt ) );
  }
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result ) ?: [];
  mysqli_stmt_close( $stmt );

  return (int)( $row['identity_attempts'] ?? 0 ) >= (int)$identity_limit
    || (int)( $row['ip_attempts'] ?? 0 ) >= (int)$ip_limit;
}

function itsm_auth_record_attempt( $con, $scope, $identifier ) {
  itsm_auth_ensure_throttle_table( $con );
  $identifier_hash = itsm_auth_identifier_hash( $identifier );
  $ip_hash = itsm_auth_ip_hash();
  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_core_auth_attempts (scope, identifierhash, iphash)
    VALUES (?, ?, ?)
  " );
  if ( !$stmt ) {
    itsm_fail( 'auth_throttle_insert_prepare_failed', mysqli_error( $con ) );
  }
  mysqli_stmt_bind_param( $stmt, 'sss', $scope, $identifier_hash, $ip_hash );
  if ( !mysqli_stmt_execute( $stmt ) ) {
    itsm_fail( 'auth_throttle_insert_failed', mysqli_stmt_error( $stmt ) );
  }
  mysqli_stmt_close( $stmt );

  if ( random_int( 1, 100 ) === 1 ) {
    mysqli_query( $con, "DELETE FROM itsm_core_auth_attempts WHERE createdat < (NOW() - INTERVAL 2 DAY)" );
  }
}

function itsm_auth_clear_attempts( $con, $scope, $identifier ) {
  itsm_auth_ensure_throttle_table( $con );
  $identifier_hash = itsm_auth_identifier_hash( $identifier );
  $stmt = mysqli_prepare( $con, "
    DELETE FROM itsm_core_auth_attempts
    WHERE scope = ? AND identifierhash = ?
  " );
  if ( !$stmt ) {
    itsm_fail( 'auth_throttle_clear_prepare_failed', mysqli_error( $con ) );
  }
  mysqli_stmt_bind_param( $stmt, 'ss', $scope, $identifier_hash );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );
}

function itsm_password_policy_error( $password ) {
  $password = (string)$password;
  $length = function_exists( 'mb_strlen' ) ? mb_strlen( $password, 'UTF-8' ) : strlen( $password );
  if ( $length < 12 ) {
    return 'Gebruik een wachtwoord van minimaal 12 tekens.';
  }
  if ( $length > 128 ) {
    return 'Het wachtwoord mag maximaal 128 tekens bevatten.';
  }
  return '';
}

function itsm_operator_security_fingerprint( $operator ) {
  $fields = [
    'id', 'username', 'password', 'allowlogin', 'firstlineincidents', 'secondlineincidents',
    'reqforchange', 'simplechange', 'extchange', 'problems', 'operations', 'assets',
    'persons', 'operators', 'buildings', 'customers', 'suppliers', 'groups', 'events',
    'ubm', 'reporting', 'isadmin'
  ];
  $values = [];
  foreach ( $fields as $field ) {
    $values[$field] = $operator[$field] ?? null;
  }
  return hash( 'sha256', json_encode( $values ) );
}

function itsm_person_security_fingerprint( $person ) {
  return hash( 'sha256', json_encode( [
    'id' => $person['id'] ?? null,
    'customerid' => $person['customerid'] ?? null,
    'email' => $person['email'] ?? null,
    'password' => $person['password'] ?? null,
    'allowssp' => $person['allowssp'] ?? null
  ] ) );
}

function itsm_refresh_session_security_fingerprint( $con ) {
  if ( session_status() !== PHP_SESSION_ACTIVE || empty( $_SESSION['id'] ) ) {
    return;
  }

  if ( !empty( $_SESSION['operatorloggedin'] ) ) {
    $session_id = (int)$_SESSION['id'];
    $stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE id = ? LIMIT 1" );
    mysqli_stmt_bind_param( $stmt, 'i', $session_id );
    mysqli_stmt_execute( $stmt );
    $result = mysqli_stmt_get_result( $stmt );
    $operator = mysqli_fetch_assoc( $result );
    mysqli_stmt_close( $stmt );
    if ( !$operator || (int)$operator['allowlogin'] !== 1 ) {
      itsm_destroy_session();
      return;
    }
    $_SESSION['security_fingerprint'] = itsm_operator_security_fingerprint( $operator );
    return;
  }

  if ( !empty( $_SESSION['ssploggedin'] ) ) {
    $session_id = (int)$_SESSION['id'];
    $stmt = mysqli_prepare( $con, "SELECT id, customerid, email, password, allowssp FROM itsm_ob_persons WHERE id = ? LIMIT 1" );
    mysqli_stmt_bind_param( $stmt, 'i', $session_id );
    mysqli_stmt_execute( $stmt );
    $result = mysqli_stmt_get_result( $stmt );
    $person = mysqli_fetch_assoc( $result );
    mysqli_stmt_close( $stmt );
    if ( !$person || (int)$person['allowssp'] !== 1 ) {
      itsm_destroy_session();
      return;
    }
    $_SESSION['security_fingerprint'] = itsm_person_security_fingerprint( $person );
  }
}

function itsm_validate_session_security_fingerprint( $con ) {
  if ( session_status() !== PHP_SESSION_ACTIVE || empty( $_SESSION['id'] ) ) {
    return;
  }

  $stored = (string)( $_SESSION['security_fingerprint'] ?? '' );
  $session_id = (int)$_SESSION['id'];
  if ( !empty( $_SESSION['operatorloggedin'] ) ) {
    $stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE id = ? LIMIT 1" );
    mysqli_stmt_bind_param( $stmt, 'i', $session_id );
    mysqli_stmt_execute( $stmt );
    $result = mysqli_stmt_get_result( $stmt );
    $operator = mysqli_fetch_assoc( $result );
    mysqli_stmt_close( $stmt );
    $current = $operator ? itsm_operator_security_fingerprint( $operator ) : '';
    $allowed = $operator && (int)$operator['allowlogin'] === 1;
  } elseif ( !empty( $_SESSION['ssploggedin'] ) ) {
    $stmt = mysqli_prepare( $con, "SELECT id, customerid, email, password, allowssp FROM itsm_ob_persons WHERE id = ? LIMIT 1" );
    mysqli_stmt_bind_param( $stmt, 'i', $session_id );
    mysqli_stmt_execute( $stmt );
    $result = mysqli_stmt_get_result( $stmt );
    $person = mysqli_fetch_assoc( $result );
    mysqli_stmt_close( $stmt );
    $current = $person ? itsm_person_security_fingerprint( $person ) : '';
    $allowed = $person && (int)$person['allowssp'] === 1;
  } else {
    return;
  }

  if ( !$allowed || ( $stored !== '' && !hash_equals( $stored, $current ) ) ) {
    $was_operator = !empty( $_SESSION['operatorloggedin'] );
    itsm_destroy_session();
    if ( !headers_sent() ) {
      header( 'Location: ' . ( $was_operator ? '/secure/login.php?expired=1' : '/public/login.php?expired=1' ) );
    }
    exit;
  }
  if ( $stored === '' ) {
    $_SESSION['security_fingerprint'] = $current;
  }
}

?>
