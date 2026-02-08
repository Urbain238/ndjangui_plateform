<?php
session_start();
// 1. CONFIGURATION STRICTE (Identique à seances.php)
date_default_timezone_set('Africa/Douala');
setlocale(LC_TIME, 'fr_FR.utf8', 'fra');

// --- 1. CONNEXION BDD ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) { die("Erreur DB : " . $e->getMessage()); }

// --- 2. SECURITE & SESSION ---
if (!isset($_SESSION['user_id'])) { 
    $_SESSION['user_id'] = 1; 
}
$user_id = $_SESSION['user_id'];

// Récupération de l'ID du cercle
$cercle_id = isset($_GET['cercle_id']) ? (int)$_GET['cercle_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($cercle_id === 0) die("Aucun cercle sélectionné.");

// --- 3. RECUPERATION INFOS ---

// Infos du Cercle
$stmt = $pdo->prepare("SELECT * FROM cercles WHERE id = ?");
$stmt->execute([$cercle_id]);
$cercle = $stmt->fetch();

if (!$cercle) die("Tontine introuvable.");

// Vérifier si membre actif
$stmtMembre = $pdo->prepare("SELECT * FROM inscriptions_cercle WHERE cercle_id = ? AND membre_id = ?");
$stmtMembre->execute([$cercle_id, $user_id]);
$monInscription = $stmtMembre->fetch();

// Détection Admin (Président)
$isAdmin = (isset($cercle['president_id']) && $cercle['president_id'] == $user_id);

if (!$monInscription && !$isAdmin) {
    die("Accès refusé : Vous n'êtes pas membre de ce cercle.");
}

// --- 4. RECUPERATION INTELLIGENTE (LOGIQUE IDENTIQUE A SEANCES.PHP) ---
$now = time(); // Temps PHP actuel (Douala)
$prochaineSeance = null;
$timestamp_js = 0; 

// On récupère TOUTES les séances prévues, ordonnées par date
$stmtSeance = $pdo->prepare("
    SELECT * FROM seances 
    WHERE cercle_id = ? 
    AND statut = 'prevue'
    ORDER BY date_seance ASC, heure_limite_pointage ASC
");
$stmtSeance->execute([$cercle_id]);
$allSeances = $stmtSeance->fetchAll();

// On boucle exactement comme dans la page Admin pour trouver la "vraie" prochaine
foreach ($allSeances as $s) {
    $date_propre = date('Y-m-d', strtotime($s['date_seance']));
    // Calcul du timestamp précis de fin de séance
    $seanceTime = strtotime($date_propre . ' ' . $s['heure_limite_pointage']);
    
    // Si cette séance est dans le futur par rapport à maintenant
    if ($seanceTime > $now) {
        $prochaineSeance = $s;
        $timestamp_js = $seanceTime; // On garde ce timestamp pour le JS
        break; // On a trouvé la plus proche, on arrête
    }
}

// Stats : Nombre de membres
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM inscriptions_cercle WHERE cercle_id = ?");
$stmtCount->execute([$cercle_id]);
$nbMembres = $stmtCount->fetchColumn();

// --- CALCULS FINANCIERS MIS A JOUR ---
$montant_unit = $cercle['montant_cotisation_standard'] ?? 0;

// REQUETE MISE A JOUR : Somme totale des cotisations réelles pour ce cercle (exclut les rejets)
// On interroge la table cotisations_cercle
$stmtSomme = $pdo->prepare("SELECT SUM(montant) FROM cotisations_cercle WHERE cercle_id = ? AND statut != 'rejete'");
$stmtSomme->execute([$cercle_id]);
$cagnotteReelle = $stmtSomme->fetchColumn();

// Si aucune cotisation n'a été faite, on met 0 par défaut
if ($cagnotteReelle === false || $cagnotteReelle === null) {
    $cagnotteReelle = 0;
}

// Chemins relatifs
$path_forum = "../messages.php?cercle_id=" . $cercle_id;
$path_admin = "seances.php?cercle_id=" . $cercle_id;
$path_cotiser = "cotiser.php?cercle_id=" . $cercle_id; 
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
            --primary-gradient: linear-gradient(135deg, #059669 0%, #064e3b 100%);
            --primary-color: #059669;
            --secondary-color: #10b981;
            --bg-body: #f8fafc;
            --text-dark: #0f172a;
            --text-light: #64748b;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); overflow-x: hidden; }
        h1, h2, h3, h4, .brand-text { font-family: 'Outfit', sans-serif; }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0;
            background: #ffffff; border-right: 1px solid #e2e8f0; z-index: 1050;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; padding: 24px;
        }

        .brand-box { display: flex; align-items: center; justify-content: space-between; margin-bottom: 35px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9; }
        .brand-icon { width: 44px; height: 44px; background: var(--primary-gradient); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; }
        .brand-text { font-weight: 800; font-size: 1.1rem; color: var(--text-dark); margin-left: 12px; }

        .nav-link {
            color: var(--text-dark); padding: 12px 16px; border-radius: 10px; margin-bottom: 6px;
            font-weight: 600; font-size: 0.95rem; display: flex; justify-content: space-between; align-items: center;
            text-decoration: none; transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active { background-color: #ecfdf5; color: var(--primary-color); transform: translateX(4px); }
        .icon-wrap { width: 26px; display: flex; justify-content: center; margin-right: 12px; color: var(--text-light); }
        .nav-link:hover .icon-wrap, .nav-link.active .icon-wrap { color: var(--primary-color); }

        .sub-link { display: block; padding: 8px 15px 8px 54px; font-size: 0.85rem; color: var(--text-light); text-decoration: none; transition: 0.2s; }
        .sub-link:hover { color: var(--primary-color); transform: translateX(5px); }
        .sub-link i { width: 20px; text-align: center; margin-right: 8px; opacity: 0.8; }
        
        /* --- MAIN --- */
        .main-content { margin-left: var(--sidebar-width); padding: 30px 40px; transition: 0.3s; }
        
        /* --- HERO CARD --- */
        .hero-card {
            background: var(--primary-gradient); border-radius: 24px; padding: 40px; color: white;
            position: relative; overflow: hidden; box-shadow: 0 20px 50px -12px rgba(5, 150, 105, 0.4); margin-bottom: 35px;
        }
        .hero-bg-shape { position: absolute; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%); top: -150px; right: -100px; border-radius: 50%; }

        .timer-container { display: flex; gap: 15px; margin-top: 30px; flex-wrap: wrap;}
        .timer-box { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.25); padding: 15px 20px; border-radius: 16px; text-align: center; min-width: 85px; }
        .timer-val { font-size: 2rem; font-weight: 800; line-height: 1; }
        .timer-label { font-size: 0.7rem; text-transform: uppercase; margin-top: 5px; opacity: 0.9; }
        .timer-box.seconds .timer-val { color: #ffd700; }

        /* --- STATS --- */
        .stat-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: transform 0.3s; height: 100%; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon-large { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 20px; }
        .bg-icon-faded { position: absolute; right: -15px; bottom: -20px; font-size: 6rem; opacity: 0.05; transform: rotate(-15deg); }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1040;display:none;"></div>

<aside class="sidebar" id="sidebar">
    <div class="brand-box">
        <div class="d-flex align-items-center">
            <div class="brand-icon"><i class="fa-solid fa-hands-holding-circle"></i></div>
            <div class="brand-text">NDJANGUI</div>
        </div>
        <button class="d-md-none btn-close bg-transparent border-0" onclick="closeSidebar()"><i class="fa-solid fa-xmark fa-lg"></i></button>
    </div>

    <nav class="flex-grow-1 overflow-auto" style="scrollbar-width: none;">
        <a href="#" class="nav-link active">
            <div class="d-flex align-items-center"><span class="icon-wrap"><i class="fa-solid fa-chart-pie"></i></span> Vue globale</div>
        </a>

        <a href="#menuFinance" class="nav-link" data-bs-toggle="collapse">
            <div class="d-flex align-items-center"><span class="icon-wrap"><i class="fa-solid fa-wallet"></i></span> Finance</div>
            <i class="fa-solid fa-chevron-down small opacity-50"></i>
        </a>
        <div class="collapse" id="menuFinance">
            <a href="<?= $path_cotiser ?>" class="sub-link"><i class="fa-solid fa-circle-plus"></i> Faire une cotisation</a>
            <a href="historique.php?cercle_id=<?= $cercle_id ?>" class="sub-link">
    <i class="fa-solid fa-clock-rotate-left"></i> Historique
</a>
<a href="modifier.php?cercle_id=<?= $cercle_id ?>" class="sub-link">
    <i class="fa-solid fa-pen-to-square"></i> 
    <span>Modifier ma part</span>
</a>
        </div>
        <a href="#menuCommunaute" class="nav-link" data-bs-toggle="collapse">
            <div class="d-flex align-items-center"><span class="icon-wrap"><i class="fa-solid fa-users"></i></span> Communauté</div>
            <i class="fa-solid fa-chevron-down small opacity-50"></i>
        </a>
        <div class="collapse" id="menuCommunaute">
            <a href="<?= $path_forum ?>" class="sub-link"><i class="fa-solid fa-comments"></i> Forum & Discussion</a>
            <a href="#" class="sub-link"><i class="fa-solid fa-address-book"></i> Liste des membres</a>
        </div>

        <?php if($isAdmin): ?>
            <div class="mt-4 mb-2 px-2 text-uppercase small fw-bold text-muted" style="font-size: 0.7rem;">Administration</div>
            <a href="#menuAdmin" class="nav-link" data-bs-toggle="collapse">
                <div class="d-flex align-items-center">
                    <span class="icon-wrap text-warning"><i class="fa-solid fa-user-shield"></i></span> Gestion
                </div>
                <i class="fa-solid fa-chevron-down small opacity-50"></i>
            </a>
            <div class="collapse" id="menuAdmin">
                <a href="<?= $path_admin ?>" class="sub-link"><i class="fa-regular fa-calendar-check"></i> Séances</a>
                <a href="parametre.php?cercle_id=<?= $cercle_id ?>" class="sub-link">
    <i class="fa-solid fa-sliders"></i> Paramètres
</a>
            </div>
        <?php endif; ?>

        <a href="../../logout.php" class="nav-link mt-auto text-danger">
            <div class="d-flex align-items-center"><span class="icon-wrap text-danger"><i class="fa-solid fa-right-from-bracket"></i></span> Quitter</div>
        </a>
    </nav>
</aside>

<main class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4 d-md-none">
        <button class="btn btn-light shadow-sm border" onclick="openSidebar()"><i class="fa-solid fa-bars-staggered text-success"></i></button>
        <h5 class="fw-bold m-0"><?= htmlspecialchars($cercle['nom_cercle']) ?></h5>
        <div style="width: 40px;"></div>
    </div>

    <div class="hero-card animate__animated animate__fadeInDown">
        <div class="hero-bg-shape"></div>
        <div class="position-relative z-1">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="badge bg-white bg-opacity-20 border border-white border-opacity-25 px-3 py-2 rounded-pill">
                    <i class="fa-regular fa-calendar-check me-2"></i> STATUS
                </span>
                <?php if($isAdmin): ?>
                     <a href="<?= $path_admin ?>" class="btn btn-light text-success fw-bold rounded-pill px-4 py-2 shadow border-0">
                        <i class="fa-solid fa-gear me-2"></i> Configurer
                    </a>
                <?php endif; ?>
            </div>

            <?php if($prochaineSeance): ?>
                <h1 class="fw-bold mb-2 display-6">Prochaine Séance</h1>
                <p class="opacity-75 mb-4 fs-5">
                    Prévue le <strong><?= date('d/m/Y', strtotime($prochaineSeance['date_seance'])) ?></strong>
                    à <strong><?= date('H:i', strtotime($prochaineSeance['heure_limite_pointage'])) ?></strong>.
                </p>

                <div class="timer-container" id="countdown">
                    <div class="timer-box"><div class="timer-val" id="days">00</div><div class="timer-label">Jours</div></div>
                    <div class="timer-box"><div class="timer-val" id="hours">00</div><div class="timer-label">Heures</div></div>
                    <div class="timer-box"><div class="timer-val" id="minutes">00</div><div class="timer-label">Min</div></div>
                    <div class="timer-box seconds"><div class="timer-val" id="seconds">00</div><div class="timer-label">Sec</div></div>
                </div>
            <?php else: ?>
                <div class="py-3">
                    <h2 class="fw-bold"><i class="fa-regular fa-clock me-2"></i> En pause</h2>
                    <p class="opacity-75">Aucune séance à venir n'est programmée.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 g-md-4">
        <div class="col-12 col-md-4">
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                <i class="fa-solid fa-sack-dollar bg-icon-faded text-success"></i>
                <div class="stat-icon-large bg-success bg-opacity-10 text-success"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <p class="text-muted fw-bold small text-uppercase mb-1">Cagnotte Actuelle</p>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($cagnotteReelle, 0, ',', ' ') ?> <small class="fs-6 text-muted">FCFA</small></h3>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                <i class="fa-solid fa-users bg-icon-faded text-primary"></i>
                <div class="stat-icon-large bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-user-group"></i></div>
                <p class="text-muted fw-bold small text-uppercase mb-1">Membres Actifs</p>
                <h3 class="fw-bold text-dark mb-0"><?= $nbMembres ?> <small class="fs-6 text-muted">personnes</small></h3>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                <i class="fa-solid fa-money-bill-wave bg-icon-faded text-warning"></i>
                <div class="stat-icon-large bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-coins"></i></div>
                <p class="text-muted fw-bold small text-uppercase mb-1">Ma Cotisation / Tour</p>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($montant_unit * ($monInscription['nombre_parts'] ?? 1), 0, ',', ' ') ?> <small class="fs-6 text-muted">FCFA</small></h3>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12 mb-3"><h6 class="fw-bold text-uppercase text-muted small">Accès Rapide</h6></div>
        <div class="col-6 col-md-3">
            <a href="<?= $path_cotiser ?>" class="card p-3 border-0 shadow-sm text-center text-decoration-none h-100 justify-content-center">
                <div class="bg-success bg-opacity-10 rounded-circle d-flex mx-auto align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-plus fs-4 text-success"></i>
                </div>
                <span class="small fw-bold text-dark">Cotiser</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= $path_forum ?>" class="card p-3 border-0 shadow-sm text-center text-decoration-none h-100 justify-content-center">
                <div class="bg-info bg-opacity-10 rounded-circle d-flex mx-auto align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-comments fs-4 text-info"></i>
                </div>
                <span class="small fw-bold text-dark">Forum</span>
            </a>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openSidebar() { document.getElementById('sidebar').classList.add('active'); document.getElementById('sidebarOverlay').style.display='block'; }
    function closeSidebar() { document.getElementById('sidebar').classList.remove('active'); document.getElementById('sidebarOverlay').style.display='none'; }

    // --- CORRECTION TIMER JS (Utilisation Timestamp numérique PHP) ---
    // On passe le timestamp calculé en PHP (secondes) * 1000 pour le JS (millisecondes)
    const targetTimestamp = <?= $timestamp_js * 1000 ?>;

    if (targetTimestamp > 0) {
        const timer = setInterval(() => {
            const now = new Date().getTime();
            const distance = targetTimestamp - now;

            if (distance < 0) {
                clearInterval(timer);
                document.getElementById("countdown").innerHTML = "<div class='text-white fw-bold fs-4 mt-2'>Séance en cours !</div>";
                return;
            }

            const d = Math.floor(distance / (1000 * 60 * 60 * 24));
            const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((distance % (1000 * 60)) / 1000);

            // Fonction pour ajouter un zéro devant (ex: 5 -> 05)
            const pad = (num) => num < 10 ? "0" + num : num;

            // Mise à jour de l'affichage
            if(document.getElementById("days")) document.getElementById("days").innerText = pad(d);
            if(document.getElementById("hours")) document.getElementById("hours").innerText = pad(h);
            if(document.getElementById("minutes")) document.getElementById("minutes").innerText = pad(m);
            if(document.getElementById("seconds")) document.getElementById("seconds").innerText = pad(s);

        }, 1000);
    }
</script>
</body>
</html>