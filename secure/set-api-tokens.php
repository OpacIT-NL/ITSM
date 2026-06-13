<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/../include/api_token_helpers.php' );

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
itsm_ensure_api_token_table( $con );

$stmt = mysqli_prepare( $con, "SELECT id, isadmin FROM itsm_ob_operators WHERE username = ? LIMIT 1" );
mysqli_stmt_bind_param( $stmt, 's', $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $logged_in_operator_id, $is_admin );
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );
if ( (int)$is_admin !== 1 ) {
  header( 'Location: index.php' );
  exit;
}

$new_token = $_SESSION['new_api_token'] ?? '';
unset( $_SESSION['new_api_token'] );
$message = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  $action = $_POST['action'] ?? '';

  if ( $action === 'create' ) {
    $name = trim( $_POST['name'] ?? '' );
    $operatorid = (int)( $_POST['operatorid'] ?? 0 );
    $expiresat = trim( $_POST['expiresat'] ?? '' );
    $expiresat_value = $expiresat !== '' ? str_replace( 'T', ' ', $expiresat ) . ':00' : null;

    if ( $name === '' || $operatorid <= 0 ) {
      $message = 'Naam en behandelaar zijn verplicht.';
    } else {
      $token = itsm_generate_api_token();
      $prefix = itsm_api_token_prefix( $token );
      $hash = itsm_hash_api_token( $token );
      $stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_api_tokens (name, token_prefix, token_hash, operatorid, createdby, expiresat, active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
      " );
      mysqli_stmt_bind_param( $stmt, 'sssiis', $name, $prefix, $hash, $operatorid, $logged_in_operator_id, $expiresat_value );
      if ( !mysqli_stmt_execute( $stmt ) ) {
        $message = 'Token kon niet worden gemaakt: ' . mysqli_stmt_error( $stmt );
      } else {
        $_SESSION['new_api_token'] = $token;
        header( 'Location: set-api-tokens.php?created=1' );
        exit;
      }
      mysqli_stmt_close( $stmt );
    }
  }

  if ( $action === 'revoke' ) {
    $tokenid = (int)( $_POST['tokenid'] ?? 0 );
    $stmt = mysqli_prepare( $con, "UPDATE itsm_api_tokens SET active = 0 WHERE id = ?" );
    mysqli_stmt_bind_param( $stmt, 'i', $tokenid );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );
    header( 'Location: set-api-tokens.php?revoked=1' );
    exit;
  }
}

if ( isset( $_GET['created'] ) && $new_token === '' ) {
  $message = 'API token gemaakt.';
}
if ( isset( $_GET['revoked'] ) ) {
  $message = 'API token ingetrokken.';
}

