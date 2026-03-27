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
// Boolean fields with display names
$boolFields = [
    'allowlogin' => 'Allow Login',
    'firstlineincidents' => 'First Line Incident Operator',
    'secondlineincidents' => 'Second Line Incident Operator',
    'reqforchange' => 'Request for Change',
    'simplechange' => 'Simple Change',
    'extchange' => 'Extended Change',
    'problems' => 'Problem Management',
    'operations' => 'Operations',
    'assets' => 'Assets',
    'persons' => 'Persons',
    'operators' => 'Operators',
    'buildings' => 'Buildings',
    'customers' => 'Customers',
    'suppliers' => 'Suppliers',
    'groups' => 'Groups',
    'events' => 'Events',
    'ubm' => 'UBM',
    'reporting' => 'Reporting',
    'isadmin' => 'Administrator'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Convert checkboxes to 0/1
    $boolValues = [];
    foreach ($boolFields as $field => $label) {
        $boolValues[$field] = isset($_POST[$field]) ? 1 : 0;
    }

    // Validate password
    if (empty($_POST['password'])) {
        die("Password is required");
    }

    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Prepare insert
    $stmt = mysqli_prepare($con, "
        INSERT INTO itsm_ob_operators (
            firstname, lastname, email, phone, username, password,
            allowlogin, firstlineincidents, secondlineincidents, reqforchange,
            simplechange, extchange, problems, operations, assets, persons,
            operators, buildings, customers, suppliers, groups, events,
            ubm, reporting, isadmin
        ) VALUES (
            ?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
        )
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "ssssssiiiiiiiiiiiiiiiiiii",
        $_POST['firstname'],
        $_POST['lastname'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['username'],
        $hashedPassword,
        $boolValues['allowlogin'],
        $boolValues['firstlineincidents'],
        $boolValues['secondlineincidents'],
        $boolValues['reqforchange'],
        $boolValues['simplechange'],
        $boolValues['extchange'],
        $boolValues['problems'],
        $boolValues['operations'],
        $boolValues['assets'],
        $boolValues['persons'],
        $boolValues['operators'],
        $boolValues['buildings'],
        $boolValues['customers'],
        $boolValues['suppliers'],
        $boolValues['groups'],
        $boolValues['events'],
        $boolValues['ubm'],
        $boolValues['reporting'],
        $boolValues['isadmin']
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Insert failed: " . mysqli_stmt_error($stmt));
    }

    echo "Operator created successfully! <a href='operators.php'>Back to list</a>";
    exit;
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<p class="results" style="width: 15%; text-align: right"><a href="operators.php">Ga terug</a></p>
		<center><h1>Nieuwe behandelaar</h1></center>
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

        <label>First Name:
            <input type="text" name="firstname" required>
        </label>
<br>
        <label>Last Name:
            <input type="text" name="lastname" required>
        </label>
<br>
        <label>Email:
            <input type="email" name="email" required>
        </label>
<br>
        <label>Phone:
            <input type="text" name="phone">
        </label>
<br>
        <label>Username:
            <input type="text" name="username" required>
        </label>
<br>
        <label>Password:
            <input type="password" name="password" required>
        </label>
    </div>

    <!-- Permissions -->
    <div class="group">
        <h3>Permissions</h3>
<div class=checkbox-grid>
        <?php foreach ($boolFields as $field => $label): ?>
            <label>
                <input type="checkbox" name="<?= $field ?>">
                <?= $label ?>
            </label>
        <?php endforeach; ?>
		</div>
    </div>

    <br>
    <button type="submit">Create Operator</button>
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



