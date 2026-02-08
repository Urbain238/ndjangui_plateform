<?php
session_start();
require_once '../config/database.php';

// 1. Récupération via la session
$user_id = $_SESSION['user_id'] ?? null;
$nom_session = $_SESSION['waiting_name'] ?? null;

if (!$user_id && !$nom_session) {
    header('Location: login.php');
    exit();
}

$pdo = Database::getConnection();

// 2. Chercher les informations réelles en base de données
try {
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id, nom_complet, statut_validation, code_promo, plaidoyer_parrain FROM membres WHERE id = ?");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, nom_complet, statut_validation, code_promo, plaidoyer_parrain FROM membres WHERE nom_complet = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$nom_session]);
    }
    $user = $stmt->fetch(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Mise à jour de la session pour assurer la continuité
if (!$user_id) { $_SESSION['user_id'] = $user->id; }

// --- LOGIQUE MISE À JOUR : CALCUL DU QUORUM ET VALIDATION AUTOMATIQUE ---
$pourcentage_pour = 0;
$total_votes = 0;
$totalMembresAdmis = 0;

if ($user->statut_validation === 'en_vote') {
    // A. Compter le nombre de membres admis (le quorum autorisé à voter)
    $stmtMembresAdmis = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut_validation = 'admis'");
    $totalMembresAdmis = (int)$stmtMembresAdmis->fetchColumn();

    // B. Récupérer les statistiques de votes pour ce candidat
    $stmt_vote = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN choix = 'pour' THEN 1 ELSE 0 END) as positifs
        FROM votes_decisions 
        WHERE type_vote = 'adhesion' AND reference_id = ?
    ");
    $stmt_vote->execute([$user->id]);
    $stats = $stmt_vote->fetch(PDO::FETCH_OBJ);
    
    if ($stats && $stats->total > 0) {
        $total_votes = $stats->total;
        
        // Calcul du pourcentage basé sur le quorum (membres admis)
        $denominateur = ($totalMembresAdmis > 0) ? $totalMembresAdmis : 1;
        $pourcentage_pour = round(($stats->positifs / $denominateur) * 100);
        
        // C. VÉRIFICATION DE LA MAJORITÉ (50% + 1)
        $seuil = floor($totalMembresAdmis / 2) + 1;
        
        if ($stats->positifs >= $seuil) {
            // Génération d'un code promo unique de 8 caractères
            $nouveau_code = "NDJ-" . strtoupper(substr(md5(uniqid()), 0, 8));
            
            // MISE À JOUR BASE DE DONNÉES : Passage au statut 'admis' et insertion du code_promo
            $upd = $pdo->prepare("UPDATE membres SET statut_validation = 'admis', code_promo = ? WHERE id = ?");
            $upd->execute([$nouveau_code, $user->id]);
            
            // Rafraîchir l'objet user pour l'affichage immédiat
            $user->statut_validation = 'admis';
            $user->code_promo = $nouveau_code;
        }
    }
}

// 3. Logique des variables d'affichage
$statut = $user->statut_validation ?? 'en_attente_parrain';
$nom_complet = $user->nom_complet ?? 'Candidat';
$prenom = !empty($nom_complet) ? explode(' ', trim($nom_complet))[0] : 'Cher membre';
$motif_affichage = $user->plaidoyer_parrain ?? "Critères d'éligibilité ou garanties insuffisantes.";

