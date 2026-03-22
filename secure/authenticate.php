
<?php
session_start();
require_once(__DIR__ . '/../my.php');
if ($con->connect_error) {
    exit('Failed to connect to MySQL: ' . $con->connect_error);
}
if ( !isset($_POST['username'], $_POST['password']) ) {
	exit('Vul AUB alle velden in!');
}

if ($stmt = $con->prepare('SELECT id, password FROM itsm_ob_operators WHERE `username` = ? AND `allowlogin` = 1')) {
	$stmt->bind_param('s', $_POST['username']);
	$stmt->execute();
	$stmt->store_result();
if ($stmt->num_rows > 0) {
	$stmt->bind_result($id, $password);
	$stmt->fetch();
	if (password_verify($_POST['password'], $password)) {
		session_regenerate_id();
		$_SESSION['operatorloggedin'] = TRUE;
		$_SESSION['name'] = $_POST['username'];
		$_SESSION['id'] = $id;
		header('Location: index.php');
	} else {
		echo 'Gebruikersnaam/wachtwoord incorrect. <a href="login.php">Ga terug</a>';
	}
} else {
	echo 'Gebruikersnaam/wachtwoord incorrect. <a href="login.php">Ga terug</a>';
}

	$stmt->close();
}
?>