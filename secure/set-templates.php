<?php
require_once( __DIR__ . '/../include/session_helpers.php' );
itsm_secure_session_start();
require_once( __DIR__ . '/../my.php' );
require_once( __DIR__ . '/include/change_helpers.php' );

if ( !isset( $_SESSION['operatorloggedin'] ) ) {
  header( 'Location: login.php' );
  exit;
}
if ( isset( $_SESSION['expires_at'] ) && time() > $_SESSION['expires_at'] ) {
  itsm_destroy_session();
  header( "Location: login.php?expired=1" );
  exit;
}
$logged_in_user = $_SESSION['name'];

$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare( $con, $sql2 );
mysqli_stmt_bind_param( $result2, "s", $logged_in_user );
mysqli_stmt_execute( $result2 );
mysqli_stmt_bind_result( $result2, $operators );
mysqli_stmt_fetch( $result2 );
mysqli_stmt_close( $result2 );
if ( $operators == 0 ) {
  header( "Location: index.php" );
  exit();
}

$type = $_GET['type'] ?? '';
$where = '';
if ( in_array( $type, [ 'INCIDENT', 'CHANGE' ], true ) ) {
  $where = " WHERE t.type = '" . mysqli_real_escape_string( $con, $type ) . "'";
}

$result = mysqli_query( $con, "
    SELECT
      t.*,
      c.name AS category_name,
      s.name AS subcategory_name,
      pg.groupname AS persongroup_name
    FROM itsm_core_templates t
    LEFT JOIN itsm_core_category c ON t.categoryid = c.id
    LEFT JOIN itsm_core_subcategory s ON t.subcategoryid = s.id
    LEFT JOIN itsm_ob_persongroups pg ON t.persongroupid = pg.id
    $where
    ORDER BY t.type ASC, c.name ASC, s.name ASC, t.name ASC
" );
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
  <?php $module_back_url = 'settings.php'; require(__DIR__ . '/include/module_links.php'); ?>
  <center>
    <h1>Sjabloonbeheer</h1>
  </center>
  <a href="new_template.php">Nieuw sjabloon</a>
  <form method="GET">
    <label for="type">Filter op soort:</label>
    <select name="type" id="type" onchange="this.form.submit()">
      <option value="">Alle soorten</option>
      <option value="INCIDENT" <?= $type === 'INCIDENT' ? 'selected' : '' ?>>INCIDENT</option>
      <option value="CHANGE" <?= $type === 'CHANGE' ? 'selected' : '' ?>>CHANGE</option>
    </select>
  </form>
  <div class="results">
    <table border="0" class="results" style="width: 100%;">
      <thead>
        <tr>
          <th style="text-align: start;">Naam</th>
          <th style="text-align: start;">Type</th>
          <th style="text-align: start;">Wijzigingssoort</th>
          <th style="text-align: start;">Persoonsgroep</th>
          <th style="text-align: start;">Categorie</th>
          <th style="text-align: start;">Subcategorie</th>
          <th style="text-align: start;">Actie</th>
        </tr>
      </thead>
      <tbody>
        <?php while ( $row = mysqli_fetch_assoc( $result ) ): ?>
        <tr>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['type']) ?></td>
          <td><?= htmlspecialchars(!empty($row['changerequesttype']) ? change_request_type_label($row['changerequesttype']) : '') ?></td>
          <td><?= htmlspecialchars($row['persongroup_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['category_name']) ?></td>
          <td><?= htmlspecialchars($row['subcategory_name']) ?></td>
          <td class="tblaction"><a class="btn" href="edit_template.php?id=<?= htmlspecialchars((string)$row['id']) ?>">Open sjabloon</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>
