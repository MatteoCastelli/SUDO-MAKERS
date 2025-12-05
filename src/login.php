<?php

use Proprietario\SudoMakers\Database;

$title = "Accedi";
session_start();
require_once "Database.php";
require_once "functions.php";

const MAX_TENTATIVI = 5;
const DURATA_SOSPENSIONE_MINUTI = 30;

if(!empty($_POST)){
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM utente WHERE email = :email");
    $stmt->execute(['email' => trim($_POST['email'])]);
    $utente = $stmt->fetch();

    if($utente){

        if($utente['stato_account'] === 'sospeso' && $utente['data_sospensione'] !== null) {
            $ora_sospensione = new DateTime($utente['data_sospensione']);
            $ora_corrente = new DateTime();
            $differenza = $ora_corrente->getTimestamp() - $ora_sospensione->getTimestamp();
            $minuti_trascorsi = floor($differenza / 60);

            if($minuti_trascorsi < DURATA_SOSPENSIONE_MINUTI) {
                $minuti_rimanenti = DURATA_SOSPENSIONE_MINUTI - $minuti_trascorsi;
                echo "<script>alert('Account temporaneamente sospeso per troppi tentativi falliti. Riprova tra $minuti_rimanenti minuti.');</script>";
            } else {
                $stmt = $pdo->prepare("UPDATE utente SET stato_account = 'attivo', tentativi_falliti = 0, data_sospensione = NULL WHERE id_utente = :id");
                $stmt->execute(['id' => $utente['id_utente']]);

                $stmt = $pdo->prepare("SELECT * FROM utente WHERE email = :email");
                $stmt->execute(['email' => trim($_POST['email'])]);
                $utente = $stmt->fetch();
            }
        }

        if ($utente['stato_account'] === 'bloccato') {
            echo "<script>alert('Account bloccato. Contatta l\\'amministratore per maggiori informazioni.');</script>";
        }

        if($utente['stato_account'] === 'attivo') {

            if ($utente["email_verificata"] == 1) {

                if (password_verify($_POST['password'], $utente['password_hash'])) {

                    $stmt = $pdo->prepare("UPDATE utente SET tentativi_falliti = 0, data_ultimo_accesso = NOW() WHERE id_utente = :id");
                    $stmt->execute(['id' => $utente['id_utente']]);

                    $_SESSION['email'] = $utente['email'];
                    $_SESSION['id_utente'] = $utente['id_utente'];
                    $_SESSION['nome'] = $utente['nome'];
                    $_SESSION['cognome'] = $utente['cognome'];

                    //sendLoginMail($utente['email'], $utente['nome']);
                    header("Location: homepage.php");
                    exit;

                } else {

                    $tentativi_attuali = $utente['tentativi_falliti'] + 1;

                    if ($tentativi_attuali >= MAX_TENTATIVI) {

                        $stmt = $pdo->prepare("UPDATE utente SET stato_account = 'sospeso', tentativi_falliti = :tentativi, data_sospensione = NOW() WHERE id_utente = :id");
                        $stmt->execute([
                                'tentativi' => $tentativi_attuali,
                                'id' => $utente['id_utente']
                        ]);

                        echo "<script>alert('Account sospeso per troppi tentativi falliti (" . MAX_TENTATIVI . " tentativi). Riprova tra " . DURATA_SOSPENSIONE_MINUTI . " minuti.');</script>";

                        sendAccountSuspensionEmail($utente['email'], $utente['nome']);

                    } else {

                        $stmt = $pdo->prepare("UPDATE utente SET tentativi_falliti = :tentativi WHERE id_utente = :id");
                        $stmt->execute([
                                'tentativi' => $tentativi_attuali,
                                'id' => $utente['id_utente']
                        ]);

                        $tentativi_rimanenti = MAX_TENTATIVI - $tentativi_attuali;
                        echo "<script>alert('Password errata. Hai ancora $tentativi_rimanenti tentativi prima che l\\'account venga sospeso.');</script>";
                    }
                }
            } else {
                echo "<script>alert('Email non verificata. Controlla la tua casella di posta.');</script>";
            }
        }
    } else {
        echo "<script>alert('Nessun account trovato con questa email.');</script>";
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../style/loginRegisterStyle.css">
</head>
<body style="margin: 0;">
<form method="POST" action="login.php">

    <h2>Accedi</h2>

    <div class="form-row">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>

    <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>

    <button type="submit">Accedi</button>
    <a href="index.php" id="indietro">Indietro</a>
</form>
<script src="../scripts/checkRegisterFormData.js"></script>
</body>
</html>