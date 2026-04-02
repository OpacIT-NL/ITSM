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
$sql = "SELECT *
        FROM itsm_ob_buildings
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
$stmt3 = $con->prepare("SELECT c.id, c.din, c.name
FROM itsm_ob_customers c
LEFT JOIN itsm_ob_buildings b
    ON c.id = b.customer");
    $stmt3->execute();
    $result4 = $stmt3->get_result();
$row3 = mysqli_fetch_assoc($result4)
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<p class="results" style="width: 15%; text-align: right"><a href="ob-menu.php">Ga terug</a></p>
		<center><h1>Gebouwen</h1></center>
	</td>
</tr>
<tr>
	<td>
		<center><table border="0" width="50%" style="width: 75%; height: 100%; vertical-align: top">
			<tr>
				<td class=results style="vertical-align: top;">
					<p style="width: 15%; text-align: right"><a href="new_building.php">Nieuw Gebouw</a></p>
					
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
            <td><?= htmlspecialchars($row3['din'] . ' - ' . $row3['name']) ?></td>
			<td><?= htmlspecialchars($row['address']) ?></td>
            <td><?= htmlspecialchars($row['postalcode']) ?></td>
            <td><?= htmlspecialchars($row['city']) ?></td>
			<td><?= htmlspecialchars($row['idvp']) ?></td>
            <td>
                <a class="btn" href="edit_building.php?id=<?= $row['id'] ?>">
                    Open Gebouw
                </a>
            </td>
        </tr>
    <?php endwhile; ?>

    </tbody>
</table>
				</td>
				<td>
					<table>
						<tr>
						
						</tr>
					</table>
				</td>
			</tr>
		</table></center>
	</td>
</tr>
<?php require_once(__DIR__ . '/nav/end.php'); ?>



