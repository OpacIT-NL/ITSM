<?php
session_start();
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION[ 'name' ];
$operator_context = change_get_operator_context( $con, $logged_in_user );
change_require_access( $operator_context );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1>Change Management</h1>
  <h2>Aanmaken</h2>
  <div class="module-grid">
    <?php if ( (int)$operator_context['reqforchange'] === 1 ): ?>
    <a href="new_change.php">Wijzigingsaanvraag</a>
    <?php endif; ?>
  </div>
  <br>
  <h2>Bekijken</h2>
  <style>
    .incident-view-columns {
      display: grid;
      grid-template-columns: repeat(3, minmax(220px, 1fr));
      gap: 20px;
      align-items: start;
    }
    .incident-view-column {
      background-color: rgba(0, 0, 0, 0.04);
      border-radius: 6px;
      padding: 12px;
    }
    .incident-view-column h3 {
      margin-top: 0;
      margin-bottom: 12px;
    }
    .incident-view-links {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .incident-view-links a {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 44px;
      width: 100%;
      box-sizing: border-box;
      padding: 8px 12px;
      text-align: center;
      background-color: #3274d6;
      color: #ffffff;
      text-decoration: none;
      border-radius: 6px;
      transition: all 0.2s ease;
    }
    .incident-view-links a:hover {
      background-color: #ffffff;
      color: #111111;
    }
    @media (max-width: 980px) {
      .incident-view-columns {
        grid-template-columns: 1fr;
      }
    }
  </style>
  <div class="incident-view-columns">
    <div class="incident-view-column">
      <h3>Wijzigingsaanvragen</h3>
      <div class="incident-view-links">
        <a href="changes.php?section=requests&view=open">Open</a>
        <a href="changes.php?section=requests&view=rejected">Afgewezen</a>
        <a href="changes.php?section=requests&view=all">Alle</a>
        <a href="changes.php?section=requests&view=mine">Op mijn naam</a>
        <a href="changes.php?section=requests&view=minegroups">Mijn naam of groepen</a>
      </div>
    </div>
    <div class="incident-view-column">
      <h3>Wijzigingen</h3>
      <div class="incident-view-links">
        <a href="changes.php?section=changes&view=open&mode=simple">Open EW</a>
        <a href="changes.php?section=changes&view=open&mode=extended">Open UW</a>
        <a href="changes.php?section=changes&view=all">Alle Wijzigingen</a>
        <a href="changes.php?section=changes&view=mine">Op mijn naam</a>
        <a href="changes.php?section=changes&view=minegroups">Mijn naam of groepen</a>
      </div>
    </div>
    <div class="incident-view-column">
      <h3>Wijzigingsactiviteiten</h3>
      <div class="incident-view-links">
        <a href="change_activities.php?view=mine">Op mijn naam</a>
        <a href="change_activities.php?view=minegroups">Mijn naam of groepen</a>
      </div>
    </div>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
