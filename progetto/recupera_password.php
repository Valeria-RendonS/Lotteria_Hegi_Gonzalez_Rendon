<?php
    session_start();

    require_once "funzioni.php";
    require_once "invio_email.php";

    $errore   = null;
    $successo = false;
    $info     = null;

    /*
        flusso:
        1. utente inserisce email
           -> trovata:     genero token (link univoco) + otp, salvo nel db, invio mail -> messaggio verde
           -> non trovata: messaggio rosso
        2. utente clicca link (recupera_password.php?token=XYZ)
           -> form: otp + nuova password + conferma
        3. trovo utente tramite token, verifico otp -> aggiorno password -> token e codice_OTP = NULL
    */

    try {
        $pdo = get_pdo();

        // — fase 2: verifica otp e aggiornamento password
        if (isset($_GET["token"]) && $_SERVER["REQUEST_METHOD"] === "POST") {

            $token             = $_GET["token"];
            $otp_inserito      = controllo_parametro("codice OTP",        $_POST["codice_otp"]        ?? "", 6, 6);
            $password_nuova    = controllo_parametro("nuova password",     $_POST["password_nuova"]    ?? "", 4);
            $password_conferma = controllo_parametro("conferma password",  $_POST["password_conferma"] ?? "", 4);

            // errore solo sulle password: salvo otp in sessione per non farlo reinserire
            if ($password_nuova !== $password_conferma) {
                $_SESSION["otp_temp"] = $otp_inserito;
                throw new Exception("le password non coincidono");
            }

            // trova l'utente tramite il token univoco
            $stm = $pdo->prepare("SELECT id, codice_OTP FROM utente WHERE token_recupero = :t AND bloccato = 0");
            $stm->execute(["t" => $token]);
            $riga = $stm->fetch(PDO::FETCH_ASSOC);

            if (!$riga || $riga["codice_OTP"] === null) {
                throw new Exception("il link non e' valido o e' gia' stato utilizzato");
            }

            if ((string) $otp_inserito !== (string) $riga["codice_OTP"]) {
                $_SESSION["otp_temp"] = $otp_inserito;
                throw new Exception("codice OTP errato, riprova");
            }

            // criteri password
            if (strlen($password_nuova) < 8) {
                throw new Exception("la password deve essere di almeno 8 caratteri");
            }
            if (!preg_match('/[A-Z]/', $password_nuova)) {
                throw new Exception("la password deve contenere almeno una lettera maiuscola");
            }
            if (!preg_match('/[0-9]/', $password_nuova)) {
                throw new Exception("la password deve contenere almeno un numero");
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $password_nuova)) {
                throw new Exception("la password deve contenere almeno un carattere speciale (es. ! @ # $ %)");
            }

            // aggiorna password e azzera entrambi i campi
            $hash = password_hash($password_nuova, PASSWORD_DEFAULT);
            $stm  = $pdo->prepare("UPDATE utente SET password = :h, codice_OTP = NULL, token_recupero = NULL WHERE id = :id");
            $stm->execute(["h" => $hash, "id" => $riga["id"]]);

            unset($_SESSION["otp_temp"]);
            $successo = true;

        // — fase 1: ricezione email, generazione token + otp, invio mail
        } elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["email"])) {

            $email = controllo_parametro("email", $_POST["email"], 5, 100);

            $stm = $pdo->prepare("SELECT id FROM utente WHERE email = :e AND bloccato = 0");
            $stm->execute(["e" => $email]);
            $utente = $stm->fetch(PDO::FETCH_ASSOC);

            if (!$utente) {
                throw new Exception("nessun account associato a questa email");
            }

            $token = bin2hex(random_bytes(32));  // 64 caratteri, univoco per ogni richiesta
            $otp   = genera_otp();

            $stm = $pdo->prepare("UPDATE utente SET codice_OTP = :otp, token_recupero = :t WHERE id = :id");
            $stm->execute(["otp" => $otp, "t" => $token, "id" => $utente["id"]]);

            invio_mail_recupero($email, $otp, $token);

            $info = "link di recupero inviato! controlla la tua email.";
        }

    } catch (Exception $e) {
        $errore = $e->getMessage();
    }

    $mostra_form_reset = isset($_GET["token"]) && !$successo;
    $otp_salvato       = $_SESSION["otp_temp"] ?? "";
    unset($_SESSION["otp_temp"]);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>recupera password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
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
        .error   { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; text-align: left; box-sizing: border-box; width: 100%; }
        .success { display: block; background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; box-sizing: border-box; width: 100%; }
        .info    { display: block; background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; box-sizing: border-box; width: 100%; }

        input { width: 100%; padding: 12px; margin: 8px 0; border: 1.5px solid #ccc; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; }
        input:focus { border-color: #007BFF; outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
        input[name="codice_otp"] { text-align: center; font-size: 22px; letter-spacing: 8px; }

        /* wrap per occhio password */
        .pw-wrap { position: relative; margin: 8px 0; }
        .pw-wrap input { margin: 0; padding-right: 42px; }
        .pw-wrap .pw-occhio {
            position: absolute !important;
            right: 12px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: auto !important;
            padding: 4px 6px !important;
            background: none !important;
            border: none !important;
            cursor: pointer !important;
            color: #888 !important;
            font-size: 1rem !important;
            line-height: 1 !important;
            z-index: 2 !important;
            margin-top: 0 !important;
        }
        .pw-wrap .pw-occhio:hover { color: #333 !important; }

        .criteri {
            background: #f8f9fa; border-radius: 8px;
            padding: 10px 14px; margin: 5px 0 12px;
            text-align: left; font-size: 0.8rem; color: #888;
        }
        .criteri b { color: #555; }
        .criteri ul { padding-left: 16px; margin-top: 4px; }
        .criteri li { margin-bottom: 2px; }

        button, .button {
            display: block; width: 100%; padding: 13px;
            background: #007BFF; color: #fff; border-radius: 8px;
            text-decoration: none; font-weight: bold; border: none;
            cursor: pointer; text-align: center; box-sizing: border-box;
            margin-top: 8px;
        }
        button:hover, .button:hover { background: #0056b3; }
        .button-grigio { background: #6c757d; }
        .button-grigio:hover { background: #5a6268; }
    </style>
</head>
<body>
<div class="container">
    <h2>recupera password</h2>

    <?php if ($errore): ?>
        <div class="error"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>

    <?php if ($info): ?>
        <div class="info"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($info) ?></div>
        <a href="index.html" class="button button-grigio">torna alla home</a>

    <?php elseif ($successo): ?>
        <div class="success"><i class="fa fa-check-circle"></i> password aggiornata con successo!</div>
        <a href="index.html" class="button">vai al login</a>

    <?php elseif ($mostra_form_reset): ?>
        <p>inserisci il codice OTP ricevuto via mail e scegli la nuova password:</p>
        <form method="POST" action="recupera_password.php?token=<?= htmlspecialchars($_GET['token']) ?>">
            <input
                type="text"
                name="codice_otp"
                placeholder="000000"
                maxlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                value="<?= htmlspecialchars($otp_salvato) ?>"
                required
                autofocus
            >
            <div class="pw-wrap">
                <input type="password" name="password_nuova" id="pw1" placeholder="nuova password" required minlength="8">
                <button type="button" class="pw-occhio" onclick="toggle_pw('pw1', this)"><i class="fa fa-eye"></i></button>
            </div>
            <div class="pw-wrap">
                <input type="password" name="password_conferma" id="pw2" placeholder="conferma password" required minlength="8">
                <button type="button" class="pw-occhio" onclick="toggle_pw('pw2', this)"><i class="fa fa-eye"></i></button>
            </div>
            <div class="criteri">
                <b>criteri password:</b>
                <ul>
                    <li>almeno 8 caratteri</li>
                    <li>almeno una lettera maiuscola</li>
                    <li>almeno un numero</li>
                    <li>almeno un carattere speciale (es. ! @ # $ %)</li>
                </ul>
            </div>
            <button type="submit">salva password</button>
        </form>

    <?php elseif (!$info): ?>
        <p>inserisci la tua email per ricevere il link di recupero:</p>
        <form method="POST">
            <input type="email" name="email" placeholder="la tua email" required>
            <button type="submit">invia link di recupero</button>
        </form>
        <a href="index.html" class="button button-grigio" style="margin-top:10px;">torna al login</a>
    <?php endif; ?>

</div>

<script>
    function toggle_pw(id, btn) {
        const input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
            btn.innerHTML = '<i class="fa fa-eye-slash"></i>';
        } else {
            input.type = "password";
            btn.innerHTML = '<i class="fa fa-eye"></i>';
        }
    }
</script>
</body>
</html>
