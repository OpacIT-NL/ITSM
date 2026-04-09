<?php
session_start();
session_regenerate_id( true );
require_once( __DIR__ . '/../my.php' );
if ( $con->connect_error ) {
  exit( 'Failed to connect to MySQL: ' . $con->connect_error );
}
if ( !isset( $_POST[ 'username' ], $_POST[ 'password' ] ) ) {
  header( 'Location: login.php?incorrect=1' );
  exit();
}
$username = (string)$_POST[ 'username' ];
$password_input = (string)$_POST[ 'password' ];

if ( $stmt = $con->prepare( 'SELECT id, password FROM itsm_ob_operators WHERE `username` = ? AND `allowlogin` = 1' ) ) {
  $stmt->bind_param( 's', $username );
  $stmt->execute();
  $stmt->store_result();
  if ( $stmt->num_rows > 0 ) {
    $stmt->bind_result( $id, $password );
    $stmt->fetch();
    if ( password_verify( $password_input, $password ) ) {
      session_regenerate_id();
      $_SESSION[ 'operatorloggedin' ] = TRUE;
      $_SESSION[ 'name' ] = $username;
      $_SESSION[ 'id' ] = $id;
      $_SESSION[ 'expires_at' ] = time() + ( 12 * 60 * 60 );
      header( 'Location: index.php' );
      exit();
    } else {
      header( 'Location: login.php?incorrect=1' );
      exit();
    }
  } else {
    header( 'Location: login.php?incorrect=1' );
    exit();
  }

  $stmt->close();
}
?>
