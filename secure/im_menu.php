<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/incident_helpers.php' );

if ( !isset( $_SESSION[ 'operatorloggedin' ] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION[ 'expires_at' ] ) && time() > $_SESSION[ 'expires_at' ] ) {
  itsm_destroy_session();
  header( 'Location: login.php?expired=1' );
  exit;
}

$logged_in_user = $_SESSION[ 'name' ];
incident_require_firstline_authorization( $con, $logged_in_user );
$operator_context = incident_get_operator_context( $con, $logged_in_user );
incident_require_access( $operator_context );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<div class="module-section">
  <h1>Incident Management</h1>
  <h2>Aanmaken</h2>
  <div class="module-grid">
    <?php if ( (int)$operator_context['firstlineincidents'] === 1 ): ?>
    <a href="new_incident.php?mode=firstline">Eerstelijns incident</a>
    <?php endif; ?>
    <?php if ( (int)$operator_context['secondlineincidents'] === 1 ): ?>
    <a href="new_incident.php?mode=secondline">Tweedelijns incident</a>
    <a href="new_incident.php?mode=major">Major incident</a>
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
      <h3>Eerstelijns</h3>
      <div class="incident-view-links">
        <a href="incidents.php?view=open&mode=firstline">Open incidenten</a>
        <a href="incidents.php?view=all&mode=firstline">Alle incidenten</a>
        <a href="incidents.php?view=ready&mode=firstline">Gereede incidenten</a>
        <a href="incidents.php?view=mine&mode=firstline">Incidenten op mijn naam</a>
        <a href="incidents.php?view=minegroups&mode=firstline">Incidenten op mijn naam en groepen</a>
      </div>
    </div>
    <div class="incident-view-column">
      <h3>Tweedelijns</h3>
      <div class="incident-view-links">
        <a href="incidents.php?view=open&mode=secondline">Open incidenten</a>
        <a href="incidents.php?view=all&mode=secondline">Alle incidenten</a>
        <a href="incidents.php?view=ready&mode=secondline">Gereede incidenten</a>
        <a href="incidents.php?view=mine&mode=secondline">Incidenten op mijn naam</a>
        <a href="incidents.php?view=minegroups&mode=secondline">Incidenten op mijn naam en groepen</a>
      </div>
    </div>
    <div class="incident-view-column">
      <h3>Major</h3>
      <div class="incident-view-links">
        <a href="incidents.php?view=open&mode=major">Open incidenten</a>
        <a href="incidents.php?view=all&mode=major">Alle incidenten</a>
        <a href="incidents.php?view=ready&mode=major">Gereede incidenten</a>
        <a href="incidents.php?view=mine&mode=major">Incidenten op mijn naam</a>
        <a href="incidents.php?view=minegroups&mode=major">Incidenten op mijn naam en groepen</a>
      </div>
    </div>
  </div>
</div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
