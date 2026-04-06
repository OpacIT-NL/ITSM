<?php
session_start();
session_regenerate_id( true );
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
require_once( __DIR__ . '/../my.php' );
if ( $con->connect_error ) {
  exit( 'Failed to connect to MySQL: ' . $con->connect_error );
}
if ( !isset( $_POST[ 'username' ], $_POST[ 'password' ] ) ) {
  exit( 'Vul AUB alle velden in!' );
}

if ( $stmt = $con->prepare( 'SELECT id, firstname, lastname, email, password FROM itsm_ob_persons WHERE `email` = ? AND `allowssp` = 1' ) ) {
  $stmt->bind_param( 's', $_POST[ 'username' ] );
  $stmt->execute();
  $stmt->store_result();
  if ( $stmt->num_rows > 0 ) {
    $stmt->bind_result( $id, $firstname, $lastname, $email, $password );
    $stmt->fetch();
    if ( password_verify( $_POST[ 'password' ], $password ) ) {
      session_regenerate_id();
      $_SESSION[ 'ssploggedin' ] = TRUE;
      $_SESSION[ 'name' ] = trim( $firstname . ' ' . $lastname );
      $_SESSION[ 'email' ] = $email;
      $_SESSION[ 'id' ] = $id;
      $_SESSION[ 'expires_at' ] = time() + ( 12 * 60 * 60 );
      header( 'Location: index.php' );
    } else {
      echo 'Gebruikersnaam/wachtwoord incorrect. <a href="login.php">Ga terug</a>';
    }
  } else {
    echo 'Gebruikersnaam/wachtwoord incorrect. <a href="login.php">Ga terug</a>';
  }

  $stmt->close();
}
?>
