<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!isset($_SESSION['operatorloggedin'])) {
	header('Location: login.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Prepare insert
    $stmt = mysqli_prepare($con, "
        INSERT INTO itsm_core_category (
            name, type
        ) VALUES (
            ?,?
        )
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $_POST['name'],
        $_POST['type']
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Insert failed: " . mysqli_stmt_error($stmt));
    }

    echo "Category created succesfully <a href='set-ls-cat.php'>Back to list</a>";
    exit;
}
?>
<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<tr height="50px">
	<td style="vertical-align: top;">
		<center><h1>Nieuwe categorie</h1></center>
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
        <label>Name:
            <input type="text" name="name" required>
        </label>
<br>
        <label for="type">Type:</label>
        <select id="type" name="type">
            <option value="" disabled selected hidden>Selecteer een type</option>
            <option value="CHANGE">Wijziging</option>
            <option value="INCIDENT">Incident</option>
            <option value="PROBLEM">Problem</option>
            <option value="EVENT">Event</option>
        </select>
<br>

    <br>
    <button type="submit">Maak categorie</button>
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

