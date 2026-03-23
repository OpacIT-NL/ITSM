<?php
session_start();

if (!isset($_SESSION['operatorloggedin'])) {
	header('Location: login.php');
	exit;
}
$logged_in_user = $_SESSION['name'];
?>

<?php require_once(__DIR__ . '/nav/nav.php'); ?>
<?php require_once(__DIR__ . '/nav/modules.php'); ?>
<!--Content here -->

</tr>
		</table>
	</td>
</tr>



<?php require_once(__DIR__ . '/nav/end.php'); ?>