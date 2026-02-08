<?php
session_start();
date_default_timezone_set('Africa/Douala');

// --- 1. CONNEXION BDD ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) { die("Erreur critique : " . $e->getMessage()); }

// --- 2. SÉCURITÉ ---
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
$user_id = $_SESSION['user_id'];

// Récupération ID (avec sécurité anti-erreur)
$cercle_id = isset($_GET['cercle_id']) ? (int)$_GET['cercle_id'] : 0;

// FIX "PARAMÈTRE MANQUANT" : Si pas d'ID, on prend le dernier cercle du membre
if ($cercle_id === 0) {
    $stmt_auto = $pdo->prepare("SELECT cercle_id FROM inscriptions_cercle WHERE membre_id = ? ORDER BY date_inscription DESC LIMIT 1");
    $stmt_auto->execute([$user_id]);
    $trouve = $stmt_auto->fetch();
    
    if ($trouve) {
        $cercle_id = $trouve['cercle_id'];
    } else {
        // Aucun cercle trouvé, redirection vers l'accueil
        header("Location: dashboard.php"); 
        exit;
    }
}

// Infos Cercle
$stmt = $pdo->prepare("SELECT * FROM cercles WHERE id = ?");
$stmt->execute([$cercle_id]);
$cercle = $stmt->fetch();

if (!$cercle) die("Cercle introuvable.");

// --- 3. RÉCUPÉRATION HISTORIQUE PERSONNEL ---
$sql = "SELECT * FROM cotisations_cercle 
        WHERE membre_id = ? AND cercle_id = ? 
        ORDER BY date_paiement DESC";
$stmt_hist = $pdo->prepare($sql);
$stmt_hist->execute([$user_id, $cercle_id]);
$historique = $stmt_hist->fetchAll();

// --- MISE À JOUR : CALCUL DU TOTAL GLOBAL (TOUS LES MEMBRES) ---
$sql_total = "SELECT SUM(montant) as total FROM cotisations_cercle WHERE cercle_id = ? AND statut = 'valide'";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute([$cercle_id]);
$res_total = $stmt_total->fetch();
$total_verse = $res_total['total'] ?? 0;

// Nombre de vos transactions personnelles
$nb_transactions = count($historique);

