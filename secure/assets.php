<?php
session_start();

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}

if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( "Location: login.php?expired=1" );
  exit;
}

$logged_in_user = $_SESSION[ 'name' ];
require_once( __DIR__ . '/../my.php' );
// Authorization check
$sql2 = "SELECT assets FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: am-menu.php" );
  exit();
}
$filtertype = null;
$filtertypename = null;

if ( isset( $_GET[ 'filtertype' ] ) && $_GET[ 'filtertype' ] !== '' ) {
  if ( is_numeric( $_GET[ 'filtertype' ] ) ) {
    $filtertype = ( int )$_GET[ 'filtertype' ];

    $type_stmt = mysqli_prepare( $con, "SELECT id, type FROM itsm_am_types WHERE id = ?" );
    mysqli_stmt_bind_param( $type_stmt, "i", $filtertype );
  } else {
    $legacy_type = ( string )$_GET[ 'filtertype' ];

    $type_stmt = mysqli_prepare( $con, "SELECT id, type FROM itsm_am_types WHERE type = ?" );
    mysqli_stmt_bind_param( $type_stmt, "s", $legacy_type );
  }

  mysqli_stmt_execute( $type_stmt );
  $type_result = mysqli_stmt_get_result( $type_stmt );
  $type_row = mysqli_fetch_assoc( $type_result );
  mysqli_stmt_close( $type_stmt );

  if ( !$type_row ) {
    die( "Invalid filter type" );
  }

  $filtertype = ( int )$type_row[ 'id' ];
  $filtertypename = $type_row[ 'type' ];
}

if ( $filtertype !== null ) {
  $stmt = mysqli_prepare( $con, "
        SELECT a.*, t.type AS typename
        FROM itsm_am_assets a
        LEFT JOIN itsm_am_types t ON a.type = t.id
        WHERE a.type = ?
        ORDER BY a.id ASC
    " );
  mysqli_stmt_bind_param( $stmt, "i", $filtertype );
} else {
  $stmt = mysqli_prepare( $con, "
        SELECT a.*, t.type AS typename
        FROM itsm_am_assets a
        LEFT JOIN itsm_am_types t ON a.type = t.id
        ORDER BY a.id ASC
    " );
}

mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<div class="content">
<a href="am-menu.php">Ga terug</a>
<center>
  <h1>
    Assets
    <?php if ( $filtertypename !== null ): ?>
      (type: <?= htmlspecialchars($filtertypename) ?>)
    <?php endif; ?>
  </h1>
</center>
<?php if ( $filtertype !== null ): ?>
  <a href="new_asset.php?type=<?= htmlspecialchars((string)$filtertype) ?>">Nieuw Asset</a>
<?php else: ?>
  <p>Kies eerst een asset type in het menu om een nieuw asset aan te maken.</p>
<?php endif; ?>
<div class="results">
  <table border="0" class="results" style="width: 100%;">
    <thead>
      <tr>
        <th style="text-align: start;">ID</th>
        <th style="text-align: start;">Type</th>
        <th style="text-align: start;">Object ID</th>
        <th style="text-align: start;">Actie</th>
      </tr>
    </thead>
    <tbody>
      <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
      <tr>
        <td><?= htmlspecialchars($row['id']) ?></td>
        <td><?= htmlspecialchars($row['typename'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['objectid']) ?></td>
        <td class="tblaction">
          <a class="btn" href="edit_asset.php?id=<?= $row['id'] ?>"> Bewerken </a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

</div>

<?php require_once(__DIR__ . '/nav/end.php'); ?>
