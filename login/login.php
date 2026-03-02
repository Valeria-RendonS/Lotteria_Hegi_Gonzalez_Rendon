<?php

    function controllo_parametro($nome_campo, $variabile, $min_lunghezza = 0, $max_lunghezza = 0){
        $variabile = trim($variabile);
        
        if(empty($variabile) || strlen($variabile) < $min_lunghezza || ($max_lunghezza != 0 && strlen($variabile) > $max_lunghezza) ){
            throw new Exception("il campo: " . $nome_campo . " non rispetta i requisiti");
        }

        return $variabile;
    }

    //controllo sui dati passati
    try{

        require "connessione.php";

        //controllo esistenza post
        if (!isset($_POST)) {
            throw new Exception("non sono stati passati i dati richiesti");
        }

        //controllo esistenza campi
        if (!isset($_POST["user_name"]) || !isset($_POST["password"])){
            throw new Exception("uno o piu campi non presenti");
        }


        $user = controllo_parametro("username", $_POST["user_name"], 4, 30);
        
        $password = controllo_parametro("password", $_POST["password"], 4);

        $hash;

        $pdo = new PDO($connString, $connUser, $connPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql_login = "SELECT hash FROM credenziali WHERE user_name = :u";
        $stm = $pdo->prepare($sql_login);
        $stm->bindParam("u", $user);

        $stm->execute();

        $hash = $stm->fetchColumn();
        
        if (!password_verify($password, $hash)){
            throw new Exception("password o username non validi");
        }

    }catch (PDOException){
        $errore = "password o username non validi";
    }catch (Exception $e){
        $errore = $e -> getMessage();
    }

?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Risultato</title>
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f0f2f5;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding-top: 50px;
        min-height: 100vh;
    }

    .container {
        background-color: #fff;
        padding: 30px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        width: 400px;
    }

    h2 {
        text-align: center;
        margin-bottom: 25px;
        color: #333;
    }

    .success {
        background-color: #d4edda;
        border-left: 6px solid #28a745;
        padding: 12px 15px;
        margin-bottom: 15px;
        color: #155724;
        border-radius: 6px;
    }

    .error {
        background-color: #f8d7da;
        border-left: 6px solid #dc3545;
        padding: 12px 15px;
        margin-bottom: 15px;
        color: #721c24;
        border-radius: 6px;
    }

    a.button {
        display: inline-block;
        padding: 10px 20px;
        background-color: #007BFF;
        color: #fff;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        margin-top: 15px;
        transition: background-color 0.3s ease;
    }

    a.button:hover {
        background-color: #0056b3;
    }
</style>
</head>
<body>

<div class="container">
    <h2>Login</h2>

    <?php if(isset($errore)): ?>
        <div class="error">
            <i class="fa fa-exclamation-triangle"></i>
            <?= htmlspecialchars($errore) ?>
        </div>
    <?php else: ?>
        <div class="success">
            <i class="fa fa-check-circle"></i>
            Login eseguito con successo!
        </div>
    <?php endif; ?>

    <a href="index.html" class="button">
        <i class="fa fa-arrow-left"></i> Torna alla Home
    </a>
</div>

</body>
</html>
