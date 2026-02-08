<?php
ob_start(); // Empêche les erreurs de header
session_start();
date_default_timezone_set('Africa/Douala');
require_once '../config/database.php';

// Sécurité
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
$cercle_id = 1;

// Récupération infos utilisateur
$stmt = $pdo->prepare("SELECT nom_complet, statut FROM membres WHERE id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();
$is_active = ($me && $me['statut'] === 'actif');
$nom_user = htmlspecialchars($me['nom_complet']);

// =================================================================
// 1. MOTEUR D'ANALYSE (Logique conservée à 100%)
// =================================================================
function getAdvancedAnalysis($pdo, $m_id) {
    $stats = ['assurance' => 0, 'cotisation' => 0, 'epargne' => 0, 'historique' => 100, 'solvabilite' => 100];
    $conseil = "Votre profil est excellent.";

    // 1. Dettes
    $imp_ass = $pdo->prepare("SELECT COUNT(*) FROM assurances WHERE membre_id = ? AND statut != 'paye'");
    $imp_ass->execute([$m_id]); $nb_imp_ass = $imp_ass->fetchColumn();
    $stats['assurance'] = ($nb_imp_ass == 0) ? 100 : max(0, 100 - ($nb_imp_ass * 25));

    $imp_cot = $pdo->prepare("SELECT COUNT(*) FROM cotisations WHERE membre_id = ? AND statut != 'paye'");
    $imp_cot->execute([$m_id]); $nb_imp_cot = $imp_cot->fetchColumn();
    $stats['cotisation'] = ($nb_imp_cot == 0) ? 100 : max(0, 100 - ($nb_imp_cot * 20));

    if($nb_imp_ass > 0 || $nb_imp_cot > 0) $conseil = "Régularisez vos impayés pour augmenter vos chances.";

    // 2. Epargne
    $ep = $pdo->prepare("SELECT COUNT(*) FROM epargnes WHERE membre_id = ?");
    $ep->execute([$m_id]); $nb_ep = $ep->fetchColumn();
    $stats['epargne'] = min(100, $nb_ep * 5);
    
    if($stats['epargne'] < 50 && $conseil == "Votre profil est excellent.") $conseil = "Épargnez plus souvent pour montrer votre stabilité.";

    // 3. Historique
    $san = $pdo->prepare("SELECT COUNT(*) FROM sanctions WHERE membre_id = ? AND statut_paiement = 'du'");
    $san->execute([$m_id]); $nb_san = $san->fetchColumn();
    $stats['historique'] = max(0, 100 - ($nb_san * 30));

    if($nb_san > 0) $conseil = "Vous avez des sanctions impayées.";

    // 4. Solvabilité
    $retard = $pdo->prepare("SELECT COUNT(*) FROM prets WHERE membre_id=? AND statut_pret='en_remboursement' AND DATE_ADD(date_demande, INTERVAL duree_mois MONTH) < NOW()");
    $retard->execute([$m_id]);
    if($retard->fetchColumn() > 0) {
        $stats['solvabilite'] = 0;
        $conseil = "ALERTE : Prêt en retard détecté.";
    }

    $score_final = ($stats['assurance'] * 0.20) + ($stats['cotisation'] * 0.20) + ($stats['epargne'] * 0.20) + ($stats['historique'] * 0.20) + ($stats['solvabilite'] * 0.20);

    return ['raw' => $stats, 'score' => round($score_final), 'conseil' => $conseil];
}

$analyse = getAdvancedAnalysis($pdo, $user_id);
$ma_proba = $analyse['score'];
$mon_conseil = $analyse['conseil'];

// Couleur dynamique
$color_hex = "#d32f2f";
if($ma_proba >= 80) $color_hex = "#2e7d32";
elseif($ma_proba >= 50) $color_hex = "#1565c0";
elseif($ma_proba >= 30) $color_hex = "#f9a825";

// =================================================================
// 2. TRAITEMENT DU FORMULAIRE
// =================================================================

// A. NOUVELLE DEMANDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nouvelle_demande') {
    $check = $pdo->prepare("SELECT id FROM prets WHERE membre_id = ? AND statut_pret IN ('en_vote', 'approuve', 'en_remboursement')");
    $check->execute([$user_id]);
    
    if ($check->rowCount() == 0) {
        $montant = intval(str_replace([' ', ','], '', $_POST['montant']));
        $raison_full = "[URGENCE: " . $_POST['urgence'] . "] [" . $_POST['categorie_motif'] . "] " . $_POST['motif_detail'];
        
        $sql = "INSERT INTO prets (cercle_id, membre_id, montant_demande, raison_pret, duree_mois, taux_interet, score_confiance_instantane, statut_pret, date_demande) 
                VALUES (?, ?, ?, ?, ?, 5, ?, 'en_vote', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cercle_id, $user_id, $montant, $raison_full, $_POST['duree'], $ma_proba]);
        $loan_id = $pdo->lastInsertId();
        
        // Notifications
        $msg = "VOTE REQUIS : $nom_user demande " . number_format($montant) . " FCFA.";
        $sql_notif = "INSERT INTO notifications (membre_id, cercle_id, message, type, reference_id, date_creation, statut) VALUES (?, ?, ?, 'vote_pret', ?, NOW(), 'non_lu')";
        $stmt_notif = $pdo->prepare($sql_notif);
        
        $targets = $pdo->query("SELECT id FROM membres WHERE statut='actif' AND id != $user_id");
        while($membre = $targets->fetch()) {
            $stmt_notif->execute([$membre['id'], $cercle_id, $msg, $loan_id]);
        }
        
        header("Location: demander-pret.php?msg=success"); exit;
    } else {
        $erreur = "Vous avez déjà une demande en cours.";
    }
}

