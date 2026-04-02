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

// Fetch multiple permission columns at once
$sql = "SELECT operators, persons, isadmin, buildings, customers, suppliers, groups FROM itsm_ob_operators WHERE username = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $logged_in_user);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $operators, $persons, $admins, $buildings, $customers, $suppliers, $groups); // add more as needed
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
				<td style="vertical-align: top">
					<table class=module border="0">
						<tr><td colspan=4><h1>Ondersteunende Bestanden</h1></td></tr>
						<tr>
							<td>
								<?php if ($persons == 1): ?>
    								<a href="persons.php">
										Personen
									</a>
								<?php endif; ?>	
							</td>
							<td>
								<?php if ($persons == 1): ?>
    								<a href="persongroups.php">
										Persoonsgroepen
									</a>
								<?php endif; ?>	
							</td>
							<td>
								<?php if ($operators == 1): ?>
    								<a href="operators.php">
										Behandelaars
									</a>
								<?php endif; ?>	
							</td>
							<td>
								<?php if ($groups == 1): ?>
    								<a href="operatorgroups.php">
										Behandelaarsgroepen
									</a>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td>
								<?php if ($suppliers == 1): ?>
    								<a href="suppliers.php">
										Leveranciers
									</a>
								<?php endif; ?>	
							</td>
							<td>
								<?php if ($buildings == 1): ?>
    								<a href="buildings.php">
										Gebouwen
									</a>
								<?php endif; ?>	
							</td>
							<td>
								<?php if ($customers == 1): ?>
    								<a href="customers.php">
										Klanten
									</a>
								<?php endif; ?>	
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</td>
</tr>



<?php require_once(__DIR__ . '/nav/end.php'); ?>