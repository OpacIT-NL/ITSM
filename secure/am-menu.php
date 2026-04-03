<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once(__DIR__ . '/../my.php');

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

$sql2 = "SELECT assets FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare($con, $sql2);
mysqli_stmt_bind_param($result2, "s", $logged_in_user);
mysqli_stmt_execute($result2);
mysqli_stmt_bind_result($result2, $operators);
mysqli_stmt_fetch($result2);
mysqli_stmt_close($result2);
if ($operators == 0) {
    header("Location: modules.php");
    exit();
}

$stmt2 = $con->prepare("SELECT type FROM itsm_am_types");
$stmt2->execute();
$result = $stmt2->get_result();
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
				<div class="module-section">

    <h1>Asset Management</h1>

    <div class="module-grid">
<? 
		while ($row = $result->fetch_assoc()) {
    $typeUrl = urlencode($row['type']);           // safe for URL
    $typeText = htmlspecialchars($row['type']);   // safe for HTML

    echo "<a href='assets.php?filtertype=$typeUrl'>$typeText</a>";
}
		?>
       
    </div>

</div>
</div>


<?php require_once(__DIR__ . '/nav/end.php'); ?>