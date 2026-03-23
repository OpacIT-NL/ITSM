<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once(__DIR__ . '/../my.php');
if (!isset($_SESSION['operatorloggedin'])) {
	header('Location: login.php');
	exit;
}
$logged_in_user = $_SESSION['name'];
$sql = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $logged_in_user);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $isadmin); // add more as needed
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px" class=main>
	<td>
		<center><h1>ITSM Dashboard - Welkom terug <?php echo $_SESSION['name'];?></h1></center>
	</td>
</tr>
<div class=main><tr>
	<td>
		<table class=main border="0" width="50%" style="width: 100%; height: 100%;">
			<tr>
				<td>
					<h1>Hoofdmenu</h1>
					<div class=quicklinkshome>
					<table border="0" style="height: 100%;">
						<tr>
							<td>
								<a href="./modules.php"><i class="fa-solid fa-cubes-stacked fa-2xl"></i>
								<span>Modules</span>
								</a>
							</td>
							<?php if ($isadmin == 1): ?>
							<td>
								<a href="./settings.php"><i class="fa-solid fa-screwdriver-wrench fa-2xl"></i>
								<span>Instellingen</span>
								</a>
							</td>
							<?php endif; ?>
							<td>
								<a href="./profile.php"><i class="fa-solid fa-user fa-2xl"></i>
								<span>Profiel</span>
								</a>
							</td>
						</tr>
					</table>
					</div>
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
	</tr></div>
<?php require_once(__DIR__ . '/nav/end.php'); ?>



