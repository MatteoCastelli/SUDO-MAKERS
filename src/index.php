<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Biblioteca Digitale</title>
    <link rel="stylesheet" href="../public/assets/css/indexStyle.css">
</head>
<body>
<?php if(isset($_GET['registered']) && $_GET['registered'] == '1'){ ?>
    <div class="welcome-container">
        <h1>Ti abbiamo mandato una email di verifica<br>clicca il link allegato e potrai accedere</h1>

        <div class="button-group">
            <a href="login.php" class="btn-primary">Va bene</a>
        </div>
    </div>
<?php } ?>
</body>
</html>