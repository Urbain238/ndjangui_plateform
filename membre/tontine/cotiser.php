<?php
session_start();
date_default_timezone_set('Africa/Douala');

// --- 1. CONNEXION BDD ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) { die("Erreur critique base de données : " . $e->getMessage()); }

// --- 2. SÉCURITÉ & DONNÉES ---
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
$user_id = $_SESSION['user_id'];
$cercle_id = isset($_GET['cercle_id']) ? (int)$_GET['cercle_id'] : 0;
if ($cercle_id === 0) die("Paramètre manquant.");

// Récupération infos
$stmt = $pdo->prepare("SELECT * FROM cercles WHERE id = ?");
$stmt->execute([$cercle_id]);
$cercle = $stmt->fetch();
if (!$cercle) die("Cercle introuvable.");

$stmt_m = $pdo->prepare("SELECT nom_complet, telephone FROM membres WHERE id = ?");
$stmt_m->execute([$user_id]);
$infos_membre = $stmt_m->fetch();
$telephone_bdd = $infos_membre['telephone']; 
$nom_membre = $infos_membre['nom_complet'];

$sql_inscrip = "SELECT * FROM inscriptions_cercle WHERE membre_id = ? AND cercle_id = ?";
$stmt_inscrip = $pdo->prepare($sql_inscrip);
$stmt_inscrip->execute([$user_id, $cercle_id]);
$inscription = $stmt_inscrip->fetch();

if (!$inscription && $cercle['president_id'] != $user_id) die("Accès refusé.");

// Montant
$nb_parts = $inscription['nombre_parts'] ?? 1;
$montant_attendu = $nb_parts * $cercle['montant_unitaire'];
if ($montant_attendu == 0) $montant_attendu = $cercle['montant_cotisation_standard'];

// Vérification doublon
$frequence = $cercle['frequence'];
$intervalle_sql = ($frequence === 'mensuel') ? '20 DAY' : '5 DAY';
$sql_check = "SELECT * FROM cotisations_cercle WHERE membre_id = ? AND cercle_id = ? AND date_paiement > DATE_SUB(NOW(), INTERVAL $intervalle_sql) AND statut IN ('en_attente', 'valide') LIMIT 1";
$stmt_check = $pdo->prepare($sql_check);
$stmt_check->execute([$user_id, $cercle_id]);
$deja_cotise = $stmt_check->fetch();

$msg = "";
$path_retour = "admin-seances.php?cercle_id=" . $cercle_id;

// --- 3. TRAITEMENT ---
if (isset($_POST['submit_payment'])) {
    if ($deja_cotise) {
        $msg = "<div class='alert-float warning'><i class='fa-solid fa-triangle-exclamation'></i> Cotisation déjà en cours.</div>";
    } else {
        $montant = (int)$_POST['montant_verse'];
        $mode = $_POST['mode_paiement'];
        $reference = $telephone_bdd; 
        $date_op = date('Y-m-d H:i:s');

        if ($montant > 0 && !empty($mode)) {
            try {
                $sql = "INSERT INTO cotisations_cercle (cercle_id, membre_id, montant, mode_paiement, reference_transaction, statut, date_paiement) VALUES (?, ?, ?, ?, ?, 'en_attente', ?)";
                $stmt_insert = $pdo->prepare($sql);
                $stmt_insert->execute([$cercle_id, $user_id, $montant, $mode, $reference, $date_op]);
                header("Location: cotiser.php?cercle_id=$cercle_id&success=1");
                exit;
            } catch (PDOException $e) {
                $msg = "<div class='alert-float error'>Erreur : " . $e->getMessage() . "</div>";
            }
        }
    }
}

