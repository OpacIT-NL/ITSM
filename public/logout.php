<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
  http_response_code( 405 );
  header( 'Allow: POST' );
  exit( 'Method not allowed.' );
}
itsm_destroy_session();
// Redirect to the login page:
header( 'Location: ../index.php' );
?>
