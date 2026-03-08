<?php
session_start();
require_once "funzioni.php";
require_once "connessione.php";
$pdo = get_pdo();

if (!isset($_SESSION['utente_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Accesso negato.");
}

$id_utente = $_SESSION['utente_id'];
$id_lotteria = intval($_POST['id_lotteria']);
$quantita = intval($_POST['quantita']);

try {
    $pdo->beginTransaction();

    // 1. Recupero dati Utente (con FOR UPDATE per bloccare la riga)
    $stmt_u = $pdo->prepare("SELECT crediti FROM utente WHERE id = :id FOR UPDATE");
    $stmt_u->execute(['id' => $id_utente]);
    $u = $stmt_u->fetch(PDO::FETCH_ASSOC);

    // 2. Recupero dati Lotteria (con FOR UPDATE)
    $stmt_l = $pdo->prepare("SELECT * FROM lotteria WHERE id = :id FOR UPDATE");
    $stmt_l->execute(['id' => $id_lotteria]);
    $l = $stmt_l->fetch(PDO::FETCH_ASSOC);

    // Verifiche di integrità
    if (!$l) throw new Exception("Lotteria non trovata.");
    if ($l['stato'] !== 'attivo') throw new Exception("La lotteria non è al momento attiva.");
    
    $prezzo_unitario = $l['prezzo_biglietto'];
    $totale_spesa = $quantita * $prezzo_unitario;

    // 3. Controllo crediti
    if ($u['crediti'] < $totale_spesa) {
        throw new Exception("Crediti insufficienti. Saldo: " . $u['crediti'] . " - Richiesti: " . $totale_spesa);
    }

    // 4. Calcoli finanziari
    $nuovo_incasso = $l['incasso'] + $totale_spesa;
    $nuovi_venduti = $l['n_biglietti_venduti'] + $quantita;

    if ($l['tipo'] === 'data_fissa') {
        $nuovo_erogato = ceil($nuovo_incasso * 0.85);
    } else {
        $valore_totale_potenziale = $l['n_biglietti_totali'] * $prezzo_unitario;
        $nuovo_erogato = ceil($valore_totale_potenziale * 0.85);
    }

    // 5. Esegui aggiornamenti (DB)
    $pdo->prepare("UPDATE utente SET crediti = crediti - :spesa WHERE id = :id")
        ->execute(['spesa' => $totale_spesa, 'id' => $id_utente]);

    $pdo->prepare("UPDATE lotteria SET incasso = :incasso, erogato = :erogato, n_biglietti_venduti = :venduti WHERE id = :id")
        ->execute([
            'incasso' => $nuovo_incasso,
            'erogato' => $nuovo_erogato,
            'venduti' => $nuovi_venduti,
            'id' => $id_lotteria
        ]);

    // 6. Inserimento Biglietti con NOW()
    // Assicurati che 'data_acquisto' in tabella sia tipo DATETIME
    $sql_big = "INSERT INTO biglietto (numero_biglietto, prezzo_biglietto, data_acquisto, id_lotteira, id_cliente) 
                VALUES (?, ?, NOW(), ?, ?)";
    $stmt_big = $pdo->prepare($sql_big);
    
    for ($i = 0; $i < $quantita; $i++) {
        $num_biglietto = $l['n_biglietti_venduti'] + $i + 1;
        $stmt_big->execute([$num_biglietto, $prezzo_unitario, $id_lotteria, $_SESSION['utente_id']]);
    }

    $pdo->commit();
    header("Location: mieiBiglietti.php?success=1");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Errore nell'acquisto: " . $e->getMessage());
}
?>