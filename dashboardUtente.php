<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(!isset($_SESSION["user_name"])) { $_SESSION["user_name"] = "Ospite"; }
require_once "funzioni.php";
require_once "connessione.php";
$pdo = get_pdo();

$ruolo = "ospite";
if (isset($_SESSION["utente_id"])) {
    $stmt_r = $pdo->prepare("SELECT ruolo FROM utente WHERE id = :id");
    $stmt_r->execute(["id" => $_SESSION["utente_id"]]);
    $ruolo = $stmt_r->fetchColumn() ?: "ospite";
}
$crediti = null;
if (isset($_SESSION["utente_id"]) && $ruolo !== "admin") {
    $stmt_c = $pdo->prepare("SELECT crediti FROM utente WHERE id = :id");
    $stmt_c->execute(["id" => $_SESSION["utente_id"]]);
    $crediti = $stmt_c->fetchColumn();
}

try {
    $stmt = $pdo->query("SELECT *, (n_biglietti_totali - n_biglietti_venduti) AS biglietti_rimasti FROM lotteria WHERE stato = 'attivo'");
    $tutte_le_lotterie = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $aperte = [];
    $programmate = [];
    $now = date('Y-m-d H:i:s');

    foreach($tutte_le_lotterie as $l) {
        if($l['data_inizio_acquisti'] > $now) {
            $programmate[] = $l;
        } else {
            $aperte[] = $l;
        }
    }
} catch (PDOException $e) { die("Errore database: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Lotterie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --bg: #f8f9fa; --text: #2b2d42; --white: #ffffff; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg); color: var(--text); margin: 0; }

        .navbar { background: var(--white); padding: 10px 5%; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .logo { font-weight: bold; font-size: 1.6rem; color: var(--primary); text-decoration: none; }
        .user-btn { background: var(--primary); color: white; padding: 10px 20px; border-radius: 50px; border: none; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        .sidebar { height: 100%; width: 0; position: fixed; z-index: 2000; top: 0; right: 0; background-color: var(--white); overflow-x: hidden; transition: 0.5s; padding-top: 60px; box-shadow: -2px 0 10px rgba(0,0,0,0.1); }
        .sidebar a { padding: 15px 25px; text-decoration: none; font-size: 1.1rem; color: var(--text); display: block; border-bottom: 1px solid #f1f1f1; }
        .sidebar a:hover { color: var(--primary); background: #f8f9ff; }
        .close-btn { position: absolute; top: 15px; right: 25px; font-size: 30px; cursor: pointer; }

        .container { padding: 40px 20px; display: flex; flex-direction: column; align-items: center; }
        h2 { width: 100%; max-width: 700px; margin-bottom: 20px; font-weight: 800; font-size: 1.6rem; text-align: left; }
        .lottery-list { display: flex; flex-direction: column; gap: 20px; width: 100%; max-width: 700px; margin-bottom: 40px; }

        .lottery-card { background: var(--white); border-radius: 15px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: 0.3s; text-decoration: none; color: inherit; border: 1px solid #edf2f7; display: block; }
        .lottery-card:hover { transform: scale(1.02); border-color: var(--primary); }

        .disabled-card { opacity: 0.8; cursor: not-allowed; pointer-events: none; border: 2px dashed #cbd5e0; background: var(--white); border-radius: 15px; padding: 25px; display: block; }

        .info-bar { display: grid; grid-template-columns: 1fr 1fr; padding-top: 15px; border-top: 1px solid #edf2f7; margin-top: 15px; }
        .stat .label { font-size: 0.7rem; color: #a0aec0; text-transform: uppercase; }
        .stat .value { font-weight: 700; font-size: 0.95rem; color: var(--text); }

        .timer { font-weight: 800; color: #d62828; }
        .badge { padding: 5px 12px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; background: #e0e7ff; color: var(--primary); }
        .badge-scheduled { padding: 5px 12px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; background: #fef9c3; color: #b45309; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="#" class="logo"><i class="fa-solid fa-ticket-simple"></i> LottoApp</a>
    <div style="display:flex; align-items:center; gap:15px;">
        <?php if($crediti !== null): ?>
            <div style="background:#f0f4ff; border:1px solid #c7d2fe; border-radius:50px; padding:8px 18px; font-weight:700; color:var(--primary); font-size:0.95rem;">
                <i class="fa-solid fa-coins"></i> <?= number_format($crediti, 0, ',', '.') ?> crediti
            </div>
        <?php endif; ?>
        <button class="user-btn" onclick="openSidebar()">
            <i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($_SESSION["user_name"]) ?>
        </button>
    </div>
</nav>

<div id="userSidebar" class="sidebar">
    <span class="close-btn" onclick="closeSidebar()">&times;</span>
    <div style="padding: 20px; font-weight: bold; color: var(--primary);">Menu Utente</div>
    <?php if($_SESSION["user_name"] !== "Ospite"): ?>
        <?php if($ruolo === "admin"): ?>
            <a href="gestione_lotteria.php"><i class="fa-solid fa-list"></i> Gestione Lotterie</a>
            <a href="inserimento.php"><i class="fa-solid fa-plus"></i> Nuova Lotteria</a>
        <?php else: ?>
            <a href="mieiBiglietti.php"><i class="fa-solid fa-ticket"></i> I miei biglietti</a>
            <a href="storico.php"><i class="fa-solid fa-exchange-alt"></i> Transazioni</a>
        <?php endif; ?>
        <a href="profilo.php"><i class="fa-solid fa-user-cog"></i> Profilo</a>
        <a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    <?php else: ?>
        <a href="index.html"><i class="fa-solid fa-sign-in-alt"></i> Login</a>
    <?php endif; ?>
</div>

<div class="container">

    <h2>Lotterie Disponibili</h2>
    <div class="lottery-list">
        <?php if(empty($aperte)): ?>
            <p style="color:#a0aec0;">Nessuna lotteria disponibile al momento.</p>
        <?php endif; ?>
        <?php foreach($aperte as $l): ?>
            <a href="infoLotteria.php?id=<?= $l['id'] ?>" class="lottery-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin-top:0"><?= htmlspecialchars($l['nome']) ?></h3>
                    <span class="badge">APERTA</span>
                </div>
                <div class="info-bar">
                    <div class="stat">
                        <span class="label">Montepremi</span><br>
                        <span class="value"><?= number_format($l['erogato'], 0, ',', '.') ?> crediti</span>
                    </div>
                    <div class="stat" style="text-align: right;">
                        <?php if($l['tipo'] === 'esaurimento'): ?>
                            <span class="label">Biglietti rimasti</span><br>
                            <span class="value" style="color: var(--primary);"><?= $l['biglietti_rimasti'] ?> / <?= $l['n_biglietti_totali'] ?></span>
                        <?php else: ?>
                            <span class="label">Estrazione tra</span><br>
                            <span class="value timer" data-date="<?= $l['data_estrazione'] ?>" data-type="estrazione">Calcolo...</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <h2>Lotterie Programmate</h2>
    <div class="lottery-list">
        <?php if(empty($programmate)): ?>
            <p style="color:#a0aec0;">Nessuna lotteria programmata.</p>
        <?php endif; ?>
        <?php foreach($programmate as $l): ?>
            <div class="disabled-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin-top:0"><?= htmlspecialchars($l['nome']) ?></h3>
                    <span class="badge-scheduled">IN ARRIVO</span>
                </div>
                <p style="color:#a0aec0; margin:5px 0 0 0;">Inizio vendite tra: <span class="timer" data-date="<?= $l['data_inizio_acquisti'] ?>" data-type="inizio">Calcolo...</span></p>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
    function openSidebar() { document.getElementById("userSidebar").style.width = "280px"; }
    function closeSidebar() { document.getElementById("userSidebar").style.width = "0"; }

    function updateTimers() {
        document.querySelectorAll('.timer').forEach(el => {
            const target = new Date(el.dataset.date.replace(' ', 'T')).getTime();
            const now = new Date().getTime();
            const diff = target - now;
            const type = el.dataset.type;

            if (diff > 0) {
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                el.innerHTML = `${days}g ${hours}h`;
            } else {
                if (type === 'estrazione') {
                    el.innerHTML = "Terminata";
                    el.style.color = "#718096";
                } else if (type === 'inizio') {
                    el.innerHTML = "In corso";
                    el.style.color = "#38a169";
                }
            }
        });
    }

    setInterval(updateTimers, 60000);
    updateTimers();
</script>

</body>
</html>