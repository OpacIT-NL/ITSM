<?php
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/portal_helpers.php' );
require_once( __DIR__ . '/../secure/include/news_helpers.php' );

$person = ssp_require_login( $con );
$counts = ssp_dashboard_counts( $con, $person );
$news_items = news_fetch_items( $con, 'public', 6 );

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

<?php if ( !empty( $news_items ) ): ?>
<section class="ssp-panel" style="margin-top: 22px;">
  <div class="ssp-panel-head">
    <h3>Nieuws</h3>
  </div>
  <?= news_render_cards( $news_items ) ?>
</section>
<?php endif; ?>

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
  <?php if ( ssp_person_is_manager( $person ) ): ?>
  <a class="ssp-stat-card" href="incidents.php?view=open&scope=customer">
    <span>Klantincidenten</span>
    <strong><?= htmlspecialchars((string)$counts['customer_open_incidents']) ?></strong>
    <small>Open incidenten van alle personen van jouw klant</small>
  </a>
  <a class="ssp-stat-card" href="changes.php?view=open&scope=customer">
    <span>Klantwijzigingen</span>
    <strong><?= htmlspecialchars((string)$counts['customer_open_changes']) ?></strong>
    <small>Open wijzigingen van alle personen van jouw klant</small>
  </a>
  <?php endif; ?>
</section>

<section class="ssp-dashboard-grid">
  <article class="ssp-panel">
    <div class="ssp-panel-head">
      <h3>Snel naar</h3>
    </div>
    <div class="ssp-quick-actions">
      <a class="ssp-quick-link" href="incidents.php"><i class="fa-solid fa-triangle-exclamation"></i><span>Mijn incidenten</span></a>
      <a class="ssp-quick-link" href="changes.php"><i class="fa-solid fa-pen-to-square"></i><span>Mijn wijzigingen</span></a>
      <a class="ssp-quick-link" href="knowledge.php"><i class="fa-solid fa-book-open"></i><span>Kennisbank</span></a>
      <a class="ssp-quick-link" href="assets.php"><i class="fa-solid fa-laptop"></i><span>Mijn assets</span></a>
      <a class="ssp-quick-link" href="new_incident.php"><i class="fa-solid fa-phone"></i><span>Nieuwe melding</span></a>
      <a class="ssp-quick-link" href="new_change.php"><i class="fa-solid fa-file-circle-plus"></i><span>Nieuwe wijziging</span></a>
      <?php if ( ssp_person_is_manager( $person ) ): ?>
      <a class="ssp-quick-link" href="incidents.php?view=all&scope=customer"><i class="fa-solid fa-users"></i><span>Klantincidenten</span></a>
      <a class="ssp-quick-link" href="changes.php?view=all&scope=customer"><i class="fa-solid fa-people-group"></i><span>Klantwijzigingen</span></a>
      <?php endif; ?>
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
