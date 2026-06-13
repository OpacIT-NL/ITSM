<?php
session_start();

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
require_once( __DIR__ . '/../my.php' );

// Authorization check
$sql2 = "SELECT operators FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: ob-menu.php" );
  exit();
}
// Validate ID
if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = ( int )$_GET[ 'id' ];

// All boolean fields
$boolFields = [
  'allowlogin', 'firstlineincidents', 'secondlineincidents', 'reqforchange',
  'simplechange', 'extchange', 'problems', 'operations', 'assets', 'persons',
  'operators', 'buildings', 'customers', 'suppliers', 'groups', 'events', 'ubm',
  'reporting', 'isadmin'
];
if ( isset( $_POST[ 'delete' ] ) ) {
  $delete_links_stmt = mysqli_prepare( $con, "DELETE FROM itsm_ob_opgrouplinks WHERE operatorid = ?" );
  mysqli_stmt_bind_param( $delete_links_stmt, "i", $id );
  mysqli_stmt_execute( $delete_links_stmt );
  mysqli_stmt_close( $delete_links_stmt );

  $stmt = mysqli_prepare( $con, "DELETE FROM itsm_ob_operators WHERE id = ?" );
  mysqli_stmt_bind_param( $stmt, "i", $id );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Delete failed: " . mysqli_stmt_error( $stmt ) );
  }

  header( 'Location: operators.php' );
  exit;
}

// Handle form submit
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {

  // Convert checkboxes to 0/1
  $boolValues = [];
  foreach ( $boolFields as $field ) {
    $boolValues[ $field ] = isset( $_POST[ $field ] ) ? 1 : 0;
  }

  $preferred_language = trim( (string)( $_POST['preferredlanguage'] ?? '' ) );
  $preferred_language = $preferred_language !== '' ? itsm_normalize_language_code( $preferred_language ) : null;
  $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_operators SET
            firstname=?,
            lastname=?,
            email=?,
            phone=?,
            username=?,
            allowlogin=?,
            firstlineincidents=?,
            secondlineincidents=?,
            reqforchange=?,
            simplechange=?,
            extchange=?,
            problems=?,
            operations=?,
            assets=?,
            persons=?,
            operators=?,
            buildings=?,
            customers=?,
            suppliers=?,
            groups=?,
            events=?,
            ubm=?,
            reporting=?,
            isadmin=?,
            preferredlanguage=?
        WHERE id=?
    " );

  mysqli_stmt_bind_param(
    $stmt,
    'sssss' . str_repeat( 'i', count( $boolFields ) ) . 'si',
    $_POST[ 'firstname' ],
    $_POST[ 'lastname' ],
    $_POST[ 'email' ],
    $_POST[ 'phone' ],
    $_POST[ 'username' ],
    $boolValues[ 'allowlogin' ],
    $boolValues[ 'firstlineincidents' ],
    $boolValues[ 'secondlineincidents' ],
    $boolValues[ 'reqforchange' ],
    $boolValues[ 'simplechange' ],
    $boolValues[ 'extchange' ],
    $boolValues[ 'problems' ],
    $boolValues[ 'operations' ],
    $boolValues[ 'assets' ],
    $boolValues[ 'persons' ],
    $boolValues[ 'operators' ],
    $boolValues[ 'buildings' ],
    $boolValues[ 'customers' ],
    $boolValues[ 'suppliers' ],
    $boolValues[ 'groups' ],
    $boolValues[ 'events' ],
    $boolValues[ 'ubm' ],
    $boolValues[ 'reporting' ],
    $boolValues[ 'isadmin' ],
    $preferred_language,
    $id
  );

  if ( !mysqli_stmt_execute( $stmt ) ) {
    die( "Update failed: " . mysqli_stmt_error( $stmt ) );
  }
  mysqli_stmt_close( $stmt );
  // Only update password if a new one is entered
  if ( !empty( $_POST[ 'password' ] ) ) {
    $hashed = password_hash( $_POST[ 'password' ], PASSWORD_DEFAULT );

    $stmt = mysqli_prepare( $con, "
        UPDATE itsm_ob_operators 
        SET password = ? 
        WHERE id = ?
    " );

    mysqli_stmt_bind_param( $stmt, "si", $hashed, $id );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );
  }

  $selected_group_ids = array_values( array_unique( array_map( 'intval', $_POST['operatorgroups'] ?? [] ) ) );
  $valid_group_ids = [];
  $groups_result = mysqli_query( $con, "SELECT id FROM itsm_ob_operatorgroups" );
  if ( !$groups_result ) {
    die( "Group lookup failed: " . mysqli_error( $con ) );
  }
  while ( $group_row = mysqli_fetch_assoc( $groups_result ) ) {
    $valid_group_ids[] = (int)$group_row['id'];
  }
  $selected_group_ids = array_values( array_intersect( $selected_group_ids, $valid_group_ids ) );

  mysqli_begin_transaction( $con );
  try {
    $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_ob_opgrouplinks WHERE operatorid = ?" );
    mysqli_stmt_bind_param( $delete_stmt, "i", $id );
    if ( !mysqli_stmt_execute( $delete_stmt ) ) {
      throw new Exception( mysqli_stmt_error( $delete_stmt ) );
    }
    mysqli_stmt_close( $delete_stmt );

    if ( !empty( $selected_group_ids ) ) {
      $insert_stmt = mysqli_prepare( $con, "INSERT INTO itsm_ob_opgrouplinks (groupid, operatorid) VALUES (?, ?)" );
      foreach ( $selected_group_ids as $group_id ) {
        mysqli_stmt_bind_param( $insert_stmt, "ii", $group_id, $id );
        if ( !mysqli_stmt_execute( $insert_stmt ) ) {
          throw new Exception( mysqli_stmt_error( $insert_stmt ) );
        }
      }
      mysqli_stmt_close( $insert_stmt );
    }

    mysqli_commit( $con );
  } catch ( Exception $e ) {
    mysqli_rollback( $con );
    die( "Group update failed: " . $e->getMessage() );
  }

  header( 'Location: operators.php' );
  exit;
}

