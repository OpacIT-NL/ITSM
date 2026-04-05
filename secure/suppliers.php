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
$sql = "SELECT *
        FROM itsm_ob_suppliers 
        ORDER BY cin ASC";
$result = mysqli_query( $con, $sql );

// Authorization check
$sql2 = "SELECT suppliers FROM itsm_ob_operators WHERE username = ?";
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
<div class="content">
<a href="ob-menu.php">Ga terug</a>
<center>
  <h1>Leveranciers</h1>
</center>
<a href="new_supplier.php">Nieuwe Leverancier</a>
<div class="results">
<table border="0" class=results style="width: 100%;">
<thead>
  <tr>
    <th style="text-align: start;">CIN</th>
    <th style="text-align: start;">Naam</th>
    <th style="text-align: start;">E-mail</th>
    <th style="text-align: start;">Telefoonnummer</th>
    <th style="text-align: start;">Actie</th>
  </tr>
</thead>
<tbody>
  <?php while ($row = mysqli_fetch_assoc($result)): ?>
  <tr>
    <td><?= htmlspecialchars($row['cin']) ?></td>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td><?= htmlspecialchars($row['primaryemail']) ?></td>
    <td><?= htmlspecialchars($row['primaryphone']) ?></td>
    <td class="tblaction"><a class="btn" href="edit_supplier.php?id=<?= $row['id'] ?>"> Open Leverancier </a></td>
  </tr>
  <?php endwhile; ?>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
