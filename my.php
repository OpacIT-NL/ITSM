<?php

date_default_timezone_set( 'Europe/Amsterdam' );
require_once( __DIR__ . '/include/session_helpers.php' );
require_once( __DIR__ . '/include/auth_security.php' );
require_once( __DIR__ . '/lang/lang_helpers.php' );
itsm_secure_session_start();

function db_connect() {

  // Define connection as a static variable, to avoid connecting more than once 
  static $con;

  // Try and connect to the database, if a connection has not been established yet
  if ( !isset( $con ) ) {
    $config_path = __DIR__ . '/../config/sql.ini';
    $config = @parse_ini_file( $config_path );
    if ( !is_array( $config ) || !isset( $config['servername'], $config['username'], $config['password'], $config['dbname'] ) ) {
      itsm_fail( 'database_configuration_invalid', 'Unable to load required values from ' . $config_path );
    }

    mysqli_report( MYSQLI_REPORT_OFF );
    $con = @mysqli_connect( $config['servername'], $config['username'], $config['password'], $config['dbname'] );
  }

  if ( !( $con instanceof mysqli ) ) {
    itsm_fail( 'database_connection_failed', mysqli_connect_error() );
  }
  if ( !mysqli_set_charset( $con, 'utf8mb4' ) ) {
    itsm_fail( 'database_charset_failed', mysqli_error( $con ) );
  }
  return $con;
}

function itsm_render_local_datetime_script() {
  ?>
<script>
(function () {
  const datetimePattern = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/;
  const datePattern = /^(\d{4})-(\d{2})-(\d{2})$/;

  function formatDateOnly(year, month, day) {
    const formatter = new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    });
    return formatter.format(new Date(year, month - 1, day));
  }

  function formatUtcDateTime(year, month, day, hour, minute, second) {
    const options = {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    };
    if (second !== null) {
      options.second = '2-digit';
    }

    const formatter = new Intl.DateTimeFormat(undefined, options);
    return formatter.format(new Date(Date.UTC(year, month - 1, day, hour, minute, second || 0)));
  }

  function localizeElement(element) {
    if (!element || element.dataset.localTimeApplied === '1') {
      return;
    }
    if (element.closest('script, style, textarea, pre, code')) {
      return;
    }
    if (element.children.length > 0) {
      return;
    }

    const rawText = (element.textContent || '').trim();
    if (!rawText) {
      return;
    }

    let match = rawText.match(datetimePattern);
    if (match) {
      const [, y, m, d, hh, mm, ss] = match;
      element.textContent = formatUtcDateTime(
        Number(y),
        Number(m),
        Number(d),
        Number(hh),
        Number(mm),
        typeof ss === 'undefined' ? null : Number(ss)
      );
      element.dataset.localTimeApplied = '1';
      return;
    }

    match = rawText.match(datePattern);
    if (match) {
      const [, y, m, d] = match;
      element.textContent = formatDateOnly(Number(y), Number(m), Number(d));
      element.dataset.localTimeApplied = '1';
    }
  }

  function runLocalization(root) {
    const scope = root || document;
    scope.querySelectorAll('td, th, span, p, small, strong, dd, div, h1, h2, h3, h4, h5, h6, label, sub').forEach(localizeElement);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      runLocalization(document);
    });
  } else {
    runLocalization(document);
  }
})();
</script>
<?php
}

function itsm_refresh_authenticated_session_timeout() {
  if ( session_status() !== PHP_SESSION_ACTIVE ) {
    return;
  }

  $script_name = basename( $_SERVER['SCRIPT_NAME'] ?? '' );
  if ( $script_name === 'authenticate.php' ) {
    return;
  }

  if ( empty( $_SESSION['operatorloggedin'] ) && empty( $_SESSION['ssploggedin'] ) ) {
    return;
  }

  $timeout_seconds = 12 * 60 * 60;

  if ( isset( $_SESSION['expires_at'] ) && time() > (int)$_SESSION['expires_at'] ) {
    itsm_destroy_session();
    header( 'Location: login.php?expired=1' );
    exit;
  }

  // Background presence heartbeats must not keep an otherwise idle session alive.
  if ( $script_name === 'form_presence.php' ) {
    return;
  }

  $_SESSION['expires_at'] = time() + $timeout_seconds;
}

// Connect to the database
$con = db_connect();

itsm_validate_session_security_fingerprint( $con );
itsm_refresh_authenticated_session_timeout();

itsm_boot_language_system( $con instanceof mysqli ? $con : null );
?>
