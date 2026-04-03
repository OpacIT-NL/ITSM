<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Authorization check
$sql2 = "SELECT persons FROM itsm_ob_operators WHERE username = ?";
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
// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid ID");
}

$id = (int)$_GET['id'];

if (isset($_POST['delete'])) {

    $stmt = mysqli_prepare($con, "DELETE FROM itsm_ob_persongroups WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        die("Delete failed: " . mysqli_stmt_error($stmt));
    }

    echo "Persoonsgroep verwijderd! <a href='persongroups.php'>Ga terug</a>";
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = mysqli_prepare($con, "
        UPDATE itsm_ob_persongroups SET
            groupname=?
        WHERE id=?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $_POST['groupname'],
        $id
    );

    mysqli_stmt_execute($stmt);



    echo "Opgeslagen! <a href='persongroups.php'>Ga terug</a>";
    exit;
}

// Fetch operator
$stmt = mysqli_prepare($con, "SELECT * FROM itsm_ob_persongroups WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$operator = mysqli_fetch_assoc($result);

$stmt2 = $con->prepare("SELECT l.id, o.firstname, o.lastname
FROM itsm_ob_persongrouplinks l
JOIN itsm_ob_persons o ON l.person = o.id
WHERE l.persongroup = ?
ORDER BY o.firstname, o.lastname;");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $result3 = $stmt2->get_result();

if (!$operator) {
    die("Group not found");
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
				<p class="results" style="width: 15%; text-align: right"><a href="persongroups.php">Ga terug</a></p>
		<center><h1>Persoonsgroep bewerken: <?= htmlspecialchars($operator['groupname']) ?></h1></center>
	</td>
</tr>
<tr>
	<td>
		<table border="0" width="50%" style="width: 100%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					
					<form method="post">

    <!-- Basic fields -->
    <label>Groepsnaam:
        <input type="text" name="groupname" value="<?= htmlspecialchars($operator['groupname']) ?>">
    </label>
<br>

    <br><br>
    <button type="submit">Opslaan</button>
						<button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze groep wil verwijderen?');"
        style="background:red;color:white;margin-left:10px;">
    Verwijder groep
</button>

</form>
<br>
<center><h1>Groepsleden</h1>
	<p class="results" style="width: 15%; text-align: right"><a href="new_persongrouplink.php?id=<?= $id ?>">Persoon koppelen</a></p>
<table border="0" class=results style="width: 50%;">
    <thead>
        <tr>
            <th style="text-align: start;">Naam</th>
            <th style="text-align: start;">Actie</th>
        </tr>
    </thead>
    <tbody>

    <?php while ($row = mysqli_fetch_assoc($result3)): ?>
        <tr>
            <td><?= htmlspecialchars($row['firstname']) ?> <?= htmlspecialchars($row['lastname']) ?></td>
            <td>
                <a class="btn" href="delete_persongrouplink.php?id=<?= $row['id'] ?>">
                    ontkoppelen
                </a>
            </td>
        </tr>
    <?php endwhile; ?>

    </tbody>
</table></center>
				</td>
				<td>
					<table>
						<tr>
						
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</td>
</tr>
<?php require_once(__DIR__ . '/nav/end.php'); ?>



