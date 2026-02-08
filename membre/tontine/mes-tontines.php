<?php
session_start();
require_once '../../config/database.php';

// 1. Sécurité
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Connexion DB
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 2. Récupération des tontines
$sql = "SELECT DISTINCT c.* FROM cercles c
        LEFT JOIN inscriptions_cercle ic ON c.id = ic.cercle_id 
        WHERE c.president_id = ? 
           OR (ic.membre_id = ? AND ic.statut = 'actif')
        ORDER BY c.date_creation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $user_id]);
$tontines = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getNombreMembres($pdo, $cercle_id) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM inscriptions_cercle WHERE cercle_id = ? AND statut = 'actif'");
    $q->execute([$cercle_id]);
    return $q->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Tontines | NDJANGUI</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            /* Palette Premium */
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); /* Indigo to Violet */
            --accent-gradient: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%); /* Cyan to Blue */
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-secondary: #6b7280;
            
            --shadow-soft: 0 10px 40px -10px rgba(0,0,0,0.08);
            --shadow-hover: 0 20px 40px -5px rgba(124, 58, 237, 0.15);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: radial-gradient(circle at 10% 20%, rgba(124, 58, 237, 0.05) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(6, 182, 212, 0.05) 0%, transparent 40%);
            color: var(--text-main);
            min-height: 100vh;
        }

        /* --- HEADER IMPRESSIONNANT --- */
        .hero-section {
            background: var(--primary-gradient);
            padding: 50px 0 100px;
            border-radius: 0 0 50px 50px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(79, 70, 229, 0.25);
        }

        /* Cercles décoratifs arrière plan */
        .hero-bg-circle {
            position: absolute;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .circle-1 { width: 300px; height: 300px; top: -100px; right: -50px; }
        .circle-2 { width: 150px; height: 150px; bottom: 20px; left: 10%; opacity: 0.05; }

        .btn-glass-back {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            transition: 0.3s;
            text-decoration: none;
            font-weight: 500;
        }
        .btn-glass-back:hover { background: rgba(255, 255, 255, 0.3); color: white; transform: translateX(-5px); }

        .btn-add-glow {
            background: white;
            color: #4f46e5;
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 50px;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-add-glow:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.6);
            color: #4f46e5;
        }

        /* --- CARTES ULTRA MODERNES --- */
        .card-container {
            margin-top: -60px; /* Chevauche le header */
            padding-bottom: 50px;
        }

        .tontine-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: none;
            padding: 25px;
            position: relative;
            box-shadow: var(--shadow-soft);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .tontine-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: var(--bg-gradient, #ccc);
        }

        .tontine-card:hover {
            transform: translateY(-12px);
            box-shadow: var(--shadow-hover);
        }

        /* Entête de carte */
        .card-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .icon-box {
            width: 55px; height: 55px;
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .grad-hebdo { background: linear-gradient(135deg, #3b82f6, #2563eb); --bg-gradient: linear-gradient(to right, #3b82f6, #2563eb); }
        .grad-mensuel { background: linear-gradient(135deg, #10b981, #059669); --bg-gradient: linear-gradient(to right, #10b981, #059669); }
        .grad-libre { background: linear-gradient(135deg, #f59e0b, #d97706); --bg-gradient: linear-gradient(to right, #f59e0b, #d97706); }

        .status-pill {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-open { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
        .status-closed { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        .tontine-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
            line-height: 1.3;
        }

        .role-tag {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #6366f1;
            background: #eef2ff;
            padding: 4px 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            background: #f9fafb;
            padding: 15px;
            border-radius: 16px;
            margin-bottom: 20px;
        }
        
        .stat-item h6 { font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; font-weight: 700; margin: 0; }
        .stat-item p { font-size: 1.1rem; color: var(--text-main); font-weight: 800; margin: 0; }
        .money-text { background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* Bouton Action */
        .btn-card-action {
            margin-top: auto;
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: 0.3s;
            display: flex; justify-content: center; align-items: center; gap: 8px;
        }
        
        .btn-president {
            background: var(--text-main);
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .btn-president:hover { background: black; color: white; transform: scale(1.02); }

        .btn-membre {
            background: white;
            color: var(--text-main);
            border: 2px solid #e5e7eb;
        }
        .btn-membre:hover { border-color: var(--text-main); color: var(--text-main); background: #f9fafb; }

        /* Empty State */
        .empty-box {
            background: white;
            border-radius: 30px;
            padding: 60px 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.05);
            max-width: 500px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .hero-section { padding: 40px 0 80px; text-align: center; }
            .hero-content { display: flex; flex-direction: column; align-items: center; }
            .btn-glass-back { margin-bottom: 20px; align-self: center; }
            .btn-add-glow { width: 100%; justify-content: center; margin-top: 20px; }
            .card-container { margin-top: -40px; }
        }
    </style>
</head>
<body>

    <div class="hero-section">
        <div class="hero-bg-circle circle-1"></div>
        <div class="hero-bg-circle circle-2"></div>
        
        <div class="container position-relative z-1">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                
                <div class="hero-content animate__animated animate__fadeInLeft">
                    <a href="../index.php" class="btn-glass-back mb-3 d-inline-block">
                        <i class="fa-solid fa-arrow-left me-2"></i>Dashboard
                    </a>
                    <h1 class="text-white fw-800 display-5 mb-1">Mes Tontines</h1>
                    <p class="text-white opacity-75 fs-5">Gérez vos cercles financiers avec élégance.</p>
                </div>

                <div class="animate__animated animate__fadeInRight">
                    <a href="../creer-tontine.php" class="btn-add-glow">
                        <i class="fa-solid fa-plus-circle fa-lg"></i>
                        <span>Nouvelle Tontine</span>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <div class="container card-container">
        
        <?php if (empty($tontines)): ?>
            <div class="empty-box animate__animated animate__zoomIn">
                <div class="mb-4">
                    <img src="https://cdn-icons-png.flaticon.com/512/9485/9485945.png" width="100" alt="Vide">
                </div>
                <h2 class="fw-bold mb-3">Aucune tontine active</h2>
                <p class="text-muted mb-4">C'est un peu vide ici. Lancez votre propre cercle ou rejoignez vos amis pour commencer à épargner !</p>
                <div class="d-grid gap-2 col-md-8 mx-auto">
                    <a href="../creer-tontine.php" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">Créer une Tontine</a>
                    <a href="../rejoindre-tontine.php" class="btn btn-light text-primary btn-lg rounded-pill fw-bold border">J'ai un code invitation</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-xl-3">
                <?php foreach ($tontines as $index => $cercle): ?>
                    <?php 
                        $isPresident = ($cercle['president_id'] == $user_id);
                        $isOpen = ($cercle['statut'] == 'ouvert');
                        $nbMembres = getNombreMembres($pdo, $cercle['id']);
                        if($nbMembres == 0) $nbMembres = 1;

                        // Configuration visuelle dynamique
                        if($cercle['frequence'] == 'hebdomadaire') {
                            $icon = 'fa-calendar-day';
                            $gradClass = 'grad-hebdo';
                            $freqText = 'Hebdomadaire';
                        } elseif($cercle['frequence'] == 'libre') {
                            $icon = 'fa-calendar-check';
                            $gradClass = 'grad-libre';
                            $freqText = 'Libre (' . $cercle['intervalle_libre'] . 'j)';
                        } else {
                            $icon = 'fa-calendar-week';
                            $gradClass = 'grad-mensuel';
                            $freqText = 'Mensuel';
                        }
                        
                        // Petite animation en cascade
                        $delay = $index * 0.1;
                    ?>

                    <div class="col animate__animated animate__fadeInUp" style="animation-delay: <?php echo $delay; ?>s;">
                        <div class="tontine-card" style="<?php echo $gradClass; /* Variable CSS pour la bordure top */ ?>">
                            
                            <div class="card-header-custom">
                                <div class="icon-box <?php echo $gradClass; ?>">
                                    <i class="fa-solid <?php echo $icon; ?>"></i>
                                </div>
                                <div class="status-pill <?php echo $isOpen ? 'status-open' : 'status-closed'; ?>">
                                    <?php echo $isOpen ? '● Actif' : '● Fermé'; ?>
                                </div>
                            </div>

                            <h3 class="tontine-title text-truncate" title="<?= htmlspecialchars($cercle['nom_cercle']) ?>">
                                <?php echo htmlspecialchars($cercle['nom_cercle']); ?>
                            </h3>

                            <div>
                                <?php if($isPresident): ?>
                                    <span class="role-tag"><i class="fa-solid fa-crown me-1"></i> Président</span>
                                <?php else: ?>
                                    <span class="role-tag text-secondary bg-light"><i class="fa-solid fa-user me-1"></i> Membre</span>
                                <?php endif; ?>
                            </div>

                            <div class="stats-grid">
                                <div class="stat-item border-end pe-2">
                                    <h6>Cotisation</h6>
                                    <p class="money-text"><?php echo number_format($cercle['montant_cotisation_standard'], 0, ',', ' '); ?> <span style="font-size:0.7em; color:#9ca3af">FCFA</span></p>
                                </div>
                                <div class="stat-item ps-2">
                                    <h6>Membres</h6>
                                    <p><?php echo $nbMembres; ?></p>
                                </div>
                                <div class="stat-item mt-2 pt-2 border-top col-span-2" style="grid-column: span 2;">
                                    <h6>Fréquence</h6>
                                    <p style="font-size: 0.95rem; font-weight: 600; color: #4b5563;">
                                        <i class="fa-regular fa-clock me-1 text-muted"></i> <?php echo $freqText; ?>
                                    </p>
                                </div>
                            </div>

                            <a href="admin-seances.php?cercle_id=<?php echo $cercle['id']; ?>" 
                               class="btn-card-action <?php echo $isPresident ? 'btn-president' : 'btn-membre'; ?>">
                                <?php if($isPresident): ?>
                                    Gérer mon Cercle <i class="fa-solid fa-arrow-right-long"></i>
                                <?php else: ?>
                                    Accéder au Cercle <i class="fa-solid fa-chevron-right"></i>
                                <?php endif; ?>
                            </a>

                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-5">
            <p class="text-muted small fw-bold opacity-50">NDJANGUI &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>