// Fetch operator
$stmt = mysqli_prepare( $con, "SELECT * FROM itsm_ob_operators WHERE id=?" );
mysqli_stmt_bind_param( $stmt, "i", $id );
mysqli_stmt_execute( $stmt );

$result = mysqli_stmt_get_result( $stmt );
$operator = mysqli_fetch_assoc( $result );


if ( !$operator ) {
  die( "Operator not found" );
}

$operator_groups_result = mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_operatorgroups ORDER BY groupname ASC" );
$operator_groups = $operator_groups_result ? mysqli_fetch_all( $operator_groups_result, MYSQLI_ASSOC ) : [];

$linked_group_ids = [];
$linked_stmt = mysqli_prepare( $con, "SELECT groupid FROM itsm_ob_opgrouplinks WHERE operatorid = ?" );
mysqli_stmt_bind_param( $linked_stmt, "i", $id );
mysqli_stmt_execute( $linked_stmt );
$linked_result = mysqli_stmt_get_result( $linked_stmt );
while ( $linked_row = mysqli_fetch_assoc( $linked_result ) ) {
  $linked_group_ids[] = (int)$linked_row['groupid'];
}
mysqli_stmt_close( $linked_stmt );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content"> <?php $list_back_url = 'operators.php'; require(__DIR__ . '/include/back_links.php'); ?>
  <center>
    <h1>Behandelaar bewerken:
      <?= htmlspecialchars($operator['firstname']) ?>
      <?= htmlspecialchars($operator['lastname']) ?>
    </h1>
  </center>
  <div class="form-wrapper">
    <div class="form-card operator-form-card">
      <div class="ticket-view-tabs caller-card-tabs" role="tablist">
        <button type="button" class="caller-card-tab is-active" data-ticket-view-tab="operator" role="tab" aria-selected="true">Behandelaar</button>
        <button type="button" class="caller-card-tab" data-ticket-view-tab="groups" role="tab" aria-selected="false">Behandelaarsgroepen</button>
      </div>
      <form method="post" class="form-grid">
        <div class="ticket-view-panel is-active" data-ticket-view-panel="operator">
        <div class="form-group"> 
          <!-- Basic fields -->
          <label>Voornaam:
            <input type="text" name="firstname" value="<?= htmlspecialchars($operator['firstname']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Achternaam:
            <input type="text" name="lastname" value="<?= htmlspecialchars($operator['lastname']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>E-mail:
            <input type="email" name="email" value="<?= htmlspecialchars($operator['email']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Telefoonnummer:
            <input type="text" name="phone" value="<?= htmlspecialchars($operator['phone']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label>Gebruikersnaam:
            <input type="text" name="username" value="<?= htmlspecialchars($operator['username']) ?>">
          </label>
        </div>
        <br>
        <div class="form-group">
          <label><?= htmlspecialchars(t('operator.preferred_language')) ?>:
            <select name="preferredlanguage">
              <option value="">-</option>
              <?php foreach ( itsm_available_languages() as $code => $label ): ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= ($operator['preferredlanguage'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <hr>
        
        <!-- Boolean fields -->
        <h3>Rechten</h3>
        <div class=checkbox-grid>
          <?php foreach ($boolFields as $field): ?>
          <label>
            <input type="checkbox" name="<?= $field ?>" <?= $operator[$field] ? 'checked' : '' ?>>
            <?= ucfirst($field) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <hr>
        <div class="form-group"> 
          <!-- Password (optional safe handling) -->
          <label><?= htmlspecialchars(t('Nieuw wachtwoord (laat leeg om niet te bewerken)')) ?>:
            <input type="password" name="password">
          </label>
        </div>
        <br>
        <br>
        <div class="form-actions">
          <button class="btn-primary" type="submit">Opslaan</button>
          <button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze behandelaar wil verwijderen?');"
        class="btn-danger"> Verwijder behandelaar </button>
        </div>
        </div>
        <div class="ticket-view-panel" data-ticket-view-panel="groups">
          <h3>Behandelaarsgroepen</h3>
          <?php if ( empty( $operator_groups ) ): ?>
          <p class="info-note">Er zijn nog geen behandelaarsgroepen beschikbaar.</p>
          <?php else: ?>
          <p class="info-note">Selecteer alle groepen waar deze behandelaar lid van moet zijn.</p>
          <div class="operator-group-grid">
            <?php foreach ( $operator_groups as $group ): ?>
            <?php $group_id = (int)$group['id']; ?>
            <label class="operator-group-option">
              <input type="checkbox" name="operatorgroups[]" value="<?= $group_id ?>" <?= in_array( $group_id, $linked_group_ids, true ) ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($group['groupname']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Opslaan</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('[data-ticket-view-tab]').forEach((tab) => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.ticketViewTab;
    document.querySelectorAll('[data-ticket-view-tab]').forEach((button) => {
      const active = button.dataset.ticketViewTab === target;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-ticket-view-panel]').forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.ticketViewPanel === target);
    });
  });
});
</script>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
