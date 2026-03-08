<?php
    session_start();

    require_once "funzioni.php";

    if (!isset($_SESSION["utente_id"])) {
        header("Location: index.html");
        exit;
    }

    $id_utente = $_SESSION["utente_id"];

    // — lettura filtri dalla get (così i filtri restano nell'url e sono bookmarkabili)
    $filtro_tipo     = $_GET["tipo"]      ?? "tutti";   // tutti | acquisti | vincite
    $filtro_nome     = trim($_GET["nome"] ?? "");
    $filtro_prezzo_min = $_GET["prezzo_min"] ?? "";
    $filtro_prezzo_max = $_GET["prezzo_max"] ?? "";
    $filtro_vincita_min = $_GET["vincita_min"] ?? "";
    $filtro_vincita_max = $_GET["vincita_max"] ?? "";
    $filtro_data_da  = $_GET["data_da"]   ?? "";
    $filtro_data_a   = $_GET["data_a"]    ?? "";

    $acquisti = [];
    $vincite  = [];
    $errore   = null;

    try {
        $pdo = get_pdo();

        // — query acquisti (biglietti)
        if ($filtro_tipo === "tutti" || $filtro_tipo === "acquisti") {

            $where   = ["b.id_cliente = :id", "b.data_acquisto IS NOT NULL"];
            $params  = ["id" => $id_utente];

            if ($filtro_nome !== "") {
                $where[]          = "l.nome LIKE :nome";
                $params["nome"]   = "%" . $filtro_nome . "%";
            }
            if ($filtro_prezzo_min !== "") {
                $where[]               = "b.prezzo_biglietto >= :pmin";
                $params["pmin"]        = (int) $filtro_prezzo_min;
            }
            if ($filtro_prezzo_max !== "") {
                $where[]               = "b.prezzo_biglietto <= :pmax";
                $params["pmax"]        = (int) $filtro_prezzo_max;
            }
            if ($filtro_data_da !== "") {
                $where[]               = "b.data_acquisto >= :dda";
                $params["dda"]         = $filtro_data_da;
            }
            if ($filtro_data_a !== "") {
                $where[]               = "b.data_acquisto <= :da";
                $params["da"]          = $filtro_data_a;
            }

            $sql = "SELECT b.numero_biglietto, b.prezzo_biglietto, b.data_acquisto,
                           l.nome AS nome_lotteria
                    FROM biglietto b
                    JOIN lotteria l ON l.id = b.id_lotteira
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY b.data_acquisto DESC";

            $stm = $pdo->prepare($sql);
            $stm->execute($params);
            $acquisti = $stm->fetchAll(PDO::FETCH_ASSOC);
        }

        // — query vincite
        if ($filtro_tipo === "tutti" || $filtro_tipo === "vincite") {

            $where  = ["v.id_cliente = :id"];
            $params = ["id" => $id_utente];

            if ($filtro_nome !== "") {
                $where[]        = "l.nome LIKE :nome";
                $params["nome"] = "%" . $filtro_nome . "%";
            }
            if ($filtro_vincita_min !== "") {
                $where[]            = "v.quantita >= :vmin";
                $params["vmin"]     = (int) $filtro_vincita_min;
            }
            if ($filtro_vincita_max !== "") {
                $where[]            = "v.quantita <= :vmax";
                $params["vmax"]     = (int) $filtro_vincita_max;
            }
            if ($filtro_data_da !== "") {
                $where[]        = "v.data >= :dda";
                $params["dda"]  = $filtro_data_da;
            }
            if ($filtro_data_a !== "") {
                $where[]        = "v.data <= :da";
                $params["da"]   = $filtro_data_a;
            }

            $sql = "SELECT v.data, v.quantita,
                           l.nome AS nome_lotteria
                    FROM vincita v
                    JOIN lotteria l ON l.id = v.id_lotteria
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY v.data DESC";

            $stm = $pdo->prepare($sql);
            $stm->execute($params);
            $vincite = $stm->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {
        $errore = $e->getMessage();
    }

    // converte data db in formato italiano
    function formato_data($data) {
        if (empty($data)) return "—";
        $d = DateTime::createFromFormat("Y-m-d", $data);
        return $d ? $d->format("d/m/Y") : $data;
    }

    $totale_speso   = (float) array_sum(array_column($acquisti, "prezzo_biglietto"));
    $totale_vinto   = (float) array_sum(array_column($vincite,  "quantita"));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>storico transazioni</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            padding: 40px 30px;
        }

        .intestazione { display: flex; align-items: baseline; gap: 15px; margin-bottom: 6px; }
        .intestazione h1 { font-size: 1.5rem; color: #333; }
        .nav-link { font-size: 0.85rem; color: #007BFF; text-decoration: none; margin-bottom: 25px; display: inline-block; }
        .nav-link:hover { text-decoration: underline; }

        .error { background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }

        /* riepilogo in cima */
        .riepilogo {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .riepilogo-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            flex: 1;
            min-width: 160px;
            text-align: center;
        }
        .riepilogo-card .etichetta { font-size: 0.75rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; }
        .riepilogo-card .cifra     { font-size: 1.4rem; font-weight: bold; color: #333; }
        .riepilogo-card.rosso .cifra  { color: #dc3545; }
        .riepilogo-card.verde .cifra  { color: #28a745; }
        .riepilogo-card.viola .cifra  { color: #6f42c1; }

        /* layout filtri + tabelle */
        .layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 25px;
            align-items: start;
        }

        /* pannello filtri */
        .filtri {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 22px 20px;
            position: sticky;
            top: 20px;
        }
        .filtri h3 { font-size: 0.85rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 18px; }

        .filtro-gruppo { margin-bottom: 16px; }
        .filtro-gruppo label { display: block; font-size: 0.78rem; color: #888; margin-bottom: 5px; font-weight: bold; }
        .filtro-gruppo input[type="text"],
        .filtro-gruppo input[type="number"],
        .filtro-gruppo input[type="date"] {
            width: 100%; padding: 8px 10px; border: 1.5px solid #e9ecef;
            border-radius: 7px; font-size: 0.88rem; color: #333;
        }
        .filtro-gruppo input:focus { border-color: #6f42c1; outline: none; }

        .range-wrap { display: flex; gap: 8px; }
        .range-wrap input { flex: 1; }

        /* tipo: tab buttons */
        .tipo-wrap { display: flex; gap: 6px; margin-bottom: 16px; }
        .tipo-btn {
            flex: 1; padding: 7px 4px; border: 1.5px solid #e9ecef;
            border-radius: 7px; background: #fff; cursor: pointer;
            font-size: 0.78rem; font-weight: bold; color: #888;
            text-align: center; transition: all 0.15s;
        }
        .tipo-btn.attivo { background: #6f42c1; color: #fff; border-color: #6f42c1; }

        .btn-applica {
            width: 100%; padding: 10px; background: #6f42c1; color: #fff;
            border: none; border-radius: 8px; font-weight: bold; cursor: pointer;
            font-size: 0.9rem; margin-top: 6px;
        }
        .btn-applica:hover { background: #5a32a3; }
        .btn-reset {
            width: 100%; padding: 8px; background: none; color: #aaa;
            border: none; cursor: pointer; font-size: 0.8rem; margin-top: 6px;
            text-decoration: underline;
        }
        .btn-reset:hover { color: #555; }

        /* sezione tabelle */
        .sezione-titolo {
            font-size: 0.78rem; color: #aaa; text-transform: uppercase;
            letter-spacing: 0.07em; margin-bottom: 12px; margin-top: 25px;
            display: flex; align-items: center; gap: 8px;
        }
        .sezione-titolo:first-child { margin-top: 0; }
        .badge-count {
            background: #6f42c1; color: #fff; border-radius: 20px;
            padding: 2px 9px; font-size: 0.75rem; font-weight: bold;
        }

        .card-tabella {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07); overflow: hidden;
            margin-bottom: 5px;
        }

        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        thead th {
            background: #f8f9fa; padding: 11px 14px;
            text-align: left; font-size: 0.75rem; color: #aaa;
            text-transform: uppercase; letter-spacing: 0.05em;
            border-bottom: 1px solid #f0f2f5;
        }
        tbody td { padding: 11px 14px; border-bottom: 1px solid #f8f9fa; color: #333; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #fafafa; }

        .badge-acquisto { background: #e8f4fd; color: #0077cc; border-radius: 6px; padding: 2px 8px; font-size: 0.8rem; font-weight: bold; }
        .badge-vincita  { background: #d4edda; color: #155724; border-radius: 6px; padding: 2px 8px; font-size: 0.8rem; font-weight: bold; }

        .vuoto { text-align: center; color: #bbb; padding: 30px; font-size: 0.9rem; }

        @media (max-width: 750px) {
            .layout { grid-template-columns: 1fr; }
            .filtri { position: static; }
        }
    </style>
</head>
<body>

    <div class="intestazione">
        <h1><i class="fa fa-history"></i> storico transazioni</h1>
    </div>
    <a href="profilo.php" class="nav-link">← torna al profilo</a>

    <?php if ($errore): ?>
        <div class="error"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>

    <!-- riepilogo numerico -->
    <div class="riepilogo">
        <div class="riepilogo-card viola">
            <div class="etichetta">biglietti acquistati</div>
            <div class="cifra"><?= count($acquisti) ?></div>
        </div>
        <div class="riepilogo-card rosso">
            <div class="etichetta">totale speso</div>
            <div class="cifra"><?= number_format($totale_speso, 2, ',', '.') ?></div>
        </div>
        <div class="riepilogo-card verde">
            <div class="etichetta">totale vinto</div>
            <div class="cifra"><?= number_format($totale_vinto, 2, ',', '.') ?></div>
        </div>
        <div class="riepilogo-card <?= $totale_vinto - $totale_speso >= 0 ? 'verde' : 'rosso' ?>">
            <div class="etichetta">saldo netto</div>
            <div class="cifra"><?= number_format($totale_vinto - $totale_speso, 2, ',', '.') ?></div>
        </div>
    </div>

    <div class="layout">

        <!-- pannello filtri -->
        <form method="GET" id="form_filtri">
            <div class="filtri">
                <h3><i class="fa fa-filter"></i> filtri</h3>

                <!-- tipo -->
                <div class="filtro-gruppo">
                    <label>tipo transazione</label>
                    <div class="tipo-wrap">
                        <button type="button" class="tipo-btn <?= $filtro_tipo === 'tutti'    ? 'attivo' : '' ?>" onclick="set_tipo('tutti')">tutti</button>
                        <button type="button" class="tipo-btn <?= $filtro_tipo === 'acquisti' ? 'attivo' : '' ?>" onclick="set_tipo('acquisti')">acquisti</button>
                        <button type="button" class="tipo-btn <?= $filtro_tipo === 'vincite'  ? 'attivo' : '' ?>" onclick="set_tipo('vincite')">vincite</button>
                    </div>
                    <input type="hidden" name="tipo" id="input_tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
                </div>

                <!-- nome lotteria -->
                <div class="filtro-gruppo">
                    <label>nome lotteria</label>
                    <input type="text" name="nome" placeholder="cerca lotteria..." value="<?= htmlspecialchars($filtro_nome) ?>">
                </div>

                <!-- range prezzo biglietto -->
                <div class="filtro-gruppo" id="gruppo_prezzo" <?= $filtro_tipo === 'vincite' ? 'style="display:none"' : '' ?>>
                    <label>prezzo biglietto (C)</label>
                    <div class="range-wrap">
                        <input type="number" name="prezzo_min" placeholder="min" min="0" value="<?= htmlspecialchars($filtro_prezzo_min) ?>">
                        <input type="number" name="prezzo_max" placeholder="max" min="0" value="<?= htmlspecialchars($filtro_prezzo_max) ?>">
                    </div>
                </div>

                <!-- range quantità vincita -->
                <div class="filtro-gruppo" id="gruppo_vincita" <?= $filtro_tipo === 'acquisti' ? 'style="display:none"' : '' ?>>
                    <label>quantità vincita (C)</label>
                    <div class="range-wrap">
                        <input type="number" name="vincita_min" placeholder="min" min="0" value="<?= htmlspecialchars($filtro_vincita_min) ?>">
                        <input type="number" name="vincita_max" placeholder="max" min="0" value="<?= htmlspecialchars($filtro_vincita_max) ?>">
                    </div>
                </div>

                <!-- range data -->
                <div class="filtro-gruppo">
                    <label>data (da / a)</label>
                    <div class="range-wrap">
                        <input type="date" name="data_da" value="<?= htmlspecialchars($filtro_data_da) ?>">
                        <input type="date" name="data_a"  value="<?= htmlspecialchars($filtro_data_a) ?>">
                    </div>
                </div>

                <button type="submit" class="btn-applica"><i class="fa fa-search"></i> applica filtri</button>
                <button type="button" class="btn-reset" onclick="window.location='storico.php'">azzera filtri</button>
            </div>
        </form>

        <!-- tabelle risultati -->
        <div>

            <?php if ($filtro_tipo === "tutti" || $filtro_tipo === "acquisti"): ?>
            <div class="sezione-titolo">
                <i class="fa fa-ticket-alt" style="color:#0077cc;"></i> acquisti
                <span class="badge-count"><?= count($acquisti) ?></span>
            </div>
            <div class="card-tabella">
                <?php if (empty($acquisti)): ?>
                    <div class="vuoto"><i class="fa fa-inbox"></i> nessun acquisto trovato</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>lotteria</th>
                            <th>n° biglietto</th>
                            <th>prezzo</th>
                            <th>data acquisto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acquisti as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r["nome_lotteria"]) ?></td>
                            <td><span class="badge-acquisto">#<?= htmlspecialchars($r["numero_biglietto"]) ?></span></td>
                            <td><?= number_format((float)$r["prezzo_biglietto"], 2, ',', '.') ?></td>
                            <td><?= formato_data($r["data_acquisto"]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($filtro_tipo === "tutti" || $filtro_tipo === "vincite"): ?>
            <div class="sezione-titolo">
                <i class="fa fa-trophy" style="color:#28a745;"></i> vincite
                <span class="badge-count"><?= count($vincite) ?></span>
            </div>
            <div class="card-tabella">
                <?php if (empty($vincite)): ?>
                    <div class="vuoto"><i class="fa fa-inbox"></i> nessuna vincita trovata</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>lotteria</th>
                            <th>quantità vinta</th>
                            <th>data vincita</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vincite as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r["nome_lotteria"]) ?></td>
                            <td><span class="badge-vincita"><?= number_format((float)$r["quantita"], 2, ',', '.') ?></span></td>
                            <td><?= formato_data($r["data"]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

<script>
    function set_tipo(valore) {
        document.getElementById("input_tipo").value = valore;

        // aggiorna stile tab
        document.querySelectorAll(".tipo-btn").forEach(btn => btn.classList.remove("attivo"));
        event.target.classList.add("attivo");

        // mostra/nasconde i gruppi filtro pertinenti
        const gruppo_prezzo  = document.getElementById("gruppo_prezzo");
        const gruppo_vincita = document.getElementById("gruppo_vincita");
        if (valore === "acquisti") {
            gruppo_prezzo.style.display  = "block";
            gruppo_vincita.style.display = "none";
        } else if (valore === "vincite") {
            gruppo_prezzo.style.display  = "none";
            gruppo_vincita.style.display = "block";
        } else {
            gruppo_prezzo.style.display  = "block";
            gruppo_vincita.style.display = "block";
        }
    }
</script>
</body>
</html>
