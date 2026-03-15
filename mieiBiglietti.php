<?php
session_start();
// impedisce al browser di mostrare la pagina dalla cache dopo il logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once "funzioni.php";
require_once "connessione.php";
$pdo = get_pdo();

// Verifica se l'utente è loggato
if (!isset($_SESSION['utente_id'])) {
    header("Location: index.html");
    exit();
}

$id_utente = $_SESSION['utente_id'];

$sql = "SELECT b.numero_biglietto, l.nome AS nome_lotteria, l.data_estrazione, l.tipo 
        FROM biglietto b
        JOIN lotteria l ON b.id_lotteria = l.id
        WHERE b.id_cliente = :id_cliente";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id_cliente' => $id_utente]);
$biglietti = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I miei biglietti</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --bg: #f8f9fa; --text: #2b2d42; --white: #ffffff; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg); color: var(--text); margin: 0; }
        
        .navbar { background: var(--white); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .logo { font-weight: bold; font-size: 1.6rem; color: var(--primary); text-decoration: none; }
        
        /* Contenitore tasti destra */
        .nav-right { display: flex; align-items: center; gap: 10px; }
        .home-btn { background: #e9ecef; color: var(--text); padding: 10px 15px; border-radius: 50px; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
        .user-btn { background: var(--primary); color: white; padding: 10px 20px; border-radius: 50px; border: none; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        
        .sidebar { height: 100%; width: 0; position: fixed; z-index: 2000; top: 0; right: 0; background-color: var(--white); overflow-x: hidden; transition: 0.5s; padding-top: 60px; box-shadow: -2px 0 10px rgba(0,0,0,0.1); }
        .sidebar a { padding: 15px 25px; text-decoration: none; font-size: 1.1rem; color: var(--text); display: block; border-bottom: 1px solid #f9f9f9; }
        .sidebar a:hover { color: var(--primary); background: #f8f9ff; }
        .close-btn { position: absolute; top: 15px; right: 25px; font-size: 30px; cursor: pointer; }

        .container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; padding: 20px; margin-bottom: 15px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-left: 5px solid #4361ee; }
        .timer { font-weight: bold; color: #4361ee; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboardUtente.php" class="logo"><i class="fa-solid fa-ticket-simple"></i> LottoApp</a>
    <div class="nav-right">
        <a href="dashboardUtente.php" class="home-btn"><i class="fa-solid fa-house"></i> Home</a>
        <button class="user-btn" onclick="openSidebar()">
            <i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($_SESSION["user_name"]) ?>
        </button>
    </div>
</nav>

<div id="userSidebar" class="sidebar">
    <span class="close-btn" onclick="closeSidebar()">&times;</span>
    <div style="padding: 20px; font-weight: bold; color: var(--primary);">Menu Utente</div>
    <a href="storico.php"><i class="fa-solid fa-exchange-alt"></i> Transazioni</a>
    <hr>
    <a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <h1><i class="fa-solid fa-ticket"></i> I miei biglietti</h1>
    
    <div style="margin-top:20px;">
        <?php if(empty($biglietti)): ?>
            <p>Non hai ancora acquistato alcun biglietto.</p>
        <?php else: ?>
            <?php foreach($biglietti as $b): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($b['nome_lotteria']) ?></h3>
                    <p>Numero Biglietto: <strong>#<?= $b['numero_biglietto'] ?></strong></p>
                    <p>Manca all'estrazione: 
                        <?php if($b['tipo'] === 'esaurimento'): ?>
                            <span class="info-text">Lotteria a esaurimento</span>
                        <?php else: ?>
                            <span class="timer" data-date="<?= htmlspecialchars($b['data_estrazione']) ?>">Calcolo...</span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    function openSidebar() { document.getElementById("userSidebar").style.width = "280px"; }
    function closeSidebar() { document.getElementById("userSidebar").style.width = "0"; }

    function updateTimers() {
        document.querySelectorAll('.timer').forEach(el => {
            const targetDate = new Date(el.dataset.date.replace(' ', 'T')).getTime();
            const now = new Date().getTime();
            const diff = targetDate - now;
            if (diff <= 0) { el.innerHTML = "Estrazione terminata"; }
            else {
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                el.innerHTML = `${days}g ${hours}h`;
            }
        });
    }
    setInterval(updateTimers, 60000);
    updateTimers();
</script>

</body>
</html>