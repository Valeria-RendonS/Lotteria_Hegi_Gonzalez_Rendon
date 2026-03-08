<?php
require 'connessione.php';

// --- 1. GESTIONE PAGINAZIONE SEMPLICE ---
$per_pagina = 10;
if (isset($_GET['pag'])) {
    $pagina = $_GET['pag'];
} else {
    $pagina = 1;
}
$offset = ($pagina - 1) * $per_pagina;

// --- 2. RECUPERO FILTRI DALL'URL ---
$search = isset($_GET['search']) ? $_GET['search'] : '';
$stato_filtro = isset($_GET['stato']) ? $_GET['stato'] : '';
$prezzo_min = isset($_GET['prezzo_min']) ? $_GET['prezzo_min'] : '';
$prezzo_max = isset($_GET['prezzo_max']) ? $_GET['prezzo_max'] : '';


// --- 3. IL MOTORE INVISIBILE: AGGIORNAMENTO STATI ED ESTRAZIONI AUTOMATICHE ---
try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // A. Passaggio da Bozza ad Attivo (Se scatta la data di inizio)
    $sql_diventa_attivo = "UPDATE lotteria SET stato = 'attivo' 
                           WHERE stato = 'bozza' 
                           AND data_inizio_acquisti <= CURDATE() 
                           AND (data_fine_acquisti >= CURDATE() OR data_fine_acquisti IS NULL)";
    $pdo->query($sql_diventa_attivo);

    // B. Chiusura vendite (Se passa la data di fine per quelle a data fissa)
    $sql_diventa_finito = "UPDATE lotteria SET stato = 'finito' 
                           WHERE stato = 'attivo' 
                           AND data_fine_acquisti < CURDATE()";
    $pdo->query($sql_diventa_finito);

    // C. IL MOTORE DI ESTRAZIONE AUTOMATICA (La magia)
    // Cerchiamo le lotterie senza vincitore che rispettano le condizioni di fine
    $sql_da_estrarre = "SELECT * FROM lotteria 
                        WHERE biglietto_vincente IS NULL 
                        AND (
                            (tipo = 'data_fissa' AND data_estrazione <= CURDATE())
                            OR 
                            (tipo = 'esaurimento' AND n_biglietti_venduti >= n_biglietti_totali AND n_biglietti_totali > 0)
                        )";
    $stm_da_estrarre = $pdo->query($sql_da_estrarre);
    $lotterie_da_estrarre = $stm_da_estrarre->fetchAll(PDO::FETCH_ASSOC);

    // Facciamo un ciclo su tutte le lotterie pronte per l'estrazione
    foreach ($lotterie_da_estrarre as $lott) {
        
        // C1. CASO NORMALE: Sono stati venduti dei biglietti
        if ($lott['n_biglietti_venduti'] > 0) {
            $pdo->beginTransaction();
            try {
                // 1. Pesco un vincitore a caso tra chi ha comprato
                $sql_vinc = "SELECT id, id_cliente FROM biglietto WHERE id_lotteira = :idl AND id_cliente IS NOT NULL ORDER BY RAND() LIMIT 1";
                $stm_vinc = $pdo->prepare($sql_vinc);
                $stm_vinc->execute([':idl' => $lott['id']]);
                $vincitore = $stm_vinc->fetch(PDO::FETCH_ASSOC);

                if ($vincitore) {
                    // 2. Matematica dei crediti
                    $incasso_totale = $lott['n_biglietti_venduti'] * $lott['prezzo_biglietto'];
                    $premio = ceil($incasso_totale * 0.85);
                    $cassa_admin = $incasso_totale - $premio; // Il restante 15%

                    // 3. Pagamento Utente e Admin
                    $pdo->prepare("UPDATE utente SET crediti = crediti + :p WHERE id = :idc")
                        ->execute([':p' => $premio, ':idc' => $vincitore['id_cliente']]);
                    $pdo->prepare("UPDATE utente SET crediti = crediti + :c WHERE id = :ida")
                        ->execute([':c' => $cassa_admin, ':ida' => $lott['id_admin']]);

                    // 4. Registro la vincita nello storico
                    $pdo->prepare("INSERT INTO vincita (data, quantita, id_lotteria, id_cliente) VALUES (CURDATE(), :q, :idl, :idc)")
                        ->execute([':q' => $premio, ':idl' => $lott['id'], ':idc' => $vincitore['id_cliente']]);

                    // 5. Salvo i dati definitivi sulla Lotteria (e forzo lo stato a 'finito' in caso di esaurimento)
                    $pdo->prepare("UPDATE lotteria SET biglietto_vincente = :idb, incasso = :inc, erogato = :er, data_accredito = CURDATE(), stato = 'finito' WHERE id = :idl")
                        ->execute([':idb' => $vincitore['id'], ':inc' => $incasso_totale, ':er' => $premio, ':idl' => $lott['id']]);
                }
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                // Saltiamo l'errore per non bloccare l'interfaccia, lo riproverà al prossimo caricamento
            }
        } 
        // C2. CASO TRISTE: Nessuno ha comprato biglietti
        else {
            // Chiudiamo la lotteria senza vincitore e forziamo la data di accredito per non farla elaborare di nuovo
            $pdo->prepare("UPDATE lotteria SET stato = 'finito', data_accredito = CURDATE() WHERE id = :idl")
                ->execute([':idl' => $lott['id']]);
        }
    }

} catch (PDOException $e) {
    echo "Errore aggiornamento stati: " . $e->getMessage();
}


