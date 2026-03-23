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

// Fetch multiple permission columns at once
$sql = "SELECT operators, persons, isadmin FROM itsm_ob_operators WHERE username = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $logged_in_user);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $operators, $persons, $admins); // add more as needed
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
				<td style="vertical-align: top">
					<table border="1" style="width: 100%;">
						<tr>
							<td>
								Personen
							</td>
							<td>
								Persoonsgroepen
							</td>
							<td>
								<?php if ($operators == 1): ?>
    								<a href="operators.php">
										Behandelaars
									</a>
								<?php endif; ?>	
							</td>
							<td>
								Behandelaarsgroepen
							</td>
						</tr>
						<tr>
							<td>
								Leveranciers
							</td>
							<td>
								Gebouwen
							</td>
							<td>
								Organisaties
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</td>
</tr>



<?php require_once(__DIR__ . '/nav/end.php'); ?>