<?php
require 'connessione.php';

// 1. INIZIALIZZO LE VARIABILI
$nome = "";
$descrizione = "";
$tipo = "data_fissa";
$prezzo_biglietto = ""; // NUOVO: per il costo in crediti
$n_biglietti = "";
$data_inizio = "";
$data_fine = "";
$data_estr = "";

$errore = "";
$successo = "";
$oggi = date('Y-m-d');

// 2. ELABORAZIONE DEL MODULO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recupero i dati
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

    // 3. CONTROLLI LATO SERVER
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

    // 4. SALVATAGGIO NEL DATABASE
    // 4. SALVATAGGIO NEL DATABASE
    if ($errore == "") {
        try {
            $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // --- NUOVO CONTROLLO: NOME UNIVOCO ---
            $sql_check = "SELECT COUNT(*) FROM lotteria WHERE nome = :nom";
            $stm_check = $pdo->prepare($sql_check);
            $stm_check->bindValue(":nom", $nome);
            $stm_check->execute();
            
            if ($stm_check->fetchColumn() > 0) {
                // Se il nome esiste già, genero l'errore e NON procedo col salvataggio
                $errore = "Esiste già una lotteria con questo nome. Usa un nome diverso o aggiungi l'anno (es: Pasqua 2026).";
            }

            // Se anche il controllo del nome è passato, salvo tutto
            if ($errore == "") {
                $pdo->beginTransaction();

                // A) INSERISCO LA LOTTERIA
                $sql_lotteria = "INSERT INTO lotteria 
                        (nome, descrizione, tipo, prezzo_biglietto, n_biglietti_totali, data_inizio_acquisti, data_fine_acquisti, data_estrazione, stato, incasso, erogato, n_biglietti_venduti) 
                        VALUES 
                        (:nom, :des, :tip, :prezzo, :ntot, :dini, :dfin, :destr, 'bozza', 0, 0, 0)";
                
                $stm = $pdo->prepare($sql_lotteria);
                $stm->bindValue(":nom", $nome);
                $stm->bindValue(":des", $descrizione);
                $stm->bindValue(":tip", $tipo);
                $stm->bindValue(":prezzo", $prezzo_biglietto);
                $stm->bindValue(":ntot", $n_biglietti);
                $stm->bindValue(":dini", $data_inizio);
                $stm->bindValue(":dfin", $data_fine);
                $stm->bindValue(":destr", $data_estr);
                $stm->execute();
                
                $id_nuova_lotteria = $pdo->lastInsertId();

                // B) GENERAZIONE BIGLIETTI
                if ($tipo == 'esaurimento') {
                    $sql_biglietto = "INSERT INTO biglietto (numero_biglietto, prezzo_biglietto, id_lotteira) 
                                      VALUES (:numero, :costo, :id_lotteria)";
                    $stm_biglietto = $pdo->prepare($sql_biglietto);

                    for ($i = 1; $i <= $n_biglietti; $i++) {
                        $stm_biglietto->bindValue(":numero", $i);
                        $stm_biglietto->bindValue(":costo", $prezzo_biglietto);
                        $stm_biglietto->bindValue(":id_lotteria", $id_nuova_lotteria);
                        $stm_biglietto->execute();
                    }
                }

                $pdo->commit();
                $successo = "Lotteria creata con successo in stato BOZZA!";
                
                $nome = $descrizione = $data_inizio = $data_fine = $data_estr = $n_biglietti = $prezzo_biglietto = "";
                $tipo = "data_fissa";
            } // Fine if($errore == "") interno

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errore = "Errore Database: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova Lotteria</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f9; font-family: 'Segoe UI', sans-serif; }
        .hero { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 40px 20px; border-radius: 0 0 40px 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-container { background: white; border-radius: 20px; padding: 30px; margin-top: -30px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width: 800px; margin-left: auto; margin-right: auto; }
        .w3-input, .w3-select { border-radius: 8px; margin-bottom: 20px; }
        .info-label { font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; display: block; }
    </style>
</head>
<body>

    <header class="hero w3-center">
        <h1 class="w3-xlarge"><b><i class="fa fa-plus-circle"></i> Crea Nuova Lotteria</b></h1>
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
            <?php endif; ?>
            
            <form method="POST" action="inserimento.php">
                
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
                        <label class="info-label w3-text-blue">Prezzo Biglietto (Crediti)</label>
                        <input class="w3-input w3-border" type="number" name="prezzo_biglietto" value="<?php echo $prezzo_biglietto; ?>" min="1" step="1" required>
                    </div>

                    <div class="w3-third" id="blocco_biglietti" style="display:none;">
                        <label class="info-label">N. Biglietti Totali</label>
                        <input class="w3-input w3-border" type="number" name="n_biglietti_totali" value="<?php echo $n_biglietti; ?>">
                    </div>
                </div>

                <div class="w3-row-padding" style="margin:0 -16px;">
                    <div class="w3-third">
                        <label class="info-label">Data Inizio Vendite</label>
                        <input class="w3-input w3-border" type="date" name="data_inizio_acquisti" value="<?php echo $data_inizio; ?>" min="<?php echo $oggi; ?>" required>
                    </div>
                    
                    <div class="w3-third" id="blocco_fine">
                        <label class="info-label">Data Fine Vendite</label>
                        <input class="w3-input w3-border" type="date" name="data_fine_acquisti" value="<?php echo $data_fine; ?>" min="<?php echo $oggi; ?>">
                    </div>
                    
                    <div class="w3-third" id="blocco_estrazione">
                        <label class="info-label">Data Estrazione</label>
                        <input class="w3-input w3-border" type="date" name="data_estrazione" value="<?php echo $data_estr; ?>" min="<?php echo $oggi; ?>">
                    </div>
   
                    <div class="w3-col s12" id="blocco_messaggio">
                        <label class="info-label" style="text-align: center; color: #ff9800;">Attenzione: le tre date devono essere progressive.</label>
                    </div>
                </div>

                <div class="w3-margin-top w3-center">
                    <button type="submit" class="w3-button w3-blue w3-round-large w3-padding-large w3-margin-right">
                        <b>Salva in Bozza</b>
                    </button>
                    <a href="gestione_lotteria.php" class="w3-button w3-light-grey w3-round-large w3-padding-large">Annulla</a>
                </div>
            </form>
            
        </div>
    </div>

    <script>
    function gestisciCampi() {
        var tipo = document.getElementById("tipo_lotteria").value;
        
        if (tipo === "esaurimento") {
            document.getElementById("blocco_fine").style.display = "none";
            document.getElementById("blocco_estrazione").style.display = "none";
            document.getElementById("blocco_messaggio").style.display = "none";
            document.getElementById("blocco_biglietti").style.display = "block";
        } else {
            document.getElementById("blocco_fine").style.display = "block";
            document.getElementById("blocco_estrazione").style.display = "block";
            document.getElementById("blocco_messaggio").style.display = "block";
            document.getElementById("blocco_biglietti").style.display = "none";
        }
    }
    
    window.onload = gestisciCampi;
    </script>

</body>
</html>