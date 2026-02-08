<?php
session_start();
// definitions du fuseaux horaires 
date_default_timezone_set('Africa/Douala'); 

require_once '../config/database.php'; 

// requettes paramettrées 
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$pdo = Database::getConnection();

//  RECUPERATION CONFIGURATION
$stmtC = $pdo->query("SELECT * FROM cercles LIMIT 1");
$config = $stmtC->fetch();

// Si pas de config, on crée une par défaut
if (!$config) {
    $pdo->query("INSERT INTO cercles (nom_cercle, frequence, montant_cotisation_standard, type_tirage) VALUES ('Cercle Principal', 'mensuel', 10000, 'aleatoire')");
    $config = $pdo->query("SELECT * FROM cercles LIMIT 1")->fetch();
}
$cercle_id = $config['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_config'])) {
        $nouveau_montant = $_POST['montant_cotisation'];
        $nouvelle_frequence = $_POST['frequence']; 
        $nouveau_type_tirage = $_POST['type_tirage']; 
        $intervalle = ($nouvelle_frequence === 'libre') ? (int)$_POST['intervalle_libre'] : null;
        $valid_freq = ['mensuel', 'hebdomadaire', 'libre'];
        $valid_tirage = ['aleatoire', 'manuel', 'ordre_inscription'];

        if(in_array($nouvelle_frequence, $valid_freq) && in_array($nouveau_type_tirage, $valid_tirage)) {
            try {
                $pdo->beginTransaction();
                $sql = "UPDATE cercles SET frequence = ?, montant_cotisation_standard = ?, intervalle_libre = ?, type_tirage = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$nouvelle_frequence, $nouveau_montant, $intervalle, $nouveau_type_tirage, $cercle_id]);
                $date_maj = new DateTime(); 
                if ($nouvelle_frequence == 'mensuel') {
                    $date_maj->modify('+1 month');
                } elseif ($nouvelle_frequence == 'hebdomadaire') {
                    $date_maj->modify('+7 days');
                } elseif ($nouvelle_frequence == 'libre' && $intervalle > 0) {
                    $date_maj->modify('+' . $intervalle . ' days');
                } else {
                    $date_maj->modify('+1 month'); 
                }
                $nouvelle_date_seance = $date_maj->format('Y-m-d');
                $sqlUpdateSeance = "UPDATE seances SET montant_cotisation_fixe = ?, date_seance = ? WHERE cercle_id = ? AND statut = 'prevue'";
                $pdo->prepare($sqlUpdateSeance)->execute([$nouveau_montant, $nouvelle_date_seance, $cercle_id]);
                $pdo->commit();
                $config = $pdo->query("SELECT * FROM cercles WHERE id = $cercle_id")->fetch(); 
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
    if (isset($_POST['creer_seance'])) {
        $date_fin = new DateTime(); 
        if ($config['frequence'] == 'mensuel') {
            $date_fin->modify('+1 month');
        } elseif ($config['frequence'] == 'hebdomadaire') {
            $date_fin->modify('+7 days');
        } elseif ($config['frequence'] == 'libre' && $config['intervalle_libre'] > 0) {
            $date_fin->modify('+' . $config['intervalle_libre'] . ' days');
        } else {
            $date_fin->modify('+1 month');
        }
        $d_seance = $date_fin->format('Y-m-d');
        $h_limite = $date_fin->format('H:i:s'); 
        if(isset($_POST['heure_limite']) && !empty($_POST['heure_limite'])) {
            $h_limite = $_POST['heure_limite'] . ':00';
        }
        try {
            $pdo->beginTransaction();
            $sqlS = "INSERT INTO seances (cercle_id, date_seance, heure_limite_pointage, statut, montant_cotisation_fixe) VALUES (?, ?, ?, 'prevue', ?)";
            $pdo->prepare($sqlS)->execute([$cercle_id, $d_seance, $h_limite, $_POST['montant_seance']]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

$stmtNext = $pdo->prepare("SELECT * FROM seances WHERE cercle_id = ? AND statut = 'prevue' ORDER BY id DESC LIMIT 1");
$stmtNext->execute([$cercle_id]);
$prochaineSeance = $stmtNext->fetch();
$secondes_restantes = 0;
if ($prochaineSeance) {
    $date_propre = date('Y-m-d', strtotime($prochaineSeance['date_seance']));
    $heure_propre = $prochaineSeance['heure_limite_pointage'];
    $timestamp_fin = strtotime("$date_propre $heure_propre");
    $timestamp_now = time();
    $secondes_restantes = $timestamp_fin - $timestamp_now;
    if ($secondes_restantes < 0) $secondes_restantes = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | NDJANGUI ELITE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981; /* Vert Émeraude */
            --primary-dark: #064e3b;
            --secondary: #3b82f6;
            --dark-bg: #0f172a;
            --glass: rgba(255, 255, 255, 0.95);
            --input-bg: #f8fafc;
        }

        body {
            background-color: #f1f5f9;
            background-image: radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.05) 0px, transparent 50%), 
                              radial-gradient(at 100% 100%, rgba(79, 70, 229, 0.05) 0px, transparent 50%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
            min-height: 100vh;
        }

        /* Navbar */
        .admin-header {
            background: var(--glass);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Cartes Premium (Utilisé pour le Timer et le Formulaire) */
        .premium-card {
            background: white;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,1);
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* Styles spécifiques au Timer (Conservés) */
        .timer-display-container {
            background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);
            border-radius: 24px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            border: 4px solid rgba(16, 185, 129, 0.1);
        }
        .timer-display-container::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(251, 191, 36, 0.1) 0%, transparent 60%);
        }
        .timer-unit {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1rem;
            min-width: 75px;
        }
        .timer-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            font-weight: 800;
            color: white;
            text-shadow: 0 0 20px rgba(16, 185, 129, 0.5);
        }
        .timer-label {
            font-size: 0.6rem; text-transform: uppercase; color: #10b981; font-weight: 800; letter-spacing: 1.5px;
        }

        /* Styles améliorés pour le Formulaire */
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select, .input-group-text {
            border-color: #e2e8f0;
            background-color: var(--input-bg);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: #334155;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-group-text {
            background-color: white;
            border-right: none;
            color: var(--primary);
            padding-right: 0.5rem;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            color: #0f172a;
        }

        .input-group .form-control {
            border-left: none;
            padding-left: 0.5rem;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--primary);
            background-color: white;
        }

        /* Boutons */
        .btn-premium {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 1rem 2rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(16, 185, 129, 0.5);
            color: white;
        }

        .btn-update {
            background: #1e293b;
            color: white;
            box-shadow: 0 10px 20px -5px rgba(30, 41, 59, 0.3);
        }
        .btn-update:hover {
            background: #0f172a;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(30, 41, 59, 0.4);
        }

        /* Badges */
        .badge-status {
            padding: 0.6rem 1.2rem; border-radius: 100px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
        }

        /* Info Bubble */
        .info-bubble {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            border-radius: 12px;
            padding: 1rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>

<header class="admin-header">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="bg-primary p-2 rounded-3 me-3 text-white">
                 <i class="fa-solid fa-handshake-simple fs-2 me-2 logo-icon"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-800 tracking-tight">NDJANGUI</h5>
                <span class="text-muted small fw-bold">ESPACE ADMINISTRATEUR</span>
            </div>
        </div>
        <a href="index.php" class="btn btn-light rounded-pill px-4 btn-sm fw-bold border">
            <i class="fa-solid fa-house me-2"></i>Retour
        </a>
    </div>
</header>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="premium-card p-4 p-md-5 mb-4">
                <div class="row align-items-center mb-4">
                    <div class="col-md-7">
                        <h3 class="fw-800 mb-1">État de la Session</h3>
                        <p class="text-muted small">Gestion du calendrier et des cotisations en cours.</p>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <?php if($secondes_restantes > 0): ?>
                            <span class="badge-status text-success bg-success-subtle">
                                <i class="fa-solid fa-bolt-lightning me-1"></i> Session Ouverte
                            </span>
                        <?php else: ?>
                            <span class="badge-status text-danger bg-danger-subtle">
                                <i class="fa-solid fa-lock me-1"></i> Session Close
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($prochaineSeance && $secondes_restantes > 0): ?>
                    <div class="timer-display-container mb-4 shadow-lg">
                        <div class="row g-2 g-md-3 justify-content-center text-center">
                            <div class="col-3"><div class="timer-unit"><div class="timer-value" id="d">00</div><div class="timer-label">Jours</div></div></div>
                            <div class="col-3"><div class="timer-unit"><div class="timer-value" id="h">00</div><div class="timer-label">Heures</div></div></div>
                            <div class="col-3"><div class="timer-unit"><div class="timer-value" id="m">00</div><div class="timer-label">Mins</div></div></div>
                            <div class="col-3"><div class="timer-unit"><div class="timer-value" id="s">00</div><div class="timer-label">Secs</div></div></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <div class="p-3 border rounded-4 bg-light">
                                <small class="text-muted d-block fw-bold mb-1">ÉCHÉANCE</small>
                                <div class="fw-800 fs-5 text-dark"><?= date('d M Y', strtotime($prochaineSeance['date_seance'])) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="p-3 border rounded-4 bg-light">
                                <small class="text-muted d-block fw-bold mb-1">CLÔTURE</small>
                                <div class="fw-800 fs-5 text-dark"><?= substr($prochaineSeance['heure_limite_pointage'], 0, 5) ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="p-3 rounded-4 bg-success text-white shadow-sm h-100 d-flex flex-column justify-content-center">
                                <small class="opacity-75 d-block fw-bold mb-1">COTISATION REQUISE</small>
                                <div class="fw-800 fs-4"><?= number_format($prochaineSeance['montant_cotisation_fixe'], 0, ',', ' ') ?> <small>XAF</small></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 border-2 border-dashed rounded-5 bg-light">
                        <div class="bg-white shadow-sm rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fa-solid fa-money-bill-transfer fs-1 text-primary"></i>
                        </div>
                        <h4 class="fw-800">Prêt pour un nouveau tour ?</h4>
                        <p class="text-muted px-4">Aucune session n'est active pour le moment. Définissez les paramètres et lancez la collecte.</p>
                        <button class="btn btn-premium mt-3" data-bs-toggle="modal" data-bs-target="#modalLaunch">
                            <i class="fa-solid fa-rocket me-2"></i>Ouvrir une nouvelle session
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="premium-card p-4 p-md-5">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom border-light">
                    <div class="bg-dark p-3 rounded-4 me-3 text-white shadow-sm">
                        <i class="fa-solid fa-sliders fs-4"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-800">Configuration du Cercle</h4>
                        <p class="text-muted small mb-0">Définissez les règles par défaut pour les futures sessions.</p>
                    </div>
                </div>

                <form method="POST">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Périodicité des séances</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-calendar-check"></i></span>
                                <select name="frequence" class="form-select" id="freqSelect" onchange="toggleLibre()">
                                    <option value="hebdomadaire" <?= ($config['frequence']=='hebdomadaire')?'selected':'' ?>>Hebdomadaire (7 jours)</option>
                                    <option value="mensuel" <?= ($config['frequence']=='mensuel')?'selected':'' ?>>Mensuelle (30 jours)</option>
                                    <option value="libre" <?= ($config['frequence']=='libre')?'selected':'' ?>>Intervalle Personnalisé</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6" id="divLibre" style="display:none;">
                            <label class="form-label text-primary">Nombre de jours (Intervalle)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-hourglass-half"></i></span>
                                <input type="number" name="intervalle_libre" class="form-control" placeholder="Ex: 15" value="<?= $config['intervalle_libre'] ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Mode d'Attribution</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-shuffle"></i></span>
                                <select name="type_tirage" class="form-select">
                                    <option value="aleatoire" <?= ($config['type_tirage']=='aleatoire')?'selected':'' ?>>🎲 Tirage Aléatoire</option>
                                    <option value="manuel" <?= ($config['type_tirage']=='manuel')?'selected':'' ?>>👤 Choix Administratif</option>
                                    <option value="ordre_inscription" <?= ($config['type_tirage']=='ordre_inscription')?'selected':'' ?>>📜 Par Ancienneté</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Cotisation Standard</label>
                            <div class="input-group">
                                <span class="input-group-text text-success"><i class="fa-solid fa-wallet"></i></span>
                                <input type="number" name="montant_cotisation" class="form-control fw-bold fs-5 text-dark" value="<?= $config['montant_cotisation_standard'] ?>">
                                <span class="input-group-text bg-light text-muted fw-bold small" style="border-left:none;">XAF</span>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <div class="info-bubble mb-4">
                                <i class="fa-solid fa-circle-info me-3 fs-5"></i>
                                <div>
                                    <strong>Note Importante :</strong> Les modifications effectuées ici s'appliqueront uniquement lors de la création de la <u>prochaine</u> session. La session en cours ne sera pas affectée.
                                </div>
                            </div>
                            <button type="submit" name="update_config" class="btn btn-premium btn-update w-100 rounded-4 py-3 fw-800">
                                <i class="fa-solid fa-floppy-disk me-2"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLaunch" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 30px;">
            <div class="modal-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="bg-primary-subtle d-inline-flex p-4 rounded-circle mb-3">
                        <i class="fa-solid fa-paper-plane fs-1 text-primary"></i>
                    </div>
                    <h3 class="fw-800">Lancer la Session</h3>
                    <p class="text-muted">Configurez les derniers détails pour ce tour.</p>
                </div>
                
                <form method="POST">
                    <div class="bg-light p-3 rounded-4 border mb-4 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-uppercase text-muted">Fréquence</span>
                        <span class="badge bg-primary rounded-pill px-3"><?= ucfirst($config['frequence']) ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Heure limite de pointage</label>
                        <input type="time" name="heure_limite" class="form-control form-control-lg" value="18:00" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Montant exceptionnel (XAF)</label>
                        <input type="number" name="montant_seance" class="form-control form-control-lg fw-800 text-primary" value="<?= $config['montant_cotisation_standard'] ?>" required>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-light w-100 py-3 rounded-4 fw-bold" data-bs-dismiss="modal">Annuler</button>
                        </div>
                        <div class="col-6">
                            <button type="submit" name="creer_seance" class="btn btn-premium w-100 py-3 rounded-4 fw-bold">Ouvrir <i class="fa-solid fa-arrow-right ms-1"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleLibre(){
    var val = document.getElementById('freqSelect').value;
    var div = document.getElementById('divLibre');
    if(div) {
        div.style.display = (val === 'libre') ? 'block' : 'none';
        // Petit effet d'animation si on veut
        if(val === 'libre') { div.classList.add('fade-in'); }
    }
}
toggleLibre();

let totalSeconds = <?php echo ($secondes_restantes > 0) ? $secondes_restantes : 0; ?>;
function updateTimerDisplay() {
    let days = Math.floor(totalSeconds / (3600 * 24));
    let hours = Math.floor((totalSeconds % (3600 * 24)) / 3600);
    let minutes = Math.floor((totalSeconds % 3600) / 60);
    let seconds = Math.floor(totalSeconds % 60);
    const pad = (num) => num < 10 ? "0" + num : num;
    const elD = document.getElementById('d');
    const elH = document.getElementById('h');
    const elM = document.getElementById('m');
    const elS = document.getElementById('s');
    if(elD) elD.innerText = pad(days);
    if(elH) elH.innerText = pad(hours);
    if(elM) elM.innerText = pad(minutes);
    if(elS) elS.innerText = pad(seconds);
}

if (totalSeconds > 0) {
    updateTimerDisplay();
    const timerInterval = setInterval(() => {
        totalSeconds--;
        if (totalSeconds < 0) {
            clearInterval(timerInterval);
            location.reload(); 
            return;
        }
        updateTimerDisplay();
    }, 1000);
}
</script>
</body>
</html>