<?php
session_start();

if (!isset($_SESSION['operatorloggedin'])) {
	header('Location: login.php');
	exit;
}
// Absolute expiration check
if (isset($_SESSION['expires_at']) && time() > $_SESSION['expires_at']) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit;
}
$logged_in_user = $_SESSION['name'];
require_once(__DIR__ . '/../my.php');

// Query
$sql = "SELECT b.*, c.din, c.name
        FROM itsm_ob_buildings b
		LEFT JOIN itsm_ob_customers c ON c.id = b.customer
        ORDER BY id ASC";
$result = mysqli_query($con, $sql);

// Authorization check
$sql2 = "SELECT buildings FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare($con, $sql2);
mysqli_stmt_bind_param($result2, "s", $logged_in_user);
mysqli_stmt_execute($result2);
mysqli_stmt_bind_result($result2, $operators);
mysqli_stmt_fetch($result2);
mysqli_stmt_close($result2);
if ($operators == 0) {
    header("Location: ob-menu.php");
    exit();
}

if (!$result) {
    die("Query failed: " . mysqli_error($con));
}

?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<div class="content">
	<a href="ob-menu.php">Ga terug</a>
			<center><h1>Gebouwen</h1></center>

					<p><a href="new_building.php">Nieuw Gebouw</a></p>
	<div class="results">

					
					<table border="0" class=results style="width: 100%;">
    <thead>
        <tr>
            <th style="text-align: start;">ID</th>
            <th style="text-align: start;">Klant</th>
            <th style="text-align: start;">Adres</th>
            <th style="text-align: start;">Postcode</th>
			<th style="text-align: start;">Plaats</th>
			<th style="text-align: start;">ICT dienstverlener pand</th>
            <th style="text-align: start;">Actie</th>
        </tr>
    </thead>
    <tbody>

    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['din'] . ' - ' . $row['name']) ?></td>
			<td><?= htmlspecialchars($row['address']) ?></td>
            <td><?= htmlspecialchars($row['postalcode']) ?></td>
            <td><?= htmlspecialchars($row['city']) ?></td>
			<td><?= htmlspecialchars($row['idvp']) ?></td>
            <td class="tblaction">
                <a href="edit_building.php?id=<?= $row['id'] ?>">
                    Open Gebouw
                </a>
            </td>
        </tr>
    <?php endwhile; ?>

    </tbody>
</table>
		</div>
</div>			
<?php require_once(__DIR__ . '/nav/end.php'); ?>