// B. TRAITEMENT VOTE (CORRIGÉ & SÉCURISÉ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'voter') {
    if ($is_active) {
        $pret_id = $_POST['pret_id'];
        $choix = $_POST['choix'];
        
        try {
            $pdo->beginTransaction();
            
            // 1. Enregistrer le vote
            $stmt = $pdo->prepare("INSERT INTO votes_decisions (type_vote, reference_id, membre_id, choix) VALUES ('pret', ?, ?, ?)");
            $stmt->execute([$pret_id, $user_id, $choix]);
            
            // 2. Vérifier majorité
            $count_actifs = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut='actif'")->fetchColumn();
            $majorite_requise = floor($count_actifs / 2) + 1;
            
            // Compter les "pour" INCLUANT celui qu'on vient de mettre
            $count_pour = $pdo->prepare("SELECT COUNT(*) FROM votes_decisions WHERE reference_id=? AND type_vote='pret' AND choix='pour'");
            $count_pour->execute([$pret_id]);
            $total_pour = $count_pour->fetchColumn();
            
            $msg_redirect = "vote_ok";

            // 3. Si approuvé (Mise à jour table prets)
            if ($total_pour >= $majorite_requise) {
                // On récupère le montant demandé pour le mettre dans montant_accorde
                $info = $pdo->prepare("SELECT montant_demande, membre_id FROM prets WHERE id=?");
                $info->execute([$pret_id]); 
                $data = $info->fetch();
                
                // --- MISE A JOUR BASE DE DONNÉES (CORRIGÉE) ---
                // On met statut à 'approuve' et on remplit montant_accorde
                $upd = $pdo->prepare("UPDATE prets SET statut_pret='approuve', montant_accorde=? WHERE id=?");
                $upd->execute([$data['montant_demande'], $pret_id]);
                
                // Notification au demandeur
                try {
                    $pdo->prepare("INSERT INTO notifications (membre_id, cercle_id, message, type, reference_id, date_creation, statut) VALUES (?, ?, ?, 'info', ?, NOW(), 'non_lu')")
                        ->execute([$data['membre_id'], $cercle_id, "FÉLICITATIONS ! Votre prêt est approuvé.", $pret_id]);
                } catch(Exception $e) { /* Ignorer erreur notif */ }
                
                $msg_redirect = "approved";
            }
            
            $pdo->commit();
            header("Location: demander-pret.php?msg=" . $msg_redirect); exit;
            
        } catch (Exception $e) { 
            $pdo->rollBack(); 
            $erreur = "Erreur lors du vote : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Analyse & Demande | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root { --primary: #1a237e; --bg: #f8f9fa; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: #1e293b; padding-bottom: 100px; overflow-x: hidden; }
        
        .main-card { background: white; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); padding: 35px; border: 1px solid rgba(0,0,0,0.02); margin-bottom: 25px; transition: all 0.3s ease; }
        
        /* Form Styling */
        .form-control, .form-select { border-radius: 12px; padding: 12px; border: 2px solid #edf2f7; background: #f8fafc; font-weight: 600; font-size: 1rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: none; background: white; }
        
        .reason-card { border: 2px solid #eee; border-radius: 16px; padding: 15px; cursor: pointer; text-align: center; transition: 0.3s; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .reason-radio:checked + .reason-card { border-color: var(--primary); background: #e8eaf6; color: var(--primary); }
        .reason-radio { display: none; }
        
        /* Stats & Charts */
        .chart-box { position: relative; height: 220px; width: 100%; display: flex; justify-content: center; align-items: center; margin-bottom: 20px; }
        .score-display { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
        
        .advice-box { background: #e3f2fd; border-left: 5px solid #2196f3; padding: 20px; border-radius: 12px; margin-top: 20px; }
        
        .vote-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-left: 6px solid #ccc; transition: transform 0.2s; position: relative; overflow: hidden; height: 100%; display: flex; flex-direction: column; }
        .vote-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        
        /* ---- MEDIA QUERIES (Vrai Responsivité) ---- */
        @media (max-width: 768px) {
            .container { padding-left: 15px; padding-right: 15px; }
            .main-card { padding: 20px; border-radius: 20px; }
            
            /* Titres plus petits sur mobile */
            h2, .h2 { font-size: 1.5rem !important; }
            h3 { font-size: 1.25rem !important; }
            
            /* Ajustement score display */
            .score-display h1 { font-size: 2.2rem !important; }
            
            /* Boutons de vote empilés sur très petits écrans */
            .vote-actions { flex-direction: row; }
            
            /* Raison cartes plus compactes */
            .reason-card { padding: 10px; }
            .reason-card i { font-size: 1.5rem !important; }
            .reason-card small { font-size: 0.75rem; }
            
            /* Graphique radar hauteur réduite */
            #radarChart { max-height: 200px; }
        }
        
        @media (max-width: 480px) {
             .vote-actions { flex-direction: column; }
             .btn { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>

<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="index.php" class="text-decoration-none fw-bold text-primary">
            <i class="fa-solid fa-arrow-left me-2"></i> <span class="d-none d-sm-inline">Tableau de bord</span><span class="d-inline d-sm-none">Retour</span>
        </a>
        <span class="badge bg-white text-dark border px-3 py-2 rounded-pill">
            <i class="fas fa-wallet me-2 text-success"></i> Finance
        </span>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg']=='success'): ?>
            <div class="alert alert-success rounded-4 text-center fw-bold shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i> Demande soumise !</div>
        <?php elseif($_GET['msg']=='vote_ok'): ?>
            <div class="alert alert-primary rounded-4 text-center fw-bold shadow-sm mb-4"><i class="fas fa-vote-yea me-2"></i> Vote enregistré.</div>
        <?php elseif($_GET['msg']=='approved'): ?>
            <div class="alert alert-success rounded-4 text-center fw-bold shadow-sm mb-4"><i class="fas fa-trophy me-2"></i> Prêt validé avec succès !</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(isset($erreur)): ?>
        <div class="alert alert-danger rounded-4 text-center fw-bold shadow-sm mb-4"><i class="fas fa-triangle-exclamation me-2"></i> <?php echo $erreur; ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <?php 
            $chk = $pdo->prepare("SELECT * FROM prets WHERE membre_id=? AND statut_pret IN ('en_vote', 'approuve', 'en_remboursement')");
            $chk->execute([$user_id]);
            $pret_actif = $chk->fetch();
            ?>

            <?php if (!$pret_actif): ?>
            <form method="POST">
                <input type="hidden" name="action" value="nouvelle_demande">
                <div class="main-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="fw-800 text-primary m-0">Nouvelle Demande</h3>
                        <span class="badge bg-primary rounded-pill px-3 py-2">Étape 1/2</span>
                    </div>

                    <div class="mb-4 mb-md-5">
                        <label class="fw-bold text-muted small text-uppercase mb-2">Montant Souhaité</label>
                        <div class="h2 fw-900 text-dark mb-3" id="amountDisplay">50,000 FCFA</div>
                        <input type="range" class="form-range" id="loanRange" min="50000" max="1500000" step="10000" value="50000" oninput="updateLoan()">
                        <input type="hidden" name="montant" id="realAmount" value="50000">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-6">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Durée</label>
                            <select name="duree" id="dureeSelect" class="form-select" onchange="updateLoan()">
                                <option value="1">1 Mois</option>
                                <option value="3">3 Mois</option>
                                <option value="6" selected>6 Mois</option>
                                <option value="10">10 Mois</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-6">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Urgence</label>
                            <select name="urgence" class="form-select text-danger fw-bold">
                                <option value="Normale">Normale</option>
                                <option value="Haute">Haute</option>
                                <option value="CRITIQUE">CRITIQUE</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold text-muted small text-uppercase mb-3">Raison principale</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="radio" name="categorie_motif" value="Business" id="r1" class="reason-radio" checked>
                                <label class="reason-card" for="r1"><i class="fa-solid fa-briefcase fs-3 mb-2"></i><small class="fw-bold d-block">Business</small></label>
                            </div>
                            <div class="col-4">
                                <input type="radio" name="categorie_motif" value="Santé" id="r2" class="reason-radio">
                                <label class="reason-card" for="r2"><i class="fa-solid fa-heart-pulse fs-3 mb-2"></i><small class="fw-bold d-block">Santé</small></label>
                            </div>
                            <div class="col-4">
                                <input type="radio" name="categorie_motif" value="Famille" id="r3" class="reason-radio">
                                <label class="reason-card" for="r3"><i class="fa-solid fa-house-chimney-user fs-3 mb-2"></i><small class="fw-bold d-block">Famille</small></label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold text-muted small text-uppercase mb-2">Détails</label>
                        <textarea name="motif_detail" class="form-control" rows="3" placeholder="Expliquez votre projet..." required></textarea>
                    </div>

                    <div class="bg-light rounded-4 p-3 p-md-4 mb-4 border">
                        <div class="d-flex justify-content-between mb-2 opacity-75 small"><span>Frais dossier</span><span>2,000 FCFA</span></div>
                        <div class="d-flex justify-content-between mb-2 opacity-75 small"><span>Intérêts (5%)</span><span id="interestVal">0 FCFA</span></div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-uppercase">Mensualité</span>
                            <span class="h4 fw-900 text-primary mb-0" id="monthlyVal">0 FCFA</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-lg">
                        <i class="fas fa-paper-plane me-2"></i> Soumettre
                    </button>
                </div>
            </form>
            <?php else: ?>
                <div class="main-card text-center py-5">
                    <div class="mb-3"><i class="fas fa-file-invoice-dollar fa-4x text-warning"></i></div>
                    <h2 class="fw-bold">Dossier #<?php echo $pret_actif['id']; ?></h2>
                    <p class="text-muted mb-4">Demande de <strong><?php echo number_format($pret_actif['montant_demande']); ?> FCFA</strong> en cours.</p>
                    <span class="badge bg-warning text-dark fs-6 px-4 py-2 rounded-pill text-uppercase shadow-sm">
                        <?php echo str_replace('_', ' ', $pret_actif['statut_pret']); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="main-card">
                <h6 class="fw-800 text-center mb-4 text-uppercase ls-1 text-muted">Analyse de Profil IA</h6>
                
                <div class="chart-box">
                    <canvas id="scoreChart"></canvas>
                    <div class="score-display">
                        <h1 class="fw-900 mb-0" style="color: <?php echo $color_hex; ?>; font-size: 3rem;"><?php echo $ma_proba; ?>%</h1>
                        <small class="fw-bold text-muted text-uppercase">Score</small>
                    </div>
                </div>

                <div class="advice-box" style="border-left-color: <?php echo $color_hex; ?>; background: <?php echo $color_hex; ?>15;">
                    <div class="d-flex align-items-start gap-3">
                        <i class="fas fa-robot fs-4 mt-1" style="color: <?php echo $color_hex; ?>"></i>
                        <div>
                            <div class="advice-title fw-bold" style="color: <?php echo $color_hex; ?>">Conseil Stratégique</div>
                            <p class="mb-0 small fw-bold mt-1 text-dark opacity-75">"<?php echo $mon_conseil; ?>"</p>
                        </div>
                    </div>
                </div>

                <hr class="my-4 opacity-10">
                <div style="height: 250px; width: 100%;">
                    <canvas id="radarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <div class="d-flex align-items-center mb-4 ps-3 border-start border-5 border-primary">
            <h4 class="fw-800 m-0 me-3">Session de Votes</h4>
            <span class="badge bg-primary rounded-pill">En direct</span>
        </div>

        <div class="row g-4">
            <?php
            $sql_votes = "SELECT p.*, m.nom_complet FROM prets p JOIN membres m ON p.membre_id = m.id WHERE p.statut_pret = 'en_vote' ORDER BY p.date_demande DESC";
            $dossiers = $pdo->query($sql_votes)->fetchAll();
            
            $q_actifs = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut='actif'");
            $tot_actifs = $q_actifs->fetchColumn(); 
            $seuil = floor($tot_actifs / 2) + 1;

            if (!$dossiers) echo '<div class="col-12"><div class="alert alert-light border shadow-sm text-center py-4 text-muted fst-italic">Aucune demande en attente pour le moment.</div></div>';

            foreach($dossiers as $d):
                $v_stats = $pdo->prepare("SELECT COUNT(*) FROM votes_decisions WHERE reference_id=? AND type_vote='pret' AND choix='pour'");
                $v_stats->execute([$d['id']]);
                $nb_pour = $v_stats->fetchColumn();
                $percent = ($tot_actifs > 0) ? ($nb_pour / $tot_actifs) * 100 : 0;

                $chk_vote = $pdo->prepare("SELECT choix FROM votes_decisions WHERE reference_id=? AND membre_id=? AND type_vote='pret'");
                $chk_vote->execute([$d['id'], $user_id]);
                $mon_vote = $chk_vote->fetch();
            ?>
            <div class="col-12 col-md-6 col-lg-4"> 
                <div class="vote-card" style="border-left-color: <?php echo ($d['score_confiance_instantane'] >= 50 ? '#2e7d32' : '#d32f2f'); ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px; flex-shrink:0;">
                                <span class="fw-bold text-primary"><?php echo strtoupper(substr($d['nom_complet'], 0, 1)); ?></span>
                            </div>
                            <div style="min-width:0;">
                                <h6 class="fw-bold mb-0 text-truncate"><?php echo htmlspecialchars($d['nom_complet']); ?></h6>
                                <span class="badge bg-light text-dark border px-2 py-1 mt-1"><?php echo number_format($d['montant_demande']); ?> FCFA</span>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 ms-2">
                            <span class="d-block fw-900 h5 mb-0" style="color: <?php echo ($d['score_confiance_instantane'] >= 50 ? '#2e7d32' : '#d32f2f'); ?>">
                                <?php echo $d['score_confiance_instantane']; ?>%
                            </span>
                            <small class="text-muted d-block" style="font-size: 10px;">CONFIANCE</small>
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 rounded-3 mb-3 border-start border-3">
                        <p class="small text-secondary mb-0 fst-italic">
                            "<?php echo (strlen($d['raison_pret']) > 80) ? substr($d['raison_pret'], 0, 80) . '...' : $d['raison_pret']; ?>"
                        </p>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between small fw-bold mb-1 text-muted">
                            <span>Progression</span>
                            <span><?php echo $nb_pour; ?> / <?php echo $seuil; ?></span>
                        </div>
                        <div class="progress rounded-pill bg-light border" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                    </div>

                    <div class="mt-auto vote-actions d-flex gap-2">
                        <?php if($d['membre_id'] == $user_id): ?>
                            <button class="btn btn-light w-100 fw-bold text-muted border py-2" disabled>
                                <i class="fas fa-user me-2"></i> Moi
                            </button>
                        
                        <?php elseif($mon_vote): ?>
                            <button class="btn btn-secondary w-100 fw-bold py-2 disabled opacity-75">
                                <i class="fas fa-check-circle me-2"></i> <?php echo strtoupper($mon_vote['choix']); ?>
                            </button>
                        
                        <?php elseif(!$is_active): ?>
                            <button class="btn btn-warning bg-opacity-25 w-100 fw-bold text-dark border-warning py-2" disabled>
                                <i class="fas fa-ban me-2"></i> Inactif
                            </button>
                        
                        <?php else: ?>
                            <form method="POST" class="w-100 d-flex gap-2 vote-actions">
                                <input type="hidden" name="action" value="voter">
                                <input type="hidden" name="pret_id" value="<?php echo $d['id']; ?>">
                                <button name="choix" value="pour" class="btn btn-outline-success flex-grow-1 fw-bold py-2 hover-shadow">
                                    <i class="fas fa-thumbs-up me-1"></i> OUI
                                </button>
                                <button name="choix" value="contre" class="btn btn-outline-danger flex-grow-1 fw-bold py-2 hover-shadow">
                                    <i class="fas fa-thumbs-down me-1"></i> NON
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    function updateLoan() {
        const amount = parseInt(document.getElementById('loanRange').value);
        const duree = parseInt(document.getElementById('dureeSelect').value);
        document.getElementById('realAmount').value = amount;
        
        const interest = amount * 0.05;
        const monthly = (amount + interest + 2000) / duree;

        document.getElementById('amountDisplay').innerText = amount.toLocaleString() + " FCFA";
        document.getElementById('interestVal').innerText = interest.toLocaleString() + " FCFA";
        document.getElementById('monthlyVal').innerText = Math.round(monthly).toLocaleString() + " FCFA";
    }
    updateLoan();

    const scoreVal = <?php echo $ma_proba; ?>;
    const colorVal = "<?php echo $color_hex; ?>";
    const radarData = [<?php echo implode(',', $analyse['raw']); ?>];

    new Chart(document.getElementById('scoreChart'), {
        type: 'doughnut',
        data: {
            labels: ['Chance', 'Risque'],
            datasets: [{ data: [scoreVal, 100 - scoreVal], backgroundColor: [colorVal, '#e0e0e0'], borderWidth: 0 }]
        },
        options: { cutout: '85%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    new Chart(document.getElementById('radarChart'), {
        type: 'radar',
        data: {
            labels: ['Assurance', 'Cotisation', 'Épargne', 'Discipline', 'Solvabilité'],
            datasets: [{
                data: radarData,
                fill: true,
                backgroundColor: colorVal + '33',
                borderColor: colorVal,
                pointBackgroundColor: colorVal,
                pointBorderColor: '#fff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                r: { suggestedMin: 0, suggestedMax: 100, ticks: { display: false }, grid: { color: '#eee' }, pointLabels: { font: { size: 10, weight: 'bold' } } }
            },
            plugins: { legend: { display: false } }
        }
    });
</script>
</body>
</html>