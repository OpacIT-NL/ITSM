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
$sql2 = "SELECT operators FROM itsm_ob_operators WHERE username = ?";
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

    $stmt = mysqli_prepare($con, "DELETE FROM itsm_core_category WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        die("Delete failed: " . mysqli_stmt_error($stmt));
    }

    echo "Categorie verwijderd. <a href='set-ls-cat.php'>Ga terug</a>";
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Convert checkboxes to 0/1

    $stmt = mysqli_prepare($con, "
        UPDATE itsm_core_category SET
            name=?,
            type=?,
        WHERE id=?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $_POST['name'],
        $_POST['type'],

        $id
    );

    mysqli_stmt_execute($stmt);
	// Only update password if a new one is entered


    echo "Categorie aangepast! <a href='set-ls-cat.php'>Ga terug</a>";
    exit;
}

// Fetch operator
$stmt = $con->prepare("SELECT * FROM itsm_core_category WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
$row2 = $result->fetch_assoc();

$stmt2 = $con->prepare("SELECT * FROM itsm_core_subcategory WHERE parent = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $result3 = $stmt2->get_result();

if (!$result) {
    die("Operator not found");
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<p class="results" style="width: 15%; text-align: right"><a href="set-ls-cat.php">Ga terug</a></p>

		<center><h1>Categorie Bewerken: <?= htmlspecialchars($row2['name']) ?></h1></center>
	</td>
</tr>
<tr>
	<td>
		<table border="0" width="50%" style="width: 100%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					
					<form method="post">

    <!-- Basic fields -->
    <label>Naam:
        <input type="text" name="name" value="<?= htmlspecialchars($row2['name']) ?>">
    </label>
<br>

    <br><br>
    <button type="submit">Opslaan</button>
						<button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze categorie wil verwijderen?');"
        style="background:red;color:white;margin-left:10px;">
    Verwijder categorie
</button>

</form>
<br>
<center><h1>Subcategoriën</h1>
	<p class="results" style="width: 15%; text-align: right"><a href="new_subcat.php?id=<?= $id ?>">Nieuwe Subcategorie</a></p>
<table border="0" class=results style="width: 50%;">
    <thead>
        <tr>
            <th style="text-align: start;">Subcategorie</th>
            <th style="text-align: start;">Actie</th>
        </tr>
    </thead>
    <tbody>

    <?php while ($row = mysqli_fetch_assoc($result3)): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td>
                <a class="btn" href="edit_subcat.php?id=<?= $row['id'] ?>">
                    Open Subcategorie
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



