<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );

$person = ssp_require_login( $con );
$counts = ssp_dashboard_counts( $con, $person );

ssp_page_title( 'ITSM Selfservice' );
ssp_render_header( $person, 'dashboard' );
?>
<section class="ssp-hero">
  <div>
    <span class="ssp-eyebrow">Welkom terug</span>
    <h2>Jouw selfservice-portaal</h2>
    <p>Bekijk je open meldingen en wijzigingen, controleer je toegewezen assets en dien zelf nieuwe aanvragen in.</p>
  </div>
  <div class="ssp-hero-actions">
    <a class="ssp-button" href="new_incident.php"><i class="fa-solid fa-phone"></i> Incident melden</a>
    <a class="ssp-button ssp-button-secondary" href="new_change.php"><i class="fa-solid fa-file-circle-plus"></i> Wijziging aanvragen</a>
  </div>
</section>

<section class="ssp-card-grid">
  <a class="ssp-stat-card" href="incidents.php?view=open">
    <span>Open incidenten</span>
    <strong><?= htmlspecialchars((string)$counts['open_incidents']) ?></strong>
    <small>Bekijk je lopende meldingen</small>
  </a>
  <a class="ssp-stat-card" href="changes.php?view=open">
    <span>Open wijzigingen</span>
    <strong><?= htmlspecialchars((string)$counts['open_changes']) ?></strong>
    <small>Inclusief open aanvragen</small>
  </a>
  <a class="ssp-stat-card" href="changes.php?view=requests">
    <span>Open aanvragen</span>
    <strong><?= htmlspecialchars((string)$counts['open_change_requests']) ?></strong>
    <small>Nog in behandeling</small>
  </a>
  <a class="ssp-stat-card" href="assets.php">
    <span>Mijn assets</span>
    <strong><?= htmlspecialchars((string)$counts['assigned_assets']) ?></strong>
    <small>Alle aan jou gekoppelde objecten</small>
  </a>
</section>

<section class="ssp-dashboard-grid">
  <article class="ssp-panel">
    <div class="ssp-panel-head">
      <h3>Snel naar</h3>
    </div>
    <div class="ssp-quick-actions">
      <a class="ssp-quick-link" href="incidents.php"><i class="fa-solid fa-triangle-exclamation"></i><span>Mijn incidenten</span></a>
      <a class="ssp-quick-link" href="changes.php"><i class="fa-solid fa-pen-to-square"></i><span>Mijn wijzigingen</span></a>
      <a class="ssp-quick-link" href="assets.php"><i class="fa-solid fa-laptop"></i><span>Mijn assets</span></a>
      <a class="ssp-quick-link" href="new_incident.php"><i class="fa-solid fa-phone"></i><span>Nieuwe melding</span></a>
      <a class="ssp-quick-link" href="new_change.php"><i class="fa-solid fa-file-circle-plus"></i><span>Nieuwe wijziging</span></a>
      <?php if ( ssp_person_has_group_name( $person, 'SSP_InfraShop' ) ): ?>
      <a class="ssp-quick-link" href="infra_shop.php"><i class="fa-solid fa-cart-shopping"></i><span>InfraShop</span></a>
      <?php endif; ?>
    </div>
  </article>

  <article class="ssp-panel">
    <div class="ssp-panel-head">
      <h3>Jouw gegevens</h3>
    </div>
    <dl class="ssp-summary-list">
      <div><dt>Klant</dt><dd><?= htmlspecialchars($person['customer_name'] ?? '') ?></dd></div>
      <div><dt>Naam</dt><dd><?= htmlspecialchars(trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))) ?></dd></div>
      <div><dt>E-mail</dt><dd><?= htmlspecialchars($person['email'] ?? '') ?></dd></div>
      <div><dt>Telefoon</dt><dd><?= htmlspecialchars($person['phone'] ?? '') ?></dd></div>
    </dl>
  </article>
</section>
<?php ssp_render_footer(); ?>
