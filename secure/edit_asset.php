<?php
session_start();
require_once( __DIR__ . '/../include/session_helpers.php' );

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

function display_asset_date( $value ) {
  $normalized = normalize_asset_date( $value );
  if ( $normalized === false || $normalized === null ) {
    return '';
  }

  $date = DateTime::createFromFormat( 'Y-m-d', $normalized );
  return $date ? $date->format( 'd-m-Y' ) : '';
}

function asset_connection_label( $asset ) {
  $type = $asset['typename'] ?? '';
  return trim( (string)$asset['objectid'] . ( $type !== '' ? ' (' . $type . ')' : '' ) );
}

function build_asset_connection_graph( $connections ) {
  $graph = [
    'by_source' => [],
    'by_node' => [],
    'incoming' => []
  ];

  foreach ( $connections as $connection ) {
    $source_id = (int)$connection['sourceassetid'];
    $target_id = (int)$connection['targetassetid'];
    $graph['by_source'][$source_id][] = $connection;
    $graph['by_node'][$source_id][] = $target_id;
    $graph['by_node'][$target_id][] = $source_id;
    $graph['incoming'][$target_id] = ( $graph['incoming'][$target_id] ?? 0 ) + 1;
    $graph['incoming'][$source_id] = $graph['incoming'][$source_id] ?? 0;
  }

  return $graph;
}

function find_asset_connection_component( $asset_id, $connections_by_node ) {
  $component = [];
  $queue = [ (int)$asset_id ];

  while ( !empty( $queue ) ) {
    $current_id = array_shift( $queue );
    if ( isset( $component[$current_id] ) ) {
      continue;
    }
    $component[$current_id] = true;

    foreach ( $connections_by_node[$current_id] ?? [] as $related_id ) {
      if ( !isset( $component[(int)$related_id] ) ) {
        $queue[] = (int)$related_id;
      }
    }
  }

  return $component;
}

function render_asset_connection_node( $asset_id, $current_asset_id, $component, $connections_by_source, $asset_lookup, &$rendered, $path = [], $incoming_connection = null ) {
  $asset = $asset_lookup[$asset_id] ?? null;
  if ( !$asset ) {
    return '';
  }

  $is_current_asset = (int)$asset_id === (int)$current_asset_id;
  $html = '<li>';
  if ( $incoming_connection ) {
    $html .= '<span class="asset-config-connection">' . htmlspecialchars( $incoming_connection['connectionname'] ) . '</span> ';
  }
  $html .= '<a class="' . ( $is_current_asset ? 'asset-config-current' : '' ) . '" href="edit_asset.php?id=' . (int)$asset_id . '">' . htmlspecialchars( asset_connection_label( $asset ) ) . '</a>';
  if ( $is_current_asset ) {
    $html .= ' <span class="muted">(huidig asset)</span>';
  }
  if ( $incoming_connection && !empty( $incoming_connection['notes'] ) ) {
    $html .= ' <span class="muted">- ' . htmlspecialchars( $incoming_connection['notes'] ) . '</span>';
  }

  if ( isset( $path[$asset_id] ) ) {
    $html .= ' <span class="muted">(lus gedetecteerd)</span></li>';
    return $html;
  }
  if ( isset( $rendered[$asset_id] ) ) {
    $html .= ' <span class="muted">(al weergegeven)</span></li>';
    return $html;
  }

  $rendered[$asset_id] = true;
  $path[$asset_id] = true;
  $children = '';

  foreach ( $connections_by_source[$asset_id] ?? [] as $connection ) {
    $target_id = (int)$connection['targetassetid'];
    if ( !isset( $component[$target_id] ) ) {
      continue;
    }
    $target = $asset_lookup[$target_id] ?? null;
    if ( !$target ) {
      continue;
    }

    $children .= render_asset_connection_node( $target_id, $current_asset_id, $component, $connections_by_source, $asset_lookup, $rendered, $path, $connection );
  }

  if ( $children !== '' ) {
    $html .= '<ul class="asset-config-tree">' . $children . '</ul>';
  }

  $html .= '</li>';
  return $html;
}

function render_asset_connection_tree( $asset_id, $all_connections, $asset_lookup ) {
  $graph = build_asset_connection_graph( $all_connections );
  $component = find_asset_connection_component( $asset_id, $graph['by_node'] );
  $roots = [];

  foreach ( array_keys( $component ) as $component_asset_id ) {
    if ( (int)( $graph['incoming'][$component_asset_id] ?? 0 ) === 0 ) {
      $roots[] = (int)$component_asset_id;
    }
  }
  if ( empty( $roots ) ) {
    $roots[] = (int)$asset_id;
  }

  usort( $roots, function ( $left_id, $right_id ) use ( $asset_lookup ) {
    return strcasecmp( asset_connection_label( $asset_lookup[$left_id] ?? [] ), asset_connection_label( $asset_lookup[$right_id] ?? [] ) );
  } );

  $rendered = [];
  $html = '<ul class="asset-config-tree asset-config-tree-root">';
  foreach ( $roots as $root_id ) {
    $html .= render_asset_connection_node( $root_id, $asset_id, $component, $graph['by_source'], $asset_lookup, $rendered );
  }
  $html .= '</ul>';

  return $html;
}

