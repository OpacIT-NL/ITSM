<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
session_regenerate_id( true );
require_once( __DIR__ . '/../my.php' );
if ( !isset( $_POST[ 'username' ], $_POST[ 'password' ] ) ) {
  header( 'Location: login.php?incorrect=1' );
  exit();
}
$username = (string)$_POST[ 'username' ];
$password_input = (string)$_POST[ 'password' ];
$rate_scope = 'operator_login';
if ( itsm_auth_rate_limited( $con, $rate_scope, $username, 5, 30, 15 * 60 ) ) {
  usleep( random_int( 250000, 600000 ) );
  header( 'Location: login.php?incorrect=1' );
  exit();
}

if ( $stmt = $con->prepare( 'SELECT id, password, preferredlanguage FROM itsm_ob_operators WHERE `username` = ? AND `allowlogin` = 1' ) ) {
  $stmt->bind_param( 's', $username );
  $stmt->execute();
  $stmt->store_result();
  if ( $stmt->num_rows > 0 ) {
    $stmt->bind_result( $id, $password, $preferredlanguage );
    $stmt->fetch();
    if ( password_verify( $password_input, $password ) ) {
      itsm_auth_clear_attempts( $con, $rate_scope, $username );
      session_regenerate_id( true );
      $_SESSION[ 'operatorloggedin' ] = TRUE;
      $_SESSION[ 'name' ] = $username;
      $_SESSION[ 'id' ] = $id;
      $_SESSION[ 'expires_at' ] = time() + ( 12 * 60 * 60 );
      if ( !empty( $preferredlanguage ) ) {
        $_SESSION['preferred_language'] = $preferredlanguage;
      } else {
        unset( $_SESSION['preferred_language'] );
      }
      itsm_refresh_session_security_fingerprint( $con );
      header( 'Location: index.php' );
      exit();
    }
  }

  $stmt->close();
} else {
  itsm_fail( 'operator_login_prepare_failed', mysqli_error( $con ) );
}

itsm_auth_record_attempt( $con, $rate_scope, $username );
usleep( random_int( 250000, 600000 ) );
header( 'Location: login.php?incorrect=1' );
exit();
?>
