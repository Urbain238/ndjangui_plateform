<?php
session_start();
require_once '../config/database.php';

// --- 1. SÉCURITÉ ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
$projet_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";
$messageType = "";

// --- 2. RÉCUPÉRATION DU PROJET ---
// CORRECTION : On a retiré 'm.cercle_id' qui causait l'erreur
$stmtProjet = $pdo->prepare("
    SELECT p.*, m.nom_complet as demandeur, m.photo_profil_url
    FROM projets p 
    JOIN membres m ON p.membre_id = m.id 
    WHERE p.id = ?
");
$stmtProjet->execute([$projet_id]);
$projet = $stmtProjet->fetch(PDO::FETCH_ASSOC);

if (!$projet) {
    header("Location: projets.php?error=projet_introuvable");
    exit;
}

// Variables clés
$statut_actuel = $projet['statut'];
$montant_demande = $projet['montant_demande'];

// Gestion des dates (Expiration 15 jours)
$date_fin = new DateTime($projet['date_creation']);
$date_fin->modify('+15 days');
$now = new DateTime();
$est_expire = $now > $date_fin;
$vote_ouvert = ($statut_actuel === 'en_attente') && !$est_expire;

// --- 3. LOGIQUE GLOBALE ---

// A. Compter TOUS les membres actifs
$stmtMembres = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut = 'actif'");
$totalMembres = $stmtMembres->fetchColumn();
if ($totalMembres < 1) $totalMembres = 1; 

// B. Seuil de Majorité (50% + 1)
$seuilMajorite = floor($totalMembres / 2) + 1;

// C. Vérifier si l'utilisateur actuel a déjà voté
$stmtCheck = $pdo->prepare("SELECT * FROM votes_decisions WHERE reference_id = ? AND membre_id = ? AND type_vote = 'projet'");
$stmtCheck->execute([$projet_id, $user_id]);
$dejaVote = $stmtCheck->fetch(PDO::FETCH_ASSOC);

// D. Compter les votes actuels
$stmtVotes = $pdo->prepare("SELECT choix, COUNT(*) as total FROM votes_decisions WHERE reference_id = ? AND type_vote = 'projet' GROUP BY choix");
$stmtVotes->execute([$projet_id]);
$votes = $stmtVotes->fetchAll(PDO::FETCH_KEY_PAIR);

$votesPour = isset($votes['pour']) ? $votes['pour'] : 0;
$votesContre = isset($votes['contre']) ? $votes['contre'] : 0;


// --- 4. TRAITEMENT DU VOTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    
    if (!$vote_ouvert) {
        $message = "Les votes sont clôturés pour ce projet.";
        $messageType = "danger";
    } elseif ($dejaVote) {
        $message = "Vous avez déjà voté.";
        $messageType = "warning";
    } else {
        $pdo->beginTransaction();
        try {
            $choix = $_POST['decision']; 
            $commentaire = htmlspecialchars(trim($_POST['commentaire']));

            // 1. Enregistrer le vote
            $stmtInsert = $pdo->prepare("INSERT INTO votes_decisions (reference_id, type_vote, membre_id, choix, commentaire_vote, date_vote) VALUES (?, 'projet', ?, ?, ?, NOW())");
            $stmtInsert->execute([$projet_id, $user_id, $choix, $commentaire]);

            if ($choix == 'pour') $votesPour++;
            if ($choix == 'contre') $votesContre++;

            // 2. VÉRIFICATION MAJORITÉ & FINANCE
            if ($votesPour >= $seuilMajorite && $statut_actuel == 'en_attente') {
                
                // --- CALCUL FINANCIER GLOBAL ---
                $stmtIn = $pdo->query("SELECT SUM(montant_paye) FROM assurances");
                $totalCaisse = $stmtIn->fetchColumn() ?: 0;

                $stmtOut = $pdo->query("SELECT SUM(montant_verse) FROM projets WHERE statut = 'approuve'");
                $totalDepense = $stmtOut->fetchColumn() ?: 0;

                $soldeDisponible = $totalCaisse - $totalDepense;

                if ($soldeDisponible >= $montant_demande) {
                    // VALIDATION
                    $stmtUpdate = $pdo->prepare("UPDATE projets SET statut = 'approuve', montant_verse = ? WHERE id = ?");
                    $stmtUpdate->execute([$montant_demande, $projet_id]);
                    
                    // --- CORRECTION NOTIFICATION ---
                    // On cherche un ID de cercle valide pour satisfaire la contrainte Foreign Key
                    // 1. D'abord dans le projet
                    $cercleIdNotif = isset($projet['cercle_id']) ? $projet['cercle_id'] : null;
                    
                    // 2. Si le projet n'a pas de cercle_id, on prend le premier cercle qui existe dans la base
                    if (empty($cercleIdNotif)) {
                        $stmtC = $pdo->query("SELECT id FROM cercles LIMIT 1");
                        $cercleIdNotif = $stmtC->fetchColumn();
                    }

                    // Insertion Notification
                    $stmtNotif = $pdo->prepare("
                        INSERT INTO notifications 
                        (membre_id, cercle_id, message, date_creation, statut, type, reference_id) 
                        VALUES (?, ?, ?, NOW(), 'non_lu', 'projet', ?)
                    ");
                    
                    $stmtNotif->execute([
                        $projet['membre_id'], 
                        $cercleIdNotif, // Sera l'ID du projet ou le 1er cercle trouvé
                        "Félicitations ! Votre projet a été financé avec succès.", 
                        $projet_id
                    ]);

                    $message = "Majorité atteinte ! Projet validé et financement accordé.";
                    $messageType = "success";
                    $statut_actuel = 'approuve';
                    $vote_ouvert = false;
                } else {
                    $manque = $montant_demande - $soldeDisponible;
                    $message = "Majorité atteinte, mais fonds insuffisants (Manque ".number_format($manque)." FCFA).";
                    $messageType = "warning";
                }
            } else {
                $reste = $seuilMajorite - $votesPour;
                $message = "Vote enregistré. Il manque encore {$reste} voix favorables.";
                $messageType = "success";
            }

            $pdo->commit();
            $dejaVote = ['choix' => $choix, 'date_vote' => date('Y-m-d H:i')];

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur : " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Pourcentages
$percPour = ($totalMembres > 0) ? ($votesPour / $totalMembres) * 100 : 0;
$percContre = ($totalMembres > 0) ? ($votesContre / $totalMembres) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Projet | NDJANGUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card-box { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: 100%; }
        .vote-btn { cursor: pointer; border: 2px solid #eee; padding: 15px; border-radius: 10px; transition: all 0.2s; }
        .vote-btn:hover { border-color: #ccc; transform: translateY(-2px); }
        input[type="radio"]:checked + .vote-btn.pour { border-color: #198754; background-color: #d1e7dd; }
        input[type="radio"]:checked + .vote-btn.contre { border-color: #dc3545; background-color: #f8d7da; }
        input[type="radio"] { display: none; }
    </style>
</head>
<body>

<div class="container py-5">
    
    <a href="projets.php" class="btn btn-outline-secondary mb-4 rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Retour aux projets</a>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> shadow-sm rounded-3 mb-4">
            <i class="fas fa-info-circle me-2"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge bg-light text-dark border mb-2">Projet #<?php echo $projet['id']; ?></span>
                        <h2 class="fw-bold mb-0"><?php echo htmlspecialchars($projet['titre']); ?></h2>
                    </div>
                    <?php if($statut_actuel == 'approuve'): ?>
                        <span class="badge bg-success fs-6 rounded-pill px-3 py-2">Validé <i class="fas fa-check ms-1"></i></span>
                    <?php elseif($statut_actuel == 'rejete'): ?>
                        <span class="badge bg-danger fs-6 rounded-pill px-3 py-2">Rejeté <i class="fas fa-times ms-1"></i></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark fs-6 rounded-pill px-3 py-2">En attente <i class="fas fa-clock ms-1"></i></span>
                    <?php endif; ?>
                </div>

                <div class="p-3 bg-light rounded-3 mb-4 border">
                    <p class="text-secondary mb-0" style="white-space: pre-line;"><?php echo htmlspecialchars($projet['description']); ?></p>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:50px; height:50px;">
                                <i class="fas fa-money-bill-wave fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-bold">Montant Demandé</small>
                                <div class="fs-4 fw-bold text-dark"><?php echo number_format($montant_demande, 0, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:50px; height:50px;">
                                <i class="fas fa-user fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-bold">Demandeur</small>
                                <div class="fs-5 fw-bold text-dark"><?php echo htmlspecialchars($projet['demandeur']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-box border-top border-4 border-primary">
                <h4 class="fw-bold mb-4"><i class="fas fa-vote-yea me-2"></i>Espace Décision</h4>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between small fw-bold mb-1 text-muted">
                        <span>Votes (<?php echo $votesPour + $votesContre; ?>/<?php echo $totalMembres; ?> membres)</span>
                        <span>Objectif : <?php echo $seuilMajorite; ?> voix</span>
                    </div>
                    <div class="progress" style="height: 20px; border-radius: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percPour; ?>%"><?php echo $votesPour > 0 ? $votesPour : ''; ?></div>
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percContre; ?>%"><?php echo $votesContre > 0 ? $votesContre : ''; ?></div>
                    </div>
                </div>

                <hr>

                <?php if($dejaVote): ?>
                    <div class="text-center py-4">
                        <?php if($dejaVote['choix'] == 'pour'): ?>
                            <i class="fas fa-check-circle text-success display-1 mb-3"></i>
                            <h3 class="text-success fw-bold">A voté POUR</h3>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-danger display-1 mb-3"></i>
                            <h3 class="text-danger fw-bold">A voté CONTRE</h3>
                        <?php endif; ?>
                        <p class="text-muted">Merci pour votre participation.</p>
                    </div>
                <?php elseif(!$vote_ouvert): ?>
                    <div class="alert alert-secondary text-center">
                        <i class="fas fa-lock me-2"></i>Les votes sont fermés.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Votre décision :</label>
                            
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <input type="radio" name="decision" id="choix_pour" value="pour" required>
                                    <label for="choix_pour" class="vote-btn pour w-100 d-flex align-items-center">
                                        <div class="bg-success text-white rounded-circle p-2 me-3"><i class="fas fa-thumbs-up"></i></div>
                                        <div>
                                            <strong>Approuver le projet</strong>
                                            <div class="small text-muted">Je suis d'accord pour le financement</div>
                                        </div>
                                    </label>
                                </div>

                                <div>
                                    <input type="radio" name="decision" id="choix_contre" value="contre">
                                    <label for="choix_contre" class="vote-btn contre w-100 d-flex align-items-center">
                                        <div class="bg-danger text-white rounded-circle p-2 me-3"><i class="fas fa-thumbs-down"></i></div>
                                        <div>
                                            <strong>Rejeter le projet</strong>
                                            <div class="small text-muted">Je ne suis pas d'accord</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Commentaire (facultatif)</label>
                            <textarea name="commentaire" class="form-control" rows="2" placeholder="Pourquoi ce choix ?"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold py-3 rounded-3 shadow-sm">
                            <i class="fas fa-paper-plane me-2"></i>Envoyer mon vote
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>