<?php
session_start();

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
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<td style="vertical-align: top">
					<table class=module border="0">
						<tr><td colspan=4></td></tr>
						<tr>
							<td>
								
							</td>
							<td>
								
							</td>
							<td>
							
							</td>
							<td>
							
							</td>
						</tr>
						<tr>
							<td>
							
							</td>
							<td>
							
							</td>
							<td>
							
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</td>
</tr>



<?php require_once(__DIR__ . '/nav/end.php'); ?>