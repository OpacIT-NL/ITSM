<html>
<head>
	<meta charset="utf-8">
	<title>ITSM SelfService</title>
	<link href="include/login.css" rel="stylesheet" type="text/css">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<body>
	<table border=0 width="100%">
		<tr height="100">
			<td>
				<center>
					<table border=0>
						<tr>
							<td width="100%"></td>
						</tr>
					</table>
				</center>
			</td>
		</tr>
		<tr>
			<td>
				<center>
					<div class="login">
						<h1>ITSM SelfService</h1>
						<h1>Inloggen</h1>
						<?php if (isset($_GET['expired'])): ?>
    						<p style="color: red;"><center style="color: red;">Je sessie is verlopen. Log opnieuw in.</center></p>
						<?php endif; ?>
						<form action="authenticate.php" method="post">
							<label for="username">
								<i class="fas fa-user"></i>
							</label>
								<input type="text" name="username" placeholder="E-mail" id="username" required>
							<label for="password">
								<i class="fas fa-lock"></i>
							</label>
								<input type="password" name="password" placeholder="Wachtwoord" id="password" required>
							<input type="submit" value="Login">
						</form>	
					</div>
				</center>
			</td>
		</tr>
		<tr></tr>
	</table>
</body>
</html>