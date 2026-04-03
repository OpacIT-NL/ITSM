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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prepare insert
    $stmt = mysqli_prepare($con, "
        INSERT INTO itsm_ob_persongroups (
            groupname
        ) VALUES (
            ?
        )
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $_POST['name']
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Insert failed: " . mysqli_stmt_error($stmt));
    }

    echo "Persoonsgroep aangemaakt! <a href='persongroups.php'>Ga terug!</a>";
    exit;
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<p class="results" style="width: 15%; text-align: right"><a href="persongroups.php">Ga terug</a></p>
		<center><h1>Nieuwe persoonsgroep</h1></center>
	</td>
</tr>
<tr>
	<td>
		<table border="0" width="50%" style="width: 100%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					
					<form method="post">

    <!-- Basic fields -->
    <div class="group">
        <h3>Basic Information</h3>

        <label>Name:
            <input type="text" name="name" required>
        </label>

    <br>
    <button type="submit">Opslaan</button>
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



