<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once(__DIR__ . '/../my.php');

if (!isset($_SESSION['operatorloggedin'])) {
	header('Location: login.php');
	exit;
}
$logged_in_user = $_SESSION['name'];

// Fetch category
$sql = "SELECT id, name, type 
        FROM itsm_core_category 
        ORDER BY id ASC";
$result = mysqli_query($con, $sql);

// Fetch user permissions
$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare($con, $sql2);
mysqli_stmt_bind_param($result2, "s", $logged_in_user);
mysqli_stmt_execute($result2);
mysqli_stmt_bind_result($result2, $operators);
mysqli_stmt_fetch($result2);
mysqli_stmt_close($result2);
if ($operators == 0) {
    header("Location: index.php");
    exit();
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
				<tr height="50px">
	<td style="vertical-align: top;">
		<center><h1>Categorie-beheer</h1></center>
	</td>
</tr>
<tr>
	<td><center>
		<table border="0" width="50%" style="width: 65%; height: 100%; vertical-align: top">
			<tr>
				<td class=results style="vertical-align: top;">
					<p style="width: 15%; text-align: right"><a href="new_cat.php">Nieuwe Categorie</a></p>
					
					<table border="0" class=results style="width: 100%;">
    <thead>
        <tr>
            <th style="text-align: start;">Categorie</th>
            <th style="text-align: start;">Soort</th>
            <th style="text-align: start;">Actie</th>
        </tr>
    </thead>
    <tbody>

    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['type']) ?></td>
            <td>
                <a class="btn" href="edit_cat.php?id=<?= $row['id'] ?>">
                    Open Categorie
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