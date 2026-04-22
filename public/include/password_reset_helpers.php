<?php

require_once( __DIR__ . '/../../secure/include/mail_helpers.php' );

function ssp_reset_base_url() {
  $scheme = !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $script_dir = rtrim( str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ?? '/public' ) ), '/' );
  if ( substr( $script_dir, -7 ) === '/secure' ) {
    $script_dir = substr( $script_dir, 0, -7 ) . '/public';
  } elseif ( $script_dir === '/secure' ) {
    $script_dir = '/public';
  }

  if ( $host === '' ) {
    return $script_dir !== '' ? $script_dir : '.';
  }

  return $scheme . '://' . $host . ( $script_dir !== '' ? $script_dir : '' );
}

function ssp_reset_find_person_by_email( $con, $email ) {
  $person = null;
  $stmt = mysqli_prepare( $con, "
    SELECT id, firstname, lastname, email
    FROM itsm_ob_persons
    WHERE email = ? AND allowssp = 1
    LIMIT 1
  " );
  if ( !$stmt ) {
    return null;
  }

  mysqli_stmt_bind_param( $stmt, 's', $email );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  if ( $row = mysqli_fetch_assoc( $result ) ) {
    $person = $row;
  }
  mysqli_stmt_close( $stmt );

  return $person;
}

function ssp_reset_invalidate_open_tokens( $con, $person_id ) {
  $stmt = mysqli_prepare( $con, "
    UPDATE itsm_public_password_resets
    SET usedat = NOW()
    WHERE personid = ? AND usedat IS NULL
  " );
  if ( !$stmt ) {
    return false;
  }

  mysqli_stmt_bind_param( $stmt, 'i', $person_id );
  $ok = mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );

  return $ok;
}

function ssp_reset_create_token( $con, $person_id, $valid_minutes = 60 ) {
  $token = bin2hex( random_bytes( 32 ) );
  $token_hash = hash( 'sha256', $token );
  $valid_minutes = max( 5, (int)$valid_minutes );
  $expires_at = date( 'Y-m-d H:i:s', time() + ( $valid_minutes * 60 ) );

  ssp_reset_invalidate_open_tokens( $con, (int)$person_id );

  $stmt = mysqli_prepare( $con, "
    INSERT INTO itsm_public_password_resets (personid, tokenhash, expiresat)
    VALUES (?, ?, ?)
  " );
  if ( !$stmt ) {
    return null;
  }

  $person_id = (int)$person_id;
  mysqli_stmt_bind_param( $stmt, 'iss', $person_id, $token_hash, $expires_at );
  $ok = mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );

  if ( !$ok ) {
    return null;
  }

  return [
    'token' => $token,
    'expires_at' => $expires_at,
    'valid_minutes' => $valid_minutes
  ];
}

function ssp_reset_load_by_token( $con, $token ) {
  $token_hash = hash( 'sha256', (string)$token );
  $stmt = mysqli_prepare( $con, "
    SELECT r.id AS resetid, r.personid, r.expiresat, p.firstname, p.lastname, p.email
    FROM itsm_public_password_resets r
    INNER JOIN itsm_ob_persons p ON r.personid = p.id
    WHERE r.tokenhash = ?
      AND r.usedat IS NULL
      AND r.expiresat >= NOW()
      AND p.allowssp = 1
    LIMIT 1
  " );
  if ( !$stmt ) {
    return null;
  }

  mysqli_stmt_bind_param( $stmt, 's', $token_hash );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $row = mysqli_fetch_assoc( $result );
  mysqli_stmt_close( $stmt );

  return $row ?: null;
}

function ssp_reset_mark_used( $con, $reset_id ) {
  $stmt = mysqli_prepare( $con, "
    UPDATE itsm_public_password_resets
    SET usedat = NOW()
    WHERE id = ? AND usedat IS NULL
  " );
  if ( !$stmt ) {
    return false;
  }

  $reset_id = (int)$reset_id;
  mysqli_stmt_bind_param( $stmt, 'i', $reset_id );
  $ok = mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );

  return $ok;
}

function ssp_reset_update_password( $con, $person_id, $password_hash ) {
  $stmt = mysqli_prepare( $con, "UPDATE itsm_ob_persons SET password = ? WHERE id = ? AND allowssp = 1" );
  if ( !$stmt ) {
    return false;
  }

  $person_id = (int)$person_id;
  mysqli_stmt_bind_param( $stmt, 'si', $password_hash, $person_id );
  $ok = mysqli_stmt_execute( $stmt );
  mysqli_stmt_close( $stmt );

  return $ok;
}

function ssp_reset_send_mail( $person, $token_data ) {
  $reset_link = ssp_reset_base_url() . '/reset_password.php?token=' . urlencode( $token_data['token'] );
  $name = trim( (string)( $person['firstname'] ?? '' ) . ' ' . (string)( $person['lastname'] ?? '' ) );
  $variables = [
    'reset_link' => $reset_link,
    'firstname' => $person['firstname'] ?? '',
    'lastname' => $person['lastname'] ?? '',
    'name' => $name,
    'email' => $person['email'] ?? '',
    'expires_minutes' => (string)( $token_data['valid_minutes'] ?? 60 ),
    'expires_at' => $token_data['expires_at'] ?? ''
  ];

  $template = mail_read_template( 'password_reset.html' );
  if ( $template === '' ) {
    $template = t( 'password_reset.fallback_template_html' );
  }

  $body = mail_apply_variables( $template, $variables );
  return mail_smtp_send( [ $person['email'] ], t( 'auth.reset_email_subject' ), $body );
}
