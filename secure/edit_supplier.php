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
$sql2 = "SELECT suppliers FROM itsm_ob_operators WHERE username = ?";
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

    $stmt = mysqli_prepare($con, "DELETE FROM itsm_ob_suppliers WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        die("Delete failed: " . mysqli_stmt_error($stmt));
    }

    echo "Leverancier verwijderd. <a href='suppliers.php'>Ga terug</a>";
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $stmt = mysqli_prepare($con, "
        UPDATE itsm_ob_suppliers SET
            cin=?,
			name=?,
			address=?,
			postalcode=?,
			city=?,
			primaryemail=?,
			primaryphone=?
        WHERE id=?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssi",
        $_POST['cin'],
		$_POST['name'],
		$_POST['address'],
		$_POST['postalcode'],
		$_POST['city'],
		$_POST['primaryemail'],
		$_POST['primaryphone'],
        $id
    );

    mysqli_stmt_execute($stmt);
	// Only update password if a new one is entered


    echo "Leverancier aangepast! <a href='suppliers.php'>Ga terug</a>";
    exit;
}

// Fetch operator
$stmt = $con->prepare("SELECT * FROM itsm_ob_suppliers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
$row2 = $result->fetch_assoc();

if (!$result) {
    die("Supplier not found");
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<p class="results" style="width: 15%; text-align: right"><a href="suppliers.php">Ga terug</a></p>

		<center><h1>Leverancier Bewerken: <?= htmlspecialchars($row2['name']) ?></h1></center>
	</td>
</tr>
<tr>
	<td>
		<center><table border="0" width="50%" style="width: 50%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					
					<form method="post">

    <!-- Basic fields --><h3>Algemeen</h3>
    <label>CIN:
        <input type="text" name="cin" value="<?= htmlspecialchars($row2['cin']) ?>" readonly>
    </label>
<br>
	<label>Naam:
        <input type="text" name="name" value="<?= htmlspecialchars($row2['name']) ?>">
    </label>
<br>
						<br> <hr> <h3>Postadres</h3>
	<label>Adres:
        <input type="text" name="address" value="<?= htmlspecialchars($row2['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label>
<br>
	<label>Postcode:
        <input type="text" name="postalcode" value="<?= htmlspecialchars($row2['postalcode'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label>
<br>
	<label>Plaats:
        <input type="text" name="city" value="<?= htmlspecialchars($row2['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label>
<br><hr><h3>Contactgegevens</h3>
	<label>Primair E-mailadres:
        <input type="text" name="primaryemail" value="<?= htmlspecialchars($row2['primaryemail'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label>
<br>
	<label>Primair telefoonnummer:
        <input type="text" name="primaryphone" value="<?= htmlspecialchars($row2['primaryphone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label>
<br>

    <br><br>
    <button type="submit">Opslaan</button>
						<button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je deze leverancier wil verwijderen?');"
        style="background:red;color:white;margin-left:10px;">
    Verwijder leverancier
</button>

</form>
<br>

				</td>

			</tr>
		</table></center>
	</td>
</tr>
<?php require_once(__DIR__ . '/nav/end.php'); ?>



