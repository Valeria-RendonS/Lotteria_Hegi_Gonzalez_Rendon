<?php
require 'connessione.php';

$messaggio = "";
$errore = false;

if (isset($_GET['id'])) {
    $id_lotteria = $_GET['id'];
    $oggi = date('Y-m-d');

    try {
        $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Verifichiamo che la lotteria sia FINITA e senza vincitore
        $sql_check = "SELECT * FROM lotteria WHERE id = :id";
        $stm_check = $pdo->prepare($sql_check);
        $stm_check->bindValue(':id', $id_lotteria);
        $stm_check->execute();
        $lotteria = $stm_check->fetch(PDO::FETCH_ASSOC);

        if (!$lotteria) {
            die("Lotteria inesistente.");
        }
        if ($lotteria['stato'] != 'finito' || !empty($lotteria['biglietto_vincente'])) {
            die("Estrazione già avvenuta o lotteria non ancora terminata.");
        }
        if ($lotteria['n_biglietti_venduti'] == 0) {
            die("Nessun biglietto venduto. Impossibile fare l'estrazione.");
        }

        // --- INIZIO TRANSAZIONE SPOSTAMENTO CREDITI ---
        $pdo->beginTransaction();

        // 2. ESTRAZIONE CASUALE DAL DATABASE
        // Cerchiamo un biglietto VENDUTO (id_cliente IS NOT NULL) a caso per questa lotteria
        // ORDER BY RAND() LIMIT 1 è il modo più facile per dire a SQL di pescarne uno a sorte
        $sql_vincente = "SELECT id, id_cliente FROM biglietto 
                         WHERE id_lotteira = :id_lotteria AND id_cliente IS NOT NULL 
                         ORDER BY RAND() LIMIT 1";
        $stm_vincente = $pdo->prepare($sql_vincente);
        $stm_vincente->bindValue(':id_lotteria', $id_lotteria);
        $stm_vincente->execute();
        $biglietto_vincente = $stm_vincente->fetch(PDO::FETCH_ASSOC);

        $id_biglietto = $biglietto_vincente['id'];
        $id_cliente_vincitore = $biglietto_vincente['id_cliente'];

        // 3. MATEMATICA: Calcolo percentuali
        $incasso_totale = $lotteria['n_biglietti_venduti'] * $lotteria['prezzo_biglietto'];
        $premio_vincitore = ceil($incasso_totale * 0.85); // 85% arrotondato per eccesso
        $cassa_gestore = $incasso_totale - $premio_vincitore; // Il resto va al gestore (equivale al 15% arrotondato per difetto)
        $id_admin = $lotteria['id_admin'];

        // 4. ACCREDITO AL VINCITORE E ALL'ADMIN
        $sql_paga_vincitore = "UPDATE utente SET crediti = crediti + :premio WHERE id = :id_cliente";
        $stm_paga_v = $pdo->prepare($sql_paga_vincitore);
        $stm_paga_v->bindValue(':premio', $premio_vincitore);
        $stm_paga_v->bindValue(':id_cliente', $id_cliente_vincitore);
        $stm_paga_v->execute();

        $sql_paga_admin = "UPDATE utente SET crediti = crediti + :cassa WHERE id = :id_admin";
        $stm_paga_a = $pdo->prepare($sql_paga_admin);
        $stm_paga_a->bindValue(':cassa', $cassa_gestore);
        $stm_paga_a->bindValue(':id_admin', $id_admin);
        $stm_paga_a->execute();

        // 5. REGISTRAZIONE NELLA TABELLA 'vincita'
        $sql_log_vincita = "INSERT INTO vincita (data, quantita, id_lotteria, id_cliente) 
                            VALUES (:oggi, :premio, :id_lott, :id_cliente)";
        $stm_log = $pdo->prepare($sql_log_vincita);
        $stm_log->bindValue(':oggi', $oggi);
        $stm_log->bindValue(':premio', $premio_vincitore);
        $stm_log->bindValue(':id_lott', $id_lotteria);
        $stm_log->bindValue(':id_cliente', $id_cliente_vincitore);
        $stm_log->execute();

        // 6. AGGIORNAMENTO DELLA TABELLA 'lotteria'
        $sql_aggiorna_lotteria = "UPDATE lotteria SET 
                                  biglietto_vincente = :id_bigl, 
                                  incasso = :incasso, 
                                  erogato = :erogato, 
                                  data_accredito = :oggi 
                                  WHERE id = :id_lott";
        $stm_agg = $pdo->prepare($sql_aggiorna_lotteria);
        $stm_agg->bindValue(':id_bigl', $id_biglietto);
        $stm_agg->bindValue(':incasso', $incasso_totale);
        $stm_agg->bindValue(':erogato', $premio_vincitore);
        $stm_agg->bindValue(':oggi', $oggi);
        $stm_agg->bindValue(':id_lott', $id_lotteria);
        $stm_agg->execute();

        // CONFERMO TUTTE LE OPERAZIONI
        $pdo->commit();

        $messaggio = "Estrazione completata! Il Biglietto #$id_biglietto ha vinto $premio_vincitore crediti.";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack(); // Se qualcosa va storto, non paga nessuno!
        }
        $messaggio = "Errore durante l'estrazione: " . $e->getMessage();
        $errore = true;
    }

} else {
    $messaggio = "ID lotteria non valido.";
    $errore = true;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Estrazione Lotteria</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f9; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .result-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        .icon-large { font-size: 80px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="result-card">
        <?php if ($errore): ?>
            <i class="fa fa-triangle-exclamation w3-text-red icon-large"></i>
            <h2 class="w3-text-red">Errore</h2>
            <p><?php echo $messaggio; ?></p>
        <?php else: ?>
            <i class="fa fa-gift w3-text-green icon-large"></i>
            <h2 class="w3-text-green">Abbiamo un Vincitore!</h2>
            <p class="w3-large"><?php echo $messaggio; ?></p>
            <p class="w3-text-grey w3-small">I crediti sono stati accreditati automaticamente sui conti dei rispettivi utenti.</p>
        <?php endif; ?>
        
        <div class="w3-margin-top" style="padding-top: 20px;">
            <a href="gestione_lotteria.php" class="w3-button w3-blue w3-round-large w3-block">Torna alla Dashboard</a>
        </div>
    </div>

</body>
</html>