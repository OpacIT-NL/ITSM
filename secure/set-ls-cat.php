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

// Fetch category
$type = ( string )$_GET[ 'type' ];
if ( $type !== '' ) {
  $stmt = $con->prepare( "SELECT * FROM itsm_core_category WHERE type = ?" );
  $stmt->bind_param( "s", $type );
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = mysqli_query( $con, "SELECT * FROM itsm_core_category" );
}

// Fetch user permissions
$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: index.php" );
  exit();
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <a href="set-general.php">Ga terug</a>
  <center>
    <h1>Categorie-beheer</h1>
  </center>
  <a href="new_cat.php">Nieuwe Categorie</a>
  <form method="GET">
    <label for="type">Filter op soort:</label>
    <select name="type" id="type" onchange="this.form.submit()">
      <option value="">Alle soorten</option>
      <option value="INCIDENT" <?= ($_GET['type'] ?? '') === 'INCIDENT' ? 'selected' : '' ?>> INCIDENT </option>
      <option value="CHANGE" <?= ($_GET['type'] ?? '') === 'CHANGE' ? 'selected' : '' ?>> CHANGE </option>
      <option value="PROBLEM" <?= ($_GET['type'] ?? '') === 'PROBLEM' ? 'selected' : '' ?>> PROBLEM </option>
      <option value="EVENT" <?= ($_GET['type'] ?? '') === 'EVENT' ? 'selected' : '' ?>> EVENT </option>
    </select>
  </form>
  <div class="results">
    <table border="0" class=results style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Categorie</th>
          <th style="text-align: start;">Soort</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['type']) ?></td>
          <td class="tblaction"><a class="btn" href="edit_cat.php?id=<?= $row['id'] ?>"> Open Categorie </a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
