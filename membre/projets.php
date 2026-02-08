<?php
session_start();
require_once '../config/database.php';

// --- SÉCURITÉ ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? 'Membre';

// --- RÉCUPÉRATION DES DONNÉES ---
$projets = [];
try {
    $sql = "
        SELECT p.*, 
               m.nom_complet as createur,
               (SELECT COUNT(*) FROM votes_decisions v WHERE v.reference_id = p.id AND v.type_vote = 'projet' AND v.choix = 'pour') as nb_pour,
               (SELECT COUNT(*) FROM votes_decisions v WHERE v.reference_id = p.id AND v.type_vote = 'projet' AND v.choix = 'contre') as nb_contre,
               CASE WHEN EXISTS (SELECT 1 FROM votes_decisions v2 WHERE v2.reference_id = p.id AND v2.membre_id = :uid AND v2.type_vote = 'projet') THEN 1 ELSE 0 END as a_vote
        FROM projets p
        JOIN membres m ON p.membre_id = m.id
        ORDER BY FIELD(p.statut, 'en_attente', 'approuve', 'rejete'), p.date_creation DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investissements | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            /* Palette sombre riche et profonde */
            --bg-dark: #0f172a;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            
            /* Couleurs d'accentuation éclatantes */
            --accent-primary: #6366f1; /* Indigo vibrant */
            --accent-glow: 0 0 20px rgba(99, 102, 241, 0.6);
            --text-light: #f8fafc;
            --text-dim: #94a3b8;
            
            /* Cards */
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-border: 1px solid rgba(255, 255, 255, 0.1);
            
            /* Status */
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed; /* Le fond reste fixe au scroll */
            color: var(--text-light);
            min-height: 100vh;
            padding-bottom: 80px;
            overflow-x: hidden;
        }

        /* --- Arrière-plan animé (Subtil) --- */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 40%),
                        radial-gradient(circle, rgba(16,185,129,0.1) 60%, transparent 80%);
            z-index: -1;
            animation: rotateBG 20s linear infinite;
        }
        @keyframes rotateBG { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* --- Navbar Glassmorphism --- */
        .navbar-glass {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 1rem 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.6rem;
            color: white !important;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
        }

        .btn-nav-glass {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-nav-glass:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            box-shadow: 0 0 15px rgba(255,255,255,0.1);
        }

        /* --- Hero Section --- */
        .page-header {
            margin: 4rem 0 3rem 0;
        }
        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 3rem;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -1px;
        }
        
        /* Bouton "Nouveau Projet" Éclatant */
        .btn-glow {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }
        .btn-glow:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.6);
            color: white;
        }

        /* --- Cards Design (Le cœur du design) --- */
        .project-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .project-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            z-index: 10;
        }

        /* Effet de brillance au survol */
        .project-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(255,255,255,0.4) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .project-card:hover::after { opacity: 1; }

        .card-body-custom {
            padding: 2rem;
            flex-grow: 1;
            z-index: 2;
        }

        /* Ruban Voté */
        .voted-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #10b981;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 8px 15px;
            border-bottom-left-radius: 20px;
            box-shadow: -2px 2px 10px rgba(16, 185, 129, 0.3);
            z-index: 5;
        }

        /* En-tête de carte */
        .creator-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }
        .avatar-gradient {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: #4338ca;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(199, 210, 254, 0.5);
        }

        /* Titre & Montant */
        .card-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.4rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .amount-highlight {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: block;
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }
        .currency {
            font-size: 1rem;
            font-weight: 600;
            color: #94a3b8;
            vertical-align: super;
            -webkit-text-fill-color: #94a3b8;
        }

        .card-desc {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Barre de progression stylée */
        .vote-track {
            height: 8px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            margin-top: auto;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }
        .bar-pour { background: linear-gradient(90deg, #34d399 0%, #10b981 100%); }
        .bar-contre { background: linear-gradient(90deg, #f87171 0%, #ef4444 100%); }

        .vote-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Bouton Action Carte */
        .card-action-area {
            padding: 1.5rem 2rem;
            background: #fafafa;
            border-top: 1px dashed #e2e8f0;
        }
        
        .btn-vote-main {
            width: 100%;
            background: var(--bg-dark);
            color: white;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
        }
        .btn-vote-main:hover {
            background: #1e293b;
            color: white;
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(15, 23, 42, 0.2);
        }

        .btn-view-details {
            width: 100%;
            background: white;
            color: #475569;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-view-details:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
        }

        /* Status Pills */
        .status-pill {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: auto;
        }
        .pill-en_attente { background: #fff7ed; color: #ea580c; }
        .pill-approuve { background: #ecfdf5; color: #059669; }
        .pill-rejete { background: #fef2f2; color: #dc2626; }

    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fa-solid fa-handshake-simple fs-2 me-2 text-white"></i> 
                NDJANGUI
            </a>
            
            <div class="ms-auto d-flex gap-3">
                <a href="index.php" class="btn-nav-glass d-none d-sm-flex">
                    <i class="fa-solid fa-layer-group"></i> Dashboard
                </a>
                <a href="../logout.php" class="btn-nav-glass text-danger border-danger border-opacity-25" style="color: #fca5a5 !important;">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="row align-items-end page-header animate__animated animate__fadeInDown">
            <div class="col-lg-7">
                <span class="text-uppercase fw-bold text-white opacity-50 small mb-2 d-block ls-1">Communauté & Finance</span>
                <h1 class="page-title">Projets d'Investissement</h1>
                <p class="text-white opacity-75 fs-5 mb-0" style="max-width: 600px;">
                    Façonnez l'avenir de la communauté. Analysez, débattez et financez les initiatives de demain.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                <a href="nouveau_projet.php" class="btn-glow animate__animated animate__pulse animate__infinite animate__slow">
                    <i class="fa-solid fa-plus-circle"></i> Soumettre un Projet
                </a>
            </div>
        </div>

        <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-lg rounded-4 mb-5 animate__animated animate__shakeX">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 pb-5">
            
            <?php if(empty($projets)): ?>
                <div class="col-12 text-center py-5 animate__animated animate__fadeInUp">
                    <div class="bg-white bg-opacity-10 rounded-4 p-5 backdrop-blur shadow-lg border border-white border-opacity-10 mx-auto" style="max-width: 600px;">
                        <i class="fa-regular fa-folder-open fa-4x text-white opacity-25 mb-3"></i>
                        <h3 class="text-white fw-bold">Aucun projet en cours</h3>
                        <p class="text-white opacity-75">Soyez le pionnier. Proposez la première opportunité d'investissement.</p>
                        <a href="nouveau_projet.php" class="btn btn-outline-light rounded-pill px-4 mt-2">Commencer</a>
                    </div>
                </div>
            <?php else: ?>

                <?php foreach($projets as $index => $p): 
                    // Calculs
                    $date = new DateTime($p['date_creation']);
                    $total_votes = $p['nb_pour'] + $p['nb_contre'];
                    $pour_pct = $total_votes > 0 ? ($p['nb_pour'] / $total_votes) * 100 : 0;
                    $contre_pct = $total_votes > 0 ? ($p['nb_contre'] / $total_votes) * 100 : 0;
                    
                    // Animation delay pour effet cascade
                    $delay = $index * 0.1; 
                ?>
                <div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: <?php echo $delay; ?>s;">
                    <div class="project-card">
                        
                        <?php if($p['a_vote']): ?>
                            <div class="voted-badge">
                                <i class="fa-solid fa-check-circle me-1"></i> A VOTÉ
                            </div>
                        <?php endif; ?>

                        <div class="card-body-custom">
                            
                            <div class="creator-info">
                                <div class="avatar-gradient">
                                    <?php echo strtoupper(substr($p['createur'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small mb-0"><?php echo htmlspecialchars($p['createur']); ?></div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                        <i class="fa-regular fa-calendar me-1"></i> <?php echo $date->format('d M Y'); ?>
                                    </div>
                                </div>
                                <span class="status-pill pill-<?php echo $p['statut']; ?>">
                                    <?php echo match($p['statut']) { 'en_attente'=>'En Vote', 'approuve'=>'Validé', 'rejete'=>'Refusé', default=>$p['statut'] }; ?>
                                </span>
                            </div>

                            <h3 class="card-title"><?php echo htmlspecialchars($p['titre']); ?></h3>
                            <span class="amount-highlight">
                                <?php echo number_format($p['montant_demande'], 0, ',', ' '); ?> <span class="currency">FCFA</span>
                            </span>
                            
                            <p class="card-desc">
                                <?php echo htmlspecialchars($p['description']); ?>
                            </p>

                            <?php if($total_votes > 0): ?>
                                <div class="mt-auto">
                                    <div class="vote-track">
                                        <div class="bar-pour" style="width: <?php echo $pour_pct; ?>%"></div>
                                        <div class="bar-contre" style="width: <?php echo $contre_pct; ?>%"></div>
                                    </div>
                                    <div class="vote-stats">
                                        <span class="text-success"><?php echo $p['nb_pour']; ?> POUR</span>
                                        <span class="text-danger"><?php echo $p['nb_contre']; ?> CONTRE</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mt-auto pt-2">
                                    <div class="text-center small text-muted fst-italic py-2 bg-light rounded-3">
                                        Aucun vote enregistré
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-action-area">
                            <?php if($p['statut'] === 'en_attente'): ?>
                                <?php if($p['a_vote']): ?>
                                    <a href="voter_projet.php?id=<?php echo $p['id']; ?>" class="btn-view-details">
                                        <i class="fa-solid fa-eye me-2"></i> Voir mon vote
                                    </a>
                                <?php else: ?>
                                    <a href="voter_projet.php?id=<?php echo $p['id']; ?>" class="btn-vote-main">
                                        <i class="fa-solid fa-gavel"></i> Examiner & Voter
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="voter_projet.php?id=<?php echo $p['id']; ?>" class="btn-view-details">
                                    <i class="fa-solid fa-chart-pie me-2"></i> Voir les détails
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>