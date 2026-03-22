<?php
session_start();

if (!isset($_SESSION['operatorloggedin'])) {
	header('Location: login.php');
	exit;
}
$logged_in_user = $_SESSION['name'];
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>

<tr height="50px">
	<td style="vertical-align: top;">
		<center><h1>ITSM Dashboard - Welkom terug <?php echo $_SESSION['name'];?></h1></center>
	</td>
</tr>
<tr>
	<td>
		<table border="1" width="50%" style="width: 100%; height: 100%; vertical-align: top">
			<tr>
				<td style="vertical-align: top;">
					<h1>Hoofdmenu</h1>
					<div class=quicklinkshome>
					<table border="1" style="height: 100%; vertical-align: top">
						<tr>
							<td style="vertical-align: top;">
								<a href="./modules.php"><i class="fa-solid fa-cubes-stacked fa-2xl"></i>
								<span>Modules</span>
								</a>
							</td>
							<td style="vertical-align: top;">
								<a href="./settings.php"><i class="fa-solid fa-screwdriver-wrench fa-2xl"></i>
								<span>Instellingen</span>
								</a>
							</td>
							<td style="vertical-align: top;">
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
</tr>
<?php require_once(__DIR__ . '/nav/end.php'); ?>



