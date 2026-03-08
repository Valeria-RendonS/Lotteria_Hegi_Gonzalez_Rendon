<?php

    // valida un campo: trim, controllo vuoto e lunghezza
    function controllo_parametro($nome_campo, $variabile, $min_lunghezza = 0, $max_lunghezza = 0) {
        $variabile = trim($variabile);
        if (empty($variabile) || strlen($variabile) < $min_lunghezza || ($max_lunghezza != 0 && strlen($variabile) > $max_lunghezza)) {
            throw new Exception("il campo '$nome_campo' non rispetta i requisiti");
        }
        return $variabile;
    }

    // crea e restituisce la connessione pdo
    function get_pdo() {
        global $connString, $connUser, $connPass;
        if (empty($connString)) {
            require_once "connessione.php";
        }
        $pdo = new PDO($connString, $connUser, $connPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    // genera un otp a 6 cifre e lo salva in sessione
    function genera_otp() {
        $otp = sprintf("%06d", random_int(0, 999999));
        $_SESSION["otp_segreto"] = $otp;
        return $otp;
    }

    // verifica l'otp inserito dall'utente rispetto a quello in sessione
    function verifica_otp($otp_utente) {
        return isset($_SESSION["otp_segreto"]) && $otp_utente === $_SESSION["otp_segreto"];
    }

?>
