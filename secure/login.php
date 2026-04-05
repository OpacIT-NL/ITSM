<html>
	<head>
		<meta charset="utf-8">
<title>ITSM Behandelaar</title>
		<link href="include/login.css" rel="stylesheet" type="text/css">
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
	</head>
	<body class="login-page">

    <div class="login-box">

        <h1>ITSM Behandelaar</h1>
        <h2>Inloggen</h2>

        <?php if (isset($_GET['expired'])): ?>
            <p class="error">Je sessie is verlopen. Log opnieuw in.</p>
        <?php endif; ?>

        <form action="authenticate.php" method="post" class="login-form">

            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Gebruikersnaam" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Wachtwoord" required>
            </div>

            <button type="submit" class="login-button">Login</button>

        </form>

    </div>

</body>
</html>