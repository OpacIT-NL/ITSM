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
$message = '';
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  if ( !empty( $_POST[ 'password' ] ) ) {
    $hashed = password_hash( $_POST[ 'password' ], PASSWORD_DEFAULT );

    $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_operators 
        SET password = ? 
        WHERE username = ?
    " );

    mysqli_stmt_bind_param( $stmt, "ss", $hashed, $logged_in_user );
    mysqli_stmt_execute( $stmt );
    $message = 'Je wachtwoord is bijgewerkt.';
  }
}
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE username=?" );
mysqli_stmt_bind_param( $stmt, "s", $logged_in_user );
mysqli_stmt_execute( $stmt );
$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );
if ( !$operator ) {
  die( "Operator not found" );
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <section class="profile-hero">
    <div class="profile-avatar">
      <?= htmlspecialchars(strtoupper(substr((string)$operator['firstname'], 0, 1) . substr((string)$operator['lastname'], 0, 1))) ?>
    </div>
    <div>
      <p class="profile-eyebrow">Mijn account</p>
      <h1><?= htmlspecialchars(trim(($operator['firstname'] ?? '') . ' ' . ($operator['lastname'] ?? ''))) ?></h1>
      <p><?= htmlspecialchars($operator['email'] ?? '') ?></p>
    </div>
  </section>

  <?php if ( $message !== '' ): ?>
  <div class="profile-success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <section class="profile-grid">
    <article class="profile-card">
      <h2>Profielgegevens</h2>
      <dl class="profile-details">
        <div><dt>Voornaam</dt><dd><?= htmlspecialchars($operator['firstname']) ?></dd></div>
        <div><dt>Achternaam</dt><dd><?= htmlspecialchars($operator['lastname']) ?></dd></div>
        <div><dt>Gebruikersnaam</dt><dd><?= htmlspecialchars($operator['username']) ?></dd></div>
        <div><dt>E-mail</dt><dd><?= htmlspecialchars($operator['email']) ?></dd></div>
      </dl>
    </article>

    <article class="profile-card">
      <h2>Beveiliging</h2>
      <p class="info-note">Wijzig hier alleen je wachtwoord. Laat het veld leeg als je niets wilt aanpassen.</p>
      <form method="post" class="profile-password-form">
        <div class="form-group">
          <label>Nieuw wachtwoord</label>
          <input type="password" name="password" autocomplete="new-password" placeholder="Nieuw wachtwoord">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Wachtwoord opslaan</button>
        </div>
      </form>
    </article>
  </section>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