// Logique des étapes (Stepper)
$step1_class = ($statut !== 'en_attente_parrain' && $statut !== 'rejete') ? 'completed' : 'active';
$step2_class = ($statut === 'en_vote') ? 'active' : (($statut === 'admis') ? 'completed' : '');
$step3_class = ($statut === 'admis') ? 'active completed' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace d'attente | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #1e3a8a;
            --secondary: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light-bg: #f8fafc;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: radial-gradient(circle at top right, #e2e8f0, #f8fafc);
            min-height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .status-card {
            background: white;
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            max-width: 550px;
            width: 90%;
            text-align: center;
            border: 1px solid #f1f5f9;
        }

        .status-icon { font-size: 4.5rem; margin-bottom: 20px; }

        .promo-box {
            background: #ecfdf5;
            border: 2px dashed var(--secondary);
            border-radius: 16px;
            padding: 15px;
            margin: 20px 0;
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--primary);
            letter-spacing: 3px;
        }

        .vote-progress {
            height: 10px;
            background-color: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .vote-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            position: relative;
        }

        .step-indicator::before {
            content: "";
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step {
            z-index: 2;
            background: white;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #e2e8f0;
            color: #cbd5e1;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .step.active { border-color: var(--primary); color: var(--primary); transform: scale(1.15); box-shadow: 0 0 15px rgba(30,58,138,0.2); }
        .step.completed { background: var(--secondary); border-color: var(--secondary); color: white; }

        .btn-refresh {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-refresh:hover { background: #e2e8f0; color: var(--primary); }
        
        .x-small { font-size: 0.7rem; }
        .fw-600 { font-weight: 600; }
        .nav-link-custom { text-decoration: none !important; }

        .fa-spin-slow {
            animation: fa-spin 3s infinite linear;
        }
    </style>
</head>
<body>

    <div class="status-card animate__animated animate__fadeInUp">
        
        <?php if($statut === 'admis'): ?>
            <div class="status-icon text-secondary">
                <i class="fa-solid fa-circle-check animate__animated animate__bounceIn"></i>
            </div>
            <h2 class="fw-800 text-primary">Bienvenue, <?php echo htmlspecialchars($prenom); ?> !</h2>
            <p class="text-muted small">Félicitations ! Votre adhésion a été validée à la majorité. Vous faites désormais partie de l'élite NDJANGUI.</p>
            
            <p class="small text-uppercase fw-bold text-muted mb-1">Votre Code Parrain :</p>
            <div class="promo-box">
                <?php echo htmlspecialchars($user->code_promo ?? 'ACTUALISATION...'); ?>
            </div>
            
            <a href="index.php" class="btn btn-primary btn-lg w-100 rounded-pill shadow-lg mt-3" style="background: var(--primary); border: none;">
                ENTRER DANS MON ESPACE <i class="fa-solid fa-door-open ms-2"></i>
            </a>

        <?php elseif($statut === 'rejete'): ?>
            <div class="status-icon text-danger">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <h2 class="fw-800 text-primary">Candidature Refusée</h2>
            <p class="text-muted small">Nous regrettons de vous informer que votre profil n'a pas été retenu par la communauté.</p>
            <div class="alert alert-danger border-0 text-start rounded-4 p-3">
                <p class="fw-bold mb-1 small"><i class="fa-solid fa-comment-dots me-2"></i>Motif du parrain :</p>
                <span class="small"><?php echo htmlspecialchars($motif_affichage); ?></span>
            </div>
            <p class="x-small text-muted mt-3">Vous pouvez contacter le support pour plus d'informations.</p>

        <?php else: ?>
            <div class="status-icon text-warning">
                <i class="fa-solid <?php echo ($statut === 'en_vote') ? 'fa-square-poll-vertical animate__animated animate__pulse animate__infinite' : 'fa-hourglass-half fa-spin-slow'; ?>"></i>
            </div>
            <h2 class="fw-800 text-primary"><?php echo ($statut === 'en_vote') ? 'Vote communautaire' : 'Analyse en cours...'; ?></h2>
            <p class="text-muted small">Veuillez patienter pendant que nous sécurisons votre adhésion.</p>
            
            <div class="step-indicator">
                <div class="step <?php echo $step1_class; ?>">
                    <i class="fa-solid <?php echo ($step1_class === 'completed') ? 'fa-check' : 'fa-user-shield'; ?>"></i>
                </div>
                <div class="step <?php echo $step2_class; ?>">
                    <i class="fa-solid <?php echo ($step2_class === 'completed') ? 'fa-check' : 'fa-users-viewfinder'; ?>"></i>
                </div>
                <div class="step <?php echo $step3_class; ?>">
                    <i class="fa-solid fa-trophy"></i>
                </div>
            </div>
            <div class="d-flex justify-content-between mt-2 mb-4" style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">
                <span>PARRAIN</span>
                <span>VOTE DES MEMBRES</span>
                <span>ADHÉSION</span>
            </div>

            <div class="p-3 rounded-4 bg-light mb-3 text-start">
                <?php if($statut === 'en_attente_parrain'): ?>
                    <span class="badge bg-warning text-dark mb-2">ÉTAPE 1</span>
                    <p class="small text-dark mb-0 fw-600">En attente de votre parrain.</p>
                    <p class="x-small text-muted mb-0">Votre parrain doit confirmer votre moralité pour ouvrir le vote à la communauté.</p>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-info text-white mb-2">ÉTAPE 2</span>
                        <span class="fw-bold text-primary" style="font-size: 0.9rem;"><?php echo $pourcentage_pour; ?>% favorables</span>
                    </div>
                    <div class="vote-progress">
                        <div class="vote-progress-bar" style="width: <?php echo $pourcentage_pour; ?>%"></div>
                    </div>
                    <p class="small text-dark mb-0 fw-600">Le vote est ouvert !</p>
                    <p class="x-small text-muted mb-0">Basé sur les votes des membres admis. Score actuel : <strong><?php echo $total_votes; ?></strong> voix sur <strong><?php echo $totalMembresAdmis; ?></strong> membres votants.</p>
                <?php endif; ?>
            </div>

            <button onclick="location.reload()" class="btn-refresh mb-3">
                <i class="fa-solid fa-rotate me-1"></i> Actualiser mon statut
            </button>
        <?php endif; ?>

        <div class="border-top mt-4 pt-2">
            <a href="../logout.php" class="nav-link-custom text-danger mt-auto">
                <i class="fa-solid fa-power-off me-2"></i> Quitter la session
            </a>
        </div>
    </div>

    <script>
        setTimeout(function() {
            window.location.reload();
        }, 15000);
    </script>
</body>
</html>