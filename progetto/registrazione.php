<?php
    session_start();

    require_once "funzioni.php";
    require_once "invio_email.php";

    $stato_otp = false;
    $successo  = false;
    $errore    = null;

    try {
        $pdo = get_pdo();

        // — fase 2: verifica otp inserito dall'utente
        if (isset($_POST["codice_otp_inviato"])) {

            if (!verifica_otp($_POST["codice_otp_inviato"])) {
                $stato_otp = true;
                throw new Exception("codice OTP errato, riprova");
            }

            $dati = $_SESSION["dati_temporanei"];

            $sql = "INSERT INTO utente 
                        (nome, cognome, email, user_name, n_telefono, data_nascita, password, last_login, ruolo)
                    VALUES 
                        (:nome, :cognome, :email, :user_name, :n_telefono, :data_nascita, :password, :last_login, 'utente')";

            $stm = $pdo->prepare($sql);
            $stm->execute([
                "nome"         => $dati["nome"],
                "cognome"      => $dati["cognome"],
                "email"        => $dati["email"],
                "user_name"    => $dati["user_name"],
                "n_telefono"   => $dati["n_telefono"],
                "data_nascita" => $dati["data_nascita"],
                "password"     => $dati["password_hash"],
                "last_login"   => date("Y-m-d")
            ]);

            $successo = true;
            session_destroy();

        // — fase 1: raccolta dati e invio otp
        } elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_name"])) {

            $user_name    = controllo_parametro("username",           $_POST["user_name"],    4, 50);
            $nome         = controllo_parametro("nome",               $_POST["nome"],         4, 50);
            $cognome      = controllo_parametro("cognome",            $_POST["cognome"],      4, 50);
            $email        = controllo_parametro("email",              $_POST["email"],        5, 100);
            $n_telefono   = controllo_parametro("numero di telefono", $_POST["n_telefono"],   3, 10);
            $data_nascita = controllo_parametro("data di nascita",    $_POST["data_nascita"]);
            $password          = controllo_parametro("password",          $_POST["password"]          ?? "", 8);
            $password_conferma = controllo_parametro("conferma password", $_POST["password_conferma"] ?? "", 1);

            if ($password !== $password_conferma) {
                throw new Exception("le password non coincidono");
            }

            // nome e cognome non possono contenere numeri
            if (preg_match('/[0-9]/', $nome)) {
                throw new Exception("il campo 'nome' non può contenere numeri");
            }
            if (preg_match('/[0-9]/', $cognome)) {
                throw new Exception("il campo 'cognome' non può contenere numeri");
            }

            // username non può contenere spazi
            if (preg_match('/\s/', $user_name)) {
                throw new Exception("l'username non può contenere spazi");
            }

            // controlla età: minimo 18 anni, anno di nascita non precedente al 1909
            $nascita = new DateTime($data_nascita);
            $oggi    = new DateTime();
            if ($nascita->format("Y") < 1909) {
                throw new Exception("anno di nascita non valido");
            }
            if ($oggi->diff($nascita)->y < 18) {
                throw new Exception("devi avere almeno 18 anni per registrarti");
            }
            $data_nascita = $nascita->format("Y-m-d");

            // controlla che username non sia già in uso
            $stm = $pdo->prepare("SELECT id FROM utente WHERE user_name = :u");
            $stm->execute(["u" => $user_name]);
            if ($stm->rowCount() > 0) {
                throw new Exception("username già in uso");
            }

            // controlla che email non sia già in uso
            $stm = $pdo->prepare("SELECT id FROM utente WHERE email = :e");
            $stm->execute(["e" => $email]);
            if ($stm->rowCount() > 0) {
                throw new Exception("email già in uso");
            }

            // criteri password: minimo 8 caratteri, almeno 1 maiuscola, 1 numero, 1 carattere speciale
            if (strlen($password) < 8) {
                throw new Exception("la password deve essere di almeno 8 caratteri");
            }
            if (!preg_match('/[A-Z]/', $password)) {
                throw new Exception("la password deve contenere almeno una lettera maiuscola");
            }
            if (!preg_match('/[0-9]/', $password)) {
                throw new Exception("la password deve contenere almeno un numero");
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
                throw new Exception("la password deve contenere almeno un carattere speciale (es. ! @ # $ %)");
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $otp           = genera_otp();

            invio_mail_otp($email, $otp);

            $_SESSION["dati_temporanei"] = [
                "user_name"     => $user_name,
                "nome"          => $nome,
                "cognome"       => $cognome,
                "email"         => $email,
                "n_telefono"    => $n_telefono,
                "data_nascita"  => $data_nascita,
                "password_hash" => $password_hash
            ];

            $stato_otp = true;
        }

    } catch (Exception $e) {
        $errore = $e->getMessage();
    }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>registrazione</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: #fff;
            padding: 35px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            width: 420px;
            text-align: center;
        }
        h2 { margin-bottom: 20px; color: #333; }
        .error   { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: left; }
        .success { display: block; background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 15px; box-sizing: border-box; width: 100%; text-align: center; }

        /* input otp */
        input[type="text"] {
            width: 100%;
            padding: 14px;
            margin: 15px 0 20px;
            border: 1.5px solid #ccc;
            border-radius: 8px;
            text-align: center;
            font-size: 22px;
            letter-spacing: 8px;
            box-sizing: border-box;
        }
        input[type="text"]:focus { border-color: #007BFF; outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }

        /* criteri password */
        .criteri {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 0.82rem;
            color: #666;
        }
        .criteri p { margin-bottom: 6px; font-weight: bold; color: #555; }
        .criteri ul { padding-left: 18px; }
        .criteri li { margin-bottom: 3px; }

        button, .button {
            display: block;
            width: 100%;
            padding: 13px;
            background: #007BFF;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
            text-align: center;
            box-sizing: border-box;
        }
        button:hover, .button:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h2>registrazione</h2>

    <?php if ($errore): ?>
        <div class="error"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>

    <?php if ($successo): ?>
        <div class="success"><i class="fa fa-check-circle"></i> registrazione completata!</div>
        <a href="index.html" class="button" onclick="sessionStorage.clear();">accedi</a>

    <?php elseif ($stato_otp): ?>
        <p>abbiamo inviato un codice OTP alla tua email. inseriscilo qui sotto:</p>
        <form method="POST">
            <input type="text" name="codice_otp_inviato" placeholder="000000" maxlength="6"
                   pattern="[0-9]{6}" inputmode="numeric" required autofocus>
            <button type="submit">verifica codice</button>
        </form>

    <?php else: ?>
        <p>accesso non valido.</p>
        <a href="index.html" class="button">torna indietro</a>
    <?php endif; ?>
</div>
</body>
</html>