// Authorization check
$sql2 = "SELECT id, assets FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operator_id, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: am-menu.php" );
  exit();
}

if ( !isset( $_GET[ 'id' ] ) || !is_numeric( $_GET[ 'id' ] ) ) {
  die( "Invalid ID" );
}

$id = (int)$_GET['id'];

$stmt = $con->prepare( "
    SELECT a.*, t.type AS typename
    FROM itsm_am_assets a
    LEFT JOIN itsm_am_types t ON a.type = t.id
    WHERE a.id = ?
" );
$stmt->bind_param( "i", $id );
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();

if ( !$asset ) {
  die( "Asset not found" );
}

$stmt2 = $con->prepare( "
    SELECT *
    FROM itsm_am_fields
    WHERE type = ?
    ORDER BY field ASC
" );
$stmt2->bind_param( "i", $asset['type'] );
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

$owner_customer_id = '';
foreach ( $persons as $person ) {
  if ( (int)$person['id'] === (int)$asset['owner'] ) {
    $owner_customer_id = (string)$person['customerid'];
    break;
  }
}

$form_values = [
  'objectid' => $asset['objectid'],
  'startdate' => display_asset_date( $asset['startdate'] ),
  'enddate' => display_asset_date( $asset['enddate'] ),
  'price' => $asset['price'],
  'status' => (string)$asset['status'],
  'customer' => $owner_customer_id,
  'owner' => (string)$asset['owner']
];

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' && isset( $_POST['asset_connection_action'] ) ) {
  $action = $_POST['asset_connection_action'];
  $posted_configuration_id = isset( $_POST['configurationid'] ) && is_numeric( $_POST['configurationid'] ) ? (int)$_POST['configurationid'] : 0;

  if ( $action === 'create_configuration' ) {
    $configuration_name = trim( $_POST['configurationname'] ?? '' );
    $configuration_description = trim( $_POST['configurationdescription'] ?? '' );
    $configuration_customer_id = isset( $_POST['configurationcustomerid'] ) && is_numeric( $_POST['configurationcustomerid'] ) ? (int)$_POST['configurationcustomerid'] : 0;
    $configuration_template_id = isset( $_POST['configurationtemplateid'] ) && is_numeric( $_POST['configurationtemplateid'] ) ? (int)$_POST['configurationtemplateid'] : null;
    $created_by = (int)$operator_id;

    if ( $configuration_name === '' || $configuration_customer_id <= 0 ) {
      header( 'Location: edit_asset.php?id=' . $id . '&configuration_error=1' );
      exit;
    }

    if ( $configuration_template_id === null ) {
      $stmt = mysqli_prepare( $con, "
          INSERT INTO itsm_am_configurations
              (customerid, templateid, name, description, createdby)
          VALUES (?, NULL, ?, ?, ?)
      " );
      mysqli_stmt_bind_param( $stmt, "issi", $configuration_customer_id, $configuration_name, $configuration_description, $created_by );
    } else {
      $stmt = mysqli_prepare( $con, "
          INSERT INTO itsm_am_configurations
              (customerid, templateid, name, description, createdby)
          VALUES (?, ?, ?, ?, ?)
      " );
      mysqli_stmt_bind_param( $stmt, "iissi", $configuration_customer_id, $configuration_template_id, $configuration_name, $configuration_description, $created_by );
    }
    mysqli_stmt_execute( $stmt );
    $new_configuration_id = mysqli_insert_id( $con );
    mysqli_stmt_close( $stmt );

    $stmt = mysqli_prepare( $con, "INSERT IGNORE INTO itsm_am_configurationassets (configurationid, assetid) VALUES (?, ?)" );
    mysqli_stmt_bind_param( $stmt, "ii", $new_configuration_id, $id );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );

    header( 'Location: edit_asset.php?id=' . $id . '&configid=' . $new_configuration_id . '&configuration_saved=1' );
    exit;
  }

  if ( $action === 'join_configuration' ) {
    if ( $posted_configuration_id <= 0 ) {
      header( 'Location: edit_asset.php?id=' . $id . '&configuration_error=1' );
      exit;
    }

    $stmt = mysqli_prepare( $con, "INSERT IGNORE INTO itsm_am_configurationassets (configurationid, assetid) VALUES (?, ?)" );
    mysqli_stmt_bind_param( $stmt, "ii", $posted_configuration_id, $id );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );

    header( 'Location: edit_asset.php?id=' . $id . '&configid=' . $posted_configuration_id . '&configuration_saved=1' );
    exit;
  }

  if ( $action === 'leave_configuration' ) {
    if ( $posted_configuration_id > 0 ) {
      $stmt = mysqli_prepare( $con, "
          DELETE FROM itsm_am_assetconnections
          WHERE configurationid = ? AND (sourceassetid = ? OR targetassetid = ?)
      " );
      mysqli_stmt_bind_param( $stmt, "iii", $posted_configuration_id, $id, $id );
      mysqli_stmt_execute( $stmt );
      mysqli_stmt_close( $stmt );

      $stmt = mysqli_prepare( $con, "DELETE FROM itsm_am_configurationassets WHERE configurationid = ? AND assetid = ?" );
      mysqli_stmt_bind_param( $stmt, "ii", $posted_configuration_id, $id );
      mysqli_stmt_execute( $stmt );
      mysqli_stmt_close( $stmt );
    }

    header( 'Location: edit_asset.php?id=' . $id . '&configuration_saved=1' );
    exit;
  }

  if ( $action === 'add' ) {
    $target_asset_id = isset( $_POST['targetassetid'] ) && is_numeric( $_POST['targetassetid'] ) ? (int)$_POST['targetassetid'] : 0;
    $connection_type_id = isset( $_POST['connectiontypeid'] ) && is_numeric( $_POST['connectiontypeid'] ) ? (int)$_POST['connectiontypeid'] : 0;
    $notes = trim( $_POST['connectionnotes'] ?? '' );
    $created_by = (int)$operator_id;

    if ( $posted_configuration_id <= 0 || $target_asset_id <= 0 || $connection_type_id <= 0 || $target_asset_id === $id ) {
      header( 'Location: edit_asset.php?id=' . $id . '&connection_error=1' );
      exit;
    }

    $stmt = mysqli_prepare( $con, "INSERT IGNORE INTO itsm_am_configurationassets (configurationid, assetid) VALUES (?, ?), (?, ?)" );
    mysqli_stmt_bind_param( $stmt, "iiii", $posted_configuration_id, $id, $posted_configuration_id, $target_asset_id );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );

    $stmt = mysqli_prepare( $con, "
        INSERT IGNORE INTO itsm_am_assetconnections
            (configurationid, sourceassetid, targetassetid, connectiontypeid, notes, createdby)
        VALUES (?, ?, ?, ?, ?, ?)
    " );
    mysqli_stmt_bind_param( $stmt, "iiiisi", $posted_configuration_id, $id, $target_asset_id, $connection_type_id, $notes, $created_by );
    mysqli_stmt_execute( $stmt );
    mysqli_stmt_close( $stmt );

    header( 'Location: edit_asset.php?id=' . $id . '&configid=' . $posted_configuration_id . '&connections_saved=1' );
    exit;
  }

  if ( $action === 'delete' ) {
    $connection_id = isset( $_POST['connectionid'] ) && is_numeric( $_POST['connectionid'] ) ? (int)$_POST['connectionid'] : 0;
    if ( $connection_id > 0 ) {
      $stmt = mysqli_prepare( $con, "
          DELETE FROM itsm_am_assetconnections
          WHERE id = ? AND (sourceassetid = ? OR targetassetid = ?)
      " );
      mysqli_stmt_bind_param( $stmt, "iii", $connection_id, $id, $id );
      mysqli_stmt_execute( $stmt );
      mysqli_stmt_close( $stmt );
    }

    $config_query = $posted_configuration_id > 0 ? '&configid=' . $posted_configuration_id : '';
    header( 'Location: edit_asset.php?id=' . $id . $config_query . '&connections_saved=1' );
    exit;
  }
}

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
  $form_values = [
    'objectid' => trim( $_POST['objectid'] ?? '' ),
    'startdate' => trim( $_POST['startdate'] ?? '' ),
    'enddate' => trim( $_POST['enddate'] ?? '' ),
    'price' => trim( $_POST['price'] ?? '' ),
    'status' => $_POST['status'] ?? '',
    'customer' => $_POST['customer'] ?? '',
    'owner' => $_POST['owner'] ?? ''
  ];

  $startdate = normalize_asset_date( $form_values['startdate'] );
  $enddate = normalize_asset_date( $form_values['enddate'] );

  if ( $startdate === false || $enddate === false ) {
    die( "Gebruik een geldige datum in DD-MM-YYYY of YYYY-MM-DD formaat." );
  }

  $status_id = isset( $_POST['status'] ) && is_numeric( $_POST['status'] ) ? (int)$_POST['status'] : 0;
  $selected_status = null;

  foreach ( $statuses as $status ) {
    if ( (int)$status['id'] === $status_id ) {
      $selected_status = $status;
      break;
    }
  }

  if ( !$selected_status ) {
    die( "Selecteer een geldige asset status." );
  }

  $owner_id = isset( $_POST['owner'] ) && is_numeric( $_POST['owner'] ) ? (int)$_POST['owner'] : 0;
  $selected_owner = null;

  foreach ( $persons as $person ) {
    if ( (int)$person['id'] === $owner_id ) {
      $selected_owner = $person;
      break;
    }
  }

  if ( !$selected_owner ) {
    die( "Selecteer een geldige eigenaar." );
  }

  $customer_id = isset( $_POST['customer'] ) && is_numeric( $_POST['customer'] ) ? (int)$_POST['customer'] : 0;
  if ( $customer_id === 0 || (int)$selected_owner['customerid'] !== $customer_id ) {
    die( "De geselecteerde persoon hoort niet bij de gekozen klant." );
  }

  $stmt = $con->prepare( "
        UPDATE itsm_am_assets SET
            objectid=?,
            owner=?,
            startdate=?,
            enddate=?,
            price=?,
            status=?,
            active=?,
            archived=?,
            customfield1=?,
            customfield2=?,
            customfield3=?,
            customfield4=?,
            customfield5=?,
            customfield6=?,
            customfield7=?,
            customfield8=?,
            customfield9=?,
            customfield10=?
        WHERE id=?
  " );

  $ready = (int)$selected_status['ready'];
  $closed = (int)$selected_status['closed'];
  $customfield1 = $_POST['customfield1'] ?? '';
  $customfield2 = $_POST['customfield2'] ?? '';
  $customfield3 = $_POST['customfield3'] ?? '';
  $customfield4 = $_POST['customfield4'] ?? '';
  $customfield5 = $_POST['customfield5'] ?? '';
  $customfield6 = $_POST['customfield6'] ?? '';
  $customfield7 = $_POST['customfield7'] ?? '';
  $customfield8 = $_POST['customfield8'] ?? '';
  $customfield9 = $_POST['customfield9'] ?? '';
  $customfield10 = $_POST['customfield10'] ?? '';

  $stmt->bind_param(
    "sisssiiissssssssssi",
    $form_values['objectid'],
    $owner_id,
    $startdate,
    $enddate,
    $form_values['price'],
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
    $customfield10,
    $id
  );

  if ( !$stmt->execute() ) {
    die( "Update failed: " . $stmt->error );
  }

  header( 'Location: assets.php?filtertype=' . $asset['type'] );
  exit;
}

$connection_type_result = mysqli_query( $con, "
    SELECT id, name, reverse_name
    FROM itsm_am_connectiontypes
    WHERE active = 1
    ORDER BY name ASC
" );
$connection_types = $connection_type_result ? mysqli_fetch_all( $connection_type_result, MYSQLI_ASSOC ) : [];

$asset_result = mysqli_query( $con, "
    SELECT a.id, a.objectid, t.type AS typename
    FROM itsm_am_assets a
    LEFT JOIN itsm_am_types t ON a.type = t.id
    ORDER BY a.objectid ASC
" );
$all_assets = $asset_result ? mysqli_fetch_all( $asset_result, MYSQLI_ASSOC ) : [];
$asset_lookup = [];
foreach ( $all_assets as $known_asset ) {
  $asset_lookup[(int)$known_asset['id']] = $known_asset;
}

$template_result = mysqli_query( $con, "
    SELECT id, name, description
    FROM itsm_am_configurationtemplates
    WHERE active = 1
    ORDER BY name ASC
" );
$configuration_templates = $template_result ? mysqli_fetch_all( $template_result, MYSQLI_ASSOC ) : [];

$configuration_result = mysqli_query( $con, "
    SELECT c.id, c.customerid, c.templateid, c.name, c.description, cust.name AS customername, cust.din, tpl.name AS templatename
    FROM itsm_am_configurations c
    INNER JOIN itsm_ob_customers cust ON c.customerid = cust.id
    LEFT JOIN itsm_am_configurationtemplates tpl ON c.templateid = tpl.id
    WHERE c.active = 1
    ORDER BY cust.name ASC, c.name ASC
" );
$all_configurations = $configuration_result ? mysqli_fetch_all( $configuration_result, MYSQLI_ASSOC ) : [];
$asset_configurations = [];
$configuration_lookup = [];
foreach ( $all_configurations as $configuration ) {
  $configuration_lookup[(int)$configuration['id']] = $configuration;
}

$asset_configuration_stmt = mysqli_prepare( $con, "
    SELECT c.id, c.customerid, c.templateid, c.name, c.description, cust.name AS customername, cust.din, tpl.name AS templatename
    FROM itsm_am_configurationassets ca
    INNER JOIN itsm_am_configurations c ON ca.configurationid = c.id
    INNER JOIN itsm_ob_customers cust ON c.customerid = cust.id
    LEFT JOIN itsm_am_configurationtemplates tpl ON c.templateid = tpl.id
    WHERE ca.assetid = ? AND c.active = 1
    ORDER BY cust.name ASC, c.name ASC
" );
mysqli_stmt_bind_param( $asset_configuration_stmt, "i", $id );
mysqli_stmt_execute( $asset_configuration_stmt );
$asset_configurations = mysqli_fetch_all( mysqli_stmt_get_result( $asset_configuration_stmt ), MYSQLI_ASSOC );
mysqli_stmt_close( $asset_configuration_stmt );

$asset_configuration_ids = array_map( function ( $configuration ) {
  return (int)$configuration['id'];
}, $asset_configurations );

$selected_configuration_id = isset( $_GET['configid'] ) && is_numeric( $_GET['configid'] ) ? (int)$_GET['configid'] : 0;
if ( $selected_configuration_id <= 0 || !in_array( $selected_configuration_id, $asset_configuration_ids, true ) ) {
  $selected_configuration_id = !empty( $asset_configuration_ids ) ? $asset_configuration_ids[0] : 0;
}
$selected_configuration = $selected_configuration_id > 0 ? ( $configuration_lookup[$selected_configuration_id] ?? null ) : null;

$outgoing_stmt = mysqli_prepare( $con, "
    SELECT c.id, c.sourceassetid, c.targetassetid, c.notes, ct.name AS connectionname, a.objectid, t.type AS typename
    FROM itsm_am_assetconnections c
    INNER JOIN itsm_am_connectiontypes ct ON c.connectiontypeid = ct.id
    INNER JOIN itsm_am_assets a ON c.targetassetid = a.id
    LEFT JOIN itsm_am_types t ON a.type = t.id
    WHERE c.configurationid = ? AND c.sourceassetid = ?
    ORDER BY ct.name ASC, a.objectid ASC
" );
mysqli_stmt_bind_param( $outgoing_stmt, "ii", $selected_configuration_id, $id );
mysqli_stmt_execute( $outgoing_stmt );
$outgoing_connections = mysqli_fetch_all( mysqli_stmt_get_result( $outgoing_stmt ), MYSQLI_ASSOC );
mysqli_stmt_close( $outgoing_stmt );

$incoming_stmt = mysqli_prepare( $con, "
    SELECT c.id, c.sourceassetid, c.targetassetid, c.notes, ct.name, ct.reverse_name, a.objectid, t.type AS typename
    FROM itsm_am_assetconnections c
    INNER JOIN itsm_am_connectiontypes ct ON c.connectiontypeid = ct.id
    INNER JOIN itsm_am_assets a ON c.sourceassetid = a.id
    LEFT JOIN itsm_am_types t ON a.type = t.id
    WHERE c.configurationid = ? AND c.targetassetid = ?
    ORDER BY ct.name ASC, a.objectid ASC
" );
mysqli_stmt_bind_param( $incoming_stmt, "ii", $selected_configuration_id, $id );
mysqli_stmt_execute( $incoming_stmt );
$incoming_connections = mysqli_fetch_all( mysqli_stmt_get_result( $incoming_stmt ), MYSQLI_ASSOC );
mysqli_stmt_close( $incoming_stmt );

$tree_stmt = mysqli_prepare( $con, "
    SELECT c.sourceassetid, c.targetassetid, c.notes, ct.name AS connectionname
    FROM itsm_am_assetconnections c
    INNER JOIN itsm_am_connectiontypes ct ON c.connectiontypeid = ct.id
    WHERE c.configurationid = ?
    ORDER BY ct.name ASC
" );
$connections_by_source = [];
$configuration_connections = [];
mysqli_stmt_bind_param( $tree_stmt, "i", $selected_configuration_id );
mysqli_stmt_execute( $tree_stmt );
$tree_result = mysqli_stmt_get_result( $tree_stmt );
if ( $tree_result ) {
  while ( $row = mysqli_fetch_assoc( $tree_result ) ) {
    $configuration_connections[] = $row;
    $connections_by_source[(int)$row['sourceassetid']][] = $row;
  }
}
mysqli_stmt_close( $tree_stmt );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<div class="content">

<?php $list_back_url = 'assets.php?filtertype=' . urlencode( (string)$asset['type'] ); require(__DIR__ . '/include/back_links.php'); ?>

<center>
  <h1>Asset bewerken (<?= htmlspecialchars($asset['typename'] ?? (string)$asset['type']) ?>)</h1>
</center>

<form method="post" class="record-layout">
  <div class="incident-column">
    <div class="incident-card incident-left-card">
      <div class="form-grid">
        <h2 class="incident-section-title">Algemeen</h2>
        <hr>
        <div class="form-group">
          <label class="incident-meta-label">Status</label>
          <label>
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
          <label class="incident-meta-label">Klant</label>
          <label>
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
          <label class="incident-meta-label">Persoon</label>
          <label>
            <select id="owner" name="owner" required>
              <option value="">Selecteer eerst een klant</option>
            </select>
          </label>
        </div>
        <hr>
        <div class="form-group">
          <label class="incident-meta-label">Startdatum</label>
          <label>
            <input type="date" id="startdate_picker" value="<?= htmlspecialchars(normalize_asset_date($form_values['startdate']) ?: '') ?>">
            <input type="text" id="startdate" name="startdate" value="<?= htmlspecialchars($form_values['startdate']) ?>" placeholder="DD-MM-YYYY of YYYY-MM-DD">
          </label>
        </div>
        <div class="form-group">
          <label class="incident-meta-label">Einddatum</label>
          <label>
            <input type="date" id="enddate_picker" value="<?= htmlspecialchars(normalize_asset_date($form_values['enddate']) ?: '') ?>">
            <input type="text" id="enddate" name="enddate" value="<?= htmlspecialchars($form_values['enddate']) ?>" placeholder="DD-MM-YYYY of YYYY-MM-DD">
          </label>
        </div>
        <div class="form-group">
          <label class="incident-meta-label">Prijs</label>
          <label><input type="text" name="price" value="<?= htmlspecialchars($form_values['price']) ?>"></label>
        </div>
      </div>
    </div>
  </div>
  <div class="incident-column">
    <div class="incident-card record-main-card">
      <div class="form-grid">
        <input type="text" name="objectid" class="incident-title-input" value="<?= htmlspecialchars($form_values['objectid']) ?>" placeholder="Object ID">
        <h3>Vrije velden</h3>
        <?php while ( $field = mysqli_fetch_assoc( $result_fields ) ):
          $column = $field['field'];
          $label = !empty($field['name']) ? $field['name'] : $field['field'];
          $value = $_POST[$column] ?? $asset[$column];
          if ( empty( $field['name'] ) ) {
            continue;
          }
        ?>
        <div class="form-group">
          <label><?= htmlspecialchars($label) ?>:
            <input type="text" name="<?= $column ?>" value="<?= htmlspecialchars($value) ?>">
          </label>
        </div>
        <?php endwhile; ?>
        <div class="form-actions">
          <button class="btn-primary" type="submit">Opslaan</button>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="asset-config-panel">
  <h2>Configuration Management</h2>

  <?php if ( isset( $_GET['connections_saved'] ) ): ?>
  <p class="success">Asset verbinding opgeslagen.</p>
  <?php endif; ?>
  <?php if ( isset( $_GET['configuration_saved'] ) ): ?>
  <p class="success">Configuratie opgeslagen.</p>
  <?php endif; ?>
  <?php if ( isset( $_GET['connection_error'] ) ): ?>
  <p class="ssp-error">Selecteer een geldig asset en verbindingstype.</p>
  <?php endif; ?>
  <?php if ( isset( $_GET['configuration_error'] ) ): ?>
  <p class="ssp-error">Selecteer een klant en vul een configuratienaam in.</p>
  <?php endif; ?>

  <div class="asset-config-grid">
    <div class="form-card form-card-wide">
      <h3>Actieve configuratie</h3>
      <?php if ( empty( $asset_configurations ) ): ?>
      <p class="info-note">Dit asset is nog niet gekoppeld aan een configuratie.</p>
      <?php else: ?>
      <form method="get" class="form-grid">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="form-group">
          <label>Configuratie:
            <select name="configid" onchange="this.form.submit()">
              <?php foreach ( $asset_configurations as $configuration ): ?>
              <?php
                $customer_label = trim(($configuration['din'] ?? '') . ' - ' . ($configuration['customername'] ?? ''), ' -');
                $template_label = !empty( $configuration['templatename'] ) ? ' / ' . $configuration['templatename'] : '';
              ?>
              <option value="<?= (int)$configuration['id'] ?>" <?= (int)$configuration['id'] === $selected_configuration_id ? 'selected' : '' ?>>
                <?= htmlspecialchars($customer_label . ' / ' . $configuration['name'] . $template_label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </form>
      <?php if ( $selected_configuration ): ?>
      <p class="info-note">
        <?= htmlspecialchars(trim(($selected_configuration['din'] ?? '') . ' - ' . ($selected_configuration['customername'] ?? ''), ' -')) ?>
        <?php if ( !empty( $selected_configuration['templatename'] ) ): ?>
        / <?= htmlspecialchars($selected_configuration['templatename']) ?>
        <?php endif; ?>
      </p>
      <?php endif; ?>
      <form method="post" class="form-actions">
        <input type="hidden" name="asset_connection_action" value="leave_configuration">
        <input type="hidden" name="configurationid" value="<?= (int)$selected_configuration_id ?>">
        <button type="submit" class="btn-danger" onclick="return confirm('Weet je zeker dat je dit asset uit deze configuratie wil verwijderen? Verbindingen van dit asset binnen deze configuratie worden ook verwijderd.');">Verwijder uit configuratie</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="form-card form-card-wide">
      <h3>Nieuwe configuratie</h3>
      <form method="post" class="form-grid">
        <input type="hidden" name="asset_connection_action" value="create_configuration">
        <div class="form-group">
          <label>Klant:
            <select name="configurationcustomerid" required>
              <option value="">Selecteer een klant</option>
              <?php foreach ( $customers as $customer ): ?>
              <option value="<?= (int)$customer['id'] ?>"><?= htmlspecialchars(trim(($customer['din'] ?? '') . ' - ' . $customer['name'], ' -')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-group">
          <label>Template:
            <select name="configurationtemplateid">
              <option value="">Geen template</option>
              <?php foreach ( $configuration_templates as $template ): ?>
              <option value="<?= (int)$template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-group">
          <label>Naam:
            <input type="text" name="configurationname" required placeholder="Bijvoorbeeld: NextCloud productie">
          </label>
        </div>
        <div class="form-group">
          <label>Omschrijving:
            <textarea name="configurationdescription" rows="3"></textarea>
          </label>
        </div>
        <div class="form-actions">
          <button class="btn-primary" type="submit">Maak configuratie</button>
        </div>
      </form>
    </div>

    <div class="form-card form-card-wide">
      <h3>Koppel aan bestaande configuratie</h3>
      <?php $joinable_count = 0; ?>
      <form method="post" class="form-grid">
        <input type="hidden" name="asset_connection_action" value="join_configuration">
        <div class="form-group">
          <label>Configuratie:
            <select name="configurationid" required>
              <option value="">Selecteer een configuratie</option>
              <?php foreach ( $all_configurations as $configuration ): ?>
              <?php if ( in_array( (int)$configuration['id'], $asset_configuration_ids, true ) ) { continue; } $joinable_count++; ?>
              <?php $customer_label = trim(($configuration['din'] ?? '') . ' - ' . ($configuration['customername'] ?? ''), ' -'); ?>
              <option value="<?= (int)$configuration['id'] ?>"><?= htmlspecialchars($customer_label . ' / ' . $configuration['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-actions">
          <button class="btn-primary" type="submit" <?= $joinable_count === 0 ? 'disabled' : '' ?>>Koppel configuratie</button>
        </div>
      </form>
    </div>
  </div>

  <div class="form-card form-card-wide asset-config-overview-card">
    <h3>Alle gekoppelde configuraties</h3>
    <?php if ( empty( $asset_configurations ) ): ?>
    <p class="info-note">Dit asset is nog niet gekoppeld aan configuraties.</p>
    <?php else: ?>
    <div class="asset-config-list">
      <?php foreach ( $asset_configurations as $configuration ): ?>
      <?php
        $customer_label = trim(($configuration['din'] ?? '') . ' - ' . ($configuration['customername'] ?? ''), ' -');
        $is_selected_configuration = (int)$configuration['id'] === $selected_configuration_id;
      ?>
      <div class="asset-config-list-row">
        <div>
          <strong><?= htmlspecialchars($configuration['name']) ?></strong>
          <?php if ( $is_selected_configuration ): ?>
          <span class="asset-config-connection">Actief</span>
          <?php endif; ?>
          <div class="muted"><?= htmlspecialchars($customer_label) ?></div>
          <?php if ( !empty( $configuration['templatename'] ) ): ?>
          <div class="muted">Template: <?= htmlspecialchars($configuration['templatename']) ?></div>
          <?php endif; ?>
          <?php if ( !empty( $configuration['description'] ) ): ?>
          <div><?= htmlspecialchars($configuration['description']) ?></div>
          <?php endif; ?>
        </div>
        <a class="btn" href="edit_asset.php?id=<?= (int)$id ?>&configid=<?= (int)$configuration['id'] ?>">Selecteer</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="asset-config-grid">
    <div class="form-card form-card-wide">
      <h3>Nieuwe verbinding</h3>
      <?php if ( $selected_configuration_id <= 0 ): ?>
      <p class="info-note">Selecteer of maak eerst een configuratie.</p>
      <?php elseif ( empty( $connection_types ) ): ?>
      <p class="info-note">Maak eerst een verbindingstype aan via Asset Management instellingen.</p>
      <?php else: ?>
      <form method="post" class="form-grid">
        <input type="hidden" name="asset_connection_action" value="add">
        <input type="hidden" name="configurationid" value="<?= (int)$selected_configuration_id ?>">
        <div class="form-group">
          <label>Verbinding:
            <select name="connectiontypeid" required>
              <option value="">Selecteer een verbinding</option>
              <?php foreach ( $connection_types as $connection_type ): ?>
              <option value="<?= (int)$connection_type['id'] ?>"><?= htmlspecialchars($connection_type['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-group">
          <label>Gekoppeld asset:
            <select name="targetassetid" required>
              <option value="">Selecteer een asset</option>
              <?php foreach ( $all_assets as $available_asset ): ?>
              <?php if ( (int)$available_asset['id'] === $id ) { continue; } ?>
              <option value="<?= (int)$available_asset['id'] ?>"><?= htmlspecialchars(asset_connection_label($available_asset)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-group">
          <label>Notitie:
            <input type="text" name="connectionnotes" maxlength="255" placeholder="Optioneel">
          </label>
        </div>
        <div class="form-actions">
          <button class="btn-primary" type="submit">Koppel asset</button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <div class="form-card form-card-wide">
      <h3>Configuratieboom</h3>
      <?php $tree_html = render_asset_connection_tree( $id, $configuration_connections, $asset_lookup ); ?>
      <?php if ( $tree_html === '' ): ?>
      <p class="info-note">Geen configuratieboom gevonden.</p>
      <?php else: ?>
      <?= $tree_html ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="asset-config-grid">
    <div class="form-card form-card-wide">
      <h3>Uitgaande verbindingen</h3>
      <?php if ( empty( $outgoing_connections ) ): ?>
      <p class="info-note">Geen uitgaande verbindingen.</p>
      <?php else: ?>
      <div class="asset-config-list">
        <?php foreach ( $outgoing_connections as $connection ): ?>
        <div class="asset-config-list-row">
          <div>
            <strong><?= htmlspecialchars($connection['connectionname']) ?></strong><br>
            <a href="edit_asset.php?id=<?= (int)$connection['targetassetid'] ?>"><?= htmlspecialchars(asset_connection_label($connection)) ?></a>
            <?php if ( !empty( $connection['notes'] ) ): ?>
            <div class="muted"><?= htmlspecialchars($connection['notes']) ?></div>
            <?php endif; ?>
          </div>
          <form method="post">
            <input type="hidden" name="asset_connection_action" value="delete">
            <input type="hidden" name="configurationid" value="<?= (int)$selected_configuration_id ?>">
            <input type="hidden" name="connectionid" value="<?= (int)$connection['id'] ?>">
            <button type="submit" class="btn-danger" onclick="return confirm('Weet je zeker dat je deze asset verbinding wil verwijderen?');">Verwijder</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="form-card form-card-wide">
      <h3>Inkomende verbindingen</h3>
      <?php if ( empty( $incoming_connections ) ): ?>
      <p class="info-note">Geen inkomende verbindingen.</p>
      <?php else: ?>
      <div class="asset-config-list">
        <?php foreach ( $incoming_connections as $connection ): ?>
        <?php $incoming_label = !empty( $connection['reverse_name'] ) ? $connection['reverse_name'] : $connection['name']; ?>
        <div class="asset-config-list-row">
          <div>
            <strong><?= htmlspecialchars($incoming_label) ?></strong><br>
            <a href="edit_asset.php?id=<?= (int)$connection['sourceassetid'] ?>"><?= htmlspecialchars(asset_connection_label($connection)) ?></a>
            <?php if ( !empty( $connection['notes'] ) ): ?>
            <div class="muted"><?= htmlspecialchars($connection['notes']) ?></div>
            <?php endif; ?>
          </div>
          <form method="post">
            <input type="hidden" name="asset_connection_action" value="delete">
            <input type="hidden" name="configurationid" value="<?= (int)$selected_configuration_id ?>">
            <input type="hidden" name="connectionid" value="<?= (int)$connection['id'] ?>">
            <button type="submit" class="btn-danger" onclick="return confirm('Weet je zeker dat je deze asset verbinding wil verwijderen?');">Verwijder</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
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
    option.textContent = <?= json_encode(t('Selecteer eerst een klant')) ?>;
    ownerSelect.appendChild(option);
    return;
  }

  const defaultOption = document.createElement('option');
  defaultOption.value = '';
  defaultOption.textContent = <?= json_encode(t('Selecteer een persoon')) ?>;
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
