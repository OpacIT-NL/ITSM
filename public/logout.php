<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_destroy_session();
// Redirect to the login page:
header( 'Location: ../index.php' );
?>
