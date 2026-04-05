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
$stmt2 = $con->prepare("SELECT * FROM itsm_ob_customers");
    $stmt2->execute();
    $result3 = $stmt2->get_result();
// Boolean fields with display names
$boolFields = [
    'allowssp' => 'Mag inloggen (SSP)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Convert checkboxes to 0/1
    $boolValues = [];
    foreach ($boolFields as $field => $label) {
        $boolValues[$field] = isset($_POST[$field]) ? 1 : 0;
    }

    // Validate password
    if (empty($_POST['password'])) {
        die("Wachtwoord is verplicht!");
    }

    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Prepare insert
    $stmt = mysqli_prepare($con, "
        INSERT INTO itsm_ob_persons (
            customerid, firstname, lastname, email, phone, password,
            allowssp
        ) VALUES (
            ?,?,?,?,?,?,
            ?
        )
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "isssssi",
		$_POST['customerid'],
        $_POST['firstname'],
        $_POST['lastname'],
        $_POST['email'],
        $_POST['phone'],
        $hashedPassword,
        $boolValues['allowssp']
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Insert failed: " . mysqli_stmt_error($stmt));
    }

header('Location: persons.php');
    exit;
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<div class="content">
	<a href="persons.php">Ga terug</a>
		<center><h1>Nieuw Persoon</h1></center>
<div class="form-wrapper">
    <div class="form-card">
		<form method="post" class="form-grid">

    <!-- Basic fields -->
    
        <h3>Basis informatie</h3>
		Klant:<div class="form-group"> <?

echo '<select name="customerid"><option>--selecteer een klant--</option>';

// Check if nothing is selected

while ($row2 = $result3->fetch_assoc()) {
    $id = $row2['id'];
    $name = htmlspecialchars($row2['din'] . ' - ' . $row2['name']);
    echo "<option value='$id'>$name</option>";
}

echo '</select>';
?></div><br><div class="form-group">

        <label>Voornaam:
            <input type="text" name="firstname" required>
        </label></div>
<br><div class="form-group">
        <label>Achternaam:
            <input type="text" name="lastname" required>
        </label></div>
<br><div class="form-group">
        <label>E-mail:
            <input type="email" name="email" required>
        </label></div>
<br><div class="form-group">
        <label>Telefoonnummer:
            <input type="text" name="phone">
        </label></div>
<br><div class="form-group">
        <label>Wachtwoord:
            <input type="password" name="password" required>
        </label></div>
    

    <!-- Permissions -->
    
<div class=checkbox-grid>
        <?php foreach ($boolFields as $field => $label): ?>
            <label>
                <input type="checkbox" name="<?= $field ?>">
                <?= $label ?>
            </label>
        <?php endforeach; ?>
		</div>

    <br>
    <div class="form-actions"><button type="submit" class="btn-primary">Maak persoon</button></div>
</form></div></div></div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>



