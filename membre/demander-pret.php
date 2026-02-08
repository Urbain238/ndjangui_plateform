<?php
ob_start();
session_start();
date_default_timezone_set('Africa/Douala');
require_once '../config/database.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
$cercle_id = 1; // À dynamiser selon votre logique de session si besoin
$stmt = $pdo->prepare("SELECT nom_complet, statut, date_inscription FROM membres WHERE id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();
$is_active = ($me && $me['statut'] === 'actif');
$nom_user = htmlspecialchars($me['nom_complet']);
function getAdvancedAnalysis($pdo, $m_id, $date_inscription) {
    $stats = [
        'discipline' => 100,
        'finance' => 0,
        'experience' => 0,
        'credit' => 0,
        'solvabilite' => 100
    ];
    $conseil = "Profil en cours d'analyse.";
    $sql_cotis = "SELECT COUNT(*) FROM cotisations WHERE membre_id = ? AND (statut != 'paye' OR (statut = 'paye' AND date_paiement > date_limite))";
    $q_cot = $pdo->prepare($sql_cotis);
    $q_cot->execute([$m_id]);
    $defauts_cotis = $q_cot->fetchColumn();
    $sql_assur = "SELECT COUNT(*) FROM assurances WHERE membre_id = ? AND (statut != 'paye' OR (statut = 'paye' AND date_paiement > date_limite))";
    $q_ass = $pdo->prepare($sql_assur);
    $q_ass->execute([$m_id]);
    $defauts_assur = $q_ass->fetchColumn();
    $malus = ($defauts_cotis * 15) + ($defauts_assur * 15);
    $stats['discipline'] = max(0, 100 - $malus);
    if($malus > 0) $conseil = "Attention aux retards ($defauts_cotis cotis, $defauts_assur assur). Respectez les dates limites.";
    $q_ep = $pdo->prepare("SELECT COALESCE(SUM(montant_paye), 0) FROM epargnes WHERE membre_id = ? AND statut = 'paye'");
    $q_ep->execute([$m_id]);
    $total_epargne = $q_ep->fetchColumn();
    $q_cot_money = $pdo->prepare("SELECT COALESCE(SUM(montant_paye), 0) FROM cotisations WHERE membre_id = ? AND statut = 'paye'");
    $q_cot_money->execute([$m_id]);
    $total_cotis = $q_cot_money->fetchColumn();
    $total_investi = $total_epargne + $total_cotis;
    $stats['finance'] = min(100, ($total_investi / 100000) * 100);
    if($total_investi < 20000) $conseil = "Injectez plus d'argent (Épargnes/Cotisations) pour rassurer le groupe.";
    $q_hist = $pdo->prepare("SELECT COUNT(*) FROM prets WHERE membre_id = ? AND statut_pret = 'paye'");
    $q_hist->execute([$m_id]);
    $nb_rembourses = $q_hist->fetchColumn();
    $stats['credit'] = min(100, $nb_rembourses * 25);
    $date_inscr = new DateTime($date_inscription);
    $now = new DateTime();
    $months = ($date_inscr->diff($now)->y * 12) + $date_inscr->diff($now)->m;
    $stats['experience'] = min(100, $months * 5);
    $sql_retard = "SELECT COUNT(*) FROM prets WHERE membre_id = ? AND statut_pret = 'en_remboursement' AND DATE_ADD(date_demande, INTERVAL duree_mois MONTH) < NOW()";
    $q_retard = $pdo->prepare($sql_retard);
    $q_retard->execute([$m_id]);
    if($q_retard->fetchColumn() > 0) {
        $stats['solvabilite'] = 0;
        $conseil = "ALERTE ROUGE : Vous avez un prêt non remboursé dans les délais.";
    }
    if($stats['solvabilite'] == 0) {
        $score_final = 0;
    } else {
        $score_final = ($stats['discipline'] * 0.40) + ($stats['finance'] * 0.30) + ($stats['credit'] * 0.20) + ($stats['experience'] * 0.10);
    }
    return [
        'raw' => array_values($stats), 
        'score' => round($score_final), 
        'conseil' => $conseil,
        'montant_total' => $total_investi
    ];
}
$analyse = getAdvancedAnalysis($pdo, $user_id, $me['date_inscription']);
$ma_proba = $analyse['score'];
$mon_conseil = $analyse['conseil'];
$color_hex = "#d32f2f"; 
if($ma_proba >= 80) $color_hex = "#2e7d32"; 
elseif($ma_proba >= 50) $color_hex = "#f9a825";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nouvelle_demande') {
    $check = $pdo->prepare("SELECT id FROM prets WHERE membre_id = ? AND statut_pret IN ('en_vote', 'approuve', 'en_remboursement')");
    $check->execute([$user_id]);
    if ($check->rowCount() == 0) {
        $montant = intval(str_replace([' ', ','], '', $_POST['montant']));
        $duree = intval($_POST['duree']);
        $raison_full = "[URGENCE: " . $_POST['urgence'] . "] [" . $_POST['categorie_motif'] . "] " . $_POST['motif_detail'];
        $sql = "INSERT INTO prets (cercle_id, membre_id, montant_demande, raison_pret, duree_mois, taux_interet, score_confiance_instantane, statut_pret, date_demande) VALUES (?, ?, ?, ?, ?, 5, ?, 'en_vote', NOW())";
        $stmt = $pdo->prepare($sql);
        if($stmt->execute([$cercle_id, $user_id, $montant, $raison_full, $duree, $ma_proba])) {
            $loan_id = $pdo->lastInsertId();
            try {
                $msg = "VOTE : $nom_user demande " . number_format($montant) . " FCFA.";
                $sql_notif = "INSERT INTO notifications (membre_id, cercle_id, message, type, reference_id, date_creation, statut) VALUES (?, ?, ?, 'vote_pret', ?, NOW(), 'non_lu')";
                $stmt_notif = $pdo->prepare($sql_notif);
                $targets = $pdo->query("SELECT id FROM membres WHERE statut='actif' AND id != $user_id");
                while($m = $targets->fetch()) {
                    $stmt_notif->execute([$m['id'], $cercle_id, $msg, $loan_id]);
                }
            } catch (Exception $e) {}
            header("Location: demander-pret.php?msg=success"); exit;
        } else {
            $erreur = "Erreur technique lors de l'enregistrement.";
        }
    } else {
        $erreur = "Vous avez déjà une demande en cours ou un prêt non remboursé.";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'voter') {
    if ($is_active) {
        $pret_id = $_POST['pret_id'];
        $choix = $_POST['choix'];
        try {
            $pdo->beginTransaction();
            $chk = $pdo->prepare("SELECT id FROM votes_decisions WHERE reference_id=? AND membre_id=? AND type_vote='pret'");
            $chk->execute([$pret_id, $user_id]);
            if($chk->rowCount() == 0) {
                $stmt = $pdo->prepare("INSERT INTO votes_decisions (type_vote, reference_id, membre_id, choix) VALUES ('pret', ?, ?, ?)");
                $stmt->execute([$pret_id, $user_id, $choix]);
                $count_actifs = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut='actif'")->fetchColumn();
                $majorite_requise = floor($count_actifs / 2) + 1;
                $count_pour = $pdo->prepare("SELECT COUNT(*) FROM votes_decisions WHERE reference_id=? AND type_vote='pret' AND choix='pour'");
                $count_pour->execute([$pret_id]);
                $total_pour = $count_pour->fetchColumn();
                $msg_redirect = "vote_ok";
                if ($total_pour >= $majorite_requise) {
                    $info = $pdo->prepare("SELECT montant_demande, membre_id FROM prets WHERE id=?");
                    $info->execute([$pret_id]); 
                    $data = $info->fetch();
                    $upd = $pdo->prepare("UPDATE prets SET statut_pret='approuve', montant_accorde=? WHERE id=?");
                    $upd->execute([$data['montant_demande'], $pret_id]);
                    $msg_redirect = "approved";
                }
                $pdo->commit();
                header("Location: demander-pret.php?msg=" . $msg_redirect); exit;
            } else {
                $pdo->rollBack();
                $erreur = "Vous avez déjà voté.";
            }
        } catch (Exception $e) { 
            $pdo->rollBack(); 
            $erreur = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demander un Prêt | NDJANGUI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #1a237e; --secondary: #3949ab; --bg: #f3f4f6; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: #334155; padding-bottom: 60px; }
        .main-card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.03); margin-bottom: 24px; overflow: hidden; }
        .form-control, .form-select { border-radius: 10px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; font-weight: 500; transition: all 0.2s; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1); background: #fff; }
        .reason-card { border: 2px solid #edf2f7; border-radius: 12px; padding: 12px; cursor: pointer; text-align: center; transition: all 0.2s; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.7; }
        .reason-radio:checked + .reason-card { border-color: var(--primary); background: #e8eaf6; color: var(--primary); opacity: 1; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .reason-card:hover { border-color: #cbd5e1; }
        .reason-radio { display: none; }
        .chart-container { position: relative; height: 180px; display: flex; justify-content: center; align-items: center; }
        .score-overlay { position: absolute; text-align: center; pointer-events: none; }
        .vote-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-left: 4px solid #ccc; height: 100%; }
        .vote-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        /* Layout Fixes */
        .sticky-sidebar { position: -webkit-sticky; position: sticky; top: 20px; z-index: 100; }
        .status-pill { padding: 0.35em 0.8em; border-radius: 50rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.025em; text-transform: uppercase; }
        /* Custom scrollbar for better look */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <a href="index.php" class="text-decoration-none text-muted small fw-bold mb-1 d-block"><i class="fas fa-arrow-left me-1"></i> RETOUR DASHBOARD</a>
            <h2 class="fw-bold m-0 text-dark">Espace Emprunteur</h2>
        </div>
        <div class="d-flex align-items-center bg-white px-3 py-2 rounded-pill shadow-sm border">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                <i class="fas fa-user-shield"></i>
            </div>
            <span class="fw-bold small text-secondary">
                <?php echo $nom_user; ?>
            </span>
        </div>
    </div>
    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg']=='success'): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center"><i class="fas fa-check-circle me-3 fs-4"></i><div><strong>Succès !</strong> Votre demande a été transmise aux membres.</div></div>
        <?php elseif($_GET['msg']=='vote_ok'): ?>
            <div class="alert alert-primary border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center"><i class="fas fa-vote-yea me-3 fs-4"></i><div><strong>Vote enregistré.</strong> Merci pour votre participation.</div></div>
        <?php elseif($_GET['msg']=='approved'): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center"><i class="fas fa-glass-cheers me-3 fs-4"></i><div><strong>Félicitations !</strong> Le prêt est approuvé.</div></div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if(isset($erreur)): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $erreur; ?></div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <?php 
            $chk = $pdo->prepare("SELECT * FROM prets WHERE membre_id=? AND statut_pret IN ('en_vote', 'approuve', 'en_remboursement')");
            $chk->execute([$user_id]);
            $pret_actif = $chk->fetch();
            ?>
            <?php if (!$pret_actif): ?>
            <form method="POST">
                <input type="hidden" name="action" value="nouvelle_demande">
                <div class="main-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <div>
                            <h4 class="fw-bold m-0 text-primary">Simulateur de Prêt</h4>
                            <small class="text-muted">Configurez votre besoin ci-dessous</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2">Étape 1/2</span>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="small text-uppercase fw-bold text-muted mb-2">Montant souhaité</label>
                            <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-3 mb-2 border">
                                <span class="h2 fw-900 text-dark mb-0" id="displayAmount">50,000</span>
                                <span class="h4 fw-bold text-muted mb-0">FCFA</span>
                            </div>
                            <input type="range" class="form-range" id="rangeAmount" min="10000" max="1000000" step="5000" value="50000" oninput="updateSim()">
                            <input type="hidden" name="montant" id="inputAmount" value="50000">
                        </div>
                        <div class="col-md-6">
                            <label class="small text-uppercase fw-bold text-muted mb-2">Durée de remboursement</label>
                            <select name="duree" id="dureeSelect" class="form-select fs-5" onchange="updateSim()">
                                <option value="1">1 mois</option>
                                <option value="3">3 mois</option>
                                <option value="6" selected>6 mois</option>
                                <option value="10">10 mois</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-uppercase fw-bold text-muted mb-2">Urgence</label>
                            <select name="urgence" class="form-select fs-5 text-danger fw-bold border-danger bg-danger bg-opacity-10">
                                <option value="Moyenne">Moyenne</option>
                                <option value="Haute">Haute</option>
                                <option value="Critique">Critique</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="small text-uppercase fw-bold text-muted mb-3">Motif de la demande</label>
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <input type="radio" name="categorie_motif" value="Business" id="r1" class="reason-radio" checked>
                                <label class="reason-card" for="r1"><i class="fa-solid fa-briefcase fs-3 mb-2"></i><small class="fw-bold">Affaires</small></label>
                            </div>
                            <div class="col-4">
                                <input type="radio" name="categorie_motif" value="Sante" id="r2" class="reason-radio">
                                <label class="reason-card" for="r2"><i class="fa-solid fa-heart-pulse fs-3 mb-2"></i><small class="fw-bold">Santé</small></label>
                            </div>
                            <div class="col-4">
                                <input type="radio" name="categorie_motif" value="Perso" id="r3" class="reason-radio">
                                <label class="reason-card" for="r3"><i class="fa-solid fa-house-user fs-3 mb-2"></i><small class="fw-bold">Perso</small></label>
                            </div>
                        </div>
                        <textarea name="motif_detail" class="form-control" rows="3" placeholder="Expliquez brièvement pourquoi vous avez besoin de ce prêt..." required></textarea>
                    </div>
                    <div class="bg-primary bg-opacity-5 rounded-4 p-4 border border-primary border-opacity-10 mt-4">
                        <div class="d-flex justify-content-between small mb-2 text-muted"><span>Intérêts (5%)</span><span id="valInterest" class="fw-bold">0 FCFA</span></div>
                        <div class="d-flex justify-content-between fw-bold text-primary align-items-center pt-2 border-top border-primary border-opacity-10">
                            <span>Mensualité à prévoir</span>
                            <span class="fs-4" id="valMonthly">0 FCFA</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3 mt-4 rounded-pill fw-bold shadow-sm text-uppercase letter-spacing-1">
                        <i class="fas fa-paper-plane me-2"></i> Envoyer ma demande
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="main-card p-0 mb-4 position-relative overflow-hidden border-primary">
                <div class="bg-primary p-4 text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-white text-primary mb-2">Dossier #<?php echo $pret_actif['id']; ?></span>
                            <h3 class="fw-bold mb-0"><?php echo number_format($pret_actif['montant_demande']); ?> FCFA</h3>
                            <small class="opacity-75">Demandé le <?php echo date('d/m/Y', strtotime($pret_actif['date_demande'])); ?></small>
                        </div>
                        <div class="text-end">
                            <?php if($pret_actif['statut_pret'] == 'en_vote'): ?>
                                <i class="fas fa-hourglass-half fa-3x opacity-25"></i>
                            <?php elseif($pret_actif['statut_pret'] == 'approuve'): ?>
                                <i class="fas fa-check-circle fa-3x opacity-25"></i>
                            <?php else: ?>
                                <i class="fas fa-wallet fa-3x opacity-25"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-bold text-uppercase small">Statut actuel</span>
                        <?php 
                            $statusClass = 'bg-warning text-dark';
                            $statusLabel = 'En attente de vote';
                            if($pret_actif['statut_pret'] == 'approuve') { $statusClass = 'bg-success text-white'; $statusLabel = 'Approuvé'; }
                            elseif($pret_actif['statut_pret'] == 'en_remboursement') { $statusClass = 'bg-info text-white'; $statusLabel = 'En Remboursement'; }
                        ?>
                        <span class="badge <?php echo $statusClass; ?> px-3 py-2 rounded-pill"><?php echo $statusLabel; ?></span>
                    </div>
                    <?php if($pret_actif['statut_pret'] == 'en_vote'): 
                        // Calculer progression vote
                         $tot_actifs = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut='actif'")->fetchColumn();
                         $c_pour = $pdo->prepare("SELECT COUNT(*) FROM votes_decisions WHERE reference_id=? AND type_vote='pret' AND choix='pour'");
                         $c_pour->execute([$pret_actif['id']]);
                         $nb_pour = $c_pour->fetchColumn();
                         $progression = ($tot_actifs > 0) ? ($nb_pour / $tot_actifs) * 100 : 0;
                    ?>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Progression des votes</span>
                                <span class="fw-bold"><?php echo round($progression); ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 10px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: <?php echo $progression; ?>%"></div>
                            </div>
                            <p class="small text-center text-muted mt-2 fst-italic">En attente de la validation par les membres...</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border mb-0 text-center small text-muted">
                            Consultez votre échéancier dans le menu "Remboursements".
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="d-flex align-items-center justify-content-between mt-5 mb-3">
                <h5 class="fw-bold m-0 text-dark"><i class="fas fa-users-cog me-2 text-primary"></i>Votes en cours</h5>
                <span class="badge bg-secondary rounded-pill">Aidez la communauté</span>
            </div>
            <div class="row g-3">
                <?php
                $sql_votes = "SELECT p.*, m.nom_complet FROM prets p JOIN membres m ON p.membre_id = m.id WHERE p.statut_pret = 'en_vote' ORDER BY p.date_demande DESC";
                $votes = $pdo->query($sql_votes)->fetchAll();
                $tot_actifs = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut='actif'")->fetchColumn(); 
                if(!$votes): ?>
                    <div class="col-12">
                        <div class="text-center text-muted py-5 bg-white rounded-4 border border-dashed">
                            <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                            <p class="mb-0 fw-medium">Aucun vote en cours actuellement.</p>
                        </div>
                    </div>
                <?php endif;
                foreach($votes as $v):
                    $c_pour = $pdo->prepare("SELECT COUNT(*) FROM votes_decisions WHERE reference_id=? AND type_vote='pret' AND choix='pour'");
                    $c_pour->execute([$v['id']]);
                    $nb_pour = $c_pour->fetchColumn();
                    $prog = ($tot_actifs > 0) ? ($nb_pour / $tot_actifs) * 100 : 0;
                    $me_voted = $pdo->prepare("SELECT choix FROM votes_decisions WHERE reference_id=? AND membre_id=? AND type_vote='pret'");
                    $me_voted->execute([$v['id'], $user_id]);
                    $my_vote = $me_voted->fetch();
                    $s_col = ($v['score_confiance_instantane'] >= 70) ? '#2e7d32' : (($v['score_confiance_instantane'] >= 50) ? '#f9a825' : '#d32f2f');
                ?>
                <div class="col-md-6">
                    <div class="main-card p-0 d-flex flex-column h-100 vote-card" style="border-left-color: <?php echo $s_col; ?>;">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-bold mb-0 text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($v['nom_complet']); ?></h6>
                                <small class="text-primary fw-bold"><?php echo number_format($v['montant_demande']); ?> FCFA</small>
                            </div>
                            <div class="text-center lh-1">
                                <span class="d-block fw-900 h5 mb-0" style="color: <?php echo $s_col; ?>"><?php echo $v['score_confiance_instantane']; ?>%</span>
                                <span style="font-size: 10px; color: <?php echo $s_col; ?>; font-weight: bold;">FIABILITÉ</span>
                            </div>
                        </div>
                        <div class="p-3 flex-grow-1">
                            <div class="bg-white border rounded p-2 mb-3">
                                <p class="small text-muted mb-0 fst-italic" style="font-size: 0.85rem;">
                                    <i class="fas fa-quote-left me-1 opacity-25"></i>
                                    <?php echo htmlspecialchars(substr($v['raison_pret'], 0, 80)) . (strlen($v['raison_pret']) > 80 ? '...' : ''); ?>
                                </p>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small fw-bold mb-1 text-muted">
                                    <span><?php echo $nb_pour; ?> votes</span>
                                    <span><?php echo round($prog); ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $prog; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 bg-light border-top mt-auto">
                            <?php if($v['membre_id'] == $user_id): ?>
                                <button class="btn btn-white border w-100 btn-sm text-muted fw-bold disabled" style="opacity: 0.6">Votre demande</button>
                            <?php elseif($my_vote): ?>
                                <?php $btnClass = ($my_vote['choix'] == 'pour') ? 'btn-success' : 'btn-danger'; ?>
                                <button class="btn <?php echo $btnClass; ?> w-100 btn-sm fw-bold disabled opacity-75">
                                    <i class="fas fa-check me-1"></i> VOTÉ : <?php echo strtoupper($my_vote['choix']); ?>
                                </button>
                            <?php else: ?>
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="voter">
                                    <input type="hidden" name="pret_id" value="<?php echo $v['id']; ?>">
                                    <button name="choix" value="pour" class="btn btn-outline-success w-50 btn-sm fw-bold hover-scale"><i class="fas fa-thumbs-up me-1"></i> OUI</button>
                                    <button name="choix" value="contre" class="btn btn-outline-danger w-50 btn-sm fw-bold hover-scale"><i class="fas fa-thumbs-down me-1"></i> NON</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="sticky-sidebar">
                <div class="main-card p-4">
                    <h6 class="fw-bold text-uppercase text-muted text-center mb-4 small ls-1">Votre Score de Crédibilité</h6>
                    <div class="chart-container mb-3">
                        <canvas id="scoreChart"></canvas>
                        <div class="score-overlay">
                            <h1 class="fw-900 mb-0 display-4" style="color: <?php echo $color_hex; ?>"><?php echo $ma_proba; ?>%</h1>
                        </div>
                    </div>
                    <div class="alert p-3 rounded-3 mb-4" style="background-color: <?php echo $color_hex; ?>10; border-left: 4px solid <?php echo $color_hex; ?>;">
                        <div class="d-flex gap-2">
                            <i class="fas fa-robot mt-1" style="color: <?php echo $color_hex; ?>"></i>
                            <div>
                                <strong style="color: <?php echo $color_hex; ?>; font-size: 0.9rem;">Analyse IA</strong>
                                <p class="small mb-0 mt-1 lh-sm text-muted"><?php echo $mon_conseil; ?></p>
                            </div>
                        </div>
                    </div>
                    <hr class="opacity-10 my-4">
                    <h6 class="fw-bold text-uppercase text-muted text-center mb-3 small">Détails Performance</h6>
                    <div style="height: 200px;">
                        <canvas id="radarChart"></canvas>
                    </div>
                    <div class="mt-4 pt-3 border-top text-center">
                         <small class="text-muted d-block mb-1">Total injecté dans le système</small>
                         <h5 class="fw-bold text-dark"><?php echo number_format($analyse['montant_total']); ?> FCFA</h5>
                    </div>
                </div>
                <div class="text-center small text-muted opacity-75">
                    <i class="fas fa-lock me-1"></i> Données chiffrées & sécurisées
                </div>
            </div>
        </div>
    </div> </div>
<script>
    function updateSim() {
        let amt = parseInt(document.getElementById('rangeAmount').value);
        let dur = parseInt(document.getElementById('dureeSelect').value);
        document.getElementById('inputAmount').value = amt;
        document.getElementById('displayAmount').innerText = amt.toLocaleString('fr-FR');
        let interest = amt * 0.05;
        let monthly = (amt + interest) / dur;
        document.getElementById('valInterest').innerText = interest.toLocaleString('fr-FR') + " FCFA";
        document.getElementById('valMonthly').innerText = Math.round(monthly).toLocaleString('fr-FR') + " FCFA";}
    if(document.getElementById('rangeAmount')) {
        updateSim();}
    const ctxScore = document.getElementById('scoreChart').getContext('2d');
    new Chart(ctxScore, {
        type: 'doughnut',
        data: {
            labels: ['Fiabilité', 'Risque'],
            datasets: [{
                data: [<?php echo $ma_proba; ?>, <?php echo 100 - $ma_proba; ?>],
                backgroundColor: ['<?php echo $color_hex; ?>', '#f1f5f9'],
                borderWidth: 0,
                borderRadius: 5
            }]
        },
        options: { cutout: '85%', plugins: { legend: { display: false }, tooltip: { enabled: false } }, responsive: true, maintainAspectRatio: false }
    });
    const ctxRadar = document.getElementById('radarChart').getContext('2d');
    new Chart(ctxRadar, {
        type: 'radar',
        data: {
            labels: ['Discipline', 'Finance', 'Exp.', 'Crédit', 'Solvab.'],
            datasets: [{
                label: 'Mon Profil',
                data: [<?php echo implode(',', $analyse['raw']); ?>],
                fill: true,
                backgroundColor: '<?php echo $color_hex; ?>20',
                borderColor: '<?php echo $color_hex; ?>',
                pointBackgroundColor: '<?php echo $color_hex; ?>',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '<?php echo $color_hex; ?>'
            }]},
        options: {
            scales: { r: { suggestedMin: 0, suggestedMax: 100, ticks: { display: false, stepSize: 25 }, pointLabels: { font: { size: 10, family: 'Plus Jakarta Sans' } } } },
            plugins: { legend: { display: false } },
            maintainAspectRatio: false }});
</script>
</body>
</html>