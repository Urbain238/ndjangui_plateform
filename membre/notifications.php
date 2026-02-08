<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
// --- TRAITEMENT DU FORMULAIRE DE PARRAINAGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_parrainage'])) {
    try {
        $filleul_id = intval($_POST['filleul_id']);
        $action = $_POST['action_parrainage']; // 'valider' ou 'rejeter'
        $motif = htmlspecialchars(trim($_POST['motif']));
        if ($action === 'valider') {
            $stmtUpd = $pdo->prepare("UPDATE membres SET statut_validation = 'en_vote' WHERE id = ?");
            $stmtUpd->execute([$filleul_id]);
            $stmtVote = $pdo->prepare("INSERT INTO votes_decisions (reference_id, type_vote, membre_id, choix, commentaire_vote, date_vote) VALUES (?, 'adhesion', ?, 'pour', ?, NOW())");
            $stmtVote->execute([$filleul_id, $user_id, $motif]);
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (membre_id, message, type) VALUES (?, 'Votre parrain a validé votre profil. Le vote du bureau commence.', 'info')");
            $stmtNotif->execute([$filleul_id]);
            $_SESSION['flash_success'] = "Le dossier a été validé et transmis au bureau pour vote.";
        } else {
            $stmtUpd = $pdo->prepare("UPDATE membres SET statut_validation = 'rejete' WHERE id = ?");
            $stmtUpd->execute([$filleul_id]);
            $_SESSION['flash_success'] = "Le dossier a été rejeté.";
        }
        header("Location: notifications.php");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Erreur : " . $e->getMessage();
    }
}
// --- MARQUER TOUT COMME LU ---
if (isset($_GET['read_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE (membre_id = ? OR membre_id IS NULL OR membre_id = 0) AND statut = 'non_lu'");
    $stmt->execute([$user_id]);
    $_SESSION['date_lecture_paiements'] = date('Y-m-d H:i:s');
    header("Location: notifications.php?msg=all_read");
    exit;
}
// --- MARQUER UNE NOTIF COMME LUE ---
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE id = ? AND (membre_id = ? OR membre_id IS NULL OR membre_id = 0)");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: notifications.php");
    exit;
}
// --- GESTION DES MESSAGES FLASH ---
if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_GET['msg']) && $_GET['msg'] === 'all_read') {
    $info_msg = "Vos messages ont été marqués comme lus. Note : Les actions requises (votes, validations) restent affichées tant qu'elles ne sont pas traitées.";
}
try {
    // 1. Parrainages en attente (Action requise)
    $stmtParr = $pdo->prepare("SELECT id, nom_complet, date_inscription FROM membres WHERE parrain_id = ? AND statut_validation = 'en_attente_parrain'");
    $stmtParr->execute([$user_id]);
    $parrainagesAttente = $stmtParr->fetchAll();
    // 2. Votes en attente
    $stmtVotes = $pdo->prepare("SELECT m.id, m.nom_complet, m.plaidoyer_parrain 
                                FROM membres m 
                                WHERE m.statut_validation = 'en_vote' 
                                AND NOT EXISTS (SELECT 1 FROM votes_decisions v WHERE v.reference_id = m.id AND v.type_vote = 'adhesion' AND v.membre_id = ?)");
    $stmtVotes->execute([$user_id]);
    $votesAttente = $stmtVotes->fetchAll();
    // 3. Projets en attente de vote
    $stmtProjets = $pdo->prepare("SELECT p.id, p.titre, p.description, p.date_creation 
                                  FROM projets p 
                                  WHERE p.statut = 'soumis' 
                                  AND NOT EXISTS (SELECT 1 FROM votes_decisions v WHERE v.reference_id = p.id AND v.type_vote = 'projet' AND v.membre_id = ?)");
    $stmtProjets->execute([$user_id]);
    $projetsAttente = $stmtProjets->fetchAll();
    // 4. Historique Notifications Système
    $stmtHist = $pdo->prepare("SELECT id, message, date_creation, statut, type, reference_id, 'system' as type_source FROM notifications WHERE (membre_id = ? OR membre_id IS NULL OR membre_id = 0) ORDER BY date_creation DESC LIMIT 50");
    $stmtHist->execute([$user_id]);
    $historiqueSysteme = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    // 5. Paiements Récents (Flux)
    $stmtCoti = $pdo->query("SELECT c.montant_paye, c.date_paiement, m.nom_complet, m.id as payeur_id
                             FROM cotisations c 
                             JOIN membres m ON c.membre_id = m.id 
                             WHERE c.date_paiement > DATE_SUB(NOW(), INTERVAL 3 DAY) 
                             ORDER BY c.date_paiement DESC");
    $paiementsRecents = $stmtCoti->fetchAll(PDO::FETCH_ASSOC);
    // Fusion des flux
    $fluxGlobal = [];
    $last_payments_read_time = isset($_SESSION['date_lecture_paiements']) ? $_SESSION['date_lecture_paiements'] : '2000-01-01 00:00:00';
    foreach($historiqueSysteme as $notif) {
        $fluxGlobal[] = [
            'type' => 'system',
            'id' => $notif['id'],
            'message' => $notif['message'],
            'date' => $notif['date_creation'],
            'statut' => $notif['statut'],
            'notif_type' => $notif['type'], // alert, info, projet, message, message_bureau, etc.
            'ref_id' => isset($notif['reference_id']) ? $notif['reference_id'] : null
        ];
    }
    foreach($paiementsRecents as $paiement) {
        $statutPaiement = ($paiement['date_paiement'] > $last_payments_read_time) ? 'non_lu' : 'lu';
        
        $prefix = ($paiement['payeur_id'] == $user_id) ? "VOUS avez versé" : "<strong>" . htmlspecialchars($paiement['nom_complet']) . "</strong> a versé";
        $fluxGlobal[] = [
            'type' => 'paiement',
            'id' => null, 
            'message' => "💰 NOUVEAU VERSEMENT : " . $prefix . " <span class='text-success fw-bold'>" . number_format($paiement['montant_paye'], 0, ',', ' ') . " FCFA</span>.",
            'date' => $paiement['date_paiement'],
            'statut' => $statutPaiement,
            'notif_type' => 'info',
            'ref_id' => null
        ];
    }
    // Tri par date décroissante
    usort($fluxGlobal, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - NDJANGUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            --bg-body: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --accent-new: #ef4444;
            --accent-projet: #8b5cf6;
        }
        body { 
            background-color: var(--bg-body); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            display: flex; flex-direction: column;
        }
        .page-header {
            background: var(--primary-gradient);
            padding: 3rem 0 4rem;
            margin-bottom: -2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;
        }
        .header-content { position: relative; z-index: 2; }

        .btn-back-header {
            background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.2);
            color: white; border-radius: 50px; padding: 0.5rem 1.2rem;
            backdrop-filter: blur(4px); transition: all 0.3s; text-decoration: none;
            font-size: 0.9rem; display: inline-flex; align-items: center;
        }
        .btn-back-header:hover { background: rgba(255, 255, 255, 0.25); color: white; transform: translateX(-3px); }

        .btn-read-all {
            background: white; color: #4f46e5; border: none; border-radius: 50px;
            padding: 0.6rem 1.5rem; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .btn-read-all:hover { background: #eef2ff; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
        .main-container { margin-top: -1.5rem; z-index: 5; position: relative; padding-bottom: 3rem; flex: 1; }
        .section-header {
            font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-muted); font-weight: 700; margin-bottom: 1rem;
            display: flex; align-items: center;
        }
        .section-header::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; margin-left: 1rem; }
        .notif-card {
            background: var(--card-bg); border: none; border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03); margin-bottom: 0.75rem;
            transition: all 0.2s ease-in-out; position: relative; overflow: hidden;
            animation: fadeIn 0.5s ease-out forwards;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .notif-card:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
        .status-indicator {
            position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #cbd5e1;
        }
        .is-unread .status-indicator { background: var(--primary-gradient); }
        .is-paiement .status-indicator { background: #10b981; }
        .is-projet .status-indicator { background: var(--accent-projet); }
        
        /* Indicateur jaune pour les messages */
        .is-message .status-indicator { background: #ffc107; }

        .unread-dot {
            width: 8px; height: 8px; background-color: var(--accent-new);
            border-radius: 50%; display: inline-block; margin-left: 8px;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }
        .notif-bg-unread { background-color: #f8fafc; }
        .notif-bg-paiement { background-color: #f0fdf4; }
        .notif-bg-projet { background-color: #f5f3ff; }
        .notif-bg-message { background-color: #fffbeb; } 

        .icon-wrapper {
            width: 45px; height: 45px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;
        }

        .notif-title { font-size: 0.95rem; line-height: 1.4; margin-bottom: 0.2rem; color: var(--text-main); }
        .notif-time { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        .action-card-btn {
            font-size: 0.8rem; padding: 0.5rem 1rem; border-radius: 6px;
            font-weight: 600; width: 100%; text-align: center; margin-top: 0.8rem;
        }
        .stretched-link-custom::after {
            position: absolute; top: 0; right: 0; bottom: 0; left: 0; z-index: 1; content: "";
        }
        .action-card-btn, .mark-check-btn { position: relative; z-index: 2; }

        .empty-state {
            padding: 3rem 1rem; text-align: center; background: white;
            border-radius: 16px; border: 2px dashed #e2e8f0;
        }
        .mark-check-btn {
            width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center;
            justify-content: center; color: #64748b; background: #f1f5f9; transition: 0.2s;
            border: none; text-decoration: none;
        }
        .mark-check-btn:hover { background: var(--primary-gradient); color: white; }
        .app-footer { margin-top: auto; border-top: 1px solid #e2e8f0; padding: 1.5rem; background: white; }

        @media (max-width: 768px) {
            .page-header { padding: 2rem 0 3rem; border-radius: 0 0 20px 20px; }
            .header-flex-mobile { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .btn-read-all { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
<header class="page-header">
    <div class="container header-content">
        <div class="d-flex justify-content-between align-items-end header-flex-mobile">
            <div>
                <a href="index.php" class="btn-back-header mb-3">
                    <i class="fa-solid fa-arrow-left me-2"></i> Retour
                </a>
                <h2 class="fw-bold mb-1"><i class="fa-regular fa-bell me-2"></i>Notifications</h2>
                <div class="opacity-75 small">Restez informé de l'activité de la communauté.</div>
            </div>
            <div>
                <a href="?read_all=1" class="btn btn-read-all">
                    <i class="fa-solid fa-check-double me-2"></i> Tout marquer comme lu
                </a>
            </div>
        </div>
    </div>
</header>
<div class="container main-container">
    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success rounded-3 shadow-sm border-0 mb-4 animate__animated animate__fadeIn">
            <i class="fa-solid fa-check-circle me-2"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>
    <?php if(isset($info_msg)): ?>
        <div class="alert alert-info rounded-3 shadow-sm border-0 mb-4 animate__animated animate__fadeIn">
            <i class="fa-solid fa-circle-info me-2"></i> <?php echo $info_msg; ?>
        </div>
    <?php endif; ?>
    <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger rounded-3 shadow-sm border-0 mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4 order-lg-2">
            <div class="section-header">
                <i class="fa-solid fa-bolt me-2 text-warning"></i> À traiter
            </div>
            <?php if(empty($parrainagesAttente) && empty($votesAttente) && empty($projetsAttente)): ?>
                <div class="empty-state">
                    <div class="mb-3">
                        <i class="fa-solid fa-clipboard-check fa-2x text-success opacity-50"></i>
                    </div>
                    <h6 class="fw-bold text-dark">Tout est à jour</h6>
                    <p class="small text-muted mb-0">Aucune action en attente pour le moment.</p>
                </div>
            <?php endif; ?>
            <?php foreach($parrainagesAttente as $p): ?>
                <div class="card notif-card p-3 border-0 shadow-sm is-unread">
                    <div class="status-indicator" style="background: #f59e0b;"></div>
                    <div class="d-flex">
                        <div class="icon-wrapper bg-warning bg-opacity-10 text-warning me-3">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.7rem;">Parrainage</span>
                                <span class="unread-dot"></span>
                            </div>
                            <h6 class="notif-title fw-bold"><?php echo htmlspecialchars($p['nom_complet']); ?></h6>
                            <p class="small text-muted mb-0">Validation de profil requise.</p>
                            <button type="button" class="btn btn-warning text-dark action-card-btn" 
                                    onclick="openParrainageModal('<?php echo $p['id']; ?>', '<?php echo htmlspecialchars(addslashes($p['nom_complet'])); ?>')">
                                Examiner la demande
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php foreach($votesAttente as $v): ?>
                <div class="card notif-card p-3 border-0 shadow-sm is-unread">
                    <div class="status-indicator" style="background: #3b82f6;"></div>
                    <div class="d-flex">
                        <div class="icon-wrapper bg-primary bg-opacity-10 text-primary me-3">
                            <i class="fa-solid fa-check-to-slot"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-primary rounded-pill" style="font-size: 0.7rem;">Vote Membre</span>
                                <span class="unread-dot"></span>
                            </div>
                            <h6 class="notif-title fw-bold"><?php echo htmlspecialchars($v['nom_complet']); ?></h6>
                            <p class="small text-muted mb-0">La communauté attend votre avis.</p>
                            <a href="voter-candidat.php?id=<?php echo $v['id']; ?>" class="btn btn-primary action-card-btn">
                                Voter maintenant
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php foreach($projetsAttente as $proj): ?>
                <div class="card notif-card p-3 border-0 shadow-sm is-unread">
                    <div class="status-indicator" style="background: var(--accent-projet);"></div>
                    <div class="d-flex">
                        <div class="icon-wrapper bg-info bg-opacity-10 text-info me-3" style="color: var(--accent-projet) !important; background-color: #f3e8ff !important;">
                            <i class="fa-solid fa-lightbulb"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge rounded-pill text-white" style="font-size: 0.7rem; background-color: var(--accent-projet);">Vote Projet</span>
                                <span class="unread-dot"></span>
                            </div>
                            <h6 class="notif-title fw-bold"><?php echo htmlspecialchars($proj['titre']); ?></h6>
                            <p class="small text-muted mb-0">Nouveau projet soumis au vote.</p>
                            <a href="projets.php?id=<?php echo $proj['id']; ?>" class="btn btn-primary">Voir & Voter</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="col-lg-8 order-lg-1">
            <div class="section-header">
                <i class="fa-solid fa-stream me-2 text-secondary"></i> Flux d'activité récent
            </div>
            <?php if(empty($fluxGlobal)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell-slash fa-2x mb-3 text-muted opacity-50"></i>
                    <p class="text-muted fw-bold">Aucune notification récente.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach($fluxGlobal as $item): 
                        $isPaiement = ($item['type'] === 'paiement');
                        $isProjet = ($item['notif_type'] === 'projet' || stripos($item['message'], 'projet') !== false);
                        // MISE A JOUR : Définition plus large des messages pour inclure bureau, admin, cercles
                        $isMessage = in_array($item['notif_type'], ['message', 'message_cercle', 'message_bureau', 'reponse_bureau', 'contact_admin']);
                        $isNonLu = ($item['statut'] === 'non_lu');
                        $cardClass = $isNonLu ? 'is-unread' : '';
                        // Couleurs de fond
                        if ($isPaiement && $isNonLu) {
                            $cardBgClass = 'notif-bg-paiement';
                        } elseif ($isProjet && $isNonLu) {
                            $cardBgClass = 'notif-bg-projet';
                        } elseif ($isMessage && $isNonLu) {
                            $cardBgClass = 'notif-bg-message';
                        } elseif ($isNonLu) {
                            $cardBgClass = 'notif-bg-unread';
                        } else {
                            $cardBgClass = '';
                        }
                        // Indicateurs de couleur latérale
                        if ($isPaiement) $cardClass .= ' is-paiement';
                        if ($isProjet) $cardClass .= ' is-projet';
                        if ($isMessage) $cardClass .= ' is-message';
                        // Icônes
                        if ($isPaiement) {
                            $icon = 'fa-sack-dollar';
                            $iconBg = 'bg-success bg-opacity-10 text-success';
                            $iconStyle = '';
                        } elseif ($isProjet) {
                            $icon = 'fa-lightbulb';
                            $iconBg = 'bg-info bg-opacity-10 text-info';
                            $iconStyle = 'color: var(--accent-projet) !important; background-color: #f3e8ff !important;';
                        } elseif ($isMessage) {
                            $icon = 'fa-envelope';
                            $iconBg = 'bg-warning bg-opacity-10 text-warning';
                            $iconStyle = '';
                        } else {
                            $icon = ($item['notif_type'] == 'alert') ? 'fa-triangle-exclamation' : 'fa-info';
                            $iconBg = ($item['notif_type'] == 'alert') ? 'bg-danger bg-opacity-10 text-danger' : ($isNonLu ? 'bg-primary bg-opacity-10 text-primary' : 'bg-light text-secondary');
                            $iconStyle = '';
                        }

                        // MISE A JOUR : Logique de redirection des liens
                        $linkUrl = '#'; // Par défaut
                        
                        if ($isMessage) {
                            // Redirection vers messages.php pour tout type de message (incluant bureau)
                            $linkUrl = 'messages.php';
                        } elseif ($isPaiement || $item['notif_type'] == 'finance' || $item['notif_type'] == 'retrait') {
                            $linkUrl = 'cotiser.php';
                        } elseif ($isProjet) {
                            $linkUrl = "projets.php" . ($item['ref_id'] ? "?id=".$item['ref_id'] : "");
                        } elseif ($item['notif_type'] == 'adhesion') {
                            $linkUrl = 'index.php#section-votes';
                        }
                    ?>
                        <div class="notif-card p-3 <?php echo $cardClass . ' ' . $cardBgClass; ?>">
                            <?php if($linkUrl !== '#'): ?>
                                <a href="<?php echo $linkUrl; ?>" class="stretched-link-custom"></a>
                            <?php endif; ?>
                            <div class="status-indicator"></div>
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper <?php echo $iconBg; ?> me-3" style="<?php echo $iconStyle; ?>">
                                    <i class="fa-solid <?php echo $icon; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="notif-title <?php echo $isNonLu ? 'fw-bold' : ''; ?>">
                                            <?php echo $isPaiement ? $item['message'] : htmlspecialchars($item['message']); ?>
                                            <?php if($isNonLu): ?><span class="unread-dot"></span><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="notif-time">
                                        <i class="fa-regular fa-clock me-1"></i>
                                        <?php 
                                            $timestamp = strtotime($item['date']);
                                            $dateJour = date('Y-m-d');
                                            $dateNotif = date('Y-m-d', $timestamp);
                                            
                                            if($dateNotif == $dateJour) {
                                                echo "Aujourd'hui à " . date('H:i', $timestamp);
                                            } elseif($dateNotif == date('Y-m-d', strtotime('-1 day'))) {
                                                echo "Hier à " . date('H:i', $timestamp);
                                            } else {
                                                echo date('d M Y à H:i', $timestamp);
                                            }
                                        ?>
                                        <?php if($isProjet): ?>
                                            <span class="ms-2 text-primary small fw-bold"><i class="fa-solid fa-arrow-right me-1"></i> Voir le projet</span>
                                        <?php elseif($isMessage): ?>
                                            <span class="ms-2 text-warning small fw-bold"><i class="fa-solid fa-reply me-1"></i> Répondre</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if($isNonLu && !$isPaiement && !$isProjet && !$isMessage): ?>
                                    <div class="ms-3">
                                        <a href="?mark_read=<?php echo $item['id']; ?>" class="mark-check-btn" title="Marquer comme lu" data-bs-toggle="tooltip">
                                            <i class="fa-solid fa-check"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="modalParrainage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <form method="POST" action="notifications.php">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 text-primary">Confirmer le Parrainage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-4 mb-3 text-center">
                        <p class="small text-muted mb-1">Candidat à valider :</p>
                        <h5 id="modalFilleulNom" class="fw-bold text-dark m-0"></h5>
                    </div>
                    <input type="hidden" name="filleul_id" id="modalFilleulId">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Votre décision :</label>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="action_parrainage" id="actValider" value="valider" checked onchange="updateLabel(false)">
                            <label class="btn btn-outline-success w-100 rounded-3 py-2 fw-bold" for="actValider"><i class="fa-solid fa-check me-2"></i>APPROUVER</label>                     
                            <input type="radio" class="btn-check" name="action_parrainage" id="actRejeter" value="rejeter" onchange="updateLabel(true)">
                            <label class="btn btn-outline-danger w-100 rounded-3 py-2 fw-bold" for="actRejeter"><i class="fa-solid fa-xmark me-2"></i>REJETER</label>
                        </div>
                    </div>            
                    <div>
                        <label id="labelMotif" class="form-label small fw-bold text-primary">Pourquoi validez-vous ce membre ?</label>
                        <textarea name="motif" id="motifRefus" class="form-control bg-light rounded-3" rows="3" required placeholder="Ce membre est digne de confiance..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<footer class="app-footer text-center">
    <small class="text-muted">&copy; <?php echo date('Y'); ?> NDJANGUI - Plateforme Sécurisée</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    function openParrainageModal(id, nom) {
        document.getElementById('modalFilleulId').value = id;
        document.getElementById('modalFilleulNom').innerText = nom;
        document.getElementById('actValider').checked = true;
        updateLabel(false);
        var modal = new bootstrap.Modal(document.getElementById('modalParrainage'));
        modal.show();
    }
    function updateLabel(isRejet) {
        const label = document.getElementById('labelMotif');
        const textarea = document.getElementById('motifRefus');
        if(isRejet) {
            label.innerText = "Motif du rejet :";
            label.className = "form-label small fw-bold text-danger";
            textarea.placeholder = "Raison du rejet...";
        } else {
            label.innerText = "Pourquoi validez-vous ce membre ?";
            label.className = "form-label small fw-bold text-primary";
            textarea.placeholder = "Ce membre est digne de confiance...";
        }
    }
</script>
</body>
</html>