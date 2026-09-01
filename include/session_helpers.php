<?php

function itsm_request_is_https() {
  return !empty( $_SERVER['HTTPS'] ) && strtolower( (string)$_SERVER['HTTPS'] ) !== 'off';
}

function itsm_trace_id() {
  try {
    return bin2hex( random_bytes( 12 ) );
  } catch ( Throwable $exception ) {
    return str_replace( '.', '', uniqid( 'itsm', true ) );
  }
}

function itsm_log_trace( $trace_id, $context, $detail = '', $exception = null ) {
  $entry = [
    'application' => 'ITSM',
    'trace_id' => (string)$trace_id,
    'context' => (string)$context,
    'method' => (string)( $_SERVER['REQUEST_METHOD'] ?? '' ),
    'path' => (string)( parse_url( (string)( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ?? '' ),
    'remote_addr' => (string)( $_SERVER['REMOTE_ADDR'] ?? '' ),
    'detail' => (string)$detail
  ];

  if ( $exception instanceof Throwable ) {
    $entry['exception'] = get_class( $exception );
    $entry['file'] = $exception->getFile();
    $entry['line'] = $exception->getLine();
    $entry['stack'] = $exception->getTraceAsString();
  } else {
    $entry['stack'] = ( new Exception( (string)$context ) )->getTraceAsString();
  }

  $encoded = json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
  error_log( $encoded !== false ? $encoded : '[ITSM trace_id=' . $trace_id . '] ' . $context );
}

function itsm_fail( $context, $detail = '', $status = 500, $exception = null ) {
  $trace_id = itsm_trace_id();
  itsm_log_trace( $trace_id, $context, $detail, $exception );

  if ( !headers_sent() ) {
    http_response_code( (int)$status );
    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'Cache-Control: no-store' );
  }

  exit( 'Er is een interne fout opgetreden. trace_id=' . $trace_id );
}

function itsm_error_reference( $context, $detail = '', $exception = null ) {
  $trace_id = itsm_trace_id();
  itsm_log_trace( $trace_id, $context, $detail, $exception );
  return 'Er is een interne fout opgetreden. trace_id=' . $trace_id;
}

function itsm_install_error_handlers() {
  static $installed = false;
  if ( $installed ) {
    return;
  }
  $installed = true;

  ini_set( 'display_errors', '0' );
  ini_set( 'log_errors', '1' );

  set_exception_handler( function ( $exception ) {
    itsm_fail( 'uncaught_exception', $exception->getMessage(), 500, $exception );
  } );

  register_shutdown_function( function () {
    $error = error_get_last();
    if ( !$error || !in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
      return;
    }

    $trace_id = itsm_trace_id();
    itsm_log_trace(
      $trace_id,
      'fatal_php_error',
      (string)$error['message'] . ' in ' . (string)$error['file'] . ':' . (int)$error['line']
    );

    if ( !headers_sent() ) {
      http_response_code( 500 );
      header( 'Content-Type: text/plain; charset=utf-8' );
      header( 'Cache-Control: no-store' );
      echo 'Er is een interne fout opgetreden. trace_id=' . $trace_id;
    }
  } );
}

function itsm_apply_security_headers() {
  if ( headers_sent() ) {
    return;
  }

  header( 'X-Content-Type-Options: nosniff' );
  header( 'X-Frame-Options: DENY' );
  header( 'Referrer-Policy: no-referrer' );
  header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()' );
  header( 'Cross-Origin-Opener-Policy: same-origin' );
  header( 'Cross-Origin-Resource-Policy: same-origin' );
  header( 'Cache-Control: no-store, private' );
  header(
    "Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; "
    . "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://use.fontawesome.com; "
    . "font-src 'self' https://use.fontawesome.com data:; img-src 'self' data:; connect-src 'self'"
  );

  if ( itsm_request_is_https() ) {
    header( 'Strict-Transport-Security: max-age=31536000' );
  }
}

function itsm_csrf_token() {
  if ( session_status() !== PHP_SESSION_ACTIVE ) {
    return '';
  }
  if ( empty( $_SESSION['itsm_csrf_token'] ) ) {
    $_SESSION['itsm_csrf_token'] = bin2hex( random_bytes( 32 ) );
  }
  return (string)$_SESSION['itsm_csrf_token'];
}

function itsm_csrf_request_token() {
  if ( isset( $_POST['_csrf_token'] ) ) {
    return (string)$_POST['_csrf_token'];
  }
  if ( isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) ) {
    return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
  }
  return '';
}

