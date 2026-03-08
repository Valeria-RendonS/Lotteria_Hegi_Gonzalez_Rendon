<?php
session_start();
require_once "funzioni.php";
require_once "connessione.php";
$pdo = get_pdo();

$id_lotteria = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_lotteria) die("ID lotteria non valido.");

// Recupero dati lotteria
$sql = "SELECT *, (n_biglietti_totali - n_biglietti_venduti) AS biglietti_rimasti 
        FROM lotteria WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id_lotteria]);
$lotteria = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lotteria) die("Lotteria non trovata.");

// Recupero crediti utente
$crediti_utente = null;

if (isset($_SESSION['utente_id'])) {
    $stmt_c = $pdo->prepare("SELECT crediti FROM utente WHERE id = :id");
    $stmt_c->execute(['id' => $_SESSION['utente_id']]);
    $crediti_utente = $stmt_c->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($lotteria['nome']) ?> - LottoApp</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>

:root {
--primary:#4361ee;
--accent:#7209b7;
--bg:#f8f9fa;
--text:#2b2d42;
--white:#ffffff;
--gold:#f1c40f;
}

body{
font-family:'Inter',system-ui,sans-serif;
background-color:var(--bg);
color:var(--text);
padding:40px 20px;
}

.saldo-top{
max-width:500px;
margin:0 auto 20px auto;
background:linear-gradient(135deg,#f1c40f,#f39c12);
color:white;
padding:14px 18px;
border-radius:14px;
font-weight:700;
display:flex;
justify-content:center;
gap:10px;
align-items:center;
box-shadow:0 8px 20px rgba(0,0,0,0.1);
}

.container-info{
max-width:500px;
margin:0 auto;
background:var(--white);
padding:30px;
border-radius:24px;
box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

.back-link{
display:inline-block;
margin-bottom:20px;
color:var(--primary);
text-decoration:none;
font-size:0.9rem;
font-weight:600;
}

h1{
font-size:1.8rem;
margin:0 0 10px 0;
}

.badge{
display:inline-block;
padding:4px 12px;
background:#e0e7ff;
color:var(--primary);
border-radius:20px;
font-size:0.75rem;
font-weight:700;
text-transform:uppercase;
margin-bottom:20px;
}

.stats-grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:15px;
margin:20px 0;
}

.stat-card{
background:#f8fafc;
padding:15px;
border-radius:16px;
border:1px solid #e2e8f0;
text-align:center;
}

.stat-value{
display:block;
font-size:1.2rem;
font-weight:700;
color:var(--accent);
}

.stat-label{
font-size:0.7rem;
color:#64748b;
text-transform:uppercase;
letter-spacing:0.5px;
}

.btn-buy{
background:var(--primary);
color:white;
border:none;
padding:16px;
width:100%;
border-radius:12px;
font-size:1rem;
font-weight:600;
cursor:pointer;
transition:transform 0.2s;
}

.btn-buy:hover{
transform:translateY(-2px);
}

.btn-buy:disabled{
background:#94a3b8;
cursor:not-allowed;
}

.login-alert{
background:#fff3cd;
color:#856404;
padding:15px;
border-radius:12px;
text-align:center;
font-size:0.9rem;
}

.costo-box{
margin:15px 0;
font-weight:600;
color:#334155;
}

.warning{
color:#dc2626;
font-weight:600;
margin-top:10px;
display:none;
}

</style>
</head>

<body>

<?php if($crediti_utente !== null): ?>
<div class="saldo-top">
<i class="fa-solid fa-coins"></i>
Crediti disponibili: <strong><?= number_format($crediti_utente,0,',','.') ?></strong>
</div>
<?php endif; ?>

<div class="container-info">

<a href="dashboardUtente.php" class="back-link">
<i class="fa-solid fa-arrow-left"></i> Torna alla dashboard
</a>

<h1><?= htmlspecialchars($lotteria['nome']) ?></h1>
<span class="badge"><?= str_replace('_',' ',$lotteria['tipo']) ?></span>

<p style="color:#475569;line-height:1.6;">
<?= htmlspecialchars($lotteria['descrizione']) ?>
</p>

<div class="stats-grid">

<div class="stat-card">
<span class="stat-value"><?= number_format($lotteria['prezzo_biglietto'],0) ?></span>
<span class="stat-label">Crediti per biglietto</span>
</div>

</div>

<div class="stat-card" style="margin-bottom:20px;">

<?php if($lotteria['tipo'] === 'data_fissa'): ?>

<i class="fa-regular fa-clock"></i>
<span class="stat-label">Estrazione tra:</span>

<div id="countdown" style="font-weight:700;font-size:1.1rem;color:var(--primary);">
Calcolo...
</div>

<?php else: ?>

<i class="fa-solid fa-ticket"></i>
<span class="stat-label">Modalità:</span>

<div style="font-weight:700;color:#4361ee;">
A esaurimento biglietti
</div>

<?php endif; ?>

</div>

<?php if(isset($_SESSION['utente_id'])): ?>

<form action="processaAcquisto.php" method="POST" id="formAcquisto">

<input type="hidden" name="id_lotteria" value="<?= $lotteria['id'] ?>">

<div>

<label>Quantità:</label>

<input
type="number"
name="quantita"
id="quantita"
min="1"
value="1"
style="padding:10px;border-radius:8px;border:1px solid #ddd;width:70px;"
>

</div>

<div class="costo-box">
Costo totale: <span id="costoTotale">0</span> crediti
</div>

<div class="warning" id="warningCrediti">
Crediti insufficienti
</div>

<button type="submit" class="btn-buy" id="btnCompra">
<i class="fa-solid fa-cart-shopping"></i> Acquista ora
</button>

</form>

<?php else: ?>

<div class="login-alert">
Effettua il <a href="index.html">login</a> per partecipare.
</div>

<?php endif; ?>

</div>

<script>

const prezzo = <?= $lotteria['prezzo_biglietto'] ?>;
const crediti = <?= $crediti_utente ?? 0 ?>;

const quantitaInput = document.getElementById("quantita");
const costoTotale = document.getElementById("costoTotale");
const btnCompra = document.getElementById("btnCompra");
const warning = document.getElementById("warningCrediti");

function aggiornaCosto(){

let q = parseInt(quantitaInput.value);
let totale = q * prezzo;

costoTotale.innerText = totale;

if(totale > crediti){

warning.style.display="block";
btnCompra.disabled=true;

}else{

warning.style.display="none";
btnCompra.disabled=false;

}

}

if(quantitaInput){
quantitaInput.addEventListener("input",aggiornaCosto);
aggiornaCosto();
}

<?php if($lotteria['tipo'] === 'data_fissa' && !empty($lotteria['data_estrazione'])): ?>

function updateCountdown(){

const estrazione = new Date("<?= $lotteria['data_estrazione'] ?>").getTime();
const diff = estrazione - new Date().getTime();

if(diff <= 0){

document.getElementById("countdown").innerHTML="In corso!";

}else{

const d = Math.floor(diff/(1000*60*60*24));
const h = Math.floor((diff%(1000*60*60*24))/(1000*60*60));

document.getElementById("countdown").innerHTML = d+"g "+h+"h rimanenti";

}

}

setInterval(updateCountdown,60000);
updateCountdown();

<?php endif; ?>

</script>

</body>
</html>