<?php
    session_start();

    require_once "funzioni.php";

    $errore  = null;
    $successo = false;

    try {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            throw new Exception("metodo non valido");
        }

        // accetta sia username che email nel primo campo
        $login    = controllo_parametro("username o email", $_POST["user_name"] ?? "", 3, 100);
        $password = controllo_parametro("password",         $_POST["password"]  ?? "", 4);

        $pdo = get_pdo();

        $stm = $pdo->prepare("SELECT id, user_name, password, bloccato, ruolo FROM utente WHERE user_name = :l OR email = :l");
        $stm->execute(["l" => $login]);
        $utente = $stm->fetch(PDO::FETCH_ASSOC);

        // messaggio generico per non rivelare se esiste lo username
        if (!$utente || !password_verify($password, $utente["password"])) {
            throw new Exception("username o password non validi");
        }

        if ($utente["bloccato"]) {
            throw new Exception("account bloccato, contatta l'assistenza");
        }

        // aggiorna last_login
        $stm = $pdo->prepare("UPDATE utente SET last_login = :oggi WHERE id = :id");
        $stm->execute(["oggi" => date("Y-m-d"), "id" => $utente["id"]]);

        // salva l'id in sessione per tener loggato l'utente
        $_SESSION["utente_id"] = $utente["id"];
        $_SESSION["user_name"] = $utente["user_name"];
        $_SESSION["ruolo"]     = $utente["ruolo"];

        $successo = true;

    } catch (Exception $e) {
        $errore = $e->getMessage();
    }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .container { background: #fff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 400px; }
        h2 { text-align: center; margin-bottom: 25px; color: #333; }
        .success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-left: 6px solid #28a745;
            padding: 20px 15px;
            color: #155724;
            border-radius: 10px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: bold;
        }
        .success i { font-size: 2rem; display: block; margin-bottom: 8px; }
        .success span { display: block; font-size: 0.85rem; font-weight: normal; margin-top: 4px; color: #1e7e34; }
        .error   { background: #f8d7da; border-left: 6px solid #dc3545; padding: 12px 15px; color: #721c24; border-radius: 6px; }
        a.button { display: block; padding: 12px 20px; background: #007BFF; color: #fff; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 15px; text-align: center; }
        a.button:hover { background: #0056b3; }
        .link-recupera { display: block; margin-top: 15px; font-size: 14px; color: #555; text-align: center; }
        .link-recupera a { color: #007BFF; text-decoration: none; }
        .link-recupera a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>login</h2>

    <?php if ($errore): ?>
        <div class="error"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($errore) ?></div>
        <p class="link-recupera">hai dimenticato la password? <a href="recupera_password.php">recuperala qui</a></p>
        <a href="index.html" class="button"><i class="fa fa-arrow-left"></i> torna alla home</a>

    <?php elseif ($successo): ?>
        <div class="success">
            <i class="fa fa-check-circle"></i>
            bentornato, <?= htmlspecialchars($_SESSION["user_name"]) ?>!
            <span>accesso effettuato con successo</span>
        </div>
        <a href="dashboardUtente.php" class="button" style="margin-top: 20px;"><i class="fa fa-home"></i> vai alla dashboard</a>

    <?php else: ?>
        <p>accesso non valido.</p>
        <a href="index.html" class="button">torna alla home</a>
    <?php endif; ?>
</div>
</body>
</html>
