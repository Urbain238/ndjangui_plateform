<?php
session_start();
date_default_timezone_set('Africa/Douala');
require_once '../config/database.php';

// --- 1. SÉCURITÉ & AUTH ---
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$pdo = Database::getConnection();
$membre_id = $_SESSION['user_id'];

// Infos membre pour l'entête
$stmtUser = $pdo->prepare("SELECT nom_complet FROM membres WHERE id = ?");
$stmtUser->execute([$membre_id]);
$user_info = $stmtUser->fetch();
$user_name = $user_info['nom_complet'] ?? 'Membre';

// --- 2. RÉCUPÉRATION DES DONNÉES (Gardée intacte) ---

// A. COTISATIONS
$stmt = $pdo->prepare("SELECT c.*, s.date_seance FROM cotisations c 
                       LEFT JOIN seances s ON c.seance_id = s.id 
                       WHERE c.membre_id = ? ORDER BY s.date_seance DESC");
$stmt->execute([$membre_id]);
$cotisations = $stmt->fetchAll();

// B. ASSURANCES
$stmt = $pdo->prepare("SELECT * FROM assurances WHERE membre_id = ? ORDER BY date_limite DESC");
$stmt->execute([$membre_id]);
$assurances = $stmt->fetchAll();

// C. ÉPARGNES
$stmt = $pdo->prepare("SELECT * FROM epargnes WHERE membre_id = ? ORDER BY date_paiement DESC");
$stmt->execute([$membre_id]);
$epargnes = $stmt->fetchAll();

// D. PRÊTS
$stmt = $pdo->prepare("SELECT * FROM prets WHERE membre_id = ? ORDER BY date_demande DESC");
$stmt->execute([$membre_id]);
$prets = $stmt->fetchAll();

// E. SANCTIONS
$stmt = $pdo->prepare("SELECT s.*, sea.date_seance as date_seance_ref 
                       FROM sanctions s 
                       LEFT JOIN seances sea ON s.seance_id = sea.id 
                       WHERE s.membre_id = ? ORDER BY s.id DESC");
$stmt->execute([$membre_id]);
$sanctions = $stmt->fetchAll();

// --- 3. CALCULS DES TOTAUX & PERFORMANCE ---
$total_epargne = 0;
foreach($epargnes as $e) { if($e['statut'] == 'paye') $total_epargne += $e['montant_paye']; }

$total_cotise = 0;
$cotisations_impayees = 0;
foreach($cotisations as $c) { 
    if($c['statut'] == 'paye') $total_cotise += $c['montant_paye']; 
    else $cotisations_impayees++;
}

$dette_active = 0;
foreach($prets as $p) { if($p['statut_pret'] == 'accorde') $dette_active += ($p['montant_accorde']); }

$total_sanctions_non_payees = 0;
foreach($sanctions as $s) { if($s['statut'] != 'paye') $total_sanctions_non_payees += $s['montant']; }

// Détermination de l'état (Gagnant vs Défaillant)
$is_defaillant = ($cotisations_impayees > 0 || $total_sanctions_non_payees > 0);

// --- 4. FUSION POUR LA TIMELINE (Gardée intacte) ---
$timeline = [];
function addToTimeline(&$arr, $item, $type, $date_key, $montant_key, $titre) {
    $date = $item[$date_key] ?? date('Y-m-d H:i:s');
    $arr[] = [
        'type' => $type,
        'date' => $date,
        'montant' => $item[$montant_key] ?? 0,
        'statut' => $item['statut'] ?? $item['statut_pret'] ?? 'inconnu',
        'details' => $titre,
        'data' => $item
    ];
}

foreach($cotisations as $c) addToTimeline($timeline, $c, 'cotisation', 'date_paiement', 'montant_paye', 'Cotisation Séance ' . ($c['date_seance'] ?? ''));
foreach($assurances as $a) addToTimeline($timeline, $a, 'assurance', 'date_paiement', 'montant_paye', 'Assurance Mensuelle');
foreach($epargnes as $e) addToTimeline($timeline, $e, 'epargne', 'date_paiement', 'montant_paye', 'Épargne Volontaire');
foreach($prets as $p) addToTimeline($timeline, $p, 'pret', 'date_demande', 'montant_demande', 'Demande de Prêt');
foreach($sanctions as $s) addToTimeline($timeline, $s, 'sanction', 'date_sanction', 'montant', 'Sanction: ' . substr($s['motif'], 0, 20).'...');

usort($timeline, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

function formatMoney($amount) { return number_format($amount, 0, ',', ' '); }
function formatDate($date) { return ($date && $date != '0000-00-00 00:00:00') ? date('d/m/Y', strtotime($date)) : '-'; }

function getStatusBadge($status) {
    $status = strtolower($status);
    switch($status) {
        case 'paye': case 'cloture': case 'rembourse': return '<span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><i class="fa-solid fa-check-circle me-1"></i>Payé</span>';
        case 'impaye': case 'refuse': case 'rejeté': return '<span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3"><i class="fa-solid fa-xmark-circle me-1"></i>Impayé</span>';
        case 'en_attente': case 'demande': return '<span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3"><i class="fa-solid fa-clock me-1"></i>En attente</span>';
        case 'accorde': case 'actif': return '<span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><i class="fa-solid fa-circle-check me-1"></i>Actif</span>';
        default: return '<span class="badge bg-secondary rounded-pill px-3">'.$status.'</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique Analytique | NDJANGUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #2ec4b6;
            --danger: #e71d36;
            --warning: #ff9f1c;
            --info: #00b4d8;
            --bg-color: #f1f5f9;
        }
        body { background-color: var(--bg-color); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
        
        /* Header & Hero */
        .main-header {
            background: radial-gradient(circle at top right, #1e3c72, #2a5298);
            color: white; padding: 4rem 0 8rem 0; margin-bottom: -5rem; position: relative;
        }
        
        .btn-retour {
            position: absolute; top: 20px; left: 20px;
            background: rgba(255, 255, 255, 0.1); color: white;
            backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);
            transition: 0.3s; z-index: 10;
        }
        .btn-retour:hover { background: white; color: var(--primary); transform: translateX(-5px); }

        /* Performance Badge */
        .perf-badge {
            display: inline-flex; align-items: center; padding: 8px 20px; border-radius: 50px;
            font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .perf-gagnant { background: #dcfce7; color: #166534; border: 2px solid #bbf7d0; }
        .perf-defaillant { background: #fee2e2; color: #991b1b; border: 2px solid #fecaca; }

        /* Cards & Glassmorphism */
        .summary-card {
            background: white; border-radius: 24px; padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03); border: 1px solid rgba(255,255,255,0.8);
            height: 100%; transition: 0.3s;
        }
        .summary-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
        
        .chart-container {
            background: white; border-radius: 24px; padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03); margin-bottom: 2rem;
        }

        /* Tabs Styles */
        .nav-pills-custom {
            background: white; padding: 8px; border-radius: 50px; display: inline-flex;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto; max-width: 100%;
        }
        .nav-pills-custom .nav-link {
            border-radius: 50px; padding: 10px 25px; color: #64748b; font-weight: 600; transition: 0.3s;
        }
        .nav-pills-custom .nav-link.active {
            background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        /* List Items */
        .hist-item {
            background: white; border-radius: 20px; padding: 1.25rem; margin-bottom: 1rem;
            border: 1px solid #f1f5f9; position: relative; transition: 0.3s;
        }
        .hist-item:hover { border-color: var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .hist-item::after {
            content: ''; position: absolute; left: 0; top: 20%; height: 60%; width: 4px; border-radius: 0 4px 4px 0;
        }
        .type-cotisation::after { background: var(--primary); }
        .type-epargne::after { background: var(--success); }
        .type-pret::after { background: var(--danger); }
        .type-sanction::after { background: var(--warning); }
        .type-assurance::after { background: var(--info); }

        .hist-icon {
            width: 50px; height: 50px; border-radius: 16px; display: flex;
            align-items: center; justify-content: center; font-size: 1.2rem;
        }
    </style>
</head>
<body>

<div class="main-header text-center">
    <a href="../membre/index.php" class="btn btn-retour rounded-pill px-4 py-2 fw-bold text-decoration-none">
        <i class="fa-solid fa-arrow-left me-2"></i>Tableau de Bord
    </a>
    <div class="container">
        <h2 class="fw-bold mb-3">Analyse de mon Historique</h2>
        
        <?php if($is_defaillant): ?>
            <div class="perf-badge perf-defaillant"><i class="fa-solid fa-triangle-exclamation me-2"></i> État : Défaillant</div>
        <?php else: ?>
            <div class="perf-badge perf-gagnant"><i class="fa-solid fa-crown me-2"></i> État : Membre Gagnant</div>
        <?php endif; ?>
        
        <p class="mt-3 opacity-75">Visualisez votre évolution financière chez <strong>NDJANGUI</strong></p>
    </div>
</div>

<div class="container pb-5">
    
    <div class="row g-4 mb-5" style="margin-top: -3rem;">
        <div class="col-lg-4">
            <div class="row g-3">
                <div class="col-12">
                    <div class="summary-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="hist-icon bg-primary bg-opacity-10 text-primary me-3"><i class="fa-solid fa-wallet"></i></div>
                            <div>
                                <div class="text-muted small fw-bold">TOTAL COTISÉ</div>
                                <h3 class="fw-bold mb-0"><?= formatMoney($total_cotise) ?> <small class="fs-6">F</small></h3>
                            </div>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar" style="width: 70%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="summary-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="hist-icon bg-success bg-opacity-10 text-success me-3"><i class="fa-solid fa-piggy-bank"></i></div>
                            <div>
                                <div class="text-muted small fw-bold">ÉPARGNE DISPONIBLE</div>
                                <h3 class="fw-bold mb-0"><?= formatMoney($total_epargne) ?> <small class="fs-6">F</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="summary-card border-start border-danger border-4">
                        <div class="d-flex align-items-center">
                            <div class="hist-icon bg-danger bg-opacity-10 text-danger me-3"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                            <div>
                                <div class="text-muted small fw-bold">DETTE ACTIVE</div>
                                <h4 class="fw-bold mb-0 text-danger"><?= formatMoney($dette_active) ?> <small class="fs-6">F</small></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="chart-container h-100">
                <h6 class="fw-bold mb-4"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Répartition des Finances</h6>
                <canvas id="financeChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>

    <div class="text-center mb-5">
        <ul class="nav nav-pills-custom" id="pills-tab" role="tablist">
            <li class="nav-item"><button class="nav-link active" id="pills-all-tab" data-bs-toggle="pill" data-bs-target="#pills-all"><i class="fa-solid fa-layer-group me-2"></i>Flux Global</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-cotis"><i class="fa-solid fa-users me-2"></i>Cotisations</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-epargne"><i class="fa-solid fa-piggy-bank me-2"></i>Épargnes</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-pret"><i class="fa-solid fa-handshake me-2"></i>Prêts</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-sanction"><i class="fa-solid fa-gavel me-2"></i>Sanctions</button></li>
        </ul>
    </div>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pills-all">
            <?php if(empty($timeline)): ?>
                <div class="text-center py-5"><img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="opacity-25 mb-3"><br>Aucun mouvement détecté.</div>
            <?php else: ?>
                <?php foreach($timeline as $item): 
                    $color = match($item['type']) { 'cotisation'=>'primary', 'assurance'=>'info', 'epargne'=>'success', 'pret'=>'danger', 'sanction'=>'warning' };
                    $icon = match($item['type']) { 'cotisation'=>'fa-users', 'assurance'=>'fa-shield-heart', 'epargne'=>'fa-piggy-bank', 'pret'=>'fa-hand-holding-dollar', 'sanction'=>'fa-gavel' };
                ?>
                <div class="hist-item type-<?= $item['type'] ?>">
                    <div class="d-flex align-items-center">
                        <div class="hist-icon bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> me-3">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="fw-bold mb-0 text-capitalize text-dark"><?= $item['type'] ?></h6>
                                    <span class="text-muted small"><i class="fa-regular fa-calendar me-1"></i><?= formatDate($item['date']) ?></span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold fs-5"><?= formatMoney($item['montant']) ?> <small>F</small></div>
                                    <?= getStatusBadge($item['statut']) ?>
                                </div>
                            </div>
                            <div class="mt-2 small text-muted fst-italic"><?= $item['details'] ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="pills-cotis">
            <?php foreach($cotisations as $c): ?>
            <div class="hist-item type-cotisation">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="label-sm mb-1">Séance du <?= formatDate($c['date_seance']) ?></div>
                        <h6 class="fw-bold mb-0">Cotisation Mensuelle</h6>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-primary"><?= formatMoney($c['montant_paye']) ?> F</div>
                        <?= getStatusBadge($c['statut']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="tab-pane fade" id="pills-epargne">
            <?php foreach($epargnes as $e): ?>
            <div class="hist-item type-epargne text-center text-md-start">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="fw-bold mb-1">Dépôt Épargne Volontaire</h6>
                        <span class="small text-muted">Effectué le <?= formatDate($e['date_paiement']) ?></span>
                    </div>
                    <div class="col-md-4 text-md-end mt-2 mt-md-0">
                        <div class="fw-bold text-success fs-5">+ <?= formatMoney($e['montant_paye']) ?> F</div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Encaissé</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="tab-pane fade" id="pills-pret">
            <?php foreach($prets as $p): ?>
            <div class="hist-item type-pret">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-1">Crédit : <?= htmlspecialchars($p['raison_pret']) ?></h6>
                        <div class="d-flex gap-2">
                            <span class="detail-badge">Durée: <?= $p['duree_mois'] ?> mois</span>
                            <span class="detail-badge">Taux: <?= $p['taux_interet'] ?>%</span>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <div class="fw-bold text-danger"><?= formatMoney($p['montant_demande']) ?> F</div>
                        <?= getStatusBadge($p['statut_pret']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="tab-pane fade" id="pills-sanction">
            <?php foreach($sanctions as $s): ?>
            <div class="hist-item type-sanction border-start border-warning border-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="fw-bold text-warning mb-1"><?= htmlspecialchars($s['type_sanction'] ?? 'Pénalité') ?></h6>
                        <p class="small text-muted mb-0"><?= htmlspecialchars($s['motif']) ?></p>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-dark">- <?= formatMoney($s['montant']) ?> F</div>
                        <?= getStatusBadge($s['statut']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // Configuration du graphique Chart.js
    const ctx = document.getElementById('financeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Cotisations', 'Épargne', 'Prêts'],
            datasets: [{
                label: 'Montant en FCFA',
                data: [<?= $total_cotise ?>, <?= $total_epargne ?>, <?= $dette_active ?>],
                backgroundColor: ['#4361ee', '#2ec4b6', '#e71d36'],
                borderRadius: 12,
                barThickness: 40
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>