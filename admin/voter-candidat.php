<?php
session_start();
require_once '../config/database.php'; 

// 1. Initialisation de la connexion via la classe Database (Singleton)
try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die("Erreur critique de connexion : " . $e->getMessage());
}

// 2. Protection de la page
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$membre_id = $_SESSION['user_id'];
$candidat_id = $_GET['id'] ?? null;

if (!$candidat_id) {
    // Redirection vers l'index admin
    header('Location: index.php');
    exit();
}

// 3. Récupération des infos du candidat (Table membres)
try {
    $stmt = $pdo->prepare("SELECT m.*, n.plaidoyer_msg, n.type 
                           FROM membres m 
                           LEFT JOIN notifications n ON m.id = n.filleul_id 
                           WHERE m.id = ? LIMIT 1");
    $stmt->execute([$candidat_id]);
    $candidat = $stmt->fetch();

    if (!$candidat) {
        die("<div class='alert alert-danger' style='margin:20px;'>Candidat introuvable dans la base de données.</div>");
    }

    // MISE À JOUR : Utilisation de la table 'votes_decisions'
    $checkVote = $pdo->prepare("SELECT id FROM votes_decisions WHERE membre_id = ? AND reference_id = ? AND type_vote = 'adhesion'");
    $checkVote->execute([$membre_id, $candidat_id]);
    $deja_vote = $checkVote->fetch();

} catch (Exception $e) {
    die("Erreur système : " . $e->getMessage());
}

// 4. Traitement du vote lors de la soumission du formulaire
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$deja_vote) {
    $choix = $_POST['choix']; 
    $commentaire = htmlspecialchars($_POST['commentaire_vote'] ?? '');

    try {
        $pdo->beginTransaction();

        // A. Insertion du vote dans 'votes_decisions'
        $insert = $pdo->prepare("INSERT INTO votes_decisions (type_vote, reference_id, membre_id, choix, commentaire_vote, date_vote) 
                                 VALUES ('adhesion', ?, ?, ?, ?, NOW())");
        $insert->execute([$candidat_id, $membre_id, $choix, $commentaire]);

        // B. LOGIQUE DE MAJORITÉ (Calcul basé sur les membres 'valide')
        // Compter le nombre de membres admis (ceux qui ont le droit de voter)
        $stmtMembresAdmis = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut_validation = 'valide'");
        $totalMembresAdmis = (int)$stmtMembresAdmis->fetchColumn();

        // Compter le nombre de votes favorables pour ce candidat
        $stmtVotesPour = $pdo->prepare("SELECT COUNT(*) FROM votes_decisions WHERE reference_id = ? AND type_vote = 'adhesion' AND choix = 'pour'");
        $stmtVotesPour->execute([$candidat_id]);
        $totalVotesPour = (int)$stmtVotesPour->fetchColumn();

        // Calcul de la majorité (50% + 1)
        $seuilMajorite = ($totalMembresAdmis > 0) ? floor($totalMembresAdmis / 2) + 1 : 1;

        if ($totalVotesPour >= $seuilMajorite) {
            // GENERATION DU CODE PROMO
            $codePromo = "NDJ-" . strtoupper(substr(md5(uniqid()), 0, 8));

            // VALIDATION DU CANDIDAT (Admis automatiquement)
            $updateCandidat = $pdo->prepare("UPDATE membres SET statut_validation = 'valide', code_promo = ? WHERE id = ?");
            $updateCandidat->execute([$codePromo, $candidat_id]);
        }

        $pdo->commit();
        $success = true;
        $deja_vote = true; 

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors du traitement : " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote d'Adhésion - NDJANGUI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --nj-primary: #2d31fa;
            --nj-dark: #050624;
            --nj-success: #00d084;
            --nj-danger: #ff4d4d;
            --nj-bg: #f8faff;
        }

        body { 
            background-color: var(--nj-bg);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--nj-dark);
            overflow-x: hidden;
        }

        .header-wave {
            background: linear-gradient(135deg, var(--nj-primary), #5d61ff);
            height: 220px;
            border-radius: 0 0 50px 50px;
            padding-top: 50px;
        }

        .main-card {
            border: none;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            margin-top: -100px;
            overflow: hidden;
        }

        .candidat-avatar {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 30px;
            border: 5px solid white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            margin-top: -55px;
            background: white;
        }

        .vote-selector { display: none; }
        
        .vote-option {
            cursor: pointer;
            padding: 20px;
            border: 2px solid #edf2f7;
            border-radius: 20px;
            transition: all 0.3s ease;
            text-align: center;
            height: 100%;
        }

        .vote-selector:checked + .option-pour {
            border-color: var(--nj-success);
            background-color: rgba(0, 208, 132, 0.05);
            color: var(--nj-success);
            transform: translateY(-5px);
        }

        .vote-selector:checked + .option-contre {
            border-color: var(--nj-danger);
            background-color: rgba(255, 77, 77, 0.05);
            color: var(--nj-danger);
            transform: translateY(-5px);
        }

        .plaidoyer-box {
            background: #f0f3ff;
            border-left: 5px solid var(--nj-primary);
            border-radius: 15px;
            padding: 20px;
            position: relative;
        }

        .plaidoyer-box i {
            position: absolute;
            top: 10px;
            right: 15px;
            opacity: 0.2;
            font-size: 2rem;
        }

        .btn-submit {
            background: var(--nj-primary);
            border: none;
            padding: 18px;
            border-radius: 20px;
            font-weight: 700;
            letter-spacing: 1px;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 20px rgba(45, 49, 250, 0.3);
            background: #1a1edb;
        }
    </style>
</head>
<body>

<div class="header-wave text-center text-white">
    <div class="container">
        <h3 class="fw-800 animate__animated animate__fadeInDown">Validation d'Adhésion</h3>
        <p class="opacity-75">Comité de parrainage NDJANGUI</p>
    </div>
</div>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            
            <div class="card main-card animate__animated animate__fadeInUp">
                <div class="card-body p-4 p-lg-5">
                    
                    <div class="text-center mb-4">
                        <img src="<?php echo !empty($candidat['photo_profil_url']) ? $candidat['photo_profil_url'] : 'https://ui-avatars.com/api/?name='.urlencode($candidat['nom_complet']).'&background=2d31fa&color=fff&size=128'; ?>" 
                             class="candidat-avatar mb-3" alt="Profil">
                        
                        <h4 class="fw-800 mb-1"><?php echo htmlspecialchars($candidat['nom_complet']); ?></h4>
                        <p class="text-muted small">
                            <i class="fa-solid fa-briefcase me-1"></i> <?php echo htmlspecialchars($candidat['profession'] ?? 'Profession non précisée'); ?><br>
                            <i class="fa-solid fa-calendar-check me-1"></i> Inscrit le <?php echo date('d/m/Y', strtotime($candidat['date_inscription'])); ?>
                        </p>
                    </div>

                    <?php if ($success): ?>
                        <div class="text-center py-4 animate__animated animate__bounceIn">
                            <div class="display-1 text-success mb-3"><i class="fa-solid fa-circle-check"></i></div>
                            <h4 class="fw-bold">Vote Enregistré !</h4>
                            <p class="text-muted">Votre décision a été prise en compte par le système.</p>
                            <a href="membre.php" class="btn btn-outline-primary rounded-pill px-4 mt-3">Retour à la liste</a>
                            <script>setTimeout(() => { window.location.href = 'membre.php'; }, 2500);</script>
                        </div>

                    <?php elseif ($deja_vote): ?>
                        <div class="alert alert-info border-0 rounded-4 p-4 text-center">
                            <i class="fa-solid fa-circle-info fs-2 d-block mb-3"></i>
                            <h5 class="fw-bold">Action impossible</h5>
                            <p class="small">Vous avez déjà exprimé votre voix pour cette demande d'adhésion.</p>
                            <a href="admin/index.php" class="btn btn-sm btn-primary rounded-pill mt-2">Retour à la liste</a>
                        </div>

                    <?php else: ?>

                        <div class="plaidoyer-box mb-4">
                            <i class="fa-solid fa-quote-right"></i>
                            <span class="d-block text-uppercase fw-bold text-primary mb-2" style="font-size: 0.7rem;">Plaidoyer du candidat</span>
                            <p class="mb-0 fst-italic text-dark" style="font-size: 0.95rem;">
                                "<?php 
                                    echo htmlspecialchars($candidat['plaidoyer_msg'] ?? $candidat['plaidoyer_parrain'] ?? 'Je souhaite rejoindre votre cercle pour contribuer activement à notre tontine.'); 
                                ?>"
                            </p>
                        </div>

                        <form method="POST" id="voteForm">
                            <input type="hidden" name="id" value="<?php echo $candidat_id; ?>">
                            <label class="fw-bold small mb-3 text-uppercase text-muted">Exprimez votre choix :</label>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <input type="radio" name="choix" id="val_pour" value="pour" class="vote-selector" required>
                                    <label for="val_pour" class="vote-option option-pour w-100">
                                        <i class="fa-solid fa-check-circle fs-2 mb-2"></i>
                                        <span class="d-block fw-bold">ACCEPTER</span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" name="choix" id="val_contre" value="contre" class="vote-selector">
                                    <label for="val_contre" class="vote-option option-contre w-100">
                                        <i class="fa-solid fa-times-circle fs-2 mb-2"></i>
                                        <span class="d-block fw-bold">REFUSER</span>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="fw-bold small mb-2 text-uppercase text-muted">Commentaire (facultatif) :</label>
                                <textarea name="commentaire_vote" class="form-control bg-light border-0 rounded-4" rows="3" placeholder="Ex: Profil sérieux..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-submit w-100 text-white shadow">
                                ENREGISTRER MA DÉCISION <i class="fa-solid fa-chevron-right ms-2"></i>
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-4 pt-3 border-top">
                        <a href="membre.php" class="text-decoration-none text-muted small hover-primary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Revenir en arrière
                        </a>
                    </div>

                </div>
            </div>

            <p class="text-center text-muted mt-4" style="font-size: 0.8rem;">
                <i class="fa-solid fa-shield-halved me-1"></i> Ce vote est sécurisé et anonyme au sein du cercle.
            </p>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>