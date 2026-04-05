<?php
session_start();
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
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
require_once( __DIR__ . '/../my.php' );

// Authorization check
$sql2 = "SELECT persons FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: ob-menu.php" );
  exit();
}
// Validate ID
if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = ( int )$_GET[ 'id' ];

// All boolean fields
$boolFields = [
  'allowssp'
];
if ( isset( $_POST[ 'delete' ] ) ) {

  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_ob_persons WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $id );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Delete failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: persons.php' );
  exit;
}

// Handle form submit
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Convert checkboxes to 0/1
  $boolValues = [];
  foreach ( $boolFields as $field ) {
    $boolValues[ $field ] = isset( $_POST[ $field ] ) ? 1 : 0;
  }

  $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_persons SET
            firstname=?,
            lastname=?,
            email=?,
            phone=?,
            allowssp=?
        WHERE id=?
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "ssssii",
    $_POST[ 'firstname' ],
    $_POST[ 'lastname' ],
    $_POST[ 'email' ],
    $_POST[ 'phone' ],
    $boolValues[ 'allowssp' ],
    $id
  );

  mysqli_stmt_execute( $stmt );
  // Only update password if a new one is entered
  if ( !empty( $_POST[ 'password' ] ) ) {
    $hashed = password_hash( $_POST[ 'password' ], PASSWORD_DEFAULT );

    $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_persons 
        SET password = ? 
        WHERE id = ?
    " );

    mysqli_stmt_bind_param( $stmt, "si", $hashed, $id );
    mysqli_stmt_execute( $stmt );
  }

  header( 'Location: persons.php' );
  exit;
}

// Fetch operator
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_persons WHERE id=?" );
mysqli_stmt_bind_param( $stmt, "i", $id );
mysqli_stmt_execute( $stmt );

$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );


if ( !$operator ) {
  die( "Person not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <a href="persons.php">Ga terug</a>
  <center>
    <h1>Persoon bewerken:
      <?= htmlspecialchars($operator['firstname']) ?>
      <?= htmlspecialchars($operator['lastname']) ?>
    </h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        <div class="form-group"> 
          <!-- Basic fields -->
          <label>Voornaam:
            <input type="text" name="firstname" value="<?= htmlspecialchars($operator['firstname']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Achternaam:
            <input type="text" name="lastname" value="<?= htmlspecialchars($operator['lastname']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>E-mail:
            <input type="email" name="email" value="<?= htmlspecialchars($operator['email']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Telefoonnummer:
            <input type="text" name="phone" value="<?= htmlspecialchars($operator['phone']) ?>">
          </label>
        </div>
        <br>
        <hr>
        
        <!-- Boolean fields -->
        <h3>Rechten</h3>
        <div class=checkbox-grid>
          <?php foreach ($boolFields as $field): ?>
          <label>
            <input type="checkbox" name="<?= $field ?>" <?= $operator[$field] ? 'checked' : '' ?>>
            <?= ucfirst($field) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <hr>
        <div class="form-group"> 
          <!-- Password (optional safe handling) -->
          <label>Nieuw wachtwoord (laat leeg om niet te bewerken):
            <input type="password" name="password">
          </label>
        </div>
        <br>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Opslaan</button>
          <button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze persoon wil verwijderen?');"
        class="btn-danger"> Verwijder persoon </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
