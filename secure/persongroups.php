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

// Query
$sql = "SELECT *
        FROM itsm_ob_persongroups";
$result = mysqli_query( $con, $sql );

// Authorization check
$sql2 = "SELECT persons FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $groups );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $groups == 0 ) {
  header( "Location: ob-menu.php" );
  exit();
}

if ( !$result ) {
  itsm_fail( 'person_group_query_failed', mysqli_error( $con ) );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <?php $module_back_url = 'ob-menu.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1><?= htmlspecialchars(t('Persoonsgroepen')) ?></h1>
  </center>
  <a href="new_persongroup.php"><?= htmlspecialchars(t('Nieuwe Persoonsgroep')) ?></a>
  <div class="results">
    <table border="0" class=results style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;"><?= htmlspecialchars(t('Naam')) ?></th>
          <th style="text-align: start;"><?= htmlspecialchars(t('Actie')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?= htmlspecialchars($row['groupname']) ?></td>
          <td class="tblaction"><a class="btn" href="edit_persongroup.php?id=<?= $row['id'] ?>"><?= htmlspecialchars(t('Open Persoonsgroep')) ?></a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