function itsm_csrf_validate_request() {
  $method = strtoupper( (string)( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
  if ( in_array( $method, [ 'GET', 'HEAD', 'OPTIONS' ], true ) ) {
    return;
  }

  $expected = itsm_csrf_token();
  $received = itsm_csrf_request_token();
  if ( $expected === '' || $received === '' || !hash_equals( $expected, $received ) ) {
    if ( !headers_sent() ) {
      http_response_code( 403 );
      header( 'Content-Type: text/plain; charset=utf-8' );
      header( 'Cache-Control: no-store' );
    }
    exit( 'Ongeldig of verlopen beveiligingstoken. Ververs de pagina en probeer opnieuw.' );
  }
}

function itsm_csrf_inject_html( $html ) {
  foreach ( headers_list() as $header_line ) {
    if ( stripos( $header_line, 'Content-Type:' ) === 0 && stripos( $header_line, 'text/html' ) === false ) {
      return $html;
    }
  }

  if ( stripos( $html, '<form' ) !== false ) {
    $token = htmlspecialchars( itsm_csrf_token(), ENT_QUOTES, 'UTF-8' );
    $html = preg_replace_callback(
      '/<form\b[^>]*>/i',
      function ( $matches ) use ( $token ) {
        if ( !preg_match( '/\bmethod\s*=\s*([\"\']?)post\1/i', $matches[0] ) ) {
          return $matches[0];
        }
        return $matches[0] . '<input type="hidden" name="_csrf_token" value="' . $token . '">';
      },
      $html
    );
  }

  if ( stripos( $html, '</head>' ) !== false && stripos( $html, 'name="itsm-csrf-token"' ) === false ) {
    $meta = '<meta name="itsm-csrf-token" content="'
      . htmlspecialchars( itsm_csrf_token(), ENT_QUOTES, 'UTF-8' )
      . '">';
    $html = preg_replace( '/<\/head>/i', $meta . "\n</head>", $html, 1 );
  }

  return $html;
}

function itsm_secure_session_start() {
  static $bootstrapped = false;

  itsm_install_error_handlers();
  itsm_apply_security_headers();

  if ( session_status() !== PHP_SESSION_ACTIVE ) {
    ini_set( 'session.use_strict_mode', '1' );
    ini_set( 'session.use_only_cookies', '1' );
    ini_set( 'session.cookie_httponly', '1' );
    ini_set( 'session.cookie_samesite', 'Lax' );
    ini_set( 'session.cookie_secure', itsm_request_is_https() ? '1' : '0' );
    session_name( 'ITSMSESSID' );
    session_set_cookie_params( [
      'lifetime' => 0,
      'path' => '/',
      'domain' => '',
      'secure' => itsm_request_is_https(),
      'httponly' => true,
      'samesite' => 'Lax'
    ] );
    session_start();
  }

  if ( !$bootstrapped ) {
    $bootstrapped = true;
    itsm_csrf_token();
    itsm_csrf_validate_request();
    ob_start( 'itsm_csrf_inject_html' );
  }
}

function itsm_destroy_session() {
  if ( session_status() !== PHP_SESSION_ACTIVE ) {
    return;
  }

  $_SESSION = [];

  if ( ini_get( 'session.use_cookies' ) ) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params['path'] ?? '/',
      $params['domain'] ?? '',
      $params['secure'] ?? false,
      $params['httponly'] ?? true
    );
  }

  session_destroy();
}

?>
