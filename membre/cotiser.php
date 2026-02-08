<?php
session_start();
date_default_timezone_set('Africa/Douala');
require_once '../config/database.php';

// --- 1. CONFIGURATION & AUTH ---
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$pdo = Database::getConnection();
$membre_id = $_SESSION['user_id'];
$cercle_id = $_SESSION['cercle_id'] ?? 1;

// Infos membre (Mise à jour selon les attributs de la BDD : nom_complet)
$stmtUser = $pdo->prepare("SELECT telephone, nom_complet FROM membres WHERE id = ?");
$stmtUser->execute([$membre_id]);
$user_info = $stmtUser->fetch();

// Sécurisation des variables
$user_phone = $user_info['telephone'] ?? '';
$user_name = $user_info['nom_complet'] ?? 'Membre';

// --- 2. LOGIQUE SANCTION (Inchangée) ---
function verifierEtSanctionnerAbsences($pdo, $membre_id, $cercle_id) {
    $today = date('Y-m-d');
    $sql = "SELECT id, date_seance, montant_cotisation_fixe FROM seances 
            WHERE date_seance < ? AND statut != 'cloturee_traitee'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);
    $seances_passees = $stmt->fetchAll();
    $prix_assu_fixe = 1000; 

    foreach ($seances_passees as $s) {
        $s_id = $s['id'];
        $date_s = $s['date_seance'];
        
        // Cotisation
        $chkCotis = $pdo->prepare("SELECT id, statut FROM cotisations WHERE seance_id = ? AND membre_id = ?");
        $chkCotis->execute([$s_id, $membre_id]);
        $cotis = $chkCotis->fetch();

        if (!$cotis || $cotis['statut'] != 'paye') {
            $motif = "Sanction Cotisation - Séance du " . $date_s;
            $chkSancC = $pdo->prepare("SELECT id FROM sanctions WHERE membre_id = ? AND seance_origine_id = ? AND motif LIKE ?");
            $chkSancC->execute([$membre_id, $s_id, '%Sanction Cotisation%']);
            if ($chkSancC->rowCount() == 0) {
                $penalite = $s['montant_cotisation_fixe'] * 1.40;
                $pdo->prepare("INSERT INTO sanctions (cercle_id, membre_id, seance_origine_id, montant, motif, statut, date_creation) VALUES (?, ?, ?, ?, ?, 'impaye', NOW())")
                    ->execute([$cercle_id, $membre_id, $s_id, $penalite, $motif]);
            }
        }

        // Assurance
        $chkAssu = $pdo->prepare("SELECT id, statut FROM assurances WHERE membre_id = ? AND date_limite = ?");
        $chkAssu->execute([$membre_id, $date_s]);
        $assu = $chkAssu->fetch();

        if (!$assu || $assu['statut'] != 'paye') {
            $motif_assu = "Sanction Assurance - Séance du " . $date_s;
            $chkSancA = $pdo->prepare("SELECT id FROM sanctions WHERE membre_id = ? AND seance_origine_id = ? AND motif LIKE ?");
            $chkSancA->execute([$membre_id, $s_id, '%Sanction Assurance%']);
            if ($chkSancA->rowCount() == 0) {
                $penalite_assu = $prix_assu_fixe * 1.30;
                $pdo->prepare("INSERT INTO sanctions (cercle_id, membre_id, seance_origine_id, montant, motif, statut, date_creation) VALUES (?, ?, ?, ?, ?, 'impaye', NOW())")
                    ->execute([$cercle_id, $membre_id, $s_id, $penalite_assu, $motif_assu]);
            }
        }
    }
}
try { verifierEtSanctionnerAbsences($pdo, $membre_id, $cercle_id); } catch(Exception $e) {}

// --- 3. SÉANCE ACTIVE ---
$stmtS = $pdo->query("SELECT * FROM seances WHERE statut = 'prevue' ORDER BY date_seance ASC LIMIT 1");
$seance = $stmtS->fetch();

