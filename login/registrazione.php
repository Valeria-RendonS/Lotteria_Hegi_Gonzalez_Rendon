<?php
    session_start();                    //necessario per salvare i dati temporaneamente e l'OTP

    function controllo_parametro($nome_campo, $variabile, $min_lunghezza = 0, $max_lunghezza = 0){
        $variabile = trim($variabile);
        if(empty($variabile) || strlen($variabile) < $min_lunghezza || ($max_lunghezza != 0 && strlen($variabile) > $max_lunghezza) ){
            throw new Exception("il campo: " . $nome_campo . " non rispetta i requisiti");
        }
        return $variabile;
    }

    $stato_otp = false;                 //serve a capire se mostrare la input OTP
    $successo = false;
    $errore = null;

    try {
        require "connessione.php";
        $pdo = new PDO($connString, $connUser, $connPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        //veririfica dell'otp inviato alla mail
        if (isset($_POST["codice_otp_inviato"])) {
            $otp_utente = $_POST["codice_otp_inviato"];

            if ($otp_utente == $_SESSION["otp_segreto"]) {
                
                //recupero i dati salvati precedentemente in sessione
                $dati = $_SESSION["dati_temporanei"];

                $sql_registrazione = "INSERT INTO credenziali (user_name, nome, cognome, n_telefono, eta, data_nascita, email, data_registrazione, hash) VALUES (:u, :n, :c, :n_t, :e, :d_n, :em, :d_r, :h)";
                $stm = $pdo->prepare($sql_registrazione);
                $stm->execute([                                     //fare in questo modo e come il bindparam, ma evita di scrivere una riga per ogni parametro
                    "u"   => $dati["user_name"],
                    "n"   => $dati["nome"],
                    "c"   => $dati["cognome"],
                    "n_t" => $dati["n_telefono"],
                    "e"   => $dati["eta"],
                    "d_n" => $dati["data_nascita"],
                    "em"  => $dati["email"],
                    "d_r" => $dati["data_registrazione"],
                    "h"   => $dati["hash"]
                ]);

                $successo = true;
                session_destroy();                  //pulizia sessione
            } else {
                $stato_otp = true;                  //mantiene la visualizzazione della input OTP
                throw new Exception("codice OTP errato, riprova");
            }
        } 


        //controllo dati utente e creazione codice OTP
        else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["user_name"])) {
            
            // Controllo parametri
            $user = controllo_parametro("user name", $_POST["user_name"], 4, 30);
            $nome = controllo_parametro("nome", $_POST["nome"], 4, 50);
            $cognome = controllo_parametro("cognome", $_POST["cognome"], 4, 50);
            $n_telefono = (int) controllo_parametro("numero di telefono", $_POST["n_telefono"], 10, 10);
            $email = controllo_parametro("email", $_POST["email"], 5, 50);
            $password = controllo_parametro("password", $_POST["password"], 4);
            
            $data_nascita = new DateTime(controllo_parametro("data di nascita", $_POST['data_nascita']));
            $data_registrazione = new DateTime("now");
            $eta = date_diff($data_nascita, $data_registrazione) -> y;                        //estrae solo l'anno

            //creazione hash
            $hash = password_hash($password, PASSWORD_DEFAULT);



            //controllo esistenza username
            $sql_esistenza_user_name = "SELECT user_name FROM credenziali WHERE user_name = :us";
            $stm = $pdo -> prepare($sql_esistenza_user_name);
            $stm -> bindParam(":us", $user);
            $stm -> execute();
            if ($stm -> rowCount() != 0) {
                throw new Exception("username gia in uso");
            }


            // Generazione OTP (es. 6 cifre)
            $otp = random_int(0, 999999);
            $otp = sprintf("%06d", $otp);
            /*
                %: Inizio formattazione.
                0: Il carattere da usare per il riempimento.
                6: La lunghezza totale desiderata.
                d: Tratta il dato come un numero intero (digit).
            */

            $_SESSION["otp_segreto"] = $otp;
            require "invio_email.php";
            invio_mail_OTP($email, $otp);
            
            
            //salva i dati in sessione per usarli dopo la conferma dell' OTP
            $_SESSION["dati_temporanei"] = [
                "user_name" => $user,
                "nome" => $nome,
                "cognome" => $cognome,
                "n_telefono" => $n_telefono,
                "eta" => $eta,
                "data_nascita" => $data_nascita->format("Y-m-d"),                               //formato per l'insert nelle query
                "email" => $email,
                "data_registrazione" => $data_registrazione->format("Y-m-d"),                   //fomrato per l'insert nelle query
                "hash" => $hash
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
    <title>Registrazione - Verifica</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; padding-top: 50px; }
        .container { background-color: #fff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 400px; text-align: center; }
        .error { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: left; }
        .success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        input[type="text"] { width: 100%; padding: 12px; margin: 15px 0; border: 1px solid #ccc; border-radius: 8px; text-align: center; font-size: 20px; letter-spacing: 5px; }
        button, .button { display: inline-block; width: 100%; padding: 12px; background-color: #007BFF; color: #fff; border-radius: 8px; text-decoration: none; font-weight: bold; border: none; cursor: pointer; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Registrazione</h2>

        <?php if($errore): ?>
            <div class="error"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>

        <?php if($successo): ?>
            <div class="success"><i class="fa fa-check-circle"></i> Registrazione completata con successo!</div>
            <a href="index.html" class="button" onclick="sessionStorage.clear();">
                Accedi
            </a>
        
        <?php elseif($stato_otp): ?>
            <p>Abbiamo inviato un codice OTP alla tua email. Inseriscilo qui sotto per confermare:</p>
            <form method="POST">
                <input type="text" name="codice_otp_inviato" placeholder="000000" maxlength="6" required autofocus>
                <button type="submit">Verifica Codice</button>
            </form>
        
        <?php else: ?>
            <p>Si è verificato un errore o l'accesso alla pagina non è valido.</p>
            <a href="index.html" class="button">Torna indietro</a>
        <?php endif; ?>
    </div>

</body>
</html>