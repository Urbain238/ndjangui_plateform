<?php
session_start();
// 1. CONFIGURATION
date_default_timezone_set('Africa/Douala'); 
setlocale(LC_TIME, 'fr_FR.utf8', 'fra');

// --- CONNEXION BDD ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) { die("Erreur Connexion : " . $e->getMessage()); }

// --- INITIALISATION ---
$cercle_id = $_GET['cercle_id'] ?? 1; 

// Récupération configuration du cercle
$stmt = $pdo->prepare("SELECT * FROM cercles WHERE id = ?");
$stmt->execute([$cercle_id]);
$cercle = $stmt->fetch();

if (!$cercle) die("Erreur : Cercle introuvable (ID: $cercle_id)");

$msg = ""; 

// --- TRAITEMENT DES FORMULAIRES ---

// A. MISE À JOUR CONFIGURATION
if (isset($_POST['btn_update_config'])) {
    $nouvelle_freq = $_POST['frequence'];
    $nouveau_montant = $_POST['montant_std'];
    
    $stmtUpd = $pdo->prepare("UPDATE cercles SET frequence = ?, montant_cotisation_standard = ? WHERE id = ?");
    $stmtUpd->execute([$nouvelle_freq, $nouveau_montant, $cercle_id]);
    
    $cercle['frequence'] = $nouvelle_freq;
    $cercle['montant_cotisation_standard'] = $nouveau_montant;
    
    $msg = "<div class='custom-alert success'><i class='fas fa-check-circle'></i> Configuration mise à jour !</div>";
}

// B. CRÉATION D'UNE NOUVELLE SÉANCE
if (isset($_POST['btn_add_seance'])) {
    $date_seance = $_POST['date_seance'];
    $heure_limite = $_POST['heure_limite'];
    $beneficiaire = !empty($_POST['beneficiaire_id']) ? $_POST['beneficiaire_id'] : null;
    $montant_fixe = $_POST['montant_fixe'];

    $chk = $pdo->prepare("SELECT id FROM seances WHERE cercle_id=? AND date_seance=?");
    $chk->execute([$cercle_id, $date_seance]);
    
    if($chk->rowCount() > 0) {
        $msg = "<div class='custom-alert warning'><i class='fas fa-exclamation-triangle'></i> Une séance existe déjà à cette date.</div>";
    } else {
        $ins = $pdo->prepare("INSERT INTO seances (cercle_id, date_seance, heure_limite_pointage, statut, beneficiaire_id, montant_cotisation_fixe) VALUES (?, ?, ?, 'prevue', ?, ?)");
        $ins->execute([$cercle_id, $date_seance, $heure_limite, $beneficiaire, $montant_fixe]);
        
        $msg = "<div class='custom-alert success'><i class='fas fa-rocket'></i> Séance programmée avec succès !</div>";
    }
}

// C. SUPPRESSION SÉANCE
if (isset($_POST['btn_delete_id'])) {
    $del = $pdo->prepare("DELETE FROM seances WHERE id = ? AND cercle_id = ? AND statut = 'prevue'");
    $del->execute([(int)$_POST['btn_delete_id'], $cercle_id]);
    $msg = "<div class='custom-alert neutral'><i class='fas fa-trash'></i> Séance supprimée.</div>";
}

// --- INTELLIGENCE (CALCULS) ---
$now = time();
$nextSeance = null;
$nextTimestamp = null; 

