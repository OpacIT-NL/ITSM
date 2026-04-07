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
$sql2 = "SELECT customers FROM itsm_ob_operators WHERE username = ?";
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
$customer_id = $id;

if ( isset( $_POST[ 'delete' ] ) ) {

  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_ob_customers WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $id );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Delete failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: customers.php' );
  exit;
}

// Handle form submit
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {


  $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_customers SET
            din=?,
			name=?,
			primarybuilding=?,
			address=?,
			postalcode=?,
			city=?,
			primaryemail=?,
			primaryphone=?
        WHERE id=?
    " );

  mysqli_stmt_bind_param(
    $stmt,
    "ssssssssi",
    $_POST[ 'din' ],
    $_POST[ 'name' ],
    $_POST[ 'primarybuilding' ],
    $_POST[ 'address' ],
    $_POST[ 'postalcode' ],
    $_POST[ 'city' ],
    $_POST[ 'primaryemail' ],
    $_POST[ 'primaryphone' ],
    $id
  );

  mysqli_stmt_execute( $stmt );
  // Only update password if a new one is entered


  header( 'Location: customers.php' );
  exit;
}

// Fetch operator
$stmt = $con->prepare( "SELECT * FROM itsm_ob_customers WHERE id = ?" );
$stmt->bind_param( "i", $id );
$stmt->execute();
$result = $stmt->get_result();
$row2 = $result->fetch_assoc();

$stmt2 = $con->prepare( "SELECT * FROM itsm_ob_buildings WHERE customer = ?" );
$stmt2->bind_param( "i", $id );
$stmt2->execute();
$result3 = $stmt2->get_result();

$stmt4 = $con->prepare( "SELECT * FROM itsm_ob_buildings WHERE customer = ?" );
$stmt4->bind_param( "i", $id );
$stmt4->execute();
$result5 = $stmt4->get_result();


$stmt3 = $con->prepare( "SELECT p.id, p.customerid, p.firstname, p.lastname
FROM itsm_ob_persons p
LEFT JOIN itsm_ob_customers c 
    ON p.customerid = c.id
    WHERE c.id = ?
ORDER BY p.firstname, p.lastname;" );
$stmt3->bind_param( "i", $id );
$stmt3->execute();
$result4 = $stmt3->get_result();


if ( !$result ) {
  die( "Customer not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <?php $list_back_url = 'customers.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Klant Bewerken:
      <?= htmlspecialchars($row2['name']) ?>
    </h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card">
      <form method="post" class="form-grid">
        
        <!-- Basic fields -->
        <h3>Algemeen</h3>
        <div class="form-group">
          <label>DIN:
            <input type="text" name="din" value="<?= htmlspecialchars($row2['din']) ?>" readonly>
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Naam:
            <input type="text" name="name" value="<?= htmlspecialchars($row2['name']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group"> Primair gebouw:
          <?
          $selectedId = $row2[ 'primarybuilding' ] ?? null;

          echo '<select name="primarybuilding">';

          // Check if nothing is selected
          $emptySelected = empty( $selectedId ) ? 'selected' : '';
          echo "<option value='' $emptySelected>--Selecteer een gebouw--</option>";

          while ( $row4 = $result3->fetch_assoc() ) {
            $building_id = $row4[ 'id' ];
            $name = htmlspecialchars( $row4[ 'address' ] );

            $selected = ( $building_id == $selectedId ) ? 'selected' : '';

            echo "<option value='$building_id' $selected>$name</option>";
          }

          echo '</select>';
          ?>
        </div>
        <br>
        <hr>
        <h3>Postadres</h3>
        <div class="form-group">
          <label>Adres:
            <input type="text" name="address" value="<?= htmlspecialchars($row2['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Postcode:
            <input type="text" name="postalcode" value="<?= htmlspecialchars($row2['postalcode'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Plaats:
            <input type="text" name="city" value="<?= htmlspecialchars($row2['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </label>
        </div>
        <br>
        <hr>
        <h3>Contactgegevens</h3>
        <div class="form-group">
          <label>Primair E-mailadres:
            <input type="text" name="primaryemail" value="<?= htmlspecialchars($row2['primaryemail'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Primair telefoonnummer:
            <input type="text" name="primaryphone" value="<?= htmlspecialchars($row2['primaryphone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </label>
        </div>
        <br>
        <br>
        <br>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Opslaan</button>
          <button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze klant wil verwijderen?');"
        class="btn-danger"> Verwijder klant </button>
        </div>
      </form>
    </div>
  </div>
  <br>
  <h1>Personen</h1>
  <p><a class="btn" href="new_person.php?customerid=<?= urlencode( (string)$customer_id ) ?>">Nieuwe persoon</a></p>
  <div class="results">
    <table border="0" class=results style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Naam</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row5 = mysqli_fetch_assoc($result4)): ?>
        <tr>
          <td><?= $name = $row5['firstname'] . ' ' . $row5['lastname']; ?></td>
          <td class="tblaction"><a class="btn" href="edit_person.php?id=<?= $row5['id'] ?>"> Open Persoon </a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <center>
    <h1>Gebouwen</h1>
  </center>
  <div class="results">
    <table border="0" class=results style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Adres</th>
          <th style="text-align: start;">Postcode</th>
          <th style="text-align: start;">Plaats</th>
          <th style="text-align: start;">IDV-P</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row7 = mysqli_fetch_assoc($result5)): ?>
        <tr>
          <td><?= htmlspecialchars($row7['address']) ?></td>
          <td><?= htmlspecialchars($row7['postalcode']) ?></td>
          <td><?= htmlspecialchars($row7['city']) ?></td>
          <td><?= htmlspecialchars($row7['idvp']) ?></td>
          <td class="tblaction"><a class="btn" href="edit_building.php?id=<?= $row7['id'] ?>"> Open Gebouw </a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
