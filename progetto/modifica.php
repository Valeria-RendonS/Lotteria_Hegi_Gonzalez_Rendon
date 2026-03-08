<?php
require 'connessione.php';

// 1. INIZIALIZZAZIONE VARIABILI
$id_lotteria = "";
$nome = "";
$descrizione = "";
$tipo = "data_fissa";
$prezzo_biglietto = "";
$n_biglietti = "";
$data_inizio = "";
$data_fine = "";
$data_estr = "";

$errore = "";
$successo = "";
$oggi = date('Y-m-d');

// Controlliamo subito se abbiamo l'ID dalla URL o dal form nascosto
if (isset($_GET['id'])) {
    $id_lotteria = $_GET['id'];
} elseif (isset($_POST['id_lotteria'])) {
    $id_lotteria = $_POST['id_lotteria'];
} else {
    // Se non c'è ID, blocco l'esecuzione (sicurezza)
    die("ID Lotteria mancante. Torna alla pagina principale.");
}


// 2. RECUPERO DATI DAL DATABASE (Quando apro la pagina la prima volta)
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    try {
        $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "SELECT * FROM lotteria WHERE id = :id";
        $stm = $pdo->prepare($sql);
        $stm->bindValue(":id", $id_lotteria);
        $stm->execute();
        $riga = $stm->fetch(PDO::FETCH_ASSOC);

        // Controllo di Sicurezza #1: La lotteria esiste?
        if (!$riga) {
            die("La lotteria richiesta non esiste.");
        }

        // Controllo di Sicurezza #2: È in bozza?
        if ($riga['stato'] != 'bozza') {
            die("ERRORE DI SICUREZZA: Puoi modificare solo le lotterie in stato 'Bozza'.");
        }

        // Riempio le variabili con i dati dal database per mostrarli nel form
        $nome = $riga['nome'];
        $descrizione = $riga['descrizione'];
        $tipo = $riga['tipo'];
        $prezzo_biglietto = $riga['prezzo_biglietto'];
        $n_biglietti = ($riga['n_biglietti_totali'] > 0) ? $riga['n_biglietti_totali'] : "";
        $data_inizio = $riga['data_inizio_acquisti'];
        $data_fine = $riga['data_fine_acquisti'];
        $data_estr = $riga['data_estrazione'];

    } catch (PDOException $e) {
        $errore = "Errore caricamento dati: " . $e->getMessage();
    }
}


