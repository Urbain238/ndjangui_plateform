<?php
session_start();
date_default_timezone_set('Africa/Douala');
require_once '../config/database.php';

// --- LOGIQUE PHP INCHANGÉE ---
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$pdo = Database::getConnection();
$membre_id = $_SESSION['user_id'];

// 1. CALCUL DU FONDS DE SOLIDARITÉ
$stmtSomme = $pdo->query("SELECT SUM(montant_paye) as total_caisse FROM assurances");
$rowSomme = $stmtSomme->fetch(PDO::FETCH_ASSOC);
$plafond_disponible = $rowSomme['total_caisse'] ? floatval($rowSomme['total_caisse']) : 0.00;

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_lancer'])) {
    $titre = htmlspecialchars($_POST['titre']);
    $montant = floatval($_POST['montant']);
    $description = htmlspecialchars($_POST['description']);

    // Validation
    if ($montant > 0 && !empty($titre) && !empty($description)) {
        
        // 2. VERIFICATION DU PLAFOND
        if ($montant > $plafond_disponible) {
            $message = "insufficient_funds";
        } else {
            try {
                // Insertion (Statut 'en_attente')
                $stmt = $pdo->prepare("INSERT INTO projets (membre_id, titre, montant_demande, description, date_creation, statut) VALUES (?, ?, ?, ?, NOW(), 'en_attente')");
                $stmt->execute([$membre_id, $titre, $montant, $description]);
                
                // --- NOTIFICATIONS ---
                $new_projet_id = $pdo->lastInsertId();
                
                $stmtDest = $pdo->prepare("SELECT id FROM membres WHERE id != ? AND statut_validation != 'rejete'");
                $stmtDest->execute([$membre_id]);
                $destinataires = $stmtDest->fetchAll();

                $stmtNotif = $pdo->prepare("INSERT INTO notifications (membre_id, message, type, date_creation, statut) VALUES (?, ?, 'info', NOW(), 'non_lu')");
                $msg_notif = "Nouveau projet : " . $titre . ". Votez maintenant dans l'espace projets.";

                foreach ($destinataires as $dest) {
                    $stmtNotif->execute([$dest['id'], $msg_notif]);
                }
                
                $message = "success";
            } catch (PDOException $e) {
                $message = "error";
            }
        }
    } else {
        $message = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lancer un Projet | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            /* Palette identique à projets.php */
            --bg-dark: #0f172a;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            --accent-primary: #6366f1; 
            --accent-glow: 0 0 20px rgba(99, 102, 241, 0.6);
            --text-light: #f8fafc;
            --text-dim: #94a3b8;
            --card-bg: rgba(255, 255, 255, 0.95);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed;
            color: var(--text-light);
            min-height: 100vh;
            padding-bottom: 80px;
            overflow-x: hidden;
        }

        /* --- Arrière-plan animé --- */
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

        /* --- Navbar Glass --- */
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
        }

        /* --- Layout Carte Principale --- */
        .main-container {
            margin-top: 3rem;
        }

        .glass-card {
            background: var(--card-bg);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }
        @media(min-width: 992px) {
            .glass-card { flex-direction: row; min-height: 600px; }
        }

        /* --- Colonne Formulaire (Gauche) --- */
        .form-column {
            padding: 3rem;
            flex: 1;
            color: #1e293b; /* Texte foncé sur fond clair */
        }

        .form-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 2rem;
            color: var(--bg-dark);
            margin-bottom: 0.5rem;
        }

        /* Inputs Modernes */
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .custom-input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .custom-input-group .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 5;
            transition: 0.3s;
        }
        
        .form-control-custom {
            background: #f1f5f9;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 14px 15px 14px 45px; /* Padding left pour l'icone */
            font-weight: 600;
            color: var(--bg-dark);
            width: 100%;
            transition: all 0.3s;
        }
        .form-control-custom:focus {
            background: #fff;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        .form-control-custom:focus + .input-icon { color: var(--accent-primary); }

        textarea.form-control-custom {
            min-height: 120px;
            resize: none;
        }

        /* Bouton Glow */
        .btn-submit-glow {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: all 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        .btn-submit-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.6);
            color: white;
        }

        /* --- Colonne Info (Droite/Sidebar) --- */
        .info-column {
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 3rem;
            width: 100%;
            border-left: 1px solid rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        @media(min-width: 992px) { .info-column { width: 40%; } }

        /* Carte Budget Dispo */
        .budget-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .budget-amount {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Etapes */
        .step-item {
            display: flex;
            gap: 15px;
            margin-bottom: 1.5rem;
        }
        .step-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            flex-shrink: 0;
            font-weight: bold;
        }

        /* --- Alerts Modernes --- */
        .alert-glass {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.95rem;
        }
        .alert-success-glass { background: #dcfce7; color: #166534; box-shadow: 0 4px 15px rgba(22, 101, 52, 0.1); }
        .alert-warning-glass { background: #fff7ed; color: #9a3412; box-shadow: 0 4px 15px rgba(154, 52, 18, 0.1); }
        .alert-danger-glass { background: #fef2f2; color: #991b1b; box-shadow: 0 4px 15px rgba(153, 27, 27, 0.1); }

    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fa-solid fa-handshake-simple fs-2 me-2 text-white"></i> 
                NDJANGUI
            </a>
            <div class="ms-auto">
                <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-2"></i> Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container main-container animate__animated animate__fadeInUp">
        
        <div class="glass-card">
            
            <div class="form-column">
                <h1 class="form-title">Proposez votre idée</h1>
                <p class="text-muted mb-4">Décrivez votre projet pour obtenir le financement de la communauté.</p>

                <?php if($message === "success"): ?>
                    <div class="alert-glass alert-success-glass">
                        <i class="fa-solid fa-circle-check fa-lg"></i>
                        <div>
                            <strong>Félicitations !</strong><br>
                            Votre projet est en ligne et prêt à recevoir des votes.
                        </div>
                    </div>
                <?php elseif($message === "insufficient_funds"): ?>
                    <div class="alert-glass alert-warning-glass">
                        <i class="fa-solid fa-piggy-bank fa-lg"></i>
                        <div>
                            <strong>Budget Insuffisant</strong><br>
                            Plafond dépassé. Limite actuelle : <?php echo number_format($plafond_disponible, 0, ',', ' '); ?> FCFA.
                        </div>
                    </div>
                <?php elseif($message === "error" || $message === "warning"): ?>
                    <div class="alert-glass alert-danger-glass">
                        <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                        <div>
                            <strong>Erreur</strong><br>
                            Veuillez remplir tous les champs correctement.
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    
                    <div class="mb-3">
                        <label class="form-label">Titre du projet</label>
                        <div class="custom-input-group">
                            <i class="fa-solid fa-lightbulb input-icon"></i>
                            <input type="text" name="titre" class="form-control-custom" 
                                   value="<?php echo isset($_POST['titre']) ? htmlspecialchars($_POST['titre']) : ''; ?>" 
                                   placeholder="Ex: Achat de matériel agricole" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Montant (FCFA)</label>
                        <div class="custom-input-group">
                            <i class="fa-solid fa-coins input-icon"></i>
                            <input type="number" name="montant" class="form-control-custom" 
                                   max="<?php echo $plafond_disponible; ?>" 
                                   placeholder="0" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Description détaillée</label>
                        <div class="custom-input-group">
                            <i class="fa-solid fa-align-left input-icon" style="top: 20px; transform: none;"></i>
                            <textarea name="description" class="form-control-custom" 
                                      placeholder="Expliquez les bénéfices de ce projet pour vous et la communauté..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="btn_lancer" class="btn-submit-glow">
                        Soumettre le dossier <i class="fa-solid fa-paper-plane"></i>
                    </button>

                </form>
            </div>

            <div class="info-column">
                
                <div class="budget-card">
                    <div class="text-uppercase text-muted fw-bold small mb-2" style="font-size: 0.7rem;">Fonds Disponibles (Assurance)</div>
                    <div class="budget-amount"><?php echo number_format($plafond_disponible, 0, ',', ' '); ?> <small style="font-size: 1rem; color: #94a3b8;">FCFA</small></div>
                    <div class="badge bg-success bg-opacity-10 text-success rounded-pill mt-2 px-3">Garantie Njangui</div>
                </div>

                <h5 class="fw-bold mb-4 ps-1 text-dark">Processus</h5>

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-file-pen"></i></div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">1. Soumission</h6>
                        <p class="text-muted small mb-0">Remplissez le formulaire. Le budget est vérifié instantanément.</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-users-viewfinder"></i></div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">2. Vote Communautaire</h6>
                        <p class="text-muted small mb-0">Les membres examinent et votent (Pour/Contre).</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">3. Décaissement</h6>
                        <p class="text-muted small mb-0">Si approuvé, les fonds sont libérés immédiatement.</p>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>