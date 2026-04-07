<?php
session_start();

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
$stmt2 = $con->prepare( "SELECT * FROM itsm_ob_customers" );
$stmt2->execute();
$result3 = $stmt2->get_result();
$selected_customer_id = isset( $_GET['customerid'] ) && is_numeric( $_GET['customerid'] ) ? (int)$_GET['customerid'] : 0;
// Boolean fields with display names
$boolFields = [
  'allowssp' => 'Mag inloggen (SSP)'
];

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  $selected_customer_id = isset( $_POST['customerid'] ) && is_numeric( $_POST['customerid'] ) ? (int)$_POST['customerid'] : 0;

  // Convert checkboxes to 0/1
  $boolValues = [];
  foreach ( $boolFields as $field => $label ) {
    $boolValues[ $field ] = isset( $_POST[ $field ] ) ? 1 : 0;
  }

  $randomPassword = bin2hex( random_bytes( 32 ) );
  $hashedPassword = password_hash( $randomPassword, PASSWORD_DEFAULT );

  // Prepare insert
  $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_ob_persons (
            customerid, firstname, lastname, email, phone, password,
            allowssp
        ) VALUES (
            ?,?,?,?,?,?,
            ?
        )
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "isssssi",
    $_POST[ 'customerid' ],
    $_POST[ 'firstname' ],
    $_POST[ 'lastname' ],
    $_POST[ 'email' ],
    $_POST[ 'phone' ],
    $hashedPassword,
    $boolValues[ 'allowssp' ]
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Insert failed: " . mysqli_stmt_error( $stmt ) );
  }

  if ( $selected_customer_id > 0 ) {
    header( 'Location: edit_customer.php?id=' . $selected_customer_id );
  } else {
    header( 'Location: persons.php' );
  }
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <?php $list_back_url = 'persons.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Nieuw Persoon</h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        
        <h3>Basis informatie</h3>
        Klant:
        <div class="form-group">
          <?

          echo '<select name="customerid" required><option value="">--selecteer een klant--</option>';

          // Check if nothing is selected

          while ( $row2 = $result3->fetch_assoc() ) {
            $id = $row2[ 'id' ];
            $name = htmlspecialchars( $row2[ 'din' ] . ' - ' . $row2[ 'name' ] );
            $selected = ( (int)$id === $selected_customer_id ) ? 'selected' : '';
            echo "<option value='$id' $selected>$name</option>";
          }

          echo '</select>';
          ?>
        </div>
        <br>
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
        <p class="info-note">Het wachtwoord wordt automatisch willekeurig gezet. De gebruiker stelt zelf een wachtwoord in via een SelfService wachtwoordreset.</p>
        
        <!-- Permissions -->
        
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
          <button type="submit" class="btn-primary">Maak persoon</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