$stmtS = $pdo->prepare("
    SELECT s.*, u.nom_complet 
    FROM seances s 
    LEFT JOIN membres u ON s.beneficiaire_id = u.id 
    WHERE s.cercle_id = ? AND s.statut = 'prevue' 
    ORDER BY s.date_seance ASC, s.heure_limite_pointage ASC
");
$stmtS->execute([$cercle_id]);
$allSeances = $stmtS->fetchAll();

foreach ($allSeances as $s) {
    $date_propre = date('Y-m-d', strtotime($s['date_seance']));
    $seanceTime = strtotime($date_propre . ' ' . $s['heure_limite_pointage']);
    if ($seanceTime > $now) {
        $nextSeance = $s;
        $nextTimestamp = $seanceTime; 
        break; 
    }
}

// Date suggérée
$stmtLast = $pdo->prepare("SELECT date_seance FROM seances WHERE cercle_id = ? ORDER BY date_seance DESC LIMIT 1");
$stmtLast->execute([$cercle_id]);
$lastDateDB = $stmtLast->fetch();
$baseDate = $lastDateDB ? new DateTime($lastDateDB['date_seance']) : new DateTime();
switch ($cercle['frequence']) {
    case 'hebdomadaire': $baseDate->modify('+7 days'); break;
    case 'mensuel':      $baseDate->modify('+1 month'); break;
    case 'libre':        $baseDate->modify('+1 day'); break;
    default:             $baseDate->modify('+7 days'); break;
}
$suggestedDate = $baseDate->format('Y-m-d');

// Membres
$stmtM = $pdo->prepare("SELECT u.id, u.nom_complet FROM inscriptions_cercle ic JOIN membres u ON ic.membre_id = u.id WHERE ic.cercle_id = ?");
$stmtM->execute([$cercle_id]);
$membres = $stmtM->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin • <?= htmlspecialchars($cercle['nom_cercle']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Palette Premium */
            --bg-body: #f8f9fc;
            --primary-dark: #0f172a; /* Bleu Nuit */
            --primary-light: #334155;
            --accent-gold: #f59e0b; /* Or */
            --accent-blue: #3b82f6;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            padding-bottom: 60px;
            overflow-x: hidden;
        }

        /* --- HEADER --- */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .brand-logo {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            color: var(--primary-dark);
            letter-spacing: -0.5px;
        }

        /* --- HERO TIMER --- */
        .timer-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.4);
            color: white;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        /* Effet de fond subtil */
        .timer-hero::after {
            content: '';
            position: absolute;
            top: 0; right: 0; width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
            pointer-events: none;
        }

        .timer-digits {
            font-family: 'Space Grotesk', monospace;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .digit-box {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1rem 0;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .digit-box:hover { transform: translateY(-3px); background: rgba(255,255,255,0.12); }
        .digit-val { font-size: 2.2rem; font-weight: 700; display: block; line-height: 1; color: #fff; }
        .digit-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.6); margin-top: 5px; }

        /* --- CARDS & FORMS --- */
        .card-glass {
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
        }
        .card-header-custom {
            padding: 1.5rem 1.5rem 0.5rem;
            border-bottom: none;
            background: transparent;
        }
        .card-title-custom {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Inputs Stylisés */
        .form-control, .form-select {
            background-color: #f1f5f9;
            border: 1px solid transparent;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            background-color: white;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }
        
        /* Boutons */
        .btn-primary-custom {
            background: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-primary-custom:hover {
            background: #1e293b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(15, 23, 42, 0.2);
        }

        /* --- TABLE --- */
        .table-custom { margin-bottom: 0; }
        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .table-custom td {
            vertical-align: middle;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--primary-dark);
        }
        .row-active { background-color: rgba(59, 130, 246, 0.04); }
        .row-active td:first-child { border-left: 3px solid var(--accent-blue); }
        
        .date-icon {
            width: 45px; height: 45px;
            background: #e0f2fe; color: var(--accent-blue);
            border-radius: 12px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            font-weight: 700; line-height: 1;
        }
        .date-icon small { font-size: 0.6rem; text-transform: uppercase; margin-top: 2px; }

        /* --- ALERTS --- */
        .custom-alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.4s ease-out;
        }
        .custom-alert.success { background: #dcfce7; color: #166534; }
        .custom-alert.warning { background: #fef9c3; color: #854d0e; }
        .custom-alert.neutral { background: #e2e8f0; color: #475569; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- ACCORDION CONFIG --- */
        .accordion-button { border-radius: 12px !important; background: white; color: var(--primary-dark); font-weight: 600; box-shadow: none !important; }
        .accordion-button:not(.collapsed) { background: #f1f5f9; color: var(--accent-blue); }
        .accordion-item { border: none; background: transparent; }

        /* Responsive */
        @media (max-width: 768px) {
            .digit-val { font-size: 1.5rem; }
            .timer-digits { gap: 0.5rem; }
            .timer-hero { padding: 1.5rem; }
            .card-title-custom { font-size: 1rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-custom mb-4">
        <div class="container">
            <div class="d-flex align-items-center">
                <a href="admin-seances.php?cercle_id=<?= $cercle_id ?>" class="btn btn-light rounded-circle shadow-sm me-3" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
    <i class="fa-solid fa-arrow-left text-muted"></i>
</a>
                <div>
                    <span class="d-block text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Administration</span>
                    <span class="brand-logo fs-5"><?= htmlspecialchars($cercle['nom_cercle']) ?></span>
                </div>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-dark text-white rounded-pill px-3 py-2 fw-normal">Mode Admin</span>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <?= $msg ?>

        <?php if($nextSeance): ?>
        <div class="timer-hero p-4 p-md-5">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-warning text-dark fw-bold rounded-pill px-3">PROCHAINE SÉANCE</span>
                        <span class="text-white-50 small"><i class="far fa-clock me-1"></i> <?= substr($nextSeance['heure_limite_pointage'], 0, 5) ?></span>
                    </div>
                    <h2 class="display-6 fw-bold mb-1">Le Rendez-vous</h2>
                    <p class="text-white-50 mb-0">
                        Date : <span class="text-white fw-bold"><?= date('d F Y', strtotime($nextSeance['date_seance'])) ?></span>
                    </p>
                    <div class="mt-3 d-inline-block bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-10">
                        <small class="text-warning text-uppercase fw-bold ls-1">Montant Attendu</small>
                        <div class="fs-4 fw-bold font-monospace"><?= number_format($nextSeance['montant_cotisation_fixe'], 0, ',', ' ') ?> <span class="fs-6">FCFA</span></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="timer-digits">
                        <div class="digit-box"><span class="digit-val" id="d">00</span><span class="digit-label">Jours</span></div>
                        <div class="digit-box"><span class="digit-val" id="h">00</span><span class="digit-label">Heures</span></div>
                        <div class="digit-box"><span class="digit-val" id="m">00</span><span class="digit-label">Min</span></div>
                        <div class="digit-box"><span class="digit-val" id="s">00</span><span class="digit-label">Sec</span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="timer-hero p-5 text-center d-flex flex-column align-items-center justify-content-center" style="background: linear-gradient(135deg, #64748b, #475569);">
            <div class="bg-white bg-opacity-25 rounded-circle p-3 mb-3">
                <i class="fa-solid fa-mug-hot fa-2x text-white"></i>
            </div>
            <h3 class="fw-bold text-white">Aucune séance active</h3>
            <p class="text-white-50">Configurez une nouvelle séance ci-dessous pour relancer le compte à rebours.</p>
        </div>
        <?php endif; ?>

        <div class="accordion mb-4 shadow-sm rounded-4 overflow-hidden bg-white" id="accConf">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#c1">
                        <i class="fa-solid fa-sliders me-3 text-primary"></i> Paramètres du Cercle
                    </button>
                </h2>
                <div id="c1" class="accordion-collapse collapse" data-bs-parent="#accConf">
                    <div class="accordion-body p-4 bg-light">
                        <form method="POST" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label">Fréquence par défaut</label>
                                <select name="frequence" class="form-select">
                                    <option value="hebdomadaire" <?= $cercle['frequence']=='hebdomadaire'?'selected':'' ?>>Hebdomadaire</option>
                                    <option value="mensuel" <?= $cercle['frequence']=='mensuel'?'selected':'' ?>>Mensuel</option>
                                    <option value="libre" <?= $cercle['frequence']=='libre'?'selected':'' ?>>Libre</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Montant Standard</label>
                                <div class="input-group">
                                    <input type="number" name="montant_std" class="form-control" value="<?= $cercle['montant_cotisation_standard'] ?>">
                                    <span class="input-group-text border-0 bg-transparent text-muted fw-bold">FCFA</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="btn_update_config" class="btn btn-dark w-100 py-2 rounded-3">
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card-glass">
                    <div class="card-header-custom">
                        <div class="card-title-custom">
                            <div class="bg-primary bg-opacity-10 text-primary rounded p-2 me-2"><i class="fa-solid fa-plus"></i></div>
                            Planifier
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Date Prévue</label>
                                <input type="date" name="date_seance" class="form-control fw-bold text-primary" value="<?= $suggestedDate ?>" required>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Heure Limite</label>
                                    <input type="time" name="heure_limite" class="form-control text-center" value="18:00" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Montant (F)</label>
                                    <input type="number" name="montant_fixe" class="form-control text-end" value="<?= $cercle['montant_cotisation_standard'] ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Bénéficiaire (Optionnel)</label>
                                <select name="beneficiaire_id" class="form-select">
                                    <option value="">-- Sélectionner plus tard --</option>
                                    <?php foreach($membres as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom_complet']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" name="btn_add_seance" class="btn-primary-custom shadow-sm">
                                Créer la séance <i class="fa-solid fa-arrow-right ms-2"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-glass">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <div class="card-title-custom">
                            <div class="bg-success bg-opacity-10 text-success rounded p-2 me-2"><i class="fa-solid fa-calendar-check"></i></div>
                            Agenda des séances
                        </div>
                        <span class="badge bg-light text-dark border"><?= count($allSeances) ?> Prévues</span>
                    </div>
                    
                    <?php if(empty($allSeances)): ?>
                        <div class="text-center py-5 px-3">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="Empty" style="width:80px; opacity:0.5; filter: grayscale(100%);" class="mb-3">
                            <p class="text-muted fw-medium">Aucune séance programmée pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Détails</th>
                                        <th class="text-end">Montant</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($allSeances as $s): 
                                        $d = new DateTime($s['date_seance']);
                                        $isActive = ($nextSeance && $s['id'] == $nextSeance['id']);
                                    ?>
                                    <tr class="<?= $isActive ? 'row-active' : '' ?>">
                                        <td width="80">
                                            <div class="date-icon <?= $isActive ? 'bg-primary text-white' : '' ?>">
                                                <span><?= $d->format('d') ?></span>
                                                <small><?= $d->format('M') ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= substr($s['heure_limite_pointage'], 0, 5) ?></div>
                                            <div class="small text-muted d-flex align-items-center gap-1">
                                                <i class="fa-solid fa-user-tag text-opacity-50" style="font-size:0.7em"></i>
                                                <?= $s['nom_complet'] ? $s['nom_complet'] : '<span class="fst-italic text-muted">À définir</span>' ?>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold font-monospace">
                                            <?= number_format($s['montant_cotisation_fixe'], 0, ',', ' ') ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" onsubmit="return confirm('Confirmer la suppression ?');">
                                                <input type="hidden" name="btn_delete_id" value="<?= $s['id'] ?>">
                                                <button class="btn btn-sm btn-light text-danger border hover-shadow" title="Supprimer">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // JS Timer inchangé mais appliqué aux nouvelles classes
    const targetDate = <?= $nextTimestamp ? ($nextTimestamp * 1000) : 0 ?>;
    if (targetDate > 0) {
        const timerInterval = setInterval(function() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0) {
                clearInterval(timerInterval);
                document.querySelector('.timer-digits').innerHTML = '<div class="text-white text-center w-100">Séance en cours / terminée</div>';
                return;
            }

            const pad = (n) => n < 10 ? "0" + n : n;
            document.getElementById("d").innerText = pad(Math.floor(distance / (1000 * 60 * 60 * 24)));
            document.getElementById("h").innerText = pad(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)));
            document.getElementById("m").innerText = pad(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)));
            document.getElementById("s").innerText = pad(Math.floor((distance % (1000 * 60)) / 1000));
        }, 1000);
    }
    </script>
</body>
</html>