<?php

    //per installare composer: https://getcomposer.org/download/
    //comando per crare la cartella: composer require phpmailer/phpmailer


    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    require 'vendor/autoload.php';

    function invio_mail_OTP($email_destinatario, $codice_OTP){
        $mail = new PHPMailer(true);

        try {
            // 1. Configurazione Server
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'insiemexcomune01@gmail.com';
            $mail->Password   = 'zogt angz nuis zvbr'; // Password per l'app
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // 2. Destinatari e Contenuto
            $mail->setFrom('insiemexcomune01@gmail.com', 'Sistema OTP');
            $mail->addAddress($email_destinatario); 
            $mail->isHTML(true);
            $mail->Subject = "Il tuo codice OTP";
            $mail->Body    = "Il tuo codice di verifica è: <b>$codice_OTP</b>";

            $mail->send();
            
            return "codice OTP inviato, controlla la tua mail";

        } catch (Exception $e) {
            $inviata = false;
            return("errore invio mail: {$mail->ErrorInfo}");
        }
    }
    
?>