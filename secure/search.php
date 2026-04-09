<?php
session_start();
require_once( __DIR__ . '/../my.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  session_unset();
  session_destroy();
  header( 'Location: login.php?expired=1' );
  exit;
}

$search_value = '';
$error = '';
$results = [];

function search_normalize_task_number( $value ) {
  $normalized = strtoupper( preg_replace( '/\s+/', '', trim( $value ) ) );
  if ( preg_match( '/^(INI|EPI|FEA|STR|SUB)(\d{4})(\d{4})$/', $normalized, $matches ) ) {
    return $matches[1] . $matches[2] . ' ' . $matches[3];
  }
  if ( preg_match( '/^(WA)(\d{4})(\d{4})$/', $normalized, $matches ) ) {
    return $matches[1] . $matches[2] . ' ' . $matches[3];
  }
  if ( preg_match( '/^(W)(\d{4})(\d{4})$/', $normalized, $matches ) ) {
    return $matches[1] . $matches[2] . ' ' . $matches[3];
  }
  if ( preg_match( '/^(I)(\d{4})(\d{4})$/', $normalized, $matches ) ) {
    return $matches[1] . $matches[2] . ' ' . $matches[3];
  }

  return '';
}

function search_run_task_lookup( $con, $query ) {
  $like = '%' . $query . '%';
  $sql = "
    SELECT
      'incident' AS taskkind,
      i.id AS taskid,
      i.incidentnumber AS tasknumber,
      i.title AS tasktitle,
      c.name AS customer_name,
      CONCAT(p.lastname, ', ', p.firstname) AS person_name,
      cat.name AS category_name,
      s.name AS status_name,
      g.groupname AS group_name,
      CONCAT(o.lastname, ', ', o.firstname) AS owner_name,
      'Incident' AS tasklabel,
      CONCAT('edit_incident.php?id=', i.id) AS target_url
    FROM itsm_im_incidents i
    LEFT JOIN itsm_ob_customers c ON i.customerid = c.id
    LEFT JOIN itsm_ob_persons p ON i.personid = p.id
    LEFT JOIN itsm_core_category cat ON i.categoryid = cat.id
    LEFT JOIN itsm_core_status s ON i.statusid = s.id
    LEFT JOIN itsm_ob_operatorgroups g ON i.operatorgroupid = g.id
    LEFT JOIN itsm_ob_operators o ON i.operatorid = o.id
    LEFT JOIN itsm_im_incidentcomments ic ON ic.incidentid = i.id
    WHERE
      i.incidentnumber LIKE ? OR
      i.title LIKE ? OR
      i.description LIKE ? OR
      c.name LIKE ? OR
      p.firstname LIKE ? OR
      p.lastname LIKE ? OR
      cat.name LIKE ? OR
      s.name LIKE ? OR
      g.groupname LIKE ? OR
      o.firstname LIKE ? OR
      o.lastname LIKE ? OR
      ic.commenttext LIKE ?

    UNION

    SELECT
      'change' AS taskkind,
      c.id AS taskid,
      c.changenumber AS tasknumber,
      c.title AS tasktitle,
      cust.name AS customer_name,
      CONCAT(per.lastname, ', ', per.firstname) AS person_name,
      cat.name AS category_name,
      st.name AS status_name,
      grp.groupname AS group_name,
      CASE
        WHEN c.requesttype = 'extended' THEN CONCAT(coord.lastname, ', ', coord.firstname)
        ELSE CONCAT(op.lastname, ', ', op.firstname)
      END AS owner_name,
      'Wijziging' AS tasklabel,
      CONCAT('edit_change.php?id=', c.id) AS target_url
    FROM itsm_cm_changes c
    LEFT JOIN itsm_ob_customers cust ON c.customerid = cust.id
    LEFT JOIN itsm_ob_persons per ON c.personid = per.id
    LEFT JOIN itsm_core_category cat ON c.categoryid = cat.id
    LEFT JOIN itsm_core_status st ON c.statusid = st.id
    LEFT JOIN itsm_ob_operatorgroups grp ON c.operatorgroupid = grp.id
    LEFT JOIN itsm_ob_operators op ON c.operatorid = op.id
    LEFT JOIN itsm_ob_operators coord ON c.coordinatorid = coord.id
    LEFT JOIN itsm_cm_changecomments cc ON cc.changeid = c.id
    WHERE
      c.changenumber LIKE ? OR
      c.title LIKE ? OR
      c.description LIKE ? OR
      cust.name LIKE ? OR
      per.firstname LIKE ? OR
      per.lastname LIKE ? OR
      cat.name LIKE ? OR
      st.name LIKE ? OR
      grp.groupname LIKE ? OR
      op.firstname LIKE ? OR
      op.lastname LIKE ? OR
      coord.firstname LIKE ? OR
      coord.lastname LIKE ? OR
      cc.commenttext LIKE ?

    UNION

    SELECT
      'activity' AS taskkind,
      a.id AS taskid,
      a.activitynumber AS tasknumber,
      a.title AS tasktitle,
      cust2.name AS customer_name,
      CONCAT(per2.lastname, ', ', per2.firstname) AS person_name,
      cat2.name AS category_name,
      st2.name AS status_name,
      grp2.groupname AS group_name,
      CONCAT(op2.lastname, ', ', op2.firstname) AS owner_name,
      'Wijzigingsactiviteit' AS tasklabel,
      CONCAT('edit_change_activity.php?id=', a.id) AS target_url
    FROM itsm_cm_changeactivities a
    INNER JOIN itsm_cm_changes c2 ON a.changeid = c2.id
    LEFT JOIN itsm_ob_customers cust2 ON c2.customerid = cust2.id
    LEFT JOIN itsm_ob_persons per2 ON c2.personid = per2.id
    LEFT JOIN itsm_core_category cat2 ON c2.categoryid = cat2.id
    LEFT JOIN itsm_core_status st2 ON a.statusid = st2.id
    LEFT JOIN itsm_ob_operatorgroups grp2 ON a.operatorgroupid = grp2.id
    LEFT JOIN itsm_ob_operators op2 ON a.operatorid = op2.id
    LEFT JOIN itsm_cm_changecomments cc2 ON cc2.changeid = c2.id
    WHERE
      a.activitynumber LIKE ? OR
      a.title LIKE ? OR
      a.description LIKE ? OR
      c2.changenumber LIKE ? OR
      c2.title LIKE ? OR
      c2.description LIKE ? OR
      cust2.name LIKE ? OR
      per2.firstname LIKE ? OR
      per2.lastname LIKE ? OR
      cat2.name LIKE ? OR
      st2.name LIKE ? OR
      grp2.groupname LIKE ? OR
      op2.firstname LIKE ? OR
      op2.lastname LIKE ? OR
      cc2.commenttext LIKE ?

    UNION

    SELECT
      'ubm' AS taskkind,
      u.id AS taskid,
      u.ubmnumber AS tasknumber,
      u.title AS tasktitle,
      '' AS customer_name,
      '' AS person_name,
      cat3.name AS category_name,
      st3.name AS status_name,
      grp3.groupname AS group_name,
      CONCAT(op3.lastname, ', ', op3.firstname) AS owner_name,
      CONCAT('UBM ', CASE
        WHEN u.itemtype = 'initiative' THEN 'Initiative'
        WHEN u.itemtype = 'epic' THEN 'Epic'
        WHEN u.itemtype = 'feature' THEN 'Feature'
        WHEN u.itemtype = 'story' THEN 'Story'
        ELSE 'Subtask'
      END) AS tasklabel,
      CONCAT('edit_ubm_item.php?id=', u.id) AS target_url
    FROM itsm_ubm_items u
    LEFT JOIN itsm_core_category cat3 ON u.categoryid = cat3.id
    LEFT JOIN itsm_core_status st3 ON u.statusid = st3.id
    LEFT JOIN itsm_ob_operatorgroups grp3 ON u.operatorgroupid = grp3.id
    LEFT JOIN itsm_ob_operators op3 ON u.operatorid = op3.id
    WHERE
      u.ubmnumber LIKE ? OR
      u.title LIKE ? OR
      u.description LIKE ? OR
      cat3.name LIKE ? OR
      st3.name LIKE ? OR
      grp3.groupname LIKE ? OR
      op3.firstname LIKE ? OR
      op3.lastname LIKE ?

    ORDER BY tasknumber ASC, tasktitle ASC
  ";

  $stmt = mysqli_prepare( $con, $sql );
  if ( !$stmt ) {
    return [];
  }

  mysqli_stmt_bind_param(
    $stmt,
    str_repeat( 's', 49 ),
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like,
    $like
  );
  mysqli_stmt_execute( $stmt );
  $result = mysqli_stmt_get_result( $stmt );
  $rows = [];
  while ( $row = mysqli_fetch_assoc( $result ) ) {
    $rows[] = $row;
  }
  mysqli_stmt_close( $stmt );

  return $rows;
}

