<?php
session_start();

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
require_once( __DIR__ . '/../my.php' );

function normalize_asset_date( $value ) {
  $value = trim( (string)$value );
  if ( $value === '' ) {
    return null;
  }

  foreach ( [ 'd-m-Y', 'Y-m-d' ] as $format ) {
    $date = DateTime::createFromFormat( $format, $value );
    if ( $date && $date->format( $format ) === $value ) {
      return $date->format( 'Y-m-d' );
    }
  }

  return false;
}

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

if ( !isset( $_GET[ 'type' ] ) || !is_numeric( $_GET[ 'type' ] ) ) {
  die( "Geen type geselecteerd" );
}

$type_id = ( int )$_GET[ 'type' ];

$stmt_type = $con->prepare( "SELECT id, type FROM itsm_am_types WHERE id = ?" );
$stmt_type->bind_param( "i", $type_id );
$stmt_type->execute();
$result_type = $stmt_type->get_result();
$row_type = $result_type->fetch_assoc();

if ( !$row_type ) {
  die( "Invalid type" );
}

$type_name = $row_type[ 'type' ];

$stmt2 = $con->prepare( "
    SELECT *
    FROM itsm_am_fields
    WHERE type = ?
    ORDER BY field ASC
" );
$stmt2->bind_param( "i", $type_id );
$stmt2->execute();
$result_fields = $stmt2->get_result();

$status_stmt = $con->prepare( "
    SELECT id, name, ready, closed
    FROM itsm_core_status
    WHERE type = 'ASSET'
    ORDER BY name ASC
" );
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$statuses = $status_result->fetch_all( MYSQLI_ASSOC );

$customer_stmt = $con->prepare( "
    SELECT id, din, name
    FROM itsm_ob_customers
    ORDER BY din ASC, name ASC
" );
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customers = $customer_result->fetch_all( MYSQLI_ASSOC );

$person_stmt = $con->prepare( "
    SELECT id, customerid, firstname, lastname
    FROM itsm_ob_persons
    ORDER BY lastname ASC, firstname ASC
" );
$person_stmt->execute();
$person_result = $person_stmt->get_result();
$persons = $person_result->fetch_all( MYSQLI_ASSOC );

$form_values = [
  'objectid' => '',
  'startdate' => '',
  'enddate' => '',
  'price' => '',
  'status' => '',
  'customer' => '',
  'owner' => ''
];

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  $form_values = [
    'objectid' => trim( $_POST[ 'objectid' ] ?? '' ),
    'startdate' => trim( $_POST[ 'startdate' ] ?? '' ),
    'enddate' => trim( $_POST[ 'enddate' ] ?? '' ),
    'price' => trim( $_POST[ 'price' ] ?? '' ),
    'status' => $_POST[ 'status' ] ?? '',
    'customer' => $_POST[ 'customer' ] ?? '',
    'owner' => $_POST[ 'owner' ] ?? ''
  ];

  $startdate = normalize_asset_date( $form_values[ 'startdate' ] );
  $enddate = normalize_asset_date( $form_values[ 'enddate' ] );

  if ( $startdate === false || $enddate === false ) {
    die( "Gebruik een geldige datum in DD-MM-YYYY of YYYY-MM-DD formaat." );
  }

  $status_id = isset( $_POST[ 'status' ] ) && is_numeric( $_POST[ 'status' ] ) ? (int)$_POST[ 'status' ] : 0;
  $selected_status = null;

  foreach ( $statuses as $status ) {
    if ( (int)$status[ 'id' ] === $status_id ) {
      $selected_status = $status;
      break;
    }
  }

  if ( !$selected_status ) {
    die( "Selecteer een geldige asset status." );
  }

  $owner_id = isset( $_POST[ 'owner' ] ) && is_numeric( $_POST[ 'owner' ] ) ? (int)$_POST[ 'owner' ] : 0;
  $selected_owner = null;

  foreach ( $persons as $person ) {
    if ( (int)$person[ 'id' ] === $owner_id ) {
      $selected_owner = $person;
      break;
    }
  }

  if ( !$selected_owner ) {
    die( "Selecteer een geldige eigenaar." );
  }

  $customer_id = isset( $_POST[ 'customer' ] ) && is_numeric( $_POST[ 'customer' ] ) ? (int)$_POST[ 'customer' ] : 0;
  if ( $customer_id === 0 || (int)$selected_owner[ 'customerid' ] !== $customer_id ) {
    die( "De geselecteerde persoon hoort niet bij de gekozen klant." );
  }

  $stmt = $con->prepare( "
        INSERT INTO itsm_am_assets (
            type,
            objectid,
            owner,
            startdate,
            enddate,
            price,
            status,
            active,
            archived,
            customfield1,
            customfield2,
            customfield3,
            customfield4,
            customfield5,
            customfield6,
            customfield7,
            customfield8,
            customfield9,
            customfield10
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  " );

  $ready = (int)$selected_status[ 'ready' ];
  $closed = (int)$selected_status[ 'closed' ];
  $customfield1 = $_POST[ 'customfield1' ] ?? '';
  $customfield2 = $_POST[ 'customfield2' ] ?? '';
  $customfield3 = $_POST[ 'customfield3' ] ?? '';
  $customfield4 = $_POST[ 'customfield4' ] ?? '';
  $customfield5 = $_POST[ 'customfield5' ] ?? '';
  $customfield6 = $_POST[ 'customfield6' ] ?? '';
  $customfield7 = $_POST[ 'customfield7' ] ?? '';
  $customfield8 = $_POST[ 'customfield8' ] ?? '';
  $customfield9 = $_POST[ 'customfield9' ] ?? '';
  $customfield10 = $_POST[ 'customfield10' ] ?? '';

  $stmt->bind_param(
    "isisssiiissssssssss",
    $type_id,
    $form_values[ 'objectid' ],
    $owner_id,
    $startdate,
    $enddate,
    $form_values[ 'price' ],
    $status_id,
    $ready,
    $closed,
    $customfield1,
    $customfield2,
    $customfield3,
    $customfield4,
    $customfield5,
    $customfield6,
    $customfield7,
    $customfield8,
    $customfield9,
    $customfield10
  );

  if ( !$stmt->execute() ) {
    die( "Insert failed: " . $stmt->error );
  }

  header( "Location: assets.php?filtertype=$type_id" );
  exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<div class="content">
<<<<<<< Updated upstream

<a href="assets.php?filtertype=<?= htmlspecialchars((string)$type_id) ?>">Ga terug</a>
=======
<?php $list_back_url = 'assets.php?filtertype=' . urlencode( (string)$type_id ); require(__DIR__ . '/include/back_links.php'); ?>
>>>>>>> Stashed changes

<center>
  <h1>Nieuw asset (<?= htmlspecialchars($type_name) ?>)</h1>
</center>

<div class="form-wrapper">
  <div class="form-card">

    <form method="post" class="form-grid">

      <div class="form-group">
        <label>Object ID:
          <input type="text" name="objectid" value="<?= htmlspecialchars($form_values['objectid']) ?>" required>
        </label>
      </div>

      <div class="form-group">
        <label>Startdatum:
          <input type="date" id="startdate_picker" value="<?= htmlspecialchars(normalize_asset_date($form_values['startdate']) ?: '') ?>">
          <input type="text" id="startdate" name="startdate" value="<?= htmlspecialchars($form_values['startdate']) ?>" placeholder="DD-MM-YYYY of YYYY-MM-DD">
        </label>
      </div>

      <div class="form-group">
        <label>Einddatum:
          <input type="date" id="enddate_picker" value="<?= htmlspecialchars(normalize_asset_date($form_values['enddate']) ?: '') ?>">
          <input type="text" id="enddate" name="enddate" value="<?= htmlspecialchars($form_values['enddate']) ?>" placeholder="DD-MM-YYYY of YYYY-MM-DD">
        </label>
      </div>

      <div class="form-group">
        <label>Prijs:
          <input type="text" name="price" value="<?= htmlspecialchars($form_values['price']) ?>">
        </label>
      </div>

      <div class="form-group">
        <label>Status:
          <select name="status" required>
            <option value="">Selecteer een status</option>
            <?php foreach ( $statuses as $status ): ?>
            <option value="<?= htmlspecialchars((string)$status['id']) ?>" <?= (string)$form_values['status'] === (string)$status['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($status['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="form-group">
        <label>Klant:
          <select id="customer" name="customer" required>
            <option value="">Selecteer een klant</option>
            <?php foreach ( $customers as $customer ): ?>
            <option value="<?= htmlspecialchars((string)$customer['id']) ?>" <?= (string)$form_values['customer'] === (string)$customer['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(trim(($customer['din'] ?? '') . ' - ' . $customer['name'], ' -')) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="form-group">
        <label>Persoon:
          <select id="owner" name="owner" required>
            <option value="">Selecteer eerst een klant</option>
          </select>
        </label>
      </div>

      <hr>
      <h3>Vrije velden</h3>

      <?php while ( $field = mysqli_fetch_assoc( $result_fields ) ): ?>
      <?php
        $column = $field[ 'field' ];
        $label = !empty( $field[ 'name' ] ) ? $field[ 'name' ] : $field[ 'field' ];
        if ( empty( $field['name'] ) ) {
          continue;
        }
      ?>
      <div class="form-group">
        <label>
          <?= htmlspecialchars( $label ) ?>:
          <input type="text" name="<?= $column ?>" value="<?= htmlspecialchars($_POST[$column] ?? '') ?>">
        </label>
      </div>
      <?php endwhile; ?>

      <div class="form-actions">
        <button class="btn-primary" type="submit">Opslaan</button>
      </div>

    </form>

  </div>
</div>

</div>

<script>
const persons = <?= json_encode($persons, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function syncDateInputs(textId, pickerId) {
  const textInput = document.getElementById(textId);
  const pickerInput = document.getElementById(pickerId);

  if (!textInput || !pickerInput) {
    return;
  }

  pickerInput.addEventListener('change', () => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(pickerInput.value)) {
      const [year, month, day] = pickerInput.value.split('-');
      textInput.value = `${day}-${month}-${year}`;
    } else {
      textInput.value = '';
    }
  });
}

syncDateInputs('startdate', 'startdate_picker');
syncDateInputs('enddate', 'enddate_picker');

function populateOwners(customerId, selectedOwnerId) {
  const ownerSelect = document.getElementById('owner');
  if (!ownerSelect) {
    return;
  }

  ownerSelect.innerHTML = '';

  if (!customerId) {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = 'Selecteer eerst een klant';
    ownerSelect.appendChild(option);
    return;
  }

  const defaultOption = document.createElement('option');
  defaultOption.value = '';
  defaultOption.textContent = 'Selecteer een persoon';
  ownerSelect.appendChild(defaultOption);

  persons
    .filter((person) => String(person.customerid) === String(customerId))
    .forEach((person) => {
      const option = document.createElement('option');
      option.value = String(person.id);
      option.textContent = `${person.lastname}, ${person.firstname}`;
      if (String(person.id) === String(selectedOwnerId)) {
        option.selected = true;
      }
      ownerSelect.appendChild(option);
    });
}

const customerSelect = document.getElementById('customer');
if (customerSelect) {
  populateOwners(customerSelect.value, <?= json_encode((string)$form_values['owner']) ?>);
  customerSelect.addEventListener('change', () => {
    populateOwners(customerSelect.value, '');
  });
}
</script>

<?php require_once(__DIR__ . '/nav/end.php'); ?>
