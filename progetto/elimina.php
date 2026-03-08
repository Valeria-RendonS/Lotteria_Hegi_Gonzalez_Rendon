<?php
require 'connessione.php';

$messaggio = "";
$errore = false;

// Controllo che mi sia stato passato un ID tramite l'URL (es. elimina.php?id=5)
if (isset($_GET['id'])) {
    $id_lotteria = $_GET['id'];

    try {
        $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. PRIMO STEP: Controllo lo stato della lotteria
        $sql_check = "SELECT stato FROM lotteria WHERE id = :id";
        $stm_check = $pdo->prepare($sql_check);
        $stm_check->bindValue(':id', $id_lotteria);
        $stm_check->execute();
        
        $lotteria = $stm_check->fetch(PDO::FETCH_ASSOC);

        // Verifico se esiste e se è in bozza
        if (!$lotteria) {
            $messaggio = "La lotteria richiesta non esiste nel database.";
            $errore = true;
        } elseif ($lotteria['stato'] != 'bozza') {
            $messaggio = "Azione negata! Puoi eliminare solo le lotterie in stato 'Bozza'. I dati delle lotterie attive o finite non possono essere cancellati.";
            $errore = true;
        } else {
            // 2. SECONDO STEP: Elimino la lotteria
            // NOTA PER IL PROF: Grazie alla regola "ON DELETE CASCADE" impostata nel database
            // sulla tabella 'biglietto', eliminando la lotteria verranno eliminati automaticamente 
            // anche tutti i biglietti ad essa collegati! Non serve fare due query separate.
            
            $sql_delete = "DELETE FROM lotteria WHERE id = :id";
            $stm_delete = $pdo->prepare($sql_delete);
            $stm_delete->bindValue(':id', $id_lotteria);
            $stm_delete->execute();

            $messaggio = "Lotteria eliminata con successo dal sistema!";
        }

    } catch (PDOException $e) {
        $messaggio = "Errore di connessione al database: " . $e->getMessage();
        $errore = true;
    }

} else {
    // Se qualcuno apre la pagina senza l'ID nell'URL
    $messaggio = "Nessuna lotteria selezionata per l'eliminazione.";
    $errore = true;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esito Eliminazione</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { 
            background-color: #f4f7f9; 
            font-family: 'Segoe UI', sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
        }
        .result-card { 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            text-align: center; 
            max-width: 500px; 
            width: 90%;
        }
        .icon-large { font-size: 80px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="result-card">
        
        <?php if ($errore): ?>
            <i class="fa fa-triangle-exclamation w3-text-red icon-large"></i>
            <h2 class="w3-text-red">Operazione Annullata</h2>
            <p class="w3-text-grey"><?php echo $messaggio; ?></p>
        <?php else: ?>
            <i class="fa fa-trash-check w3-text-green icon-large"></i>
            <h2 class="w3-text-green">Eliminazione Completata</h2>
            <p class="w3-text-grey"><?php echo $messaggio; ?></p>
        <?php endif; ?>
        
        <div class="w3-margin-top" style="padding-top: 20px;">
            <a href="gestione_lotteria.php" class="w3-button w3-blue w3-round-large w3-block w3-padding-large">
                <i class="fa fa-arrow-left"></i> Torna alla Dashboard
            </a>
        </div>
        
    </div>

</body>
</html>