if ( isset( $_GET['tasknumber'] ) ) {
  $search_value = trim( $_GET['tasknumber'] ?? '' );
  $normalized_number = search_normalize_task_number( $search_value );

  if ( $search_value === '' ) {
    $error = 'Voer een zoekterm of taaknummer in.';
  } else {
    if ( $normalized_number !== '' ) {
      if ( strncmp( $normalized_number, 'WA', 2 ) === 0 ) {
        $stmt = mysqli_prepare( $con, "SELECT id FROM itsm_cm_changeactivities WHERE activitynumber = ? LIMIT 1" );
        mysqli_stmt_bind_param( $stmt, "s", $normalized_number );
        mysqli_stmt_execute( $stmt );
        $result = mysqli_stmt_get_result( $stmt );
        $row = mysqli_fetch_assoc( $result );
        mysqli_stmt_close( $stmt );

        if ( $row ) {
          header( 'Location: edit_change_activity.php?id=' . (int)$row['id'] . '&from_search=' . urlencode( $search_value ) );
          exit;
        }
      } elseif ( $normalized_number[0] === 'W' ) {
        $stmt = mysqli_prepare( $con, "SELECT id FROM itsm_cm_changes WHERE changenumber = ? LIMIT 1" );
        mysqli_stmt_bind_param( $stmt, "s", $normalized_number );
        mysqli_stmt_execute( $stmt );
        $result = mysqli_stmt_get_result( $stmt );
        $row = mysqli_fetch_assoc( $result );
        mysqli_stmt_close( $stmt );

        if ( $row ) {
          header( 'Location: edit_change.php?id=' . (int)$row['id'] . '&from_search=' . urlencode( $search_value ) );
          exit;
        }
      } elseif ( $normalized_number[0] === 'I' ) {
        $stmt = mysqli_prepare( $con, "SELECT id FROM itsm_im_incidents WHERE incidentnumber = ? LIMIT 1" );
        mysqli_stmt_bind_param( $stmt, "s", $normalized_number );
        mysqli_stmt_execute( $stmt );
        $result = mysqli_stmt_get_result( $stmt );
        $row = mysqli_fetch_assoc( $result );
        mysqli_stmt_close( $stmt );

        if ( $row ) {
          header( 'Location: edit_incident.php?id=' . (int)$row['id'] . '&from_search=' . urlencode( $search_value ) );
          exit;
        }
      } elseif ( preg_match( '/^(INI|EPI|FEA|STR|SUB)\d{4}\s\d{4}$/', $normalized_number ) ) {
        $stmt = mysqli_prepare( $con, "SELECT id FROM itsm_ubm_items WHERE ubmnumber = ? LIMIT 1" );
        mysqli_stmt_bind_param( $stmt, "s", $normalized_number );
        mysqli_stmt_execute( $stmt );
        $result = mysqli_stmt_get_result( $stmt );
        $row = mysqli_fetch_assoc( $result );
        mysqli_stmt_close( $stmt );

        if ( $row ) {
          header( 'Location: edit_ubm_item.php?id=' . (int)$row['id'] . '&from_search=' . urlencode( $search_value ) );
          exit;
        }
      }
    }

    $results = search_run_task_lookup( $con, $search_value );
    if ( empty( $results ) ) {
      $error = 'Geen taken gevonden voor deze zoekterm.';
    }
  }
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'index.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1>Taak zoeken</h1>
  </center>

  <div class="form-wrapper">
    <form method="get" class="form-card search-card">
      <div class="form-grid">
        <?php if ( $error !== '' ): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class="form-group">
          <label>Zoekterm</label>
          <input type="text" name="tasknumber" value="<?= htmlspecialchars($search_value) ?>" placeholder="I2604 0001 / W2604 0001 / WA2604 0001 / INI2604 0001 / titel / klant / omschrijving" required>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Zoeken</button>
        </div>
      </div>
    </form>
  </div>

  <?php if ( !empty( $results ) ): ?>
  <br>
  <div class="results incident-results">
    <table border="0" class="results incident-results-table" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Soort</th>
          <th style="text-align: start;">Nummer</th>
          <th style="text-align: start;">Klant</th>
          <th style="text-align: start;">Persoon</th>
          <th style="text-align: start;">Categorie</th>
          <th style="text-align: start;">Titel</th>
          <th style="text-align: start;">Status</th>
          <th style="text-align: start;">Groep</th>
          <th style="text-align: start;">Behandelaar</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $results as $row ): ?>
        <tr>
          <td><?= htmlspecialchars($row['tasklabel']) ?></td>
          <td><?= htmlspecialchars($row['tasknumber']) ?></td>
          <td><?= htmlspecialchars($row['customer_name']) ?></td>
          <td><?= htmlspecialchars($row['person_name']) ?></td>
          <td><?= htmlspecialchars($row['category_name']) ?></td>
          <td><?= htmlspecialchars($row['tasktitle']) ?></td>
          <td><?= htmlspecialchars($row['status_name']) ?></td>
          <td><?= htmlspecialchars($row['group_name']) ?></td>
          <td><?= htmlspecialchars($row['owner_name']) ?></td>
          <td class="tblaction"><a class="btn" href="<?= htmlspecialchars($row['target_url']) ?>">Open taak</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
