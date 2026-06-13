<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/imap_helpers.php' );

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
$stmt = mysqli_prepare( $con, "SELECT id, isadmin FROM itsm_ob_operators WHERE username = ?" );
mysqli_stmt_bind_param( $stmt, 's', $logged_in_user );
mysqli_stmt_execute( $stmt );
mysqli_stmt_bind_result( $stmt, $operator_id, $is_admin );
mysqli_stmt_fetch( $stmt );
mysqli_stmt_close( $stmt );

if ( (int)$is_admin === 0 ) {
  header( 'Location: index.php' );
  exit;
}

$errors = [];
$edit_id = isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ? (int)$_GET['id'] : 0;
$task_types = [ 'incident' => 'Incident', 'change' => 'Wijzigingsaanvraag' ];
$form_values = [
  'id' => '',
  'folder' => '',
  'tasktype' => 'incident',
  'categoryid' => '',
  'subcategoryid' => '',
  'fallback_customerid' => '',
  'fallback_personid' => '',
  'operatorgroupid' => '',
  'active' => 1
];

if ( isset( $_POST['delete_rule'] ) && isset( $_POST['id'] ) && is_numeric( $_POST['id'] ) ) {
  $delete_id = (int)$_POST['id'];
  $delete_stmt = mysqli_prepare( $con, "DELETE FROM itsm_core_imap_rules WHERE id = ?" );
  mysqli_stmt_bind_param( $delete_stmt, 'i', $delete_id );
  mysqli_stmt_execute( $delete_stmt );
  header( 'Location: set-imaprules.php' );
  exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && !isset( $_POST['delete_rule'] ) ) {
  $form_values = [
    'id' => $_POST['id'] ?? '',
    'folder' => trim( $_POST['folder'] ?? '' ),
    'tasktype' => $_POST['tasktype'] ?? 'incident',
    'categoryid' => $_POST['categoryid'] ?? '',
    'subcategoryid' => $_POST['subcategoryid'] ?? '',
    'fallback_customerid' => $_POST['fallback_customerid'] ?? '',
    'fallback_personid' => $_POST['fallback_personid'] ?? '',
    'operatorgroupid' => $_POST['operatorgroupid'] ?? '',
    'active' => isset( $_POST['active'] ) ? 1 : 0
  ];

  if ( $form_values['folder'] === '' ) {
    $errors[] = 'IMAP-map is verplicht.';
  }
  if ( !array_key_exists( $form_values['tasktype'], $task_types ) ) {
    $errors[] = 'Selecteer een geldige taaksoort.';
  }
  if ( is_numeric( $form_values['categoryid'] ) ) {
    $expected_category_type = $form_values['tasktype'] === 'incident' ? 'INCIDENT' : 'CHANGE';
    $category_check_stmt = mysqli_prepare( $con, "SELECT id FROM itsm_core_category WHERE id = ? AND type = ?" );
    $category_check_id = (int)$form_values['categoryid'];
    mysqli_stmt_bind_param( $category_check_stmt, 'is', $category_check_id, $expected_category_type );
    mysqli_stmt_execute( $category_check_stmt );
    mysqli_stmt_store_result( $category_check_stmt );
    if ( mysqli_stmt_num_rows( $category_check_stmt ) === 0 ) {
      $errors[] = 'De categorie hoort niet bij de gekozen taaksoort.';
    }
    mysqli_stmt_close( $category_check_stmt );
  }
  if ( is_numeric( $form_values['fallback_customerid'] ) && $form_values['fallback_personid'] !== '' && is_numeric( $form_values['fallback_personid'] ) ) {
    $person_check_stmt = mysqli_prepare( $con, "SELECT id FROM itsm_ob_persons WHERE id = ? AND customerid = ?" );
    $person_check_id = (int)$form_values['fallback_personid'];
    $customer_check_id = (int)$form_values['fallback_customerid'];
    mysqli_stmt_bind_param( $person_check_stmt, 'ii', $person_check_id, $customer_check_id );
    mysqli_stmt_execute( $person_check_stmt );
    mysqli_stmt_store_result( $person_check_stmt );
    if ( mysqli_stmt_num_rows( $person_check_stmt ) === 0 ) {
      $errors[] = 'De fallback persoon hoort niet bij de fallback klant.';
    }
    mysqli_stmt_close( $person_check_stmt );
  }

  if ( empty( $errors ) ) {
    $rule_id = $form_values['id'] !== '' && is_numeric( $form_values['id'] ) ? (int)$form_values['id'] : 0;
    $folder = $form_values['folder'];
    $tasktype = $form_values['tasktype'];
    $categoryid = $form_values['categoryid'] !== '' ? (int)$form_values['categoryid'] : null;
    $subcategoryid = $form_values['subcategoryid'] !== '' ? (int)$form_values['subcategoryid'] : null;
    $fallback_customerid = $form_values['fallback_customerid'] !== '' ? (int)$form_values['fallback_customerid'] : null;
    $fallback_personid = $form_values['fallback_personid'] !== '' ? (int)$form_values['fallback_personid'] : null;
    $operatorgroupid = $form_values['operatorgroupid'] !== '' ? (int)$form_values['operatorgroupid'] : null;
    $active = (int)$form_values['active'];
    $createdby = (int)$operator_id;

    if ( $rule_id > 0 ) {
      $save_stmt = mysqli_prepare( $con, "
        UPDATE itsm_core_imap_rules
        SET folder = ?, tasktype = ?, categoryid = ?, subcategoryid = ?, fallback_customerid = ?, fallback_personid = ?, operatorgroupid = ?, active = ?
        WHERE id = ?
      " );
      mysqli_stmt_bind_param( $save_stmt, 'ssiiiiiii', $folder, $tasktype, $categoryid, $subcategoryid, $fallback_customerid, $fallback_personid, $operatorgroupid, $active, $rule_id );
    } else {
      $save_stmt = mysqli_prepare( $con, "
        INSERT INTO itsm_core_imap_rules
          (folder, tasktype, categoryid, subcategoryid, fallback_customerid, fallback_personid, operatorgroupid, active, createdby)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      " );
      mysqli_stmt_bind_param( $save_stmt, 'ssiiiiiii', $folder, $tasktype, $categoryid, $subcategoryid, $fallback_customerid, $fallback_personid, $operatorgroupid, $active, $createdby );
    }

    if ( !mysqli_stmt_execute( $save_stmt ) ) {
      $errors[] = 'IMAP-regel opslaan mislukt: ' . mysqli_stmt_error( $save_stmt );
    } else {
      header( 'Location: set-imaprules.php' );
      exit;
    }
  }
} elseif ( $edit_id > 0 ) {
  $edit_stmt = mysqli_prepare( $con, "SELECT * FROM itsm_core_imap_rules WHERE id = ?" );
  mysqli_stmt_bind_param( $edit_stmt, 'i', $edit_id );
  mysqli_stmt_execute( $edit_stmt );
  $edit_result = mysqli_stmt_get_result( $edit_stmt );
  $rule = mysqli_fetch_assoc( $edit_result );
  mysqli_stmt_close( $edit_stmt );

  if ( $rule ) {
    $form_values = [
      'id' => (string)$rule['id'],
      'folder' => $rule['folder'],
      'tasktype' => $rule['tasktype'],
      'categoryid' => (string)$rule['categoryid'],
      'subcategoryid' => (string)($rule['subcategoryid'] ?? ''),
      'fallback_customerid' => (string)$rule['fallback_customerid'],
      'fallback_personid' => (string)$rule['fallback_personid'],
      'operatorgroupid' => (string)($rule['operatorgroupid'] ?? ''),
      'active' => (int)$rule['active']
    ];
  }
}

$categories = mysqli_query( $con, "SELECT id, type, name FROM itsm_core_category WHERE type IN ('INCIDENT','CHANGE') ORDER BY type ASC, name ASC" )->fetch_all( MYSQLI_ASSOC );
$subcategories = mysqli_query( $con, "SELECT id, parent, name FROM itsm_core_subcategory ORDER BY name ASC" )->fetch_all( MYSQLI_ASSOC );
$customers = mysqli_query( $con, "SELECT id, din, name FROM itsm_ob_customers ORDER BY din ASC, name ASC" )->fetch_all( MYSQLI_ASSOC );
$persons = mysqli_query( $con, "SELECT id, customerid, firstname, lastname, email FROM itsm_ob_persons ORDER BY lastname ASC, firstname ASC" )->fetch_all( MYSQLI_ASSOC );
$groups = mysqli_query( $con, "SELECT id, groupname FROM itsm_ob_operatorgroups ORDER BY groupname ASC" )->fetch_all( MYSQLI_ASSOC );
$rules = mysqli_query( $con, "
  SELECT r.*, c.name AS category_name, sc.name AS subcategory_name, cust.name AS customer_name,
         CONCAT(p.lastname, ', ', p.firstname) AS person_name, g.groupname
  FROM itsm_core_imap_rules r
  LEFT JOIN itsm_core_category c ON r.categoryid = c.id
  LEFT JOIN itsm_core_subcategory sc ON r.subcategoryid = sc.id
  LEFT JOIN itsm_ob_customers cust ON r.fallback_customerid = cust.id
  LEFT JOIN itsm_ob_persons p ON r.fallback_personid = p.id
  LEFT JOIN itsm_ob_operatorgroups g ON r.operatorgroupid = g.id
  ORDER BY r.tasktype ASC, r.folder ASC
" );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'settings.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center><h1>IMAP importregels</h1></center>

  <?php foreach ( $errors as $error ): ?>
    <p class="ssp-error"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <div class="form-wrapper record-form-wrapper">
    <div class="form-card form-card-wide record-form-card">
      <h2><?= $form_values['id'] !== '' ? 'IMAP-regel bewerken' : 'Nieuwe IMAP-regel' ?></h2>
      <p class="info-note">IMAP-configuratie wordt gelezen uit <code>../config/imap.ini</code>. Gebruik hier de mapnaam zoals de server die kent, bijvoorbeeld <code>INBOX.Incidenten</code>.</p>
      <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$form_values['id']) ?>">

        <div class="form-group">
          <label>IMAP-map</label>
          <input type="text" name="folder" value="<?= htmlspecialchars($form_values['folder']) ?>" placeholder="INBOX.Incidenten" required>
        </div>

        <div class="form-group">
          <label>Taaksoort</label>
          <select name="tasktype" required>
            <?php foreach ( $task_types as $value => $label ): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $form_values['tasktype'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Categorie</label>
          <select name="categoryid">
            <option value="">Geen categorie</option>
            <?php foreach ( $categories as $category ): ?>
              <option value="<?= htmlspecialchars((string)$category['id']) ?>" <?= (string)$form_values['categoryid'] === (string)$category['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($category['type'] . ' - ' . $category['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Subcategorie</label>
          <select name="subcategoryid">
            <option value="">Geen subcategorie</option>
            <?php foreach ( $subcategories as $subcategory ): ?>
              <option value="<?= htmlspecialchars((string)$subcategory['id']) ?>" <?= (string)$form_values['subcategoryid'] === (string)$subcategory['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($subcategory['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Fallback klant</label>
          <select name="fallback_customerid">
            <option value="">Geen fallback klant</option>
            <?php foreach ( $customers as $customer ): ?>
              <option value="<?= htmlspecialchars((string)$customer['id']) ?>" <?= (string)$form_values['fallback_customerid'] === (string)$customer['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(($customer['din'] ?? $customer['id']) . ' - ' . $customer['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Fallback persoon</label>
          <select name="fallback_personid">
            <option value="">Geen fallback persoon</option>
            <?php foreach ( $persons as $person ): ?>
              <option value="<?= htmlspecialchars((string)$person['id']) ?>" <?= (string)$form_values['fallback_personid'] === (string)$person['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($person['lastname'] . ', ' . $person['firstname'] . ' (' . $person['email'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="info-note">Onbekende e-mailafzenders worden zonder persoon opgeslagen, met alleen het e-mailadres op de ticketkaart.</p>
        </div>

        <div class="form-group">
          <label>Behandelaarsgroep</label>
          <select name="operatorgroupid">
            <option value="">Geen groep</option>
            <?php foreach ( $groups as $group ): ?>
              <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= (string)$form_values['operatorgroupid'] === (string)$group['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($group['groupname']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <label class="checkbox-label"><input type="checkbox" name="active" <?= !empty($form_values['active']) ? 'checked' : '' ?>> Actief</label>

        <div class="form-actions">
          <button type="submit" class="btn-primary">IMAP-regel opslaan</button>
          <?php if ( $form_values['id'] !== '' ): ?>
            <button type="submit" name="delete_rule" class="btn-danger" onclick="return confirm('Weet je zeker dat je deze IMAP-regel wil verwijderen?');">IMAP-regel verwijderen</button>
            <a href="set-imaprules.php">Nieuwe regel</a>
          <?php endif; ?>
          <a href="run_imap_import.php">Import nu uitvoeren</a>
        </div>
      </form>
    </div>
  </div>

  <div class="results">
    <table>
      <thead>
        <tr>
          <th>Map</th>
          <th>Taaksoort</th>
          <th>Categorie</th>
          <th>Fallback klant</th>
          <th>Fallback persoon</th>
          <th>Groep</th>
          <th>Actief</th>
          <th>Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $rule = mysqli_fetch_assoc( $rules ) ): ?>
          <tr>
            <td><?= htmlspecialchars($rule['folder']) ?></td>
            <td><?= htmlspecialchars($task_types[$rule['tasktype']] ?? $rule['tasktype']) ?></td>
            <td><?= htmlspecialchars($rule['category_name'] ?? '') ?><?= !empty($rule['subcategory_name']) ? ' / ' . htmlspecialchars($rule['subcategory_name']) : '' ?></td>
            <td><?= htmlspecialchars($rule['customer_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($rule['person_name'] ?? 'Geen') ?></td>
            <td><?= htmlspecialchars($rule['groupname'] ?? '') ?></td>
            <td><?= (int)$rule['active'] === 1 ? 'Ja' : 'Nee' ?></td>
            <td class="tblaction"><a href="set-imaprules.php?id=<?= htmlspecialchars((string)$rule['id']) ?>">Open regel</a></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