if ($seance) {
    $cercle_id = $seance['cercle_id'];
    $_SESSION['cercle_id'] = $cercle_id;
    $seance_id = $seance['id'];
    $date_seance = $seance['date_seance'];
    $fixe_cotis = $seance['montant_cotisation_fixe'] ?? 0;
    $fixe_assu = 1000;
} else {
    $msg_seance = "Aucune séance active.";
}

// --- 4. CALCULS ---
$reste_cotis = 0;
$reste_assurance = 0;
$total_dette = 0;
$total_sanction = 0;

if ($seance) {
    // Cotisation
    $stmtC = $pdo->prepare("SELECT montant_paye FROM cotisations WHERE seance_id = ? AND membre_id = ?");
    $stmtC->execute([$seance_id, $membre_id]);
    $resC = $stmtC->fetch();
    $paye_c = $resC['montant_paye'] ?? 0;
    $reste_cotis = max(0, $fixe_cotis - $paye_c);

    // Assurance
    $stmtA = $pdo->prepare("SELECT montant_paye FROM assurances WHERE membre_id = ? AND date_limite = ?");
    $stmtA->execute([$membre_id, $date_seance]);
    $resA = $stmtA->fetch();
    $paye_a = $resA['montant_paye'] ?? 0;
    $reste_assurance = max(0, $fixe_assu - $paye_a);
}

// Dettes & Sanctions
$stmtD = $pdo->prepare("SELECT SUM(montant_accorde) as total FROM prets WHERE membre_id = ? AND statut_pret = 'accorde'");
$stmtD->execute([$membre_id]);
$total_dette = $stmtD->fetch()['total'] ?? 0;

$stmtSan = $pdo->prepare("SELECT SUM(montant) as total FROM sanctions WHERE membre_id = ? AND statut = 'impaye'");
$stmtSan->execute([$membre_id]);
$total_sanction = $stmtSan->fetch()['total'] ?? 0;

// Logique de sélection par défaut pour le JS
$default_selection = 'epargne'; // Valeur de repli
if ($reste_cotis > 0) $default_selection = 'cotisation';
elseif ($reste_assurance > 0) $default_selection = 'assurance';
elseif ($total_dette > 0) $default_selection = 'dette';
elseif ($total_sanction > 0) $default_selection = 'sanction';


