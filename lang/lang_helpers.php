<?php

function itsm_available_languages() {
  return [
    'nl_NL' => 'Nederlands',
    'en_US' => 'English (US)'
  ];
}

function itsm_normalize_language_code( $language ) {
  $language = str_replace( '-', '_', (string)$language );
  $available = itsm_available_languages();

  return isset( $available[$language] ) ? $language : 'nl_NL';
}

function itsm_lang_file_path( $language ) {
  return __DIR__ . '/' . itsm_normalize_language_code( $language ) . '.php';
}

function itsm_load_language_pack( $language ) {
  $path = itsm_lang_file_path( $language );
  if ( !is_readable( $path ) ) {
    $path = itsm_lang_file_path( 'nl_NL' );
  }

  $pack = require $path;
  if ( !is_array( $pack ) ) {
    $pack = [];
  }

  return [
    'messages' => is_array( $pack['messages'] ?? null ) ? $pack['messages'] : [],
    'literals' => is_array( $pack['literals'] ?? null ) ? $pack['literals'] : []
  ];
}

function itsm_fetch_setting_value( $con, $setting_key, $default_value = null ) {
  if ( !$con || !($con instanceof mysqli) ) {
    return $default_value;
  }

  $table_check = @mysqli_query( $con, "SHOW TABLES LIKE 'itsm_core_settings'" );
  if ( !$table_check || mysqli_num_rows( $table_check ) === 0 ) {
    return $default_value;
  }

  $stmt = mysqli_prepare( $con, "SELECT settingvalue FROM itsm_core_settings WHERE settingkey = ? LIMIT 1" );
  if ( !$stmt ) {
    return $default_value;
  }
  mysqli_stmt_bind_param( $stmt, 's', $setting_key );
  mysqli_stmt_execute( $stmt );
  mysqli_stmt_bind_result( $stmt, $setting_value );
  mysqli_stmt_fetch( $stmt );
  mysqli_stmt_close( $stmt );

  return $setting_value !== null && $setting_value !== '' ? $setting_value : $default_value;
}

function itsm_language_from_context( $con ) {
  $default_language = itsm_normalize_language_code( itsm_fetch_setting_value( $con, 'default_language', 'nl_NL' ) );

  if ( session_status() !== PHP_SESSION_ACTIVE ) {
    return $default_language;
  }

  if ( !empty( $_SESSION['preferred_language'] ) ) {
    return itsm_normalize_language_code( $_SESSION['preferred_language'] );
  }

  if ( !empty( $_SESSION['operatorloggedin'] ) && !empty( $_SESSION['id'] ) && $con instanceof mysqli ) {
    $operator_id = (int)$_SESSION['id'];
    $stmt = mysqli_prepare( $con, "SELECT preferredlanguage FROM itsm_ob_operators WHERE id = ? LIMIT 1" );
    if ( $stmt ) {
      mysqli_stmt_bind_param( $stmt, 'i', $operator_id );
      mysqli_stmt_execute( $stmt );
      mysqli_stmt_bind_result( $stmt, $preferred_language );
      mysqli_stmt_fetch( $stmt );
      mysqli_stmt_close( $stmt );
      if ( !empty( $preferred_language ) ) {
        return itsm_normalize_language_code( $preferred_language );
      }
    }
  }

  if ( !empty( $_SESSION['ssploggedin'] ) && !empty( $_SESSION['id'] ) && $con instanceof mysqli ) {
    $person_id = (int)$_SESSION['id'];
    $stmt = mysqli_prepare( $con, "
      SELECT p.preferredlanguage, c.defaultlanguage
      FROM itsm_ob_persons p
      LEFT JOIN itsm_ob_customers c ON p.customerid = c.id
      WHERE p.id = ?
      LIMIT 1
    " );
    if ( $stmt ) {
      mysqli_stmt_bind_param( $stmt, 'i', $person_id );
      mysqli_stmt_execute( $stmt );
      mysqli_stmt_bind_result( $stmt, $preferred_language, $customer_language );
      mysqli_stmt_fetch( $stmt );
      mysqli_stmt_close( $stmt );
      if ( !empty( $preferred_language ) ) {
        return itsm_normalize_language_code( $preferred_language );
      }
      if ( !empty( $customer_language ) ) {
        return itsm_normalize_language_code( $customer_language );
      }
    }
  }

  return $default_language;
}

function itsm_current_language() {
  return $GLOBALS['itsm_current_language'] ?? 'nl_NL';
}

function itsm_current_language_pack() {
  return $GLOBALS['itsm_language_pack'] ?? [ 'messages' => [], 'literals' => [] ];
}

function itsm_refresh_language_system( $con = null ) {
  $language = itsm_language_from_context( $con );
  $GLOBALS['itsm_current_language'] = $language;
  $GLOBALS['itsm_language_pack'] = itsm_load_language_pack( $language );

  return $language;
}

function t( $key, $replacements = [] ) {
  $pack = itsm_current_language_pack();
  $messages = $pack['messages'] ?? [];
  $literals = $pack['literals'] ?? [];
  $text = $messages[$key] ?? ( $literals[$key] ?? $key );

  foreach ( $replacements as $placeholder => $value ) {
    $text = str_replace( '{' . $placeholder . '}', (string)$value, $text );
  }

  return $text;
}

function itsm_should_translate_output() {
  $basename = basename( $_SERVER['SCRIPT_NAME'] ?? '' );
  $excluded = [
    'authenticate.php',
    'download_attachment.php',
    'form_presence.php',
    'logout.php'
  ];

  return !in_array( $basename, $excluded, true );
}

function itsm_translate_output( $buffer ) {
  $pack = itsm_current_language_pack();
  $literals = $pack['literals'] ?? [];
  if ( empty( $literals ) ) {
    return $buffer;
  }

  uksort(
    $literals,
    function( $a, $b ) {
      return strlen( $b ) <=> strlen( $a );
    }
  );

  $buffer = strtr( $buffer, $literals );

  $language = htmlspecialchars( itsm_current_language(), ENT_QUOTES, 'UTF-8' );
  if ( strpos( $buffer, '<html' ) !== false && strpos( $buffer, 'lang=' ) === false ) {
    $buffer = preg_replace( '/<html(\s|>)/i', '<html lang="' . $language . '"$1', $buffer, 1 );
  }

  return $buffer;
}

function itsm_boot_language_system( $con = null ) {
  static $booted = false;
  if ( $booted ) {
    return;
  }
  $booted = true;

  itsm_refresh_language_system( $con );

  if ( PHP_SAPI !== 'cli' && itsm_should_translate_output() ) {
    ob_start( 'itsm_translate_output' );
  }
}
