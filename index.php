<?php
$title = "Accesso";

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $title ?></title>
</head>
<body>
<form method="POST" action="register.php">

    <h2>Registrazione Utente</h2>

    <div>
        <label for="nome">Nome</label><br>
        <input type="text" id="nome" name="nome" placeholder="Inserisci il tuo nome" required>
    </div>

    <div>
        <label for="cognome">Cognome</label><br>
        <input type="text" id="cognome" name="cognome" placeholder="Inserisci il tuo cognome" required>
    </div>

    <div>
        <label for="data_nascita">Data di nascita</label><br>
        <input type="date" id="data_nascita" name="data_nascita" required>
    </div>

    <div>
        <label for="sesso">Sesso</label><br>
        <select id="sesso" name="sesso" required>
            <option value="">Seleziona...</option>
            <option value="M">Maschio</option>
            <option value="F">Femmina</option>
        </select>
    </div>

    <div>
        <label for="comune_nascita">Comune di nascita</label><br>
        <input type="text" id="comune_nascita" name="comune_nascita" placeholder="Es. Milano" required>
    </div>

    <div>
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" placeholder="nome@example.com" required>
    </div>

    <div>
        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" placeholder="Minimo 8 caratteri" required>
    </div>

    <div>
        <label for="codice_fiscale">Codice Fiscale (opzionale)</label><br>
        <input type="text" id="codice_fiscale" name="codice_fiscale" placeholder="Inserisci solo se lo conosci">
    </div>

    <div>
        <button type="submit">Registrati</button>
    </div>

</form>

</body>
</html>
