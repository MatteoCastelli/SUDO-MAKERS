<?php
$title = "Accedi"



?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="registerStyle.css">
</head>
<body style="margin: 0;">
<form method="POST" action="login.php">

    <h2>Accedi</h2>

    <div class="form-row">
        <label for="email">Email</label>
        <input type="text" id="email" name="email" required>
    </div>

    <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>

    <button type="submit">Accedi</button>
    <a href="index.php" id="indietro">Indietro</a>
</form>
</body>
</html>
