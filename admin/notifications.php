<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];

// --- LOGIQUE DE MARQUAGE COMME LU ---
if (isset($_GET['read_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE (membre_id = ? OR membre_id IS NULL)");
    $stmt->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}

if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE id = ? AND (membre_id = ? OR membre_id IS NULL)");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: notifications.php");
    exit;
}

try {
    // 1. Récupérer les parrainages en attente
    $stmtParr = $pdo->prepare("SELECT id, nom_complet, date_inscription FROM membres WHERE parrain_id = ? AND statut_validation = 'en_attente_parrain'");
    $stmtParr->execute([$user_id]);
    $parrainagesAttente = $stmtParr->fetchAll();

    // 2. Récupérer les votes communautaires en cours
    $stmtVotes = $pdo->prepare("SELECT m.id, m.nom_complet, m.plaidoyer_parrain 
                                FROM membres m 
                                WHERE m.statut_validation = 'en_vote' 
                                AND NOT EXISTS (SELECT 1 FROM votes_decisions v WHERE v.reference_id = m.id AND v.membre_id = ?)");
    $stmtVotes->execute([$user_id]);
    $votesAttente = $stmtVotes->fetchAll();

    // 3. Récupérer l'historique des notifications
    $stmtHist = $pdo->prepare("SELECT * FROM notifications WHERE (membre_id = ? OR membre_id IS NULL) ORDER BY date_creation DESC LIMIT 50");
    $stmtHist->execute([$user_id]);
    $historique = $stmtHist->fetchAll();

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Notifications - NDJANGUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1a237e;
            --accent-color: #3949ab;
            --bg-light: #f8faff;
            --unread-border: #4f46e5;
        }

        body { 
            background-color: var(--bg-light); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            color: white;
            box-shadow: 0 4px 20px rgba(26, 35, 126, 0.15);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(-5px);
        }

        .section-title {
            font-weight: 800;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1.5rem;
            color: #475569;
            display: flex;
            align-items: center;
        }

        .notif-card {
            background: white;
            border-radius: 16px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .notif-card:hover {
            transform: scale(1.01);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .notif-unread {
            border-left: 6px solid var(--unread-border) !important;
            background-color: #f0f4ff;
        }

        .notif-read {
            opacity: 0.85;
            background-color: white;
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.2rem;
        }

        .badge-pulse {
            position: relative;
            display: inline-block;
        }

        .badge-pulse::after {
            content: "";
            position: absolute;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            top: 0;
            right: 0;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .action-btn {
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .mark-read-btn {
            color: var(--accent-color);
            background: rgba(57, 73, 171, 0.1);
            border: none;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .mark-read-btn:hover {
            background: var(--accent-color);
            color: white;
            transform: rotate(360deg);
        }

        .empty-state {
            padding: 3rem;
            background: white;
            border-radius: 20px;
            border: 2px dashed #e2e8f0;
        }
    </style>
</head>
<body>

<header class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <a href="ndjangu_plateform/admin/index.php" class="btn btn-back mb-3 rounded-pill px-4">
                    <i class="fa-solid fa-arrow-left me-2"></i> Tableau de bord
                </a>
                <h1 class="fw-bolder mb-0"><i class="fa-solid fa-bell me-3"></i>Centre de Notifications</h1>
                <p class="opacity-75 mb-0">Gérez vos validations et suivez l'actualité de votre tontine.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="?read_all=1" class="btn btn-light rounded-pill px-4 fw-bold shadow">
                    <i class="fa-solid fa-check-double me-2 text-primary"></i> Tout marquer comme lu
                </a>
            </div>
        </div>
    </div>
</header>

<div class="container">
    <div class="row g-4">
        <div class="col-lg-5">
            <h5 class="section-title">
                <span class="badge-pulse me-2"></span>
                Actions Prioritaires
            </h5>
            
            <?php if(empty($parrainagesAttente) && empty($votesAttente)): ?>
                <div class="empty-state text-center">
                    <img src="https://cdn-icons-png.flaticon.com/512/3247/3247858.png" width="60" class="mb-3 opacity-25" alt="vide">
                    <p class="text-muted fw-bold m-0">Tout est à jour ! Aucune action requise.</p>
                </div>
            <?php endif; ?>

            <?php foreach($parrainagesAttente as $p): ?>
                <div class="card notif-card p-3">
                    <div class="d-flex align-items-start">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning me-3">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <span class="badge bg-warning text-dark mb-2">Parrainage</span>
                                <small class="text-muted">En attente</small>
                            </div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($p['nom_complet']); ?></h6>
                            <p class="small text-muted">Ce candidat a besoin de votre validation pour rejoindre le groupe.</p>
                            <a href="ndjangu_plateform/admin/index.php?open_modal=<?php echo $p['id']; ?>" class="btn btn-warning btn-sm action-btn w-100 shadow-sm mt-2">
                                Valider le profil
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?> 

            <?php foreach($votesAttente as $v): ?>
                <div class="card notif-card p-3">
                    <div class="d-flex align-items-start">
                        <div class="icon-box bg-info bg-opacity-10 text-info me-3">
                            <i class="fa-solid fa-square-poll-vertical"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <span class="badge bg-info text-white mb-2">Vote Ouvert</span>
                                <small class="text-muted small">Communautaire</small>
                            </div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($v['nom_complet']); ?></h6>
                            <p class="small text-muted">Exprimez votre avis sur cette nouvelle adhésion.</p>
                            <a href="voter-candidat.php?id=<?php echo $v['id']; ?>" class="btn btn-info text-white btn-sm action-btn w-100 shadow-sm mt-2">
                                Soumettre mon vote
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="col-lg-7">
            <h5 class="section-title">
                <i class="fa-solid fa-history me-2 text-muted"></i> Flux récent
            </h5>
            
            <?php if(empty($historique)): ?>
                <div class="empty-state text-center">
                    <i class="fa-solid fa-bell-slash fs-1 text-light mb-2"></i>
                    <p class="text-muted m-0">Votre flux de notifications est vide.</p>
                </div>
            <?php else: ?>
                <div class="notif-timeline">
                    <?php foreach($historique as $h): ?>
                        <div class="card notif-card p-3 <?php echo ($h['statut'] == 'non_lu') ? 'notif-unread' : 'notif-read'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box bg-light me-3">
                                        <i class="fa-solid fa-circle-info <?php echo ($h['statut'] == 'non_lu') ? 'text-primary' : 'text-muted'; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="mb-0 <?php echo ($h['statut'] == 'non_lu') ? 'fw-bold' : ''; ?> text-dark">
                                            <?php echo htmlspecialchars($h['message']); ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fa-regular fa-clock me-1"></i>
                                            <?php echo date('d M Y à H:i', strtotime($h['date_creation'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if($h['statut'] == 'non_lu'): ?>
                                    <a href="?mark_read=<?php echo $h['id']; ?>" class="mark-read-btn" title="Marquer comme lu">
                                        <i class="fa-solid fa-check"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="py-5 text-center text-muted small">
    &copy; <?php echo date('Y'); ?> NDJANGUI PLATEFORME - Système de Notifications Intelligent
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>