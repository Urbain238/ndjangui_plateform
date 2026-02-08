<?php
session_start();
require_once '../../config/database.php';

// --- 1. LOGIQUE BACKEND (INCHANGÉE & SÉCURISÉE) ---
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../login.php");
    exit;
}

$cercle_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$pdo = Database::getConnection();

// Infos du Cercle
$stmt = $pdo->prepare("SELECT * FROM cercles WHERE id = ?");
$stmt->execute([$cercle_id]);
$cercle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cercle) die("Tontine introuvable.");

// Vérifier si membre actif
$stmtMembre = $pdo->prepare("SELECT * FROM inscriptions_cercle WHERE cercle_id = ? AND membre_id = ? AND statut = 'actif'");
$stmtMembre->execute([$cercle_id, $user_id]);
$monInscription = $stmtMembre->fetch(PDO::FETCH_ASSOC);

if (!$monInscription) {
    header("Location: mes-tontines.php?error=acces_interdit");
    exit;
}

// Détection Admin (Président)
$isAdmin = ($cercle['president_id'] == $user_id);

// Récupération de la prochaine séance pour le compte à rebours
$stmtSeance = $pdo->prepare("
    SELECT * FROM seances 
    WHERE cercle_id = ? 
    AND CONCAT(date_seance, ' ', heure_limite_pointage) > NOW() 
    AND statut != 'cloture'
    ORDER BY date_seance ASC 
    LIMIT 1
");
$stmtSeance->execute([$cercle_id]);
$prochaineSeance = $stmtSeance->fetch(PDO::FETCH_ASSOC);

$targetDate = $prochaineSeance ? $prochaineSeance['date_seance'] . ' ' . $prochaineSeance['heure_limite_pointage'] : null;
$nbMembres = $pdo->query("SELECT COUNT(*) FROM inscriptions_cercle WHERE cercle_id = $cercle_id AND statut='actif'")->fetchColumn();

// Calcul Cagnotte (Simplifié : Montant x Membres)
$cagnottePotentielle = $cercle['montant_unitaire'] * $nbMembres;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cercle['nom_cercle']) ?> | Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --sidebar-width: 280px;
            /* Changement vers une couleur "Argent/Finance" (Vert Émeraude Profond) */
            --primary-gradient: linear-gradient(135deg, #059669 0%, #064e3b 100%);
            --primary-color: #059669;
            --secondary-color: #10b981;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --bg-body: #f8fafc;
            --text-dark: #0f172a;
            --text-light: #64748b;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-body); 
            overflow-x: hidden; 
        }

        h1, h2, h3, h4, .brand-text { font-family: 'Outfit', sans-serif; }

        /* --- SIDEBAR DESIGN --- */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            z-index: 1050; /* Au-dessus de tout */
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column;
            padding: 24px;
            box-shadow: 4px 0 24px rgba(0,0,0,0.02);
        }

        .brand-box {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 35px; padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .brand-left { display: flex; align-items: center; gap: 12px; }

        .brand-icon {
            width: 44px; height: 44px;
            background: var(--primary-gradient);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }
        .brand-text { font-weight: 800; font-size: 1.1rem; color: var(--text-dark); line-height: 1.2; letter-spacing: -0.5px;}

        /* Close btn for mobile */
        .btn-close-sidebar {
            background: none; border: none; font-size: 1.2rem; color: var(--text-light);
            cursor: pointer; padding: 5px; transition: 0.2s;
        }
        .btn-close-sidebar:hover { color: red; transform: rotate(90deg); }

        /* Navigation Links */
        .nav-link {
            color: var(--text-dark);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 6px;
            font-weight: 600; font-size: 0.95rem;
            display: flex; justify-content: space-between; align-items: center;
            transition: all 0.2s;
            text-decoration: none;
        }
        .nav-link:hover, .nav-link[aria-expanded="true"] {
            background-color: #ecfdf5; /* Vert très clair */
            color: var(--primary-color);
            transform: translateX(4px);
        }
        
        .icon-wrap {
            width: 26px; height: 26px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-right: 12px; border-radius: 6px;
            background: #f1f5f9; color: var(--text-light);
            transition: 0.2s;
        }
        .nav-link:hover .icon-wrap, .nav-link.active .icon-wrap { background: var(--primary-color); color: white; }
        
        .nav-link.active {
            background-color: #ecfdf5; color: var(--primary-color);
        }
        
        .nav-link i.arrow { font-size: 0.75rem; opacity: 0.5; transition: transform 0.3s; }
        .nav-link[aria-expanded="true"] i.arrow { transform: rotate(180deg); }

        /* Submenu */
        .collapse-inner {
            padding-left: 12px; margin-bottom: 15px;
            border-left: 2px solid #e2e8f0; margin-left: 23px;
        }
        .sub-link {
            display: block; padding: 8px 15px;
            font-size: 0.85rem; color: var(--text-light);
            text-decoration: none; font-weight: 500;
            transition: 0.2s; position: relative;
            display: flex; align-items: center;
        }
        .sub-link i { margin-right: 8px; font-size: 0.8rem; opacity: 0.7;}
        .sub-link:hover { color: var(--primary-color); padding-left: 18px; }
        
        /* User Footer */
        .user-footer {
            margin-top: auto;
            background: #f8fafc; border: 1px solid #e2e8f0;
            padding: 12px; border-radius: 12px;
            display: flex; align-items: center; gap: 12px;
        }

        /* --- MAIN CONTENT --- */
        .main-content { margin-left: var(--sidebar-width); padding: 30px 40px; transition: 0.3s; }

        /* Overlay pour mobile */
        .sidebar-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1040;
            display: none; opacity: 0; transition: opacity 0.3s;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active { display: block; opacity: 1; }

        /* HERO CARD */
        .hero-card {
            background: var(--primary-gradient);
            border-radius: 24px; padding: 40px;
            color: white; position: relative; overflow: hidden;
            box-shadow: 0 20px 50px -12px rgba(5, 150, 105, 0.4);
            margin-bottom: 35px;
        }
        .hero-bg-shape {
            position: absolute; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
            top: -150px; right: -100px; border-radius: 50%;
            z-index: 0;
        }

        .timer-container { display: flex; gap: 15px; margin-top: 30px; flex-wrap: wrap;}
        
        .timer-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 15px 20px; 
            border-radius: 16px; 
            text-align: center; 
            min-width: 85px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
            position: relative; z-index: 2;
        }
        .timer-val { font-size: 2rem; font-weight: 800; line-height: 1; text-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .timer-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; opacity: 0.9; }
        
        .timer-box.seconds { border-color: rgba(255, 215, 0, 0.5); }
        .timer-box.seconds .timer-val { color: #ffd700; }

        /* STATS CARDS */
        .stat-card {
            background: white; border-radius: 20px; padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9;
            transition: transform 0.3s, box-shadow 0.3s; height: 100%; position: relative; overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.06); }
        .stat-icon-large {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 20px;
        }
        .bg-icon-faded {
            position: absolute; right: -15px; bottom: -20px;
            font-size: 6rem; opacity: 0.05; transform: rotate(-15deg);
        }

        /* Responsive Mobile */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
            
            /* Ajustement Timer Mobile */
            .timer-container { gap: 8px; justify-content: space-between; }
            .timer-box { padding: 10px 5px; min-width: calc(25% - 8px); flex-grow: 1; }
            .timer-val { font-size: 1.4rem; }
            .timer-label { font-size: 0.6rem; }
            
            /* Navbar Header Mobile */
            .mobile-header { display: flex; }
            
            .hero-card { padding: 25px; }
        }

        .hover-up { transition: 0.3s; cursor: pointer; }
        .hover-up:hover { transform: translateY(-3px); }
        
        /* Utilitaires Z-Index */
        .z-1 { z-index: 1; position: relative; }
        .z-2 { z-index: 2; position: relative; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="brand-box">
        <div class="brand-left">
            <div class="brand-icon">
                <i class="fa-solid fa-hands-holding-circle"></i>
            </div>
            <div class="brand-text">
                <?= htmlspecialchars($cercle['nom_cercle']) ?>
            </div>
        </div>
        <button class="d-md-none btn-close-sidebar" onclick="closeSidebar()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="flex-grow-1 overflow-auto" style="scrollbar-width: none;">
        
        <a href="#" class="nav-link active">
            <div class="d-flex align-items-center">
                <span class="icon-wrap"><i class="fa-solid fa-chart-pie"></i></span> 
                Vue globale
            </div>
        </a>

        <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#menuFinance" aria-expanded="false">
            <div class="d-flex align-items-center">
                <span class="icon-wrap"><i class="fa-solid fa-wallet"></i></span>
                Finance
            </div>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </a>
        <div class="collapse collapse-inner" id="menuFinance">
            <a href="cotiser.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-solid fa-circle-plus"></i> Faire une cotisation</a>
            <a href="retirer.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-solid fa-hand-holding-dollar"></i> Demande de retrait</a>
            <a href="historique.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-solid fa-clock-rotate-left"></i> Historique</a>
        </div>

        <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#menuCommunaute" aria-expanded="false">
            <div class="d-flex align-items-center">
                <span class="icon-wrap"><i class="fa-solid fa-users"></i></span>
                Communauté
            </div>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </a>
        <div class="collapse collapse-inner" id="menuCommunaute">
            <a href="forum.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-solid fa-comments"></i> Forum & Discussion</a>
            <a href="membres.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-solid fa-address-book"></i> Liste des membres</a>
        </div>

        <?php if($isAdmin): ?>
            <div class="mt-4 mb-2 px-2 text-uppercase small fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 1px;">Administration</div>
            
            <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#menuAdmin" aria-expanded="false">
                <div class="d-flex align-items-center">
                    <span class="icon-wrap bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-user-shield"></i></span>
                    Gestion
                </div>
                <i class="fa-solid fa-chevron-down arrow"></i>
            </a>
            <div class="collapse collapse-inner" id="menuAdmin">
                <a href="admin-seances.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-regular fa-calendar-check"></i> Programmer Séances</a>
                <a href="parametres.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-solid fa-sliders"></i> Paramètres Tontine</a>
                <a href="demandes.php?id=<?= $cercle_id ?>" class="sub-link"><i class="fa-solid fa-check-double"></i> Validations <span class="badge bg-danger rounded-pill ms-1" style="font-size:0.6rem;">2</span></a>
            </div>
        <?php endif; ?>

        <a href="mes-tontines.php" class="nav-link mt-4 text-danger">
            <div class="d-flex align-items-center">
                <span class="icon-wrap bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-right-from-bracket"></i></span>
                Quitter
            </div>
        </a>
    </nav>

    <div class="user-footer">
        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-weight: bold;">
            <?= strtoupper(substr($_SESSION['user_id'], 0, 1)) ?>
        </div>
        <div style="line-height: 1.3;">
            <div class="fw-bold text-dark small">Mon Compte</div>
            <div class="text-muted" style="font-size: 0.75rem;">
                <i class="fa-solid fa-coins text-warning me-1"></i> <?= $monInscription['nombre_parts'] ?> part(s)
            </div>
        </div>
    </div>
</aside>


<main class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4 d-md-none">
        <button class="btn btn-light shadow-sm border" onclick="openSidebar()" style="color: var(--primary-color);">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>
        <h5 class="fw-bold m-0 text-dark"><?= htmlspecialchars($cercle['nom_cercle']) ?></h5>
        <div style="width: 40px;"> </div>
    </div>

    <div class="hero-card animate__animated animate__fadeInDown">
        <div class="hero-bg-shape"></div>
        <div class="position-relative z-1">
            
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <span class="badge bg-white bg-opacity-20 border border-white border-opacity-25 px-3 py-2 rounded-pill backdrop-blur mb-2 mb-md-0">
                    <i class="fa-solid fa-bell me-2"></i> PROCHAINE SÉANCE
                </span>
                
                <?php if($isAdmin): ?>
                     <a href="admin-seances.php?id=<?= $cercle_id ?>" class="btn btn-light text-success fw-bold rounded-pill px-4 py-2 shadow z-2 position-relative">
                        <i class="fa-solid fa-pen-to-square me-2"></i> Modifier
                    </a>
                <?php endif; ?>
            </div>

            <?php if($prochaineSeance): ?>
                <h1 class="fw-900 mb-2 display-6">Rendez-vous financier</h1>
                <p class="opacity-90 mb-4" style="font-size: 1.05rem; max-width: 600px;">
                    La session est prévue pour le <strong><?= date('d/m/Y', strtotime($prochaineSeance['date_seance'])) ?></strong>. 
                    Préparez votre investissement.
                </p>

                <div class="timer-container" id="countdown">
                    <div class="timer-box">
                        <div class="timer-val" id="days">00</div>
                        <div class="timer-label">Jours</div>
                    </div>
                    <div class="timer-box">
                        <div class="timer-val" id="hours">00</div>
                        <div class="timer-label">Heures</div>
                    </div>
                    <div class="timer-box">
                        <div class="timer-val" id="minutes">00</div>
                        <div class="timer-label">Min</div>
                    </div>
                    <div class="timer-box seconds">
                        <div class="timer-val" id="seconds">00</div>
                        <div class="timer-label">Sec</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="py-4">
                    <h2 class="fw-bold"><i class="fa-regular fa-calendar-xmark me-2 opacity-50"></i> En attente de planification</h2>
                    <p class="opacity-75 mt-2">Le calendrier des cotisations est en cours d'élaboration par le bureau.</p>
                    
                    <?php if($isAdmin): ?>
                        <div class="mt-4">
                            <a href="admin-seances.php?id=<?= $cercle_id ?>" class="btn btn-light text-success fw-bold px-4 py-2 rounded-pill shadow-lg hover-up z-2 position-relative">
                                <i class="fa-solid fa-calendar-plus me-2"></i> Fixer une date
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 g-md-4">
        <div class="col-12 col-md-4">
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                <i class="fa-solid fa-sack-dollar bg-icon-faded text-success"></i>
                <div class="stat-icon-large bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
                <p class="text-muted fw-bold small text-uppercase mb-1">Cagnotte Globale</p>
                <h3 class="fw-800 text-dark mb-0"><?= number_format($cagnottePotentielle, 0, ',', ' ') ?> <small class="fs-6 text-muted">FCFA</small></h3>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                <i class="fa-solid fa-users bg-icon-faded text-primary"></i>
                <div class="stat-icon-large bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <p class="text-muted fw-bold small text-uppercase mb-1">Contributeurs</p>
                <h3 class="fw-800 text-dark mb-0"><?= $nbMembres ?> <small class="fs-6 text-muted">actifs</small></h3>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                <i class="fa-solid fa-money-bill-wave bg-icon-faded text-warning"></i>
                <div class="stat-icon-large bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <p class="text-muted fw-bold small text-uppercase mb-1">Ma Part / Tour</p>
                <h3 class="fw-800 text-dark mb-0"><?= number_format($cercle['montant_unitaire'], 0, ',', ' ') ?> <small class="fs-6 text-muted">FCFA</small></h3>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12 mb-3">
            <h6 class="fw-bold text-uppercase text-muted small letter-spacing-1">Accès Rapide</h6>
        </div>
        <div class="col-6 col-md-3">
            <a href="cotiser.php?id=<?= $cercle_id ?>" class="card p-3 border-0 shadow-sm text-center text-decoration-none hover-up align-items-center h-100 justify-content-center">
                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-plus fs-4 text-success"></i>
                </div>
                <span class="small fw-bold text-dark">Cotiser</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="forum.php?id=<?= $cercle_id ?>" class="card p-3 border-0 shadow-sm text-center text-decoration-none hover-up align-items-center h-100 justify-content-center">
                <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-comments fs-4 text-info"></i>
                </div>
                <span class="small fw-bold text-dark">Forum</span>
            </a>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Gestion de la Sidebar Mobile ---
    function openSidebar() {
        document.getElementById('sidebar').classList.add('active');
        document.getElementById('sidebarOverlay').classList.add('active');
    }

    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }

    // --- JS Compte à Rebours ---
    <?php if($targetDate): ?>
    const targetDate = new Date("<?= $targetDate ?>").getTime();
    
    const timer = setInterval(() => {
        const now = new Date().getTime();
        const distance = targetDate - now;

        if (distance < 0) {
            clearInterval(timer);
            document.getElementById("countdown").innerHTML = "<div class='text-white fw-bold fs-3 text-center w-100'><i class='fa-solid fa-spinner fa-spin me-2'></i> Séance en cours !</div>";
            return;
        }

        const d = Math.floor(distance / (1000 * 60 * 60 * 24));
        const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById("days").innerText = d < 10 ? "0"+d : d;
        document.getElementById("hours").innerText = h < 10 ? "0"+h : h;
        document.getElementById("minutes").innerText = m < 10 ? "0"+m : m;
        document.getElementById("seconds").innerText = s < 10 ? "0"+s : s;
    }, 1000);
    <?php endif; ?>
</script>
</body>
</html>