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

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid ID");
}

$id = (int)$_GET['id'];

// All boolean fields
$boolFields = [
    'allowlogin','firstlineincidents','secondlineincidents','reqforchange',
    'simplechange','extchange','problems','operations','assets','persons',
    'operators','buildings','customers','suppliers','groups','events','ubm',
    'reporting','isadmin'
];

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Convert checkboxes to 0/1
    $boolValues = [];
    foreach ($boolFields as $field) {
        $boolValues[$field] = isset($_POST[$field]) ? 1 : 0;
    }

    $stmt = mysqli_prepare($con, "
        UPDATE itsm_ob_operators SET
            firstname=?,
            lastname=?,
            email=?,
            phone=?,
            username=?,
            allowlogin=?,
            firstlineincidents=?,
            secondlineincidents=?,
            reqforchange=?,
            simplechange=?,
            extchange=?,
            problems=?,
            operations=?,
            assets=?,
            persons=?,
            operators=?,
            buildings=?,
            customers=?,
            suppliers=?,
            groups=?,
            events=?,
            ubm=?,
            reporting=?,
            isadmin=?
        WHERE id=?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sssssiiiiiiiiiiiiiiiiiiii",
        $_POST['firstname'],
        $_POST['lastname'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['username'],
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
        $boolValues['isadmin'],
        $id
    );

    mysqli_stmt_execute($stmt);
	// Only update password if a new one is entered
if (!empty($_POST['password'])) {
    $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($con, "
        UPDATE itsm_ob_operators 
        SET password = ? 
        WHERE id = ?
    ");

    mysqli_stmt_bind_param($stmt, "si", $hashed, $id);
    mysqli_stmt_execute($stmt);
}

    echo "Updated successfully! <a href='operators.php'>Back</a>";
    exit;
}

// Fetch operator
$stmt = mysqli_prepare($con, "SELECT * FROM itsm_ob_operators WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$operator = mysqli_fetch_assoc($result);

if (!$operator) {
    die("Operator not found");
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<center><h1>Edit Operator: <?= htmlspecialchars($operator['firstname']) ?> <?= htmlspecialchars($operator['lastname']) ?></h1></center>
	</td>
</tr>
<tr>
	<td>
		<table border="1" width="50%" style="width: 100%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					
					<form method="post">

    <!-- Basic fields -->
    <label>First Name:
        <input type="text" name="firstname" value="<?= htmlspecialchars($operator['firstname']) ?>">
    </label>

    <label>Last Name:
        <input type="text" name="lastname" value="<?= htmlspecialchars($operator['lastname']) ?>">
    </label>

    <label>Email:
        <input type="email" name="email" value="<?= htmlspecialchars($operator['email']) ?>">
    </label>

    <label>Phone:
        <input type="text" name="phone" value="<?= htmlspecialchars($operator['phone']) ?>">
    </label>

    <label>Username:
        <input type="text" name="username" value="<?= htmlspecialchars($operator['username']) ?>">
    </label>

    <hr>

    <!-- Boolean fields -->
    <?php foreach ($boolFields as $field): ?>
        <label>
            <input type="checkbox" name="<?= $field ?>" <?= $operator[$field] ? 'checked' : '' ?>>
            <?= ucfirst($field) ?>
        </label>
    <?php endforeach; ?>

    <hr>

    <!-- Password (optional safe handling) -->
    <label>New Password (leave empty to keep current):
        <input type="password" name="password">
    </label>

    <br><br>
    <button type="submit">Save</button>

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