// --- 4. COSTRUZIONE QUERY PER LA TABELLA (Invariata) ---
$sql = "SELECT * FROM lotteria WHERE 1=1";
$sql_count = "SELECT COUNT(*) FROM lotteria WHERE 1=1"; 

if ($search != '') {
    $stringa_ricerca = " AND (nome LIKE '%$search%' OR descrizione LIKE '%$search%')";
    $sql .= $stringa_ricerca;
    $sql_count .= $stringa_ricerca;
}
if ($stato_filtro != '') {
    $stringa_stato = " AND stato = '$stato_filtro'";
    $sql .= $stringa_stato;
    $sql_count .= $stringa_stato;
}
if ($prezzo_min != '') {
    $stringa_min = " AND prezzo_biglietto >= $prezzo_min";
    $sql .= $stringa_min;
    $sql_count .= $stringa_min;
}
if ($prezzo_max != '') {
    $stringa_max = " AND prezzo_biglietto <= $prezzo_max";
    $sql .= $stringa_max;
    $sql_count .= $stringa_max;
}

$sql .= " ORDER BY id DESC LIMIT $per_pagina OFFSET $offset";

// ESECUZIONE QUERY
try {
    $pdo = new PDO($conn_str, $conn_usr, $conn_psw);
    $stm = $pdo->prepare($sql);
    $stm->execute();
    $risultati = $stm->fetchAll(PDO::FETCH_ASSOC);

    $stm_count = $pdo->prepare($sql_count);
    $stm_count->execute();
    $numero_righe_totali = $stm_count->fetchColumn();
    $pagine_totali = ($numero_righe_totali > 0) ? ceil($numero_righe_totali / $per_pagina) : 1;

} catch (PDOException $e) {
    echo "Errore Database: " . $e->getMessage();
}

