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

// Query
$sql = "SELECT id, firstname, lastname, email, phone, username, allowlogin 
        FROM itsm_ob_operators 
        ORDER BY lastname ASC";
$result = mysqli_query( $con, $sql );

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

if ( !$result ) {
  die( "Query failed: " . mysqli_error( $con ) );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <a href='javascript:history.back(1)'>Ga terug</a>
  <center>
    <h1>Behandelaars</h1>
  </center>
  <a href="new_operator.php">Nieuwe Behandelaar</a>
  <div class="results">
    <table border="0" class=results style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Voornaam</th>
          <th style="text-align: start;">Achternaam</th>
          <th style="text-align: start;">E-mail</th>
          <th style="text-align: start;">Telefoonnummer</th>
          <th style="text-align: start;">Gebruikersnaam</th>
          <th style="text-align: start;">Login toegestaan</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?= htmlspecialchars($row['firstname']) ?></td>
          <td><?= htmlspecialchars($row['lastname']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= $row['allowlogin'] == 1 ?
          '<span class="check">✔</span>' :
          '<span class="cross">✖</span>'
          ?></td>
          <td class="tblaction"><a class="btn" href="edit_operator.php?id=<?= $row['id'] ?>"> Open Behandelaar </a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