// 3. ELABORAZIONE DELLE MODIFICHE (Quando premo "Salva")
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recupero i nuovi dati dal modulo
    $nome = $_POST['nome'];
    $descrizione = $_POST['descrizione'];
    $tipo = $_POST['tipo'];
    $prezzo_biglietto = $_POST['prezzo_biglietto'];
    $data_inizio = $_POST['data_inizio_acquisti'];
    
    if ($tipo == 'data_fissa') {
        $data_fine = $_POST['data_fine_acquisti'];
        $data_estr = $_POST['data_estrazione'];
        $n_biglietti = 0; 
    } else {
        $n_biglietti = $_POST['n_biglietti_totali'];
        $data_fine = null;
        $data_estr = null;
    }

    // --- CONTROLLI LATO SERVER ---
    if (empty($nome) || empty($descrizione) || empty($tipo) || empty($data_inizio) || empty($prezzo_biglietto)) {
        $errore = "Compila tutti i campi obbligatori di base, incluso il prezzo.";
    } elseif ($prezzo_biglietto < 1) {
        $errore = "Il costo in crediti deve essere almeno 1.";
    } elseif ($data_inizio < $oggi) {
        $errore = "La data di inizio non può essere precedente a oggi.";
    } else {
        if ($tipo == 'data_fissa') {
            if (empty($data_fine) || empty($data_estr)) {
                $errore = "Inserisci le date di fine vendita e di estrazione.";
            } elseif ($data_fine <= $data_inizio) {
                $errore = "La data di fine vendita deve essere successiva all'inizio.";
            } elseif ($data_estr <= $data_fine) {
                $errore = "La data di estrazione deve essere successiva alla fine vendita.";
            }
        } 
        elseif ($tipo == 'esaurimento') {
            if (empty($n_biglietti) || $n_biglietti <= 0) {
                $errore = "Inserisci un numero valido di biglietti totali.";
            }
        }
    }

    // --- AGGIORNAMENTO NEL DATABASE ---
    // --- AGGIORNAMENTO NEL DATABASE ---
    if ($errore == "") {
        try {
            $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // --- NUOVO CONTROLLO: NOME UNIVOCO (Ignorando se stessa) ---
            $sql_check = "SELECT COUNT(*) FROM lotteria WHERE nome = :nom AND id != :id_attuale";
            $stm_check = $pdo->prepare($sql_check);
            $stm_check->bindValue(":nom", $nome);
            $stm_check->bindValue(":id_attuale", $id_lotteria);
            $stm_check->execute();
            
            if ($stm_check->fetchColumn() > 0) {
                $errore = "Esiste già UN'ALTRA lotteria con questo nome. Scegli un nome diverso.";
            }

            // Se il nome è libero, procedo col salvataggio
            if ($errore == "") {
                $pdo->beginTransaction();

                // A) AGGIORNO I DATI DELLA LOTTERIA
                $sql_update = "UPDATE lotteria SET 
                                nome = :nom, 
                                descrizione = :des, 
                                tipo = :tip, 
                                prezzo_biglietto = :prezzo, 
                                n_biglietti_totali = :ntot, 
                                data_inizio_acquisti = :dini, 
                                data_fine_acquisti = :dfin, 
                                data_estrazione = :destr 
                               WHERE id = :id AND stato = 'bozza'";
                
                $stm = $pdo->prepare($sql_update);
                $stm->bindValue(":nom", $nome);
                $stm->bindValue(":des", $descrizione);
                $stm->bindValue(":tip", $tipo);
                $stm->bindValue(":prezzo", $prezzo_biglietto);
                $stm->bindValue(":ntot", $n_biglietti);
                $stm->bindValue(":dini", $data_inizio);
                $stm->bindValue(":dfin", $data_fine);
                $stm->bindValue(":destr", $data_estr);
                $stm->bindValue(":id", $id_lotteria);
                $stm->execute();

                // B) GESTIONE BIGLIETTI
                $sql_cancella_biglietti = "DELETE FROM biglietto WHERE id_lotteira = :id";
                $stm_canc = $pdo->prepare($sql_cancella_biglietti);
                $stm_canc->bindValue(":id", $id_lotteria);
                $stm_canc->execute();

                if ($tipo == 'esaurimento') {
                    $sql_biglietto = "INSERT INTO biglietto (numero_biglietto, prezzo_biglietto, id_lotteira) 
                                      VALUES (:numero, :costo, :id_lotteria)";
                    $stm_biglietto = $pdo->prepare($sql_biglietto);

                    for ($i = 1; $i <= $n_biglietti; $i++) {
                        $stm_biglietto->bindValue(":numero", $i);
                        $stm_biglietto->bindValue(":costo", $prezzo_biglietto);
                        $stm_biglietto->bindValue(":id_lotteria", $id_lotteria);
                        $stm_biglietto->execute();
                    }
                }

                $pdo->commit();
                $successo = "Lotteria modificata con successo!";
            } // Fine if($errore == "") interno

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errore = "Errore di Aggiornamento: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Lotteria</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f9; font-family: 'Segoe UI', sans-serif; }
        .hero { background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%); color: white; padding: 40px 20px; border-radius: 0 0 40px 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-container { background: white; border-radius: 20px; padding: 30px; margin-top: -30px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width: 800px; margin-left: auto; margin-right: auto; }
        .w3-input, .w3-select { border-radius: 8px; margin-bottom: 20px; }
        .info-label { font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; display: block; }
    </style>
</head>
<body>

    <header class="hero w3-center">
        <h1 class="w3-xlarge"><b><i class="fa fa-edit"></i> Modifica Lotteria in Bozza</b></h1>
        <p>Stai modificando la lotteria #<?php echo $id_lotteria; ?></p>
    </header>

    <div class="w3-content w3-padding-32">
        <div class="form-container">
            
            <?php if ($errore != ""): ?>
                <div class="w3-panel w3-red w3-round-large w3-padding">
                    <p><b>Errore:</b> <?php echo $errore; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($successo != ""): ?>
                <div class="w3-panel w3-green w3-round-large w3-padding w3-center">
                    <p><b>Ottimo!</b> <?php echo $successo; ?></p>
                    <a href="gestione_lotteria.php" class="w3-button w3-white w3-round w3-margin-bottom">Torna alla Lista</a>
                </div>
            <?php else: ?>
            
            <form method="POST" action="modifica.php">
                
                <input type="hidden" name="id_lotteria" value="<?php echo $id_lotteria; ?>">

                <div class="w3-row-padding" style="margin:0 -16px;">
                    <div class="w3-half">
                        <label class="info-label">Nome Lotteria</label>
                        <input class="w3-input w3-border" type="text" name="nome" value="<?php echo $nome; ?>" required>
                    </div>
                    
                    <div class="w3-half">
                        <label class="info-label">Tipo Estrazione</label>
                        <select class="w3-select w3-border" name="tipo" id="tipo_lotteria" onchange="gestisciCampi()" required>
                            <option value="data_fissa" <?php if($tipo == 'data_fissa') echo 'selected'; ?>>Data Fissa</option>
                            <option value="esaurimento" <?php if($tipo == 'esaurimento') echo 'selected'; ?>>Esaurimento</option>
                        </select>
                    </div>
                </div>

                <label class="info-label">Descrizione e Premi</label>
                <textarea class="w3-input w3-border" name="descrizione" rows="2" required><?php echo $descrizione; ?></textarea>

                <hr style="border-top: 1px dashed #ccc;">

                <div class="w3-row-padding" style="margin:0 -16px;">
                    <div class="w3-third">
                        <label class="info-label w3-text-orange">Prezzo Biglietto (Crediti)</label>
                        <input class="w3-input w3-border" type="number" name="prezzo_biglietto" value="<?php echo $prezzo_biglietto; ?>" min="1" step="1" required>
                    </div>

                    <div class="w3-third" id="blocco_biglietti" style="display:none;">
                        <label class="info-label">N. Biglietti Totali</label>
                        <input class="w3-input w3-border" type="number" name="n_biglietti_totali" id="input_biglietti" value="<?php echo $n_biglietti; ?>">
                    </div>
                </div>

                <div class="w3-row-padding" style="margin:0 -16px;">
                    <div class="w3-third">
                        <label class="info-label">Data Inizio Vendite</label>
                        <input class="w3-input w3-border" type="date" name="data_inizio_acquisti" value="<?php echo $data_inizio; ?>" min="<?php echo $oggi; ?>" required>
                    </div>
                    
                    <div class="w3-third" id="blocco_fine">
                        <label class="info-label">Data Fine Vendite</label>
                        <input class="w3-input w3-border" type="date" name="data_fine_acquisti" id="input_fine" value="<?php echo $data_fine; ?>" min="<?php echo $oggi; ?>">
                    </div>
                    
                    <div class="w3-third" id="blocco_estrazione">
                        <label class="info-label">Data Estrazione</label>
                        <input class="w3-input w3-border" type="date" name="data_estrazione" id="input_estr" value="<?php echo $data_estr; ?>" min="<?php echo $oggi; ?>">
                    </div>
                </div>

                <div class="w3-margin-top w3-center">
                    <button type="submit" class="w3-button w3-orange w3-text-white w3-round-large w3-padding-large w3-margin-right">
                        <b>Salva Modifiche</b>
                    </button>
                    <a href="gestione_lotteria.php" class="w3-button w3-light-grey w3-round-large w3-padding-large">Annulla</a>
                </div>
            </form>
            
            <?php endif; ?>
            
        </div>
    </div>

    <script>
    // Stessa logica vista nell'inserimento per mostrare/nascondere i campi
    function gestisciCampi() {
        var tipo = document.getElementById("tipo_lotteria").value;
        
        var divFine = document.getElementById("blocco_fine");
        var divEstr = document.getElementById("blocco_estrazione");
        var inputFine = document.getElementById("input_fine");
        var inputEstr = document.getElementById("input_estr");
        
        var divBiglietti = document.getElementById("blocco_biglietti");
        var inputBiglietti = document.getElementById("input_biglietti");

        if (tipo === "esaurimento") {
            divFine.style.display = "none";
            divEstr.style.display = "none";
            inputFine.required = false;
            inputEstr.required = false;
            
            divBiglietti.style.display = "block";
            inputBiglietti.required = true;
        } else {
            divFine.style.display = "block";
            divEstr.style.display = "block";
            inputFine.required = true;
            inputEstr.required = true;
            
            divBiglietti.style.display = "none";
            inputBiglietti.required = false;
        }
    }
    
    window.onload = gestisciCampi;
    </script>

</body>
</html>