<?php
session_start();

require_once( __DIR__ . '/../my.php' );
if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Absolute expiration check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION[ 'name' ];
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  if ( !empty( $_POST[ 'password' ] ) ) {
    $hashed = password_hash( $_POST[ 'password' ], PASSWORD_DEFAULT );

    $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_operators 
        SET password = ? 
        WHERE username = ?
    " );

    mysqli_stmt_bind_param( $stmt, "ss", $hashed, $logged_in_user );
    mysqli_stmt_execute( $stmt );
  }
}
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE username=?" );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );
if ( !$operator ) {
  die( "Operator not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <h1>Mijn account</h1>
  <p>Voornaam:
    <?= htmlspecialchars($operator['firstname']) ?>
  </p>
  <p>Achternaam:
    <?= htmlspecialchars($operator['lastname']) ?>
  </p>
  <p>Gebruikersnaam:
    <?= htmlspecialchars($operator['username']) ?>
  </p>
  <p>Email:
    <?= htmlspecialchars($operator['email']) ?>
  </p>
  <form method="post">
    <label>Nieuw wachtwoord (Laat leeg om het wachtwoord niet te wijzigen):
      <input type="password" name="password">
    </label>
    <br>
    <br>
    <button type="submit">Save</button>
  </form>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
