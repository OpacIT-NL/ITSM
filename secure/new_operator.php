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
$sql2 = "SELECT operators FROM itsm_ob_operators WHERE username = ?";
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
// Boolean fields with display names
$boolFields = [
  'allowlogin' => 'Mag inloggen',
  'firstlineincidents' => 'Eerstelijns incidenten',
  'secondlineincidents' => 'Tweedelijns incidenten',
  'reqforchange' => 'Wijzigingsaanvragen',
  'simplechange' => 'Eenvoudige Wijzigingen',
  'extchange' => 'Uitgebreide Wijzigingen',
  'problems' => 'Probleem beheer',
  'operations' => 'Operationele taken',
  'assets' => 'Middelenbeheer',
  'persons' => 'Personen',
  'operators' => 'Behandelaren',
  'buildings' => 'Gebouwen',
  'customers' => 'Klanten',
  'suppliers' => 'Leveranciers',
  'groups' => 'Groepen',
  'events' => 'Events',
  'ubm' => 'Projecten / UBM',
  'reporting' => 'Rapportages',
  'isadmin' => 'Administrator'
];

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Convert checkboxes to 0/1
  $boolValues = [];
  foreach ( $boolFields as $field => $label ) {
    $boolValues[ $field ] = isset( $_POST[ $field ] ) ? 1 : 0;
  }

  // Validate password
  if ( empty( $_POST[ 'password' ] ) ) {
    die( "Wachtwoord is verplicht!" );
  }

  $hashedPassword = password_hash( $_POST[ 'password' ], PASSWORD_DEFAULT );

  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_ob_operators (
            firstname, lastname, email, phone, username, password,
            allowlogin, firstlineincidents, secondlineincidents, reqforchange,
            simplechange, extchange, problems, operations, assets, persons,
            operators, buildings, customers, suppliers, groups, events,
            ubm, reporting, isadmin
        ) VALUES (
            ?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "ssssssiiiiiiiiiiiiiiiiiii",
    $_POST[ 'firstname' ],
    $_POST[ 'lastname' ],
    $_POST[ 'email' ],
    $_POST[ 'phone' ],
    $_POST[ 'username' ],
    $hashedPassword,
    $boolValues[ 'allowlogin' ],
    $boolValues[ 'firstlineincidents' ],
    $boolValues[ 'secondlineincidents' ],
    $boolValues[ 'reqforchange' ],
    $boolValues[ 'simplechange' ],
    $boolValues[ 'extchange' ],
    $boolValues[ 'problems' ],
    $boolValues[ 'operations' ],
    $boolValues[ 'assets' ],
    $boolValues[ 'persons' ],
    $boolValues[ 'operators' ],
    $boolValues[ 'buildings' ],
    $boolValues[ 'customers' ],
    $boolValues[ 'suppliers' ],
    $boolValues[ 'groups' ],
    $boolValues[ 'events' ],
    $boolValues[ 'ubm' ],
    $boolValues[ 'reporting' ],
    $boolValues[ 'isadmin' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: operators.php' );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"><a href='javascript:history.back(1)'>Ga terug</a>
  <center>
    <h1>Nieuwe behandelaar</h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        
        <h3>Basis informatie</h3>
        <div class="form-group">
          <label>Voornaam:
            <input type="text" name="firstname" required>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Achternaam:
            <input type="text" name="lastname" required>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>E-mail:
            <input type="email" name="email" required>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Telefoonnummer:
            <input type="text" name="phone">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Gebruikersnaam:
            <input type="text" name="username" required>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Wachtwoord:
            <input type="password" name="password" required>
          </label>
        </div>
        
        <!-- Permissions -->
        
        <h3>Rollen</h3>
        <div class=checkbox-grid>
          <?php foreach ($boolFields as $field => $label): ?>
          <label>
            <input type="checkbox" name="<?= $field ?>">
            <?= $label ?>
          </label>
          <?php endforeach; ?>
        </div>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Maak behandelaar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