function linkPagina($num_pagina, $search, $stato, $p_min, $p_max) {
    return "gestione_lotteria.php?pag=$num_pagina&search=$search&stato=$stato&prezzo_min=$p_min&prezzo_max=$p_max";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Lotterie</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --blue-dark: #1e3a8a; --blue-light: #3b82f6; }
        body { background-color: #f4f7f9; font-family: 'Segoe UI', sans-serif; }
        .hero { background: linear-gradient(135deg, var(--blue-dark) 0%, var(--blue-light) 100%); color: white; padding: 60px 20px; border-radius: 0 0 40px 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card-container { background: white; border-radius: 20px; padding: 25px; margin-top: -40px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .status { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .bg-bozza { background: #fff3e0; color: #ff9800; }
        .bg-attivo { background: #e8f5e9; color: #4caf50; }
        .bg-finito { background: #ffebee; color: #f44336; }
        .btn-modifica { color: #3b82f6; text-decoration: none; font-weight: bold; }
        .btn-elimina { color: #f44336; text-decoration: none; margin-left: 10px; }
        .info-label { font-size: 10px; color: #888; text-transform: uppercase; font-weight: bold; }
    </style>
</head>
<body>

    <header class="hero w3-center">
        <a href="dashboardUtente.php" style="display:inline-block;margin-bottom:15px;color:rgba(255,255,255,0.8);font-size:0.9rem;text-decoration:none;"><i class="fa fa-arrow-left"></i> torna alla dashboard</a>
        <h1 class="w3-xxlarge"><b><i class="fa fa-ticket"></i> Gestione Lotterie</b></h1>
        <p class="w3-opacity">Pannello di controllo amministratore</p>
    </header>

    <div class="w3-content" style="max-width:1200px">
        
        <div class="w3-container card-container w3-white">
            <form method="GET" class="w3-row-padding">
                <div class="w3-col m3 s12">
                    <label class="w3-small w3-text-grey">Cerca Testo</label>
                    <input class="w3-input w3-border w3-round-large" type="text" name="search" value="<?php echo $search; ?>" placeholder="Nome o desc...">
                </div>
                <div class="w3-col m2 s6">
                    <label class="w3-small w3-text-grey">Costo Min</label>
                    <input class="w3-input w3-border w3-round-large" type="number" name="prezzo_min" value="<?php echo $prezzo_min; ?>">
                </div>
                <div class="w3-col m2 s6">
                    <label class="w3-small w3-text-grey">Costo Max</label>
                    <input class="w3-input w3-border w3-round-large" type="number" name="prezzo_max" value="<?php echo $prezzo_max; ?>">
                </div>
                <div class="w3-col m3 s12">
                    <label class="w3-small w3-text-grey">Stato</label>
                    <select name="stato" class="w3-select w3-border w3-round-large">
                        <option value="">Tutti gli stati</option>
                        <option value="bozza" <?php if($stato_filtro=='bozza') echo 'selected'; ?>>Bozza</option>
                        <option value="attivo" <?php if($stato_filtro=='attivo') echo 'selected'; ?>>Attivo</option>
                        <option value="finito" <?php if($stato_filtro=='finito') echo 'selected'; ?>>Finito</option>
                    </select>
                </div>
                <div class="w3-col m2 s12 w3-margin-top">
                    <button type="submit" class="w3-button w3-blue w3-round-large" title="Applica Filtri">
                        <i class="fa fa-search"></i>
                    </button>
                    <a href="gestione_lotteria.php" class="w3-button w3-light-grey w3-round-large" title="Azzera Filtri">
                        <i class="fa fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="w3-container w3-margin-top w3-padding-32">
            
            <div class="w3-row w3-margin-bottom">
                <div class="w3-col s6">
                    <h3 class="w3-large"><b>Elenco Lotterie</b></h3>
                </div>
                <div class="w3-col s6 w3-right-align">
                    <span class="w3-text-grey w3-small">Elementi trovati: <b><?php echo $numero_righe_totali; ?></b></span>
                </div>
            </div>

            <div class="w3-white w3-card w3-round-xlarge w3-responsive">
                <table class="w3-table w3-striped w3-bordered w3-hoverable w3-small">
                    <thead>
                        <tr class="w3-light-grey">
                            <th>NOME & INFO</th>
                            <th>COSTO / TIPO</th>
                            <th>FINANZE (Crediti)</th>
                            <th>DATE</th>
                            <th>STATO</th>
                            <th class="w3-center">AZIONI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($risultati) == 0): ?>
                            <tr><td colspan="6" class="w3-center w3-padding-32">Nessuna lotteria trovata.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($risultati as $r): ?>
                        <tr>
                            <td>
                                <b><?php echo $r['nome']; ?></b><br>
                                <span class="w3-text-grey w3-tiny"><?php echo $r['descrizione']; ?></span>
                            </td>
                            
                            <td>
                                <span class="info-label w3-text-blue">Prezzo Biglietto:</span> <?php echo $r['prezzo_biglietto']; ?> C<br><br>
                                <span class="w3-tag w3-light-grey w3-round w3-tiny"><?php echo $r['tipo']; ?></span>
                            </td>
                            
                            <td>
                                <?php 
                                $incasso_reale = $r['n_biglietti_venduti'] * $r['prezzo_biglietto'];
                                $premio_stimato = ceil($incasso_reale * 0.85);
                                $gestore_stimato = $incasso_reale - $premio_stimato;
                                ?>
                                <span class="info-label">B venduti/tot:</span> <?php echo $r['n_biglietti_venduti']; ?> / <?php echo $r['n_biglietti_totali']; ?> <br>
                                <span class="info-label w3-text-blue">Incasso:</span> <?php echo $incasso_reale; ?> <br>
                                
                                <?php if(empty($r['biglietto_vincente']) && $r['data_accredito'] == null): ?>
                                    <span class="info-label w3-text-green">Stima Premio:</span> <?php echo $premio_stimato; ?> <br>
                                    <span class="info-label w3-text-orange">Stima Gestore:</span> <?php echo $gestore_stimato; ?>
                                <?php elseif($r['n_biglietti_venduti'] == 0): ?>
                                    <span class="info-label w3-text-red">Nessun Incasso</span>
                                <?php else: ?>
                                    <span class="info-label w3-text-green">Erogato Vincitore:</span> <?php echo $r['erogato']; ?> <br>
                                    <span class="info-label">Vincitore:</span> B. #<?php echo $r['biglietto_vincente']; ?>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span class="info-label">Inizio:</span> <?php echo date_format(date_create($r['data_inizio_acquisti']), 'd/m/Y'); ?> <br>
                                <span class="info-label">Fine:</span> <?php echo $r['data_fine_acquisti'] ? date_format(date_create($r['data_fine_acquisti']), 'd/m/Y') : '---'; ?> <br>
                                <span class="info-label">Estraz:</span> <?php echo $r['data_estrazione'] ? date_format(date_create($r['data_estrazione']), 'd/m/Y') : '---'; ?>
                            </td>
                            
                            <td>
                                <?php 
                                    if($r['stato'] == 'bozza') echo '<span class="status bg-bozza">Bozza</span>';
                                    if($r['stato'] == 'attivo') echo '<span class="status bg-attivo">Attivo</span>';
                                    if($r['stato'] == 'finito') echo '<span class="status bg-finito">Finito</span>';
                                ?>
                            </td>
                            
                            <td class="w3-center">
                                <?php if($r['stato'] == 'bozza'): ?>
                                    <a href="modifica.php?id=<?php echo $r['id']; ?>" class="btn-modifica">
                                        <i class="fa fa-edit"></i> Modifica
                                    </a>
                                    <br><br>
                                    <a href="elimina.php?id=<?php echo $r['id']; ?>" class="btn-elimina" onclick="return confirm('Eliminare questa lotteria?')">
                                        <i class="fa fa-trash"></i> Elimina
                                    </a>
                                <?php else: ?>
                                    <span class="w3-text-grey w3-small"><i class="fa fa-lock"></i> Bloccata</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="w3-center w3-margin-top w3-padding-16">
                <div class="w3-bar w3-border w3-round-large w3-white">
                    <?php 
                    for ($i = 1; $i <= $pagine_totali; $i++) {
                        $link = linkPagina($i, $search, $stato_filtro, $prezzo_min, $prezzo_max);
                        if ($i == $pagina) {
                            echo "<a href='$link' class='w3-bar-item w3-button w3-blue'>$i</a>";
                        } else {
                            echo "<a href='$link' class='w3-bar-item w3-button'>$i</a>";
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="w3-padding-16">
                <a href="inserimento.php" class="w3-button w3-blue w3-block w3-round-xxlarge w3-xlarge w3-card-4">
                    <i class="fa fa-plus-circle"></i> <b>CREA NUOVA LOTTERIA</b>
                </a>
            </div>
        </div>
    </div>

</body>
</html>