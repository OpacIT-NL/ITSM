<html>
	<head>
		<meta charset="utf-8">
<title>ITSM Behandelaar</title>
		<link href="include/login.css" rel="stylesheet" type="text/css">
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
	</head>
	<body>
		<div class="login">
			<h1>ITSM Behandelaar</h1>

			<h1>Inloggen</h1>
						<?php if (isset($_GET['expired'])): ?>
    <p style="color: red;"><center style="color: red;">Je sessie is verlopen. Log opnieuw in.</center></p>
<?php endif; ?>
			<form action="authenticate.php" method="post">
				<label for="username">
					<i class="fas fa-user"></i>
				</label>
				<input type="text" name="username" placeholder="Gebruikersnaam" id="username" required>
				<label for="password">
					<i class="fas fa-lock"></i>
				</label>
				<input type="password" name="password" placeholder="Wachtwoord" id="password" required>
				<input type="submit" value="Login">
			</form>
		</div>
	</body>
</html>