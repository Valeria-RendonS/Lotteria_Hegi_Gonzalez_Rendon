<?php

    // per installare: composer require phpmailer/phpmailer
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    require 'vendor/autoload.php';

    // invio otp per registrazione
    function invio_mail_otp($email_destinatario, $codice_otp) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'insiemexcomune01@gmail.com';
            $mail->Password   = 'zogt angz nuis zvbr';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('insiemexcomune01@gmail.com', 'Sistema OTP');
            $mail->addAddress($email_destinatario);
            $mail->isHTML(true);
            $mail->Subject = "il tuo codice OTP";
            $mail->Body    = "il tuo codice di verifica e': <b>$codice_otp</b>";

            $mail->send();

        } catch (Exception $e) {
            throw new Exception("errore invio mail: {$mail->ErrorInfo}");
        }
    }

    // invio link univoco + otp per recupero password
    function invio_mail_recupero($email_destinatario, $codice_otp, $token) {
        $mail = new PHPMailer(true);

        // il token nell'url identifica univocamente la richiesta senza esporre l'id utente
        $link = "http://localhost/pagine_web/prove%20lotteria/progetto/recupera_password.php?token=" . urlencode($token);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'insiemexcomune01@gmail.com';
            $mail->Password   = 'zogt angz nuis zvbr';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('insiemexcomune01@gmail.com', 'Recupero Password');
            $mail->addAddress($email_destinatario);
            $mail->isHTML(true);
            $mail->Subject = "recupero password";
            $mail->Body    = "
                <p>hai richiesto il recupero della password.</p>
                <p>clicca il link qui sotto per accedere alla pagina di recupero:</p>
                <p><a href='$link'>$link</a></p>
                <p>una volta nella pagina, inserisci questo codice OTP: <b>$codice_otp</b></p>
                <p>se non hai fatto tu questa richiesta, ignora questa mail.</p>
            ";

            $mail->send();

        } catch (Exception $e) {
            throw new Exception("errore invio mail: {$mail->ErrorInfo}");
        }
    }

    // invio otp per conferma cambio email
    function invio_mail_conferma_email($email_destinatario, $codice_otp) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'insiemexcomune01@gmail.com';
            $mail->Password   = 'zogt angz nuis zvbr';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('insiemexcomune01@gmail.com', 'Conferma Email');
            $mail->addAddress($email_destinatario);
            $mail->isHTML(true);
            $mail->Subject = "conferma il cambio email";
            $mail->Body    = "
                <p>hai richiesto di cambiare la tua email con questo indirizzo.</p>
                <p>inserisci il codice OTP nella pagina del profilo per confermare: <b>$codice_otp</b></p>
                <p>se non hai fatto tu questa richiesta, ignora questa mail. la tua email attuale rimarrà invariata.</p>
            ";

            $mail->send();

        } catch (Exception $e) {
            throw new Exception("errore invio mail: {$mail->ErrorInfo}");
        }
    }

?>
