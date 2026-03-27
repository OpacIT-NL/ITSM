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

// Fetch user permissions
$sql2 = "SELECT isadmin FROM itsm_ob_operators WHERE username = ?";
$result2 = mysqli_prepare($con, $sql2);
mysqli_stmt_bind_param($result2, "s", $logged_in_user);
mysqli_stmt_execute($result2);
mysqli_stmt_bind_result($result2, $operators);
mysqli_stmt_fetch($result2);
mysqli_stmt_close($result2);
if ($operators == 0) {
    header("Location: index.php");
    exit();
}
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/settings.php'); ?>
				<td style="vertical-align: top">
					<table class=module border="0">
						<tr><td colspan=4><h1>Algemene Instellingen</h1></td></tr>
						<tr>
							<td>
    								<a href="set-ls-cat.php">
										Categorie<br>/Subcategorie
									</a>
							</td>
							<td>
    								<a href="set-status.php">
										Statussen
									</a>
							</td>
							<td>
    								<!--<a href="operators.php">
										Behandelaars
									</a>-->
							</td>
							<td>
    								<!--<a href="operatorgroups.php">
										Behandelaarsgroepen
									</a>-->
							</td>
						</tr>
						<tr>
							<td>
    								<!--<a href="suppliers.php">
										Leveranciers
									</a>-->
							</td>
							<td>
    								<!--<a href="buildings.php">
										Gebouwen
									</a>-->
							</td>
							<td>
    								<!--<a href="customers.php">
										Organisaties
									</a>-->
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</td>
</tr>



<?php require_once(__DIR__ . '/nav/end.php'); ?>