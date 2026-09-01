<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/problem_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION['name'];
$operator_context = problem_get_operator_context( $con, $logged_in_user );
problem_require_access( $operator_context );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1>Problem Management</h1>
  <h2>Aanmaken</h2>
  <div class="module-grid">
    <a href="new_problem.php">Nieuw probleem</a>
  </div>
  <br>
  <h2>Bekijken</h2>
  <div class="module-grid">
    <a href="problems.php?view=open">Open problemen</a>
    <a href="problems.php?view=all">Alle problemen</a>
    <a href="problems.php?view=ready">Gereede problemen</a>
    <a href="problems.php?view=mine">Op mijn naam</a>
    <a href="problems.php?view=minegroups">Mijn naam of groepen</a>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
