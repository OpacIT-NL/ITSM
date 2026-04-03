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
// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid ID");
}

$id = (int)$_GET['id'];

if (isset($_POST['delete'])) {

    $stmt = mysqli_prepare($con, "DELETE FROM itsm_ob_buildings WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        die("Delete failed: " . mysqli_stmt_error($stmt));
    }

    echo "Gebouw verwijderd. <a href='buildings.php'>Ga terug</a>";
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $stmt = mysqli_prepare($con, "
        UPDATE itsm_ob_buildings SET
            customer=?,
			address=?,
			postalcode=?,
			city=?,
			idvp=?
        WHERE id=?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sssssi",
        $_POST['customer'],
		$_POST['address'],
		$_POST['postalcode'],
		$_POST['city'],
		$_POST['idvp'],
        $id
    );

    mysqli_stmt_execute($stmt);
	// Only update password if a new one is entered


    echo "Gebouw aangepast! <a href='buildings.php'>Ga terug</a>";
    exit;
}


$stmt = $con->prepare("SELECT * FROM itsm_ob_buildings WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
$row2 = $result->fetch_assoc();

$stmt2 = $con->prepare("SELECT * FROM itsm_ob_buildings WHERE customer = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $result3 = $stmt2->get_result();

$stmt3 = $con->prepare("SELECT c.id, c.din, c.name
FROM itsm_ob_customers c
LEFT JOIN itsm_ob_buildings b
    ON c.id = b.customer");
    $stmt3->execute();
    $result4 = $stmt3->get_result();

if (!$result) {
    die("Customer not found");
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<p class="results" style="width: 15%; text-align: right"><a href="buildings.php">Ga terug</a></p>

		<center><h1>Gebouw Bewerken: <?= htmlspecialchars($row2['address'] . ', ' . $row2['postalcode'] . ', ' . $row2['city']) ?></h1></center>
	</td>
</tr>
<tr>
	<td>
		<center><table border="0" width="50%" style="width: 50%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					
					<form method="post">

    <!-- Basic fields -->
							Klant: <?
$selectedId = $row2['customer'] ?? null;

echo '<select name="customer">';

// Check if nothing is selected
$emptySelected = empty($selectedId) ? 'selected' : '';
echo "<option value='' $emptySelected>--Selecteer een klant--</option>";

while ($row3 = $result4->fetch_assoc()) {
    $id = $row3['id'];
    $name = htmlspecialchars($row3['din'] . ' - ' . $row3['name']);

    $selected = ($id == $selectedId) ? 'selected' : '';

    echo "<option value='$id' $selected>$name</option>";
}

echo '</select>';
?>
						<br>

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
<br>
	<label>ICT Dienstverlener Pand:
        <input type="text" name="idvp" value="<?= htmlspecialchars($row2['idvp'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label>
<br>


    <br><br>
    <button type="submit">Opslaan</button>
						<button type="submit" name="delete" 
        onclick="return confirm('Weet je zeker dat je dit gebouw wil verwijderen?');"
        style="background:red;color:white;margin-left:10px;">
    Verwijder gebouw
</button>

</form>
<br>

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



