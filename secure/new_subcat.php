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
$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare($con, $sql2);
mysqli_stmt_bind_param($result2, "s", $logged_in_user);
mysqli_stmt_execute($result2);
mysqli_stmt_bind_result($result2, $isadmin);
mysqli_stmt_fetch($result2);
mysqli_stmt_close($result2);
if ($isadmin == 0) {
    header("Location: index.php");
    exit();
}
$id = (int)$_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Prepare insert
    $stmt = mysqli_prepare($con, "
        INSERT INTO itsm_core_subcategory (
            name, parent
        ) VALUES (
            ?,?
        )
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $_POST['name'],
        $_POST['parent']
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Insert failed: " . mysqli_stmt_error($stmt));
    }

    echo "Subcategorie aangemaakt. <a href='edit_cat.php?id=$id'>Ga terug</a>";
    exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<tr height="50px">
	<td style="vertical-align: top;">
		<p class="results" style="width: 15%; text-align: right"><a href="edit_cat.php?id=<?= $id ?>">Ga terug</a></p>
		<center><h1>Nieuwe subcategorie</h1></center>
	</td>
</tr>
<tr>
	<td>
		<table border="0" width="50%" style="width: 50%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					
					<form method="post">

    <!-- Basic fields -->
    <div class="group">
        <label>Naam:
            <input type="text" name="name" required>
        </label>
<br>
        <label>Hoofdcategorie:
            <input type="text" name="parent" value="<?= $id ?>" required>
        </label>
<br>

    <br>
    <button type="submit">Maak subcategorie</button>
</form>
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