if (isset($_GET['success'])) {
    $deja_cotise = true;
    $msg = "<div class='alert-float success'><i class='fa-solid fa-check-circle'></i> Paiement initié avec succès !</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Paiement Sécurisé | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            /* Couleurs Modernes & Vibrantes */
            --bg-body: #f0f4f8;
            --primary: #0f172a;
            --accent: #3b82f6;
            --accent-dark: #2563eb;
            --accent-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            
            /* Couleurs Opérateurs */
            --om-color: #ff7900;
            --momo-color: #ffcc00;
            
            /* Effets */
            --radius-main: 24px;
            --shadow-subtle: 0 10px 40px -10px rgba(0,0,0,0.08);
            --shadow-float: 0 20px 40px -10px rgba(37, 99, 235, 0.4);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            /* Fond abstrait moderne */
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(15, 23, 42, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
            padding-bottom: 2rem;
        }

        /* --- NAVBAR OPTIMISÉE MOBILE --- */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            padding: 0.8rem 0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            z-index: 1000;
        }
        
        .brand-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .brand-icon {
            width: 38px;
            height: 38px;
            background: var(--primary);
            color: white;
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
        }
        .brand-text {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        /* --- CONTAINER PRINCIPAL --- */
        .payment-wrapper {
            max-width: 1000px;
            margin: 6rem auto 2rem; /* Espace pour la navbar fixe */
            padding: 0 1rem;
        }

        .main-card {
            background: var(--surface);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
            border: 1px solid white;
        }

        /* --- COLONNE GAUCHE (FORMULAIRE) --- */
        .left-col {
            padding: 3rem;
        }
        
        .form-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }
        .form-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* --- OPERATEURS SELECTOR --- */
        .momo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .momo-option input { display: none; }
        .momo-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            border: 2px solid var(--border-light);
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: #fafafa;
            height: 100%;
        }
        .momo-card i { font-size: 2rem; margin-bottom: 0.5rem; transition: transform 0.3s; }
        .momo-card span { font-weight: 600; font-size: 0.85rem; }

        /* États Actifs */
        .momo-option input:checked + .momo-card {
            background: white;
            border-color: currentColor;
            transform: translateY(-4px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1);
        }
        .om-style { color: #9ca3af; } /* Gris par défaut */
        .momo-style { color: #9ca3af; }
        
        .momo-option input:checked + .momo-card.om-style { color: var(--om-color); background: #fff8f0; }
        .momo-option input:checked + .momo-card.momo-style { color: var(--momo-color); background: #fffbeb; }

        /* --- INPUTS --- */
        .form-group { margin-bottom: 1.5rem; }
        .form-label-custom {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            display: block;
        }
        .input-wrapper {
            position: relative;
        }
        .custom-input {
            width: 100%;
            height: 56px; /* Touch target optimisé */
            padding: 0 1.25rem;
            background: #f8fafc;
            border: 2px solid transparent;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            transition: all 0.2s;
        }
        .custom-input:focus {
            outline: none;
            background: white;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }
        .custom-input.locked {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
            padding-right: 3rem;
        }
        .lock-badge {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
        }

        /* --- BOUTON ACTION --- */
        .btn-pay {
            width: 100%;
            height: 60px;
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: var(--shadow-float);
            transition: transform 0.2s;
            margin-top: 1rem;
        }
        .btn-pay:active { transform: scale(0.98); }

        /* --- COLONNE DROITE (RECU) --- */
        .right-col {
            background: var(--primary);
            color: white;
            padding: 3rem;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            /* Motif tech subtil */
            background-image: radial-gradient(circle at 100% 0%, rgba(255,255,255,0.1) 0%, transparent 20%);
        }
        
        .receipt-ticket {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 2rem;
            position: relative;
        }
        
        .amount-display {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 1rem 0 2rem;
            letter-spacing: -1px;
            background: linear-gradient(to bottom, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed rgba(255,255,255,0.2);
            font-size: 0.95rem;
        }
        .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
        .detail-label { opacity: 0.7; }
        .detail-value { font-weight: 600; text-align: right; }

        /* --- ALERTES --- */
        .alert-float {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            animation: slideDown 0.4s ease;
        }
        .alert-float.warning { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
        .alert-float.success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-float.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- RESPONSIVE SMARTPHONE (Le coeur de la demande) --- */
        @media (max-width: 991px) {
            .payment-wrapper {
                margin-top: 5rem;
                padding: 0 15px;
            }
            .main-card {
                display: flex;
                flex-direction: column;
            }
            .left-col {
                padding: 2rem 1.5rem; /* Padding réduit sur mobile */
                order: 1; /* Formulaire en premier */
            }
            .right-col {
                padding: 1.5rem;
                order: 2; /* Reçu en dessous */
                border-radius: 0 0 var(--radius-main) var(--radius-main);
                /* Effet de découpe ticket en haut pour le style */
                mask-image: radial-gradient(circle at top, transparent 10px, black 11px);
                -webkit-mask-image: radial-gradient(circle at 10px top, transparent 10px, black 10.5px);
                -webkit-mask-size: 20px 100%;
                -webkit-mask-repeat: repeat-x;
                padding-top: 2.5rem;
            }
            .receipt-ticket {
                background: transparent; /* Intégrer au fond sur mobile */
                border: none;
                backdrop-filter: none;
                padding: 0;
            }
            .form-header h1 { font-size: 1.5rem; }
        }

        /* --- MODAL STYLE --- */
        .modal-content { border-radius: 24px; border: none; }
        .spinner-ring {
            width: 70px; height: 70px;
            border-radius: 50%;
            border: 4px solid #f1f5f9;
            border-top-color: var(--accent);
            animation: spin 1s infinite linear;
            margin: 0 auto 1.5rem;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

    </style>
</head>
<body>

<nav class="navbar-custom fixed-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="dashboard.php" class="brand-container">
            <div class="brand-icon"><i class="fa-solid fa-hands-holding-circle"></i></div>
            <span class="brand-text">NDJANGUI</span>
        </a>

        <a href="<?= $path_retour ?>" class="text-decoration-none" style="color: var(--text-main); font-weight: 600; font-size: 0.9rem;">
            <i class="fa-solid fa-xmark fs-4"></i>
        </a>
    </div>
</nav>

<div class="payment-wrapper">

    <?= $msg ?>

    <?php if($deja_cotise && !isset($_GET['success'])): ?>
        <div class="main-card p-5 text-center animate__animated animate__zoomIn">
            <div style="width: 80px; height: 80px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 1.5rem;">
                <i class="fa-solid fa-check fs-1"></i>
            </div>
            <h2 class="fw-bold mb-2">C'est tout bon !</h2>
            <p class="text-muted mb-4">Votre cotisation pour cette séance est déjà réglée.</p>
            <a href="<?= $path_retour ?>" class="btn btn-dark rounded-pill px-5 py-3 fw-bold w-100 w-md-auto">Retour au Dashboard</a>
        </div>
    <?php else: ?>

        <div class="main-card animate__animated animate__fadeInUp">
            <div class="row g-0">
                
                <div class="col-lg-7 left-col">
                    <div class="form-header mb-4">
                        <h1>Régler ma cotisation</h1>
                        <p>Sécurisé, simple et instantané.</p>
                    </div>

                    <form id="paymentForm" method="POST">
                        
                        <div class="form-group">
                            <label class="form-label-custom">Moyen de paiement</label>
                            <div class="momo-grid">
                                <div class="momo-option">
                                    <input type="radio" name="mode_paiement" id="om" value="orange_money" checked>
                                    <label for="om" class="momo-card om-style">
                                        <i class="fa-solid fa-mobile-screen"></i>
                                        <span>Orange Money</span>
                                    </label>
                                </div>
                                <div class="momo-option">
                                    <input type="radio" name="mode_paiement" id="momo" value="mtn_momo">
                                    <label for="momo" class="momo-card momo-style">
                                        <i class="fa-solid fa-mobile-screen-button"></i>
                                        <span>MTN MoMo</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label-custom">Compte à débiter</label>
                            <div class="input-wrapper">
                                <input type="tel" class="custom-input locked" value="<?= htmlspecialchars($telephone_bdd) ?>" readonly>
                                <div class="lock-badge"><i class="fa-solid fa-lock"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label-custom">Montant (FCFA)</label>
                            <input type="number" name="montant_verse" class="custom-input fw-bold fs-5" value="<?= $montant_attendu ?>">
                        </div>

                        <button type="button" onclick="startSimulation()" class="btn-pay">
                            <span>Payer maintenant</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>

                <div class="col-lg-5 right-col">
                    <div class="receipt-ticket">
                        <div class="d-flex align-items-center gap-2 opacity-50 mb-2">
                            <i class="fa-solid fa-receipt"></i>
                            <span class="small text-uppercase ls-1">RÉCAPITULATIF</span>
                        </div>
                        
                        <div class="amount-display"><?= number_format($montant_attendu, 0, ',', ' ') ?> <small class="fs-6 opacity-50">FCFA</small></div>
                        
                        <div class="receipt-details">
                            <div class="detail-row">
                                <span class="detail-label">Cercle</span>
                                <span class="detail-value text-truncate" style="max-width: 150px;"><?= htmlspecialchars($cercle['nom_cercle']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Membre</span>
                                <span class="detail-value"><?= htmlspecialchars($nom_membre) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date</span>
                                <span class="detail-value"><?= date('d M Y') ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 text-center small opacity-50 border-top border-white border-opacity-10">
                            <i class="fa-solid fa-shield-halved me-1"></i> Transaction chiffrée SSL
                        </div>
                    </div>
                </div>

            </div>
        </div>

    <?php endif; ?>
</div>

<div class="modal fade" id="ussdModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">
            <div class="modal-body">
                
                <div id="ussd-step-1">
                    <div class="spinner-ring"></div>
                    <h5 class="fw-bold mb-2">Vérifiez votre téléphone</h5>
                    <p class="text-muted small mb-3">Une notification push a été envoyée au <strong><?= $telephone_bdd ?></strong>. Veuillez saisir votre code PIN pour valider.</p>
                </div>

                <div id="ussd-step-2" style="display:none;">
                    <div class="mb-3 text-success animate__animated animate__bounceIn" style="font-size: 4rem;">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <h4 class="fw-bold text-dark">Paiement Validé !</h4>
                    <p class="text-muted small">Redirection en cours...</p>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function startSimulation() {
        const modalEl = document.getElementById('ussdModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        setTimeout(() => {
            document.getElementById('ussd-step-1').style.display = 'none';
            document.getElementById('ussd-step-2').style.display = 'block';

            setTimeout(() => {
                const form = document.getElementById('paymentForm');
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'submit_payment';
                hidden.value = '1';
                form.appendChild(hidden);
                form.submit();
            }, 1500);
        }, 3500);
    }
</script>

</body>
</html>