$operators = mysqli_query( $con, "
  SELECT id, firstname, lastname, username
  FROM itsm_ob_operators
  WHERE allowlogin = 1
  ORDER BY lastname ASC, firstname ASC
" );

$tokens = mysqli_query( $con, "
  SELECT
    t.id,
    t.name,
    t.token_prefix,
    t.createdat,
    t.lastusedat,
    t.expiresat,
    t.active,
    o.firstname,
    o.lastname,
    o.username,
    c.username AS createdby_username
  FROM itsm_api_tokens t
  LEFT JOIN itsm_ob_operators o ON o.id = t.operatorid
  LEFT JOIN itsm_ob_operators c ON c.id = t.createdby
  ORDER BY t.createdat DESC
" );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/settings.php'); ?>
<div class="content">
  <div class="module-section">
    <h1><?= htmlspecialchars(t('API tokens')) ?></h1>
    <p class="info-note"><?= htmlspecialchars(t('Bearer tokens worden eenmalig getoond en daarna als versleutelde hash opgeslagen.')) ?></p>

    <?php if ( $new_token !== '' ): ?>
    <div class="info-note">
      <strong><?= htmlspecialchars(t('Nieuw API token')) ?>:</strong>
      <code><?= htmlspecialchars($new_token) ?></code>
      <br>
      <?= htmlspecialchars(t('Bewaar dit token nu. Het kan later niet opnieuw worden getoond.')) ?>
    </div>
    <?php elseif ( $message !== '' ): ?>
    <p class="info-note"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <div class="record-layout api-token-layout">
      <section class="record-main">
        <div class="form-wrapper record-form-wrapper">
          <div class="form-card record-form-card">
            <h3><?= htmlspecialchars(t('Nieuw token')) ?></h3>
            <form method="post" class="form-grid">
              <input type="hidden" name="action" value="create">
              <div class="form-group">
                <label><?= htmlspecialchars(t('Naam')) ?>:
                  <input type="text" name="name" required>
                </label>
              </div>
              <div class="form-group">
                <label><?= htmlspecialchars(t('Behandelaar')) ?>:
                  <select name="operatorid" required>
                    <option value=""><?= htmlspecialchars(t('Selecteer behandelaar')) ?></option>
                    <?php while ( $operator = mysqli_fetch_assoc( $operators ) ): ?>
                    <?php $operator_name = trim( ( $operator['firstname'] ?? '' ) . ' ' . ( $operator['lastname'] ?? '' ) ); ?>
                    <option value="<?= (int)$operator['id'] ?>"><?= htmlspecialchars($operator_name . ' (' . $operator['username'] . ')') ?></option>
                    <?php endwhile; ?>
                  </select>
                </label>
              </div>
              <div class="form-group">
                <label><?= htmlspecialchars(t('Verloopt op')) ?>:
                  <input type="datetime-local" name="expiresat">
                </label>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn-primary"><?= htmlspecialchars(t('Maak token')) ?></button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <aside class="record-side">
        <div class="ticket-card">
          <h3><?= htmlspecialchars(t('API gebruik')) ?></h3>
          <p class="info-note"><code>Authorization: Bearer &lt;token&gt;</code></p>
          <p class="info-note"><code>/api/v1/index.php?resource=incidents</code></p>
        </div>
      </aside>
    </div>

    <div class="results">
      <table border="0" class="results" style="width: 100%;">
        <thead>
          <tr>
            <th style="text-align: start;"><?= htmlspecialchars(t('Naam')) ?></th>
            <th style="text-align: start;"><?= htmlspecialchars(t('Behandelaar')) ?></th>
            <th style="text-align: start;"><?= htmlspecialchars(t('Prefix')) ?></th>
            <th style="text-align: start;"><?= htmlspecialchars(t('Aangemaakt')) ?></th>
            <th style="text-align: start;"><?= htmlspecialchars(t('Laatst gebruikt')) ?></th>
            <th style="text-align: start;"><?= htmlspecialchars(t('Verloopt')) ?></th>
            <th style="text-align: start;"><?= htmlspecialchars(t('Status')) ?></th>
            <th style="text-align: start;"><?= htmlspecialchars(t('Actie')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php while ( $token = mysqli_fetch_assoc( $tokens ) ): ?>
          <?php $operator_name = trim( ( $token['firstname'] ?? '' ) . ' ' . ( $token['lastname'] ?? '' ) ); ?>
          <tr>
            <td><?= htmlspecialchars($token['name']) ?></td>
            <td><?= htmlspecialchars($operator_name . ' (' . ( $token['username'] ?? '' ) . ')') ?></td>
            <td><code><?= htmlspecialchars($token['token_prefix']) ?></code></td>
            <td><?= htmlspecialchars($token['createdat']) ?></td>
            <td><?= htmlspecialchars($token['lastusedat'] ?? '') ?></td>
            <td><?= htmlspecialchars($token['expiresat'] ?? '') ?></td>
            <td><?= (int)$token['active'] === 1 ? htmlspecialchars(t('Actief')) : htmlspecialchars(t('Ingetrokken')) ?></td>
            <td class="tblaction">
              <?php if ( (int)$token['active'] === 1 ): ?>
              <form method="post" onsubmit="return confirm('Token intrekken?');">
                <input type="hidden" name="action" value="revoke">
                <input type="hidden" name="tokenid" value="<?= (int)$token['id'] ?>">
                <button type="submit" class="btn"><?= htmlspecialchars(t('Intrekken')) ?></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
