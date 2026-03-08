<?php
    session_start();

    require_once "funzioni.php";
    require_once "invio_email.php";

    if (!isset($_SESSION["utente_id"])) {
        header("Location: index.html");
        exit;
    }

    $id_utente    = $_SESSION["utente_id"];
    $errore       = null;
    $successo     = null;
    $in_modifica  = false;      // rimane true se c'è un errore durante la modifica

    try {
        $pdo = get_pdo();

        // — annulla modifica: svuota sessione e ricarica
        if (isset($_POST["azione"]) && $_POST["azione"] === "annulla_modifica") {
            unset($_SESSION["modifica_pending"], $_SESSION["otp_segreto"]);
            $stm = $pdo->prepare("UPDATE utente SET codice_OTP = NULL WHERE id = :id");
            $stm->execute(["id" => $id_utente]);
            header("Location: profilo.php");
            exit;
        }

        // — conferma otp cambio email
        if (isset($_POST["azione"]) && $_POST["azione"] === "conferma_email") {

            if (!isset($_SESSION["modifica_pending"])) {
                throw new Exception("sessione scaduta, ripeti la modifica");
            }

            $otp_inserito = controllo_parametro("codice OTP", $_POST["codice_otp"] ?? "", 6, 6);

            if (!verifica_otp($otp_inserito)) {
                throw new Exception("codice OTP errato, riprova");
            }

            $dati  = $_SESSION["modifica_pending"];
            $params = [
                "nome"         => $dati["nome"],
                "cognome"      => $dati["cognome"],
                "email"        => $dati["email"],
                "user_name"    => $dati["user_name"],
                "n_telefono"   => $dati["n_telefono"],
                "data_nascita" => $dati["data_nascita"],
                "id"           => $id_utente
            ];

            $sql_password = "";
            if (!empty($dati["password_hash"])) {
                $sql_password       = ", password = :password";
                $params["password"] = $dati["password_hash"];
            }

            $stm = $pdo->prepare("UPDATE utente SET nome = :nome, cognome = :cognome, email = :email, user_name = :user_name, n_telefono = :n_telefono, data_nascita = :data_nascita, codice_OTP = NULL $sql_password WHERE id = :id");
            $stm->execute($params);

            // aggiorna lo username in sessione se cambiato
            $_SESSION["user_name"] = $dati["user_name"];
            unset($_SESSION["modifica_pending"], $_SESSION["otp_segreto"]);
            $successo = "dati aggiornati con successo!";
        }

        // — eliminazione account
        elseif (isset($_POST["azione"]) && $_POST["azione"] === "elimina") {

            $password_conferma = controllo_parametro("password", $_POST["password_conferma"] ?? "", 4);
            $stm = $pdo->prepare("SELECT password FROM utente WHERE id = :id");
            $stm->execute(["id" => $id_utente]);
            $hash = $stm->fetchColumn();

            if (!password_verify($password_conferma, $hash)) {
                throw new Exception("password errata, account non eliminato");
            }

            $stm = $pdo->prepare("DELETE FROM utente WHERE id = :id");
            $stm->execute(["id" => $id_utente]);
            session_destroy();
            header("Location: index.html");
            exit;
        }

        // — salvataggio modifica
        elseif (isset($_POST["azione"]) && $_POST["azione"] === "modifica") {

            $in_modifica = true;

            $nome         = controllo_parametro("nome",               $_POST["nome"]         ?? "", 2, 50);
            $cognome      = controllo_parametro("cognome",            $_POST["cognome"]      ?? "", 2, 50);
            $email        = controllo_parametro("email",              $_POST["email"]        ?? "", 5, 100);
            $user_name    = controllo_parametro("username",           $_POST["user_name"]    ?? "", 4, 50);
            $n_telefono   = controllo_parametro("numero di telefono", $_POST["n_telefono"]   ?? "", 3, 10);
            $data_nascita = controllo_parametro("data di nascita",    $_POST["data_nascita"] ?? "");

            // controlla che l'utente abbia almeno 18 anni
            $nascita = new DateTime($data_nascita);
            $oggi    = new DateTime();
            if ($oggi->diff($nascita)->y < 18) {
                throw new Exception("devi avere almeno 18 anni");
            }
            $data_nascita = $nascita->format("Y-m-d");

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("formato email non valido");
            }

            // controlla email duplicata su altri utenti
            $stm = $pdo->prepare("SELECT id FROM utente WHERE email = :e AND id != :id");
            $stm->execute(["e" => $email, "id" => $id_utente]);
            if ($stm->rowCount() > 0) {
                throw new Exception("email già in uso da un altro account");
            }

            // controlla username duplicato su altri utenti
            $stm = $pdo->prepare("SELECT id FROM utente WHERE user_name = :u AND id != :id");
            $stm->execute(["u" => $user_name, "id" => $id_utente]);
            if ($stm->rowCount() > 0) {
                throw new Exception("username già in uso");
            }

            $password_hash = "";
            if (!empty(trim($_POST["password_nuova"] ?? ""))) {
                $password_nuova    = controllo_parametro("nuova password",    $_POST["password_nuova"]    ?? "", 8);
                $password_conferma = controllo_parametro("conferma password", $_POST["password_conferma"] ?? "", 1);
                if ($password_nuova !== $password_conferma) {
                    throw new Exception("le password non coincidono");
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
                $password_hash = password_hash($password_nuova, PASSWORD_DEFAULT);
            }

            // recupera dati attuali per confronto
            $stm = $pdo->prepare("SELECT email, nome, cognome, user_name, n_telefono, data_nascita FROM utente WHERE id = :id");
            $stm->execute(["id" => $id_utente]);
            $attuali = $stm->fetch(PDO::FETCH_ASSOC);

            // controlla se qualcosa è effettivamente cambiato
            $dati_cambiati = (
                $nome         !== $attuali["nome"]         ||
                $cognome      !== $attuali["cognome"]      ||
                $email        !== $attuali["email"]        ||
                $user_name    !== $attuali["user_name"]    ||
                $n_telefono   !== $attuali["n_telefono"]   ||
                $data_nascita !== $attuali["data_nascita"] ||
                !empty($password_hash)
            );

            if (!$dati_cambiati) {
                $in_modifica = false;
                $successo = "nessuna modifica rilevata.";
            }

            // se l'email è cambiata: otp + sessione
            elseif ($email !== $attuali["email"]) {

                $otp = genera_otp();
                $stm = $pdo->prepare("UPDATE utente SET codice_OTP = :otp WHERE id = :id");
                $stm->execute(["otp" => $otp, "id" => $id_utente]);

                $_SESSION["modifica_pending"] = [
                    "nome"          => $nome,
                    "cognome"       => $cognome,
                    "email"         => $email,
                    "user_name"     => $user_name,
                    "n_telefono"    => $n_telefono,
                    "data_nascita"  => $data_nascita,
                    "password_hash" => $password_hash
                ];

                invio_mail_conferma_email($email, $otp);
                $in_modifica = false;

            } else {
                // nessun cambio email: aggiorna subito
                $params = [
                    "nome"         => $nome,
                    "cognome"      => $cognome,
                    "email"        => $email,
                    "user_name"    => $user_name,
                    "n_telefono"   => $n_telefono,
                    "data_nascita" => $data_nascita,
                    "id"           => $id_utente
                ];

                $sql_password = "";
                if (!empty($password_hash)) {
                    $sql_password       = ", password = :password";
                    $params["password"] = $password_hash;
                }

                $stm = $pdo->prepare("UPDATE utente SET nome = :nome, cognome = :cognome, email = :email, user_name = :user_name, n_telefono = :n_telefono, data_nascita = :data_nascita $sql_password WHERE id = :id");
                $stm->execute($params);

                $_SESSION["user_name"] = $user_name;
                $in_modifica = false;
                $successo = "dati aggiornati con successo!";
            }
        }

    } catch (Exception $e) {
        $errore = $e->getMessage();
    }

    // carica sempre i dati, anche in caso di errore nel blocco modifica
    try {
        $pdo   = $pdo ?? get_pdo();
        $stm   = $pdo->prepare("SELECT nome, cognome, email, user_name, n_telefono, data_nascita, data_registrazione, crediti, last_login FROM utente WHERE id = :id");
        $stm->execute(["id" => $id_utente]);
        $utente = $stm->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $utente = null;
    }

    // dati da precompilare nei campi: pending se in attesa otp, post se errore modifica, altrimenti db
    if (isset($_SESSION["modifica_pending"])) {
        $dati_form = $_SESSION["modifica_pending"];
    } elseif ($in_modifica && isset($_POST["nome"])) {
        $dati_form = $_POST;
    } else {
        $dati_form = $utente;
    }

    $in_attesa_otp = isset($_SESSION["modifica_pending"]);

    // converte data dal formato Y-m-d a d/m/Y per visualizzazione
    function formato_data($data) {
        if (empty($data)) return "";
        $d = DateTime::createFromFormat("Y-m-d", $data);
        return $d ? $d->format("d/m/Y") : $data;
    }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>profilo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .wrapper {
            width: 100%;
            max-width: 1100px;
        }

        /* intestazione */
        .intestazione {
            display: flex;
            align-items: baseline;
            gap: 15px;
            margin-bottom: 6px;
        }
        .intestazione h1 { font-size: 1.6rem; color: #333; }
        .intestazione .username { font-size: 1rem; color: #888; }
        .nav-link { font-size: 0.85rem; color: #007BFF; text-decoration: none; margin-bottom: 25px; display: inline-block; }
        .nav-link:hover { text-decoration: underline; }

        /* messaggi */
        .error   { background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .info-msg{ background: #fff3cd; color: #856404; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }

        /* layout a due colonne */
        .layout {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 25px;
            align-items: start;
        }

        /* card generica */
        .card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            padding: 24px 28px;
            margin-bottom: 20px;
        }
        .card:last-child { margin-bottom: 0; }

        .card-titolo {
            font-size: 0.75rem;
            font-weight: 700;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f2f5;
        }

        /* cluster: griglia interna */
        .cluster {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 20px;
        }
        .cluster.colonna-singola { grid-template-columns: 1fr; }

        /* riga info (visualizzazione) */
        .info-riga {
            display: flex;
            flex-direction: column;
            padding: 9px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .cluster .info-riga:nth-last-child(-n+2):not(:nth-last-child(1) ~ .info-riga) { border-bottom: none; }
        .info-riga:last-child { border-bottom: none; }
        .info-riga .etichetta { font-size: 0.72rem; color: #bbb; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
        .info-riga .valore    { font-size: 0.92rem; color: #333; font-weight: 600; }

        .badge-crediti {
            display: inline-block;
            background: #e8f4fd;
            color: #0077cc;
            border-radius: 20px;
            padding: 3px 12px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        /* form modifica */
        .form-cluster {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 20px;
        }
        .form-cluster.colonna-singola { grid-template-columns: 1fr; }

        .form-gruppo { margin-bottom: 14px; }
        .form-gruppo label {
            display: block;
            font-size: 0.72rem;
            color: #bbb;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }
        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.92rem;
            color: #333;
            background: #f8f9fa;
            pointer-events: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .input-wrap input.modificabile {
            background: #fff;
            border-color: #007BFF;
            pointer-events: auto;
        }
        .input-wrap input.modificabile:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }

        /* pulsante occhio */
        .btn-occhio {
            position: absolute; right: 9px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #bbb; font-size: 0.85rem; padding: 3px;
            display: none;
        }
        .input-wrap input.modificabile ~ .btn-occhio { display: block; }

        .sep { border: none; border-top: 1px solid #f0f2f5; margin: 16px 0; }

        /* pulsanti */
        .btn { padding: 10px 18px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 0.88rem; transition: background 0.2s; }
        .btn-blu    { background: #007BFF; color: #fff; }
        .btn-blu:hover:not(:disabled) { background: #0056b3; }
        .btn-blu:disabled { background: #b3d1f7; cursor: not-allowed; }
        .btn-grigio { background: #6c757d; color: #fff; }
        .btn-grigio:hover { background: #5a6268; }
        .btn-rosso  { background: #dc3545; color: #fff; }
        .btn-rosso:hover { background: #b02a37; }
        .btn-full   { width: 100%; padding: 11px; }
        .btn-link   { display: inline-block; padding: 10px 18px; background: #6c757d; color: #fff; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 0.88rem; }
        .btn-link:hover { background: #5a6268; }
        .gruppo-btn { display: flex; gap: 10px; flex-wrap: wrap; }

        /* otp */
        .otp-input {
            width: 180px; text-align: center; font-size: 22px;
            letter-spacing: 8px; padding: 12px; border: 1.5px solid #ccc;
            border-radius: 8px; display: block; margin: 15px auto;
        }

        /* modale */
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); justify-content: center; align-items: center; z-index: 100; }
        .overlay.visibile { display: flex; }
        .modale { background: #fff; padding: 35px; border-radius: 14px; width: 380px; text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
        .modale h3 { margin-bottom: 10px; }
        .modale p  { color: #666; margin-bottom: 20px; line-height: 1.5; }
        .modale input { width: 100%; padding: 10px 13px; border: 1.5px solid #ddd; border-radius: 8px; margin-bottom: 16px; font-size: 0.92rem; }
        .modale input:focus { border-color: #dc3545; outline: none; }

        @media (max-width: 800px) {
            .layout, .cluster, .form-cluster { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="intestazione" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div style="display:flex;align-items:baseline;gap:12px;">
            <h1><i class="fa fa-user-circle"></i> profilo</h1>
            <span class="username">@<?= htmlspecialchars($utente["user_name"] ?? "") ?></span>
        </div>
        <a href="storico.php" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:#6f42c1;color:#fff;border-radius:8px;text-decoration:none;font-weight:bold;font-size:0.88rem;">
            <i class="fa fa-history"></i> storico transazioni
        </a>
    </div>
    <a href="dashboard.php" class="nav-link">← torna alla dashboard</a>

    <?php if ($errore): ?>
        <div class="error"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>
    <?php if ($successo): ?>
        <div class="success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($successo) ?></div>
    <?php endif; ?>

    <?php if ($in_attesa_otp): ?>

        <!-- schermata conferma otp cambio email -->
        <div class="card" style="max-width:480px; margin: 0 auto;">
            <div class="card-titolo"><i class="fa fa-envelope"></i> conferma nuova email</div>
            <div class="info-msg">abbiamo inviato un codice OTP a <b><?= htmlspecialchars($_SESSION["modifica_pending"]["email"]) ?></b>. inseriscilo per confermare il cambio.</div>
            <form method="POST" style="text-align:center;">
                <input type="hidden" name="azione" value="conferma_email">
                <input type="text" name="codice_otp" class="otp-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus>
                <div class="gruppo-btn" style="justify-content:center;">
                    <button type="submit" class="btn btn-blu"><i class="fa fa-check"></i> conferma</button>
                    <button type="submit" form="form_annulla" class="btn btn-grigio"><i class="fa fa-times"></i> annulla</button>
                </div>
            </form>
            <form method="POST" id="form_annulla">
                <input type="hidden" name="azione" value="annulla_modifica">
            </form>
        </div>

    <?php else: ?>

    <div class="layout">

        <!-- ===== COLONNA SINISTRA: visualizzazione ===== -->
        <div>

            <!-- cluster 1: identità -->
            <div class="card">
                <div class="card-titolo"><i class="fa fa-user"></i> identità</div>
                <div class="cluster">
                    <div class="info-riga">
                        <span class="etichetta">nome</span>
                        <span class="valore"><?= htmlspecialchars($utente["nome"]) ?></span>
                    </div>
                    <div class="info-riga">
                        <span class="etichetta">cognome</span>
                        <span class="valore"><?= htmlspecialchars($utente["cognome"]) ?></span>
                    </div>
                    <div class="info-riga">
                        <span class="etichetta">username</span>
                        <span class="valore">@<?= htmlspecialchars($utente["user_name"]) ?></span>
                    </div>
                    <div class="info-riga">
                        <span class="etichetta">data di nascita</span>
                        <span class="valore"><?= formato_data($utente["data_nascita"]) ?></span>
                    </div>
                </div>
            </div>

            <!-- cluster 2: contatti -->
            <div class="card">
                <div class="card-titolo"><i class="fa fa-address-book"></i> contatti</div>
                <div class="cluster">
                    <div class="info-riga">
                        <span class="etichetta">email</span>
                        <span class="valore"><?= htmlspecialchars($utente["email"]) ?></span>
                    </div>
                    <div class="info-riga">
                        <span class="etichetta">telefono</span>
                        <span class="valore"><?= htmlspecialchars($utente["n_telefono"]) ?></span>
                    </div>
                </div>
            </div>

            <!-- cluster 3: account -->
            <div class="card">
                <div class="card-titolo"><i class="fa fa-info-circle"></i> account</div>
                <div class="cluster">
                    <div class="info-riga">
                        <span class="etichetta">registrato il</span>
                        <span class="valore"><?= formato_data($utente["data_registrazione"]) ?></span>
                    </div>
                    <div class="info-riga">
                        <span class="etichetta">ultimo accesso</span>
                        <span class="valore"><?= formato_data($utente["last_login"]) ?></span>
                    </div>
                    <div class="info-riga" style="grid-column: 1 / -1;">
                        <span class="etichetta">crediti</span>
                        <span class="badge-crediti"><i class="fa fa-coins"></i> <?= htmlspecialchars($utente["crediti"]) ?></span>
                    </div>
                </div>
            </div>

            <button class="btn btn-rosso btn-full" onclick="document.getElementById('overlay_elimina').classList.add('visibile')">
                <i class="fa fa-trash"></i> elimina account
            </button>
        </div>

        <!-- ===== COLONNA DESTRA: modifica ===== -->
        <div class="card">
            <div class="card-titolo"><i class="fa fa-pen"></i> modifica dati</div>

            <form method="POST" id="form_modifica">
                <input type="hidden" name="azione" value="modifica">

                <!-- cluster 1: identità -->
                <p style="font-size:0.72rem;color:#bbb;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">identità</p>
                <div class="form-cluster">
                    <div class="form-gruppo">
                        <label>nome</label>
                        <div class="input-wrap">
                            <input type="text" name="nome" value="<?= htmlspecialchars($dati_form["nome"] ?? "") ?>" minlength="2" maxlength="50" required>
                        </div>
                    </div>
                    <div class="form-gruppo">
                        <label>cognome</label>
                        <div class="input-wrap">
                            <input type="text" name="cognome" value="<?= htmlspecialchars($dati_form["cognome"] ?? "") ?>" minlength="2" maxlength="50" required>
                        </div>
                    </div>
                    <div class="form-gruppo">
                        <label>username</label>
                        <div class="input-wrap">
                            <input type="text" name="user_name" value="<?= htmlspecialchars($dati_form["user_name"] ?? "") ?>" minlength="4" maxlength="50" required>
                        </div>
                    </div>
                    <div class="form-gruppo">
                        <label>data di nascita</label>
                        <div class="input-wrap">
                            <input type="date" name="data_nascita"
                                value="<?= htmlspecialchars($dati_form["data_nascita"] ?? "") ?>"
                                max="<?= (new DateTime("-18 years"))->format("Y-m-d") ?>"
                                required>
                        </div>
                    </div>
                </div>

                <hr class="sep">

                <!-- cluster 2: contatti -->
                <p style="font-size:0.72rem;color:#bbb;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">contatti</p>
                <div class="form-cluster">
                    <div class="form-gruppo">
                        <label>email</label>
                        <div class="input-wrap">
                            <input type="email" name="email" value="<?= htmlspecialchars($dati_form["email"] ?? "") ?>" maxlength="100" required>
                        </div>
                    </div>
                    <div class="form-gruppo">
                        <label>telefono</label>
                        <div class="input-wrap">
                            <input type="tel" name="n_telefono" value="<?= htmlspecialchars($dati_form["n_telefono"] ?? "") ?>" minlength="3" maxlength="10" required>
                        </div>
                    </div>
                </div>

                <hr class="sep">

                <!-- cluster 3: password -->
                <p style="font-size:0.72rem;color:#bbb;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">password &nbsp;<span style="text-transform:none;letter-spacing:0;">(lascia vuoto per non cambiarla)</span></p>
                <div class="form-cluster">
                    <div class="form-gruppo">
                        <label>nuova password</label>
                        <div class="input-wrap">
                            <input type="password" name="password_nuova" id="pw1" minlength="8">
                            <button type="button" class="btn-occhio" onclick="toggle_password('pw1', this)"><i class="fa fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-gruppo">
                        <label>conferma password</label>
                        <div class="input-wrap">
                            <input type="password" name="password_conferma" id="pw2" minlength="8">
                            <button type="button" class="btn-occhio" onclick="toggle_password('pw2', this)"><i class="fa fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div id="box_criteri" style="display:none;background:#f8f9fa;border-radius:8px;padding:10px 14px;margin-top:-5px;margin-bottom:10px;font-size:0.8rem;color:#888;">
                    <b style="color:#555;">criteri password:</b>
                    <ul style="padding-left:16px;margin-top:4px;">
                        <li>almeno 8 caratteri</li>
                        <li>almeno una lettera maiuscola</li>
                        <li>almeno un numero</li>
                        <li>almeno un carattere speciale (es. ! @ # $ %)</li>
                    </ul>
                </div>

                <hr class="sep">

                <div class="gruppo-btn">
                    <button type="button" class="btn btn-grigio" id="btn_modifica" onclick="abilita_modifica()">
                        <i class="fa fa-pen"></i> modifica
                    </button>
                    <button type="button" class="btn btn-blu" id="btn_salva" disabled onclick="mostra_conferma()">
                        <i class="fa fa-save"></i> salva
                    </button>
                    <button type="button" class="btn btn-grigio" id="btn_annulla" style="display:none;" onclick="annulla_modifica()">
                        <i class="fa fa-times"></i> annulla
                    </button>
                </div>
            </form>
        </div>

    </div>
    <?php endif; ?>

</div><!-- fine wrapper -->

<!-- modale conferma salvataggio -->
<div id="overlay_conferma" class="overlay">
    <div class="modale">
        <h3><i class="fa fa-question-circle" style="color:#007BFF;"></i> conferma modifiche</h3>
        <p>sei sicuro di voler salvare le modifiche?</p>
        <div class="gruppo-btn" style="justify-content:center;">
            <button class="btn btn-grigio" onclick="document.getElementById('overlay_conferma').classList.remove('visibile')">annulla</button>
            <button class="btn btn-blu" onclick="document.getElementById('form_modifica').submit()">salva</button>
        </div>
    </div>
</div>

<!-- modale conferma eliminazione -->
<div id="overlay_elimina" class="overlay">
    <div class="modale">
        <h3 style="color:#dc3545;"><i class="fa fa-exclamation-triangle"></i> elimina account</h3>
        <p>questa azione è irreversibile.<br>inserisci la tua password per confermare.</p>
        <form method="POST">
            <input type="hidden" name="azione" value="elimina">
            <input type="password" name="password_conferma" placeholder="la tua password" required>
            <div class="gruppo-btn" style="justify-content:center;">
                <button type="button" class="btn btn-grigio" onclick="document.getElementById('overlay_elimina').classList.remove('visibile')">annulla</button>
                <button type="submit" class="btn btn-rosso">elimina</button>
            </div>
        </form>
    </div>
</div>

<script>
    const valori_originali = {
        nome:         "<?= addslashes($utente["nome"]         ?? "") ?>",
        cognome:      "<?= addslashes($utente["cognome"]      ?? "") ?>",
        user_name:    "<?= addslashes($utente["user_name"]    ?? "") ?>",
        email:        "<?= addslashes($utente["email"]        ?? "") ?>",
        n_telefono:   "<?= addslashes($utente["n_telefono"]   ?? "") ?>",
        data_nascita: "<?= addslashes($utente["data_nascita"] ?? "") ?>"
    };

    function abilita_modifica() {
        document.querySelectorAll("#form_modifica input").forEach(el => el.classList.add("modificabile"));
        document.getElementById("box_criteri").style.display = "block";
        document.getElementById("btn_modifica").style.display = "none";
        document.getElementById("btn_salva").disabled         = false;
        document.getElementById("btn_annulla").style.display  = "inline-block";
    }

    function annulla_modifica() {
        document.getElementById("box_criteri").style.display = "none";
        Object.keys(valori_originali).forEach(nome => {
            const campo = document.querySelector(`[name="${nome}"]`);
            if (campo) campo.value = valori_originali[nome];
        });
        document.getElementById("pw1").value = "";
        document.getElementById("pw2").value = "";
        document.querySelectorAll("#form_modifica input").forEach(el => el.classList.remove("modificabile"));
        document.getElementById("btn_modifica").style.display = "inline-block";
        document.getElementById("btn_salva").disabled         = true;
        document.getElementById("btn_annulla").style.display  = "none";
    }

    function mostra_conferma() {
        document.getElementById("overlay_conferma").classList.add("visibile");
    }

    function toggle_password(id, btn) {
        const input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
            btn.innerHTML = '<i class="fa fa-eye-slash"></i>';
        } else {
            input.type = "password";
            btn.innerHTML = '<i class="fa fa-eye"></i>';
        }
    }

    <?php if ($in_modifica || ($errore && isset($_POST["azione"]) && $_POST["azione"] === "modifica")): ?>
        window.onload = () => abilita_modifica();
    <?php endif; ?>
</script>
</body>
</html>