// Lien de retour
$path_retour = "admin-seances.php?cercle_id=" . $cercle_id;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-body: #f3f6f8;
            --primary: #0f172a;
            --accent: #2563eb;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --white: #ffffff;
            --success-bg: #dcfce7; --success-text: #166534;
            --pending-bg: #fef9c3; --pending-text: #854d0e;
            --danger-bg: #fee2e2; --danger-text: #991b1b;
            --shadow-card: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            padding-top: 90px;
            /* Motif de fond subtil */
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 20px 20px;
        }

        /* NAVBAR PREMIUM */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            height: 80px;
        }
        .brand-container { text-decoration: none; display: flex; align-items: center; gap: 12px; }
        .brand-icon {
            width: 42px; height: 42px;
            background: var(--primary); color: white;
            border-radius: 12px; display: grid; place-items: center; font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
            transition: transform 0.3s;
        }
        .brand-icon:hover { transform: rotate(-10deg); }
        .brand-text { font-weight: 800; font-size: 1.35rem; color: var(--primary); letter-spacing: -0.5px; }

        /* CARDS STATS */
        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: var(--shadow-card);
            height: 100%;
            display: flex; flex-direction: column; justify-content: center;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-label { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .stat-value { font-size: 2.2rem; font-weight: 800; color: var(--primary); letter-spacing: -1px; }

        /* TABLEAU */
        .history-container {
            background: var(--white);
            border-radius: 24px;
            box-shadow: var(--shadow-card);
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
        }
        
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th {
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);
            padding: 0 1.5rem 1rem 1.5rem; border: none; letter-spacing: 0.5px;
        }
        .custom-table tbody tr { transition: all 0.2s; }
        .custom-table tbody tr:hover { transform: scale(1.01); }
        
        .custom-table td {
            padding: 1.25rem 1.5rem;
            vertical-align: middle;
            background: #f8fafc;
            border: none;
        }
        .custom-table td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .custom-table td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

        /* STATUTS */
        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 30px;
            font-size: 0.8rem; font-weight: 700;
        }
        .status-valide { background: var(--success-bg); color: var(--success-text); }
        .status-attente { background: var(--pending-bg); color: var(--pending-text); }
        .status-refuse { background: var(--danger-bg); color: var(--danger-text); }

        /* ICONS PAIEMENT */
        .pay-icon { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 10px; font-size: 1rem; }
        .bg-om { background: #fff7ed; color: #ff7900; }
        .bg-momo { background: #fefce8; color: #ffcc00; }

        .back-link {
            color: var(--text-muted); font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; transition: 0.2s;
            padding: 0.5rem 1rem; border-radius: 8px;
        }
        .back-link:hover { color: var(--primary); background: rgba(0,0,0,0.03); }
    </style>
</head>
<body>

<nav class="navbar navbar-custom fixed-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="<?= $path_retour ?>" class="brand-container">
            <div class="brand-icon"><i class="fa-solid fa-hands-holding-circle"></i></div>
            <span class="brand-text">NDJANGUI</span>
        </a>
        
        <a href="<?= $path_retour ?>" class="back-link">
            <i class="fa-solid fa-arrow-left-long me-2"></i> Retour aux séances
        </a>
    </div>
</nav>

<div class="container py-4">
    
    <div class="row g-4 mb-4 align-items-end">
        <div class="col-lg-6">
            <h1 class="fw-bold text-dark mb-2">Historique des transactions</h1>
            <p class="text-muted mb-0 d-flex align-items-center gap-2">
                <i class="fa-solid fa-layer-group"></i> 
                Cercle : <strong><?= htmlspecialchars($cercle['nom_cercle']) ?></strong>
            </p>
        </div>
        <div class="col-lg-3">
            <div class="stat-card">
                <div class="stat-label"><i class="fa-solid fa-wallet me-2"></i>Total Versé (Cercle)</div>
                <div class="stat-value"><?= number_format($total_verse, 0, ',', ' ') ?> <span class="fs-6 text-muted fw-normal">FCFA</span></div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="stat-card">
                <div class="stat-label"><i class="fa-solid fa-receipt me-2"></i>Vos Opérations</div>
                <div class="stat-value"><?= $nb_transactions ?></div>
            </div>
        </div>
    </div>

    <div class="history-container animate__animated animate__fadeInUp">
        <?php if (empty($historique)): ?>
            
            <div class="text-center py-5">
                <div class="mb-3 text-muted opacity-25" style="font-size: 5rem;"><i class="fa-solid fa-file-circle-xmark"></i></div>
                <h4 class="fw-bold">Aucune transaction</h4>
                <p class="text-muted mb-4">Vous n'avez pas encore effectué de paiement dans ce cercle.</p>
                <a href="cotiser.php?cercle_id=<?= $cercle_id ?>" class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow-sm">
                    Faire un premier dépôt
                </a>
            </div>

        <?php else: ?>
            
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Date / Heure</th>
                            <th>Portefeuille</th>
                            <th>Référence ID</th>
                            <th>Montant</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historique as $h): 
                            $date = new DateTime($h['date_paiement']);
                            
                            $badge_class = 'status-attente';
                            $icon_statut = 'fa-spinner fa-spin-pulse'; 
                            $text_statut = 'En attente';

                            if($h['statut'] === 'valide') {
                                $badge_class = 'status-valide';
                                $icon_statut = 'fa-check';
                                $text_statut = 'Validé';
                            } elseif($h['statut'] === 'refuse') {
                                $badge_class = 'status-refuse';
                                $icon_statut = 'fa-xmark';
                                $text_statut = 'Refusé';
                            }

                            $is_om = ($h['mode_paiement'] === 'orange_money');
                            $pay_class = $is_om ? 'bg-om' : 'bg-momo';
                            $pay_icon = $is_om ? 'fa-mobile-screen' : 'fa-mobile-screen-button';
                            $pay_name = $is_om ? 'Orange M.' : 'MTN MoMo';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark mb-1"><?= $date->format('d M Y') ?></div>
                                <div class="small text-muted font-monospace"><i class="fa-regular fa-clock me-1"></i><?= $date->format('H:i') ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="pay-icon <?= $pay_class ?>">
                                        <i class="fa-solid <?= $pay_icon ?>"></i>
                                    </div>
                                    <span class="fw-bold text-secondary small"><?= $pay_name ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark font-monospace border fw-normal px-2 py-1">
                                    #<?= htmlspecialchars($h['reference_transaction']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-5"><?= number_format($h['montant'], 0, ',', ' ') ?></span> 
                                <span class="small text-muted fw-bold" style="font-size: 0.7em;">FCFA</span>
                            </td>
                            <td>
                                <div class="status-badge <?= $badge_class ?>">
                                    <i class="fa-solid <?= $icon_statut ?>"></i> <?= $text_statut ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>