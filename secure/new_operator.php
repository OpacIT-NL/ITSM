<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
// Sliding idle timeout check
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION[ 'name' ];
require_once( __DIR__ . '/../my.php' );

// Authorization check
$sql2 = "SELECT operators, isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators, $current_isadmin );
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
$form_error = '';

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Convert checkboxes to 0/1
  $boolValues = [];
  foreach ( $boolFields as $field => $label ) {
    $boolValues[ $field ] = isset( $_POST[ $field ] ) ? 1 : 0;
  }
  if ( (int)$current_isadmin !== 1 ) {
    $boolValues['isadmin'] = 0;
  }

  $form_error = itsm_password_policy_error( $_POST['password'] ?? '' );

  if ( $form_error === '' ) {
    $hashedPassword = password_hash( (string)$_POST['password'], PASSWORD_DEFAULT );

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
      itsm_fail( 'operator_insert_failed', mysqli_stmt_error( $stmt ) );
    }

    header( 'Location: operators.php' );
    exit;
  }
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"><?php $list_back_url = 'operators.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Nieuwe behandelaar</h1>
  </center>
  <div class="form-wrapper record-form-wrapper">
    <div class="form-card record-form-card">
      <form method="post" class="form-grid">
        <?php if ( $form_error !== '' ): ?>
        <p class="error"><?= htmlspecialchars( $form_error ) ?></p>
        <?php endif; ?>
        
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
            <input type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password">
          </label>
        </div>
        
        <!-- Permissions -->
        
        <h3>Rollen</h3>
        <div class=checkbox-grid>
          <?php foreach ($boolFields as $field => $label): ?>
          <?php if ( $field === 'isadmin' && (int)$current_isadmin !== 1 ) continue; ?>
          <label>
            <input type="checkbox" name="<?= htmlspecialchars($field) ?>" <?= !empty($_POST[$field]) ? 'checked' : '' ?>>
            <?= htmlspecialchars($label) ?>
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