// --- 5. TRAITEMENT PAIEMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payer'])) {
    $type = $_POST['type_paiement'];
    $montant = (float) $_POST['montant'];

    if ($montant <= 0) {
        $erreur = "Le montant doit être positif.";
    } else {
        try {
            $pdo->beginTransaction();

            switch ($type) {
                case 'cotisation':
                    if ($reste_cotis <= 0) throw new Exception("La cotisation est déjà réglée.");
                    if ($montant > $reste_cotis) throw new Exception("Montant supérieur au reste à payer.");
                    
                    $check = $pdo->prepare("SELECT id, montant_paye FROM cotisations WHERE seance_id=? AND membre_id=?");
                    $check->execute([$seance_id, $membre_id]);
                    $exist = $check->fetch();
                    $new_paye = ($exist['montant_paye'] ?? 0) + $montant;
                    $statut = ($new_paye >= $fixe_cotis) ? 'paye' : 'partiel';
                    
                    if ($exist) {
                        $pdo->prepare("UPDATE cotisations SET montant_paye=?, statut=?, date_paiement=NOW() WHERE id=?")->execute([$new_paye, $statut, $exist['id']]);
                    } else {
                        $pdo->prepare("INSERT INTO cotisations (cercle_id, seance_id, membre_id, montant_attendu, montant_paye, statut, date_limite, date_paiement) VALUES (?,?,?,?,?,?,?,NOW())")->execute([$cercle_id, $seance_id, $membre_id, $fixe_cotis, $montant, $statut, $date_seance]);
                    }

                    // --- MISE À JOUR DEMANDÉE : PASSAGE AU STATUT ACTIF ---
                    // Dès qu'une cotisation est payée, le membre devient actif.
                    $pdo->prepare("UPDATE membres SET statut = 'actif' WHERE id = ?")->execute([$membre_id]);
                    
                    break;

                case 'assurance':
                    if ($reste_assurance <= 0) throw new Exception("L'assurance est déjà réglée.");
                    if ($montant > $reste_assurance) throw new Exception("Montant trop élevé.");
                    
                    $checkA = $pdo->prepare("SELECT id, montant_paye FROM assurances WHERE membre_id=? AND date_limite=?");
                    $checkA->execute([$membre_id, $date_seance]);
                    $existA = $checkA->fetch();
                    $new_paye_a = ($existA['montant_paye'] ?? 0) + $montant;
                    $statut_a = ($new_paye_a >= $fixe_assu) ? 'paye' : 'partiel';

                    if ($existA) {
                        $pdo->prepare("UPDATE assurances SET montant_paye=?, statut=?, date_paiement=NOW() WHERE id=?")->execute([$new_paye_a, $statut_a, $existA['id']]);
                    } else {
                        $pdo->prepare("INSERT INTO assurances (cercle_id, membre_id, montant_attendu, montant_paye, statut, date_limite, date_paiement) VALUES (?, ?, ?, ?, ?, ?, NOW())")->execute([$cercle_id, $membre_id, $fixe_assu, $montant, $statut_a, $date_seance]);
                    }
                    break;

                case 'epargne':
                    $pdo->prepare("INSERT INTO epargnes (cercle_id, membre_id, montant_paye, statut, date_paiement, date_limite) VALUES (?,?,?, 'paye', NOW(), ?)")->execute([$cercle_id, $membre_id, $montant, date('Y-12-31')]);
                    break;

                case 'sanction':
                    if ($total_sanction <= 0) throw new Exception("Aucune sanction à payer.");
                    if ($montant > $total_sanction) throw new Exception("Montant supérieur aux sanctions.");
                    $reste_op = $montant;
                    $stmtList = $pdo->prepare("SELECT * FROM sanctions WHERE membre_id=? AND statut='impaye' ORDER BY id ASC");
                    $stmtList->execute([$membre_id]);
                    while($s = $stmtList->fetch()) {
                        if ($reste_op <= 0) break;
                        $to_pay = min($reste_op, $s['montant']);
                        $pdo->prepare("UPDATE sanctions SET statut='paye', date_paiement=NOW() WHERE id=?")->execute([$s['id']]);
                        $reste_op -= $to_pay;
                    }
                    break;
                
                case 'dette':
                      // Logique dette (placeholder)
                    break;
            }

            $pdo->commit();
            header("Location: cotiser.php?success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guichet Paiement | NDJANGUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --success-color: #2ec4b6;
            --info-color: #00b4d8;
            --warning-color: #ff9f1c;
            --danger-color: #e71d36;
            --bg-color: #f8f9fc;
        }
        body { background-color: var(--bg-color); font-family: 'Poppins', sans-serif; color: #333; }
        
        /* Stats Cards */
        .stat-card {
            background: #fff; border-radius: 16px; border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #eee; }
        .stat-row:last-child { border-bottom: none; }
        
        /* Payment Options */
        .card-option {
            cursor: pointer; position: relative;
            background: #fff; border: 2px solid transparent; border-radius: 16px;
            padding: 20px; text-align: center; transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;
        }
        
        /* Interactive States */
        .card-option:hover:not(.disabled) { border-color: #e2e8f0; transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        
        /* Active States per Category */
        .card-option.active[data-type="cotisation"] { border-color: var(--primary-color); background-color: rgba(67, 97, 238, 0.05); color: var(--primary-color); }
        .card-option.active[data-type="assurance"] { border-color: var(--info-color); background-color: rgba(0, 180, 216, 0.05); color: var(--info-color); }
        .card-option.active[data-type="epargne"] { border-color: var(--success-color); background-color: rgba(46, 196, 182, 0.05); color: var(--success-color); }
        .card-option.active[data-type="dette"] { border-color: var(--danger-color); background-color: rgba(231, 29, 54, 0.05); color: var(--danger-color); }
        .card-option.active[data-type="sanction"] { border-color: var(--warning-color); background-color: rgba(255, 159, 28, 0.05); color: var(--warning-color); }

        /* Disabled State (Already Paid) */
        .card-option.disabled {
            background-color: #f8f9fa; border-color: #eee; cursor: not-allowed; opacity: 0.7; filter: grayscale(100%);
        }
        .check-badge {
            position: absolute; top: 10px; right: 10px; font-size: 14px;
            background: #20c997; color: white; padding: 2px 8px; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Input styling */
        .input-group-lg > .form-control { border-top-right-radius: 12px; border-bottom-right-radius: 12px; font-size: 1.5rem; font-weight: 600; }
        .input-group-text { border-top-left-radius: 12px; border-bottom-left-radius: 12px; background: #fff; }
        
        /* Modal Enhanced Styling */
        .modal-content {
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            background: #fff;
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.5rem 1.5rem 0.5rem 1.5rem;
        }
        .operator-card {
            border: 2px solid #f1f5f9; border-radius: 16px; cursor: pointer; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #fff;
        }
        .operator-card:hover { transform: translateY(-2px); border-color: #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .operator-card.selected { border-color: var(--primary-color); background: rgba(67, 97, 238, 0.04); position: relative; }
        .operator-card.selected::after {
            content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; top: -10px; right: -10px;
            background: var(--primary-color); color: white; width: 24px; height: 24px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;
        }

        /* Success Animation */
        .success-checkmark {
            width: 80px; height: 80px; margin: 0 auto;
            border-radius: 50%; display: block;
            stroke-width: 2; stroke: #fff; stroke-miterlimit: 10;
            box-shadow: inset 0px 0px 0px var(--success-color);
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        .success-checkmark circle {
            stroke-dasharray: 166; stroke-dashoffset: 166; stroke-width: 2;
            stroke-miterlimit: 10; stroke: var(--success-color); fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        .success-checkmark path {
            transform-origin: 50% 50%; stroke-dasharray: 48; stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        @keyframes stroke { 100% { stroke-dashoffset: 0; } }
        @keyframes scale { 0%, 100% { transform: none; } 50% { transform: scale3d(1.1, 1.1, 1); } }
        @keyframes fill { 100% { box-shadow: inset 0px 0px 0px 50px var(--success-color); } }

        .main-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white; padding: 3rem 0; margin-bottom: -2rem; padding-bottom: 5rem;
            position: relative;
        }
        .btn-retour {
            position: absolute; top: 20px; left: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white; backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }
        .btn-retour:hover { background: rgba(255, 255, 255, 0.3); color: white; transform: translateX(-3px); }
        .card-raised { margin-top: -3rem; }
    </style>
</head>
<body>

<div class="main-header text-center">
    <a href="../membre/index.php" class="btn btn-retour rounded-pill px-4 py-2 fw-bold text-decoration-none">
        <i class="fa-solid fa-arrow-left me-2"></i>Retour
    </a>

    <div class="container">
        <h2 class="fw-bold mb-1">Guichet de Paiement</h2>
        <p class="opacity-75">Bienvenue, <?= htmlspecialchars($user_name) ?> • Séance du <?= isset($date_seance) ? date('d/m/Y', strtotime($date_seance)) : '--' ?></p>
    </div>
</div>

<div class="container pb-5">
    
    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 text-center mb-4 mt-3 card-raised"><i class="fa-solid fa-check-circle me-2"></i>Paiement enregistré avec succès !</div>
    <?php endif; ?>
    
    <?php if(isset($erreur)): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 text-center mb-4 mt-3 card-raised"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= $erreur ?></div>
    <?php endif; ?>

    <?php if(!$seance): ?>
        <div class="alert alert-warning text-center mt-4 card-raised">Aucune séance active n'est programmée.</div>
    <?php else: ?>

    <div class="row g-4 card-raised">
        <div class="col-lg-4">
            <div class="stat-card p-4 h-100">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-light p-3 rounded-circle me-3">
                        <i class="fa-solid fa-chart-pie text-primary fa-lg"></i>
                    </div>
                    <h5 class="fw-bold m-0">Ma Situation</h5>
                </div>
                
                <div class="stat-row">
                    <span class="text-muted small text-uppercase fw-bold">Cotisation</span>
                    <?php if($reste_cotis > 0): ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2"><?= number_format($reste_cotis, 0, ',', ' ') ?> F</span>
                    <?php else: ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="fa-solid fa-check me-1"></i> Payé</span>
                    <?php endif; ?>
                </div>

                <div class="stat-row">
                    <span class="text-muted small text-uppercase fw-bold">Assurance</span>
                    <?php if($reste_assurance > 0): ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2"><?= number_format($reste_assurance, 0, ',', ' ') ?> F</span>
                    <?php else: ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="fa-solid fa-check me-1"></i> Payé</span>
                    <?php endif; ?>
                </div>

                <div class="stat-row">
                    <span class="text-muted small text-uppercase fw-bold">Dettes</span>
                    <span class="fw-bold <?= $total_dette > 0 ? 'text-danger' : 'text-muted' ?>"><?= number_format($total_dette, 0, ',', ' ') ?> F</span>
                </div>

                <div class="stat-row">
                    <span class="text-muted small text-uppercase fw-bold">Sanctions</span>
                    <span class="fw-bold <?= $total_sanction > 0 ? 'text-warning' : 'text-muted' ?>"><?= number_format($total_sanction, 0, ',', ' ') ?> F</span>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <form method="POST" id="paymentForm">
                <div class="stat-card p-4">
                    <h5 class="fw-bold mb-4">Choisir l'opération</h5>
                    
                    <div class="row row-cols-2 row-cols-md-3 g-3 mb-4">
                        
                        <div class="col">
                            <?php $isDisabled = ($reste_cotis <= 0); ?>
                            <label class="card-option <?= $isDisabled ? 'disabled' : '' ?>" 
                                   data-type="cotisation"
                                   onclick="<?= $isDisabled ? "return false;" : "selectType('cotisation', $reste_cotis)" ?>">
                                <?php if($isDisabled): ?><div class="check-badge"><i class="fa-solid fa-check"></i></div><?php endif; ?>
                                <input type="radio" name="type_paiement" value="cotisation" class="d-none" <?= $isDisabled ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-users fa-2x mb-3" style="color: var(--primary-color)"></i>
                                <span class="fw-bold small">Cotisation</span>
                                <span class="small text-muted mt-1"><?= $isDisabled ? 'Réglé' : 'À payer' ?></span>
                            </label>
                        </div>

                        <div class="col">
                            <?php $isDisabledAssu = ($reste_assurance <= 0); ?>
                            <label class="card-option <?= $isDisabledAssu ? 'disabled' : '' ?>" 
                                   data-type="assurance"
                                   onclick="<?= $isDisabledAssu ? "return false;" : "selectType('assurance', $reste_assurance)" ?>">
                                <?php if($isDisabledAssu): ?><div class="check-badge"><i class="fa-solid fa-check"></i></div><?php endif; ?>
                                <input type="radio" name="type_paiement" value="assurance" class="d-none" <?= $isDisabledAssu ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-shield-heart fa-2x mb-3" style="color: var(--info-color)"></i>
                                <span class="fw-bold small">Assurance</span>
                                <span class="small text-muted mt-1"><?= $isDisabledAssu ? 'Réglé' : 'À payer' ?></span>
                            </label>
                        </div>

                        <div class="col">
                            <label class="card-option" data-type="epargne" onclick="selectType('epargne', 10000000)">
                                <input type="radio" name="type_paiement" value="epargne" class="d-none">
                                <i class="fa-solid fa-piggy-bank fa-2x mb-3" style="color: var(--success-color)"></i>
                                <span class="fw-bold small">Épargne</span>
                                <span class="small text-muted mt-1">Libre</span>
                            </label>
                        </div>

                        <div class="col">
                            <?php $isDisabledDette = ($total_dette <= 0); ?>
                            <label class="card-option <?= $isDisabledDette ? 'disabled' : '' ?>" 
                                   data-type="dette"
                                   onclick="<?= $isDisabledDette ? "return false;" : "selectType('dette', $total_dette)" ?>">
                                <?php if($isDisabledDette): ?><div class="check-badge"><i class="fa-solid fa-check"></i></div><?php endif; ?>
                                <input type="radio" name="type_paiement" value="dette" class="d-none" <?= $isDisabledDette ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-hand-holding-dollar fa-2x mb-3" style="color: var(--danger-color)"></i>
                                <span class="fw-bold small">Dette</span>
                                <span class="small text-muted mt-1"><?= $isDisabledDette ? 'Aucune' : 'En cours' ?></span>
                            </label>
                        </div>

                        <div class="col">
                            <?php $isDisabledSanc = ($total_sanction <= 0); ?>
                            <label class="card-option <?= $isDisabledSanc ? 'disabled' : '' ?>" 
                                   data-type="sanction"
                                   onclick="<?= $isDisabledSanc ? "return false;" : "selectType('sanction', $total_sanction)" ?>">
                                <?php if($isDisabledSanc): ?><div class="check-badge"><i class="fa-solid fa-check"></i></div><?php endif; ?>
                                <input type="radio" name="type_paiement" value="sanction" class="d-none" <?= $isDisabledSanc ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-gavel fa-2x mb-3" style="color: var(--warning-color)"></i>
                                <span class="fw-bold small">Sanctions</span>
                                <span class="small text-muted mt-1"><?= $isDisabledSanc ? 'Aucune' : 'En retard' ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold small text-muted text-uppercase mb-2">Montant à verser</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text border-0 text-muted">XAF</span>
                            <input type="number" name="montant" id="inputMontant" class="form-control border-0 bg-light" placeholder="0" required min="100">
                        </div>
                        <div class="d-flex justify-content-between mt-2 px-1">
                            <small class="text-muted">Min: 100 FCFA</small>
                            <small class="text-muted">Max: <span id="maxAmountDisplay" class="fw-bold">0</span> FCFA</small>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-lg" 
                            style="background: linear-gradient(90deg, #4361ee, #3f37c9); border:none;"
                            onclick="openPaymentModal()">
                        <i class="fa-solid fa-mobile-screen-button me-2"></i> Payer maintenant
                    </button>
                    <input type="hidden" name="payer" value="1">
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="momoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold mx-auto">Confirmation Paiement</h6>
                <button type="button" class="btn-close position-absolute end-0 top-0 m-3" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <div id="step1">
                    <p class="text-muted text-center mb-4 small">Sélectionnez votre moyen de paiement</p>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="card operator-card p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center" onclick="selectOperator(this, 'MTN')">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/9/93/New-mtn-logo.jpg" alt="MTN" style="width:50px; border-radius:10px;" class="mb-2 shadow-sm">
                                <div class="small fw-bold mt-1">MTN MoMo</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card operator-card p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center" onclick="selectOperator(this, 'ORANGE')">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c8/Orange_logo.svg" alt="Orange" style="width:50px;" class="mb-2">
                                <div class="small fw-bold mt-1">Orange Money</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control rounded-4 bg-light border-0" id="modalPhone" value="<?= htmlspecialchars($user_phone) ?>" placeholder="Numéro">
                        <label for="modalPhone" class="text-muted">Numéro Mobile Money</label>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-4 mb-4 border border-1">
                        <span class="text-muted small text-uppercase fw-bold">Total</span>
                        <span class="fw-bold fs-5 text-primary" id="modalAmountDisplay">0 FCFA</span>
                    </div>

                    <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm" onclick="startSimulation()">
                        Confirmer et Payer
                    </button>
                </div>

                <div id="step2" style="display:none;" class="text-center py-4">
                    <div class="spinner-border text-primary mb-4" style="width: 3rem; height: 3rem;" role="status"></div>
                    <h5 class="fw-bold mb-2">Traitement en cours...</h5>
                    <p class="small text-muted mb-3 px-2">Veuillez valider le code PIN sur votre téléphone.</p>
                    <div class="alert alert-warning py-2 rounded-3 small">
                        <i class="fa-solid fa-info-circle me-1"></i> Composez *126# ou #150# si le prompt n'apparaît pas.
                    </div>
                </div>

                <div id="step3" style="display:none;" class="text-center py-2">
                    <div class="mb-3">
                        <svg class="success-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>
                    <h5 class="fw-bold text-success mb-1">Paiement Réussi !</h5>
                    <p class="small text-muted mb-4">Votre transaction a été validée avec succès.</p>
                    <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" onclick="finalizePayment()">Terminer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let selectedOperator = null;
    
    // Variables PHP injectées dans JS
    const defaultSelection = "<?= $default_selection ?>";
    const resteCotis = <?= $reste_cotis ?>;
    const resteAssu = <?= $reste_assurance ?>;
    const totalDette = <?= $total_dette ?>;
    const totalSanc = <?= $total_sanction ?>;

    function selectType(type, maxAmount) {
        // Retirer la classe active de tous
        document.querySelectorAll('.card-option').forEach(el => {
            el.classList.remove('active');
        });

        // Ajouter active à l'élément sélectionné
        const selectedEl = document.querySelector(`.card-option[data-type="${type}"]`);
        if(selectedEl && !selectedEl.classList.contains('disabled')) {
            selectedEl.classList.add('active');
            
            // Cocher le radio bouton
            const radio = selectedEl.querySelector('input[type="radio"]');
            if(radio) radio.checked = true;

            // Mise à jour input montant
            const input = document.getElementById('inputMontant');
            const display = document.getElementById('maxAmountDisplay');
            
            if(maxAmount > 9000000) {
                display.innerText = "illimité";
                input.removeAttribute('max');
                input.value = ""; // Laisser vide pour épargne
            } else {
                display.innerText = new Intl.NumberFormat('fr-FR').format(maxAmount);
                input.setAttribute('max', maxAmount);
                input.value = maxAmount; // Pré-remplir avec le reste à payer
            }
        }
    }

    function openPaymentModal() {
        const montant = document.getElementById('inputMontant').value;
        if(!montant || montant <= 0) { 
            alert("Veuillez entrer un montant valide."); 
            return; 
        }
        
        document.getElementById('modalAmountDisplay').innerText = new Intl.NumberFormat('fr-FR').format(montant) + " FCFA";
        document.getElementById('step1').style.display = 'block';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step3').style.display = 'none';
        
        new bootstrap.Modal(document.getElementById('momoModal')).show();
    }

    function selectOperator(elem, op) {
        document.querySelectorAll('.operator-card').forEach(e => e.classList.remove('selected'));
        elem.classList.add('selected');
        selectedOperator = op;
    }

    function startSimulation() {
        if(!selectedOperator) { alert("Veuillez sélectionner MTN ou Orange."); return; }
        
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        
        // Simulation plus longue : 5 secondes (5000ms)
        setTimeout(() => {
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'block';
        }, 5000);
    }

    function finalizePayment() {
        document.getElementById('paymentForm').submit();
    }

    // Initialisation au chargement
    document.addEventListener('DOMContentLoaded', () => {
        // On déclenche le clic sur l'option par défaut calculée en PHP
        let amount = 0;
        if(defaultSelection === 'cotisation') amount = resteCotis;
        else if(defaultSelection === 'assurance') amount = resteAssu;
        else if(defaultSelection === 'dette') amount = totalDette;
        else if(defaultSelection === 'sanction') amount = totalSanc;
        else amount = 10000000;

        selectType(defaultSelection, amount);
    });
</script>
</body>
</html>