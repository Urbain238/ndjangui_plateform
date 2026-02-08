<?php
// =================================================================================
// 0. SESSION & SÉCURITÉ
// =================================================================================
session_start(); 

$current_admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

// =================================================================================
// 1. CONFIGURATION & CONNEXION BDD
// =================================================================================

// Inclusion du fichier de configuration
require_once '../config/database.php';

$message = "";
$messageType = ""; 

try {
    // Connexion via la classe Database
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// --- RÉCUPÉRATION DYNAMIQUE DE LA SÉANCE (Pour éviter le bug ID=1) ---
// On cherche la dernière séance. Si aucune n'existe, on met 0 (ce qui affichera un message d'erreur doux au lieu de planter)
$stmt_check = $pdo->query("SELECT id FROM seances ORDER BY id DESC LIMIT 1");
$last_seance = $stmt_check->fetchColumn();
$current_seance_id = $last_seance ? $last_seance : 0; 
$current_cercle_id = 1;

$message = "";
$message_type = "";

// =================================================================================
// 2. TRAITEMENT DES ACTIONS (PHP)
// =================================================================================

// --- A. AJOUTER UNE SANCTION ---
if (isset($_POST['action']) && $_POST['action'] == 'add_sanction') {
    $membre_id = $_POST['membre_id'];
    $motif = $_POST['motif'];
    $montant = $_POST['montant'];
    $date_sanction = $_POST['date_sanction'];

    if($membre_id && $montant > 0) {
        // BLOC TRY/CATCH POUR EVITER L'ERREUR FATALE
        try {
            $sql = "INSERT INTO sanctions (membre_id, seance_id, motif, montant, statut_paiement, date_sanction, statut) 
                    VALUES (?, ?, ?, ?, 'du', ?, 'actif')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$membre_id, $current_seance_id, $motif, $montant, $date_sanction]);
            
            $message = "Sanction appliquée avec succès.";
            $message_type = "success";
        } catch (PDOException $e) {
            // Si l'erreur est une contrainte de clé étrangère (ex: séance n'existe pas)
            if ($e->getCode() == '23000') {
                $message = "Impossible d'ajouter : La séance (ID $current_seance_id) n'existe pas ou a été supprimée. Créez d'abord une séance.";
            } else {
                $message = "Erreur technique : " . $e->getMessage();
            }
            $message_type = "danger";
        }
    }
}

// --- B. BASCULER STATUT PAIEMENT (RÉVERSIBLE) ---
if (isset($_POST['action']) && $_POST['action'] == 'toggle_payment') {
    try {
        $id = $_POST['id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'du') ? 'paye' : 'du';
        
        $sql = "UPDATE sanctions SET statut_paiement = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_status, $id]);

        $message = ($new_status == 'paye') ? "Paiement enregistré." : "Paiement annulé.";
        $message_type = ($new_status == 'paye') ? "success" : "warning";
    } catch (PDOException $e) {
        $message = "Erreur lors de la modification.";
        $message_type = "danger";
    }
}

// --- C. SUPPRIMER UNE SANCTION ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_sanction') {
    try {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM sanctions WHERE id = ?")->execute([$id]);
        $message = "Sanction supprimée définitivement.";
        $message_type = "dark";
    } catch (PDOException $e) {
        $message = "Impossible de supprimer.";
        $message_type = "danger";
    }
}

// =================================================================================
// 3. RÉCUPÉRATION DES DONNÉES
// =================================================================================

$membres = $pdo->query("SELECT id, nom_complet FROM membres")->fetchAll();

$sql = "SELECT s.*, m.nom_complet 
        FROM sanctions s 
        JOIN membres m ON s.membre_id = m.id 
        WHERE s.seance_id = ? 
        ORDER BY s.date_sanction DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$current_seance_id]);
$sanctions = $stmt->fetchAll();

// KPI
$total_attendu = 0; $total_recu = 0; $nb_retards = 0; $nb_absences = 0;
foreach($sanctions as $s) {
    $total_attendu += $s['montant'];
    if($s['statut_paiement'] == 'paye') $total_recu += $s['montant'];
    if($s['motif'] == 'retard') $nb_retards++;
    if($s['motif'] == 'absence') $nb_absences++;
}
$reste_a_payer = $total_attendu - $total_recu;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Sanctions | Ndjangui Premium</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-dark: #022c22;
            --primary: #065f46;
            --accent: #f59e0b;
            --bg-soft: #f8fafc;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }
        body { background-color: var(--bg-soft); font-family: 'Poppins', sans-serif; color: #1e293b; }

        /* HEADER */
        .premium-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            padding: 2rem 0 4rem;
            color: white;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
        }
        .btn-glass-back {
            background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2); color: white;
            border-radius: 30px; padding: 0.5rem 1.2rem; text-decoration: none;
            transition: all 0.3s;
        }
        .btn-glass-back:hover { background: rgba(255, 255, 255, 0.2); color: white; }

        /* KPI */
        .kpi-container { margin-top: -3rem; }
        .kpi-card {
            background: white; border: none; border-radius: 20px;
            padding: 1.5rem; box-shadow: var(--card-shadow);
            transition: transform 0.3s; position: relative; overflow: hidden;
            height: 100%; /* Equal height */
        }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-icon {
            position: absolute; right: -10px; bottom: -10px;
            font-size: 4rem; opacity: 0.05; color: var(--primary);
        }
        
        /* TABLE */
        .table-card {
            background: white; border-radius: 20px; border: none;
            box-shadow: var(--card-shadow); overflow: hidden; margin-top: 2rem;
        }
        .table-premium thead th {
            background: #f1f5f9; color: #64748b; font-weight: 700;
            text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;
            padding: 1rem; border: none; white-space: nowrap;
        }
        .table-premium tbody td {
            padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
        }

        /* BADGES */
        .motif-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.8rem;
        }
        .motif-retard { background: #fef3c7; color: #b45309; border-left: 4px solid #f59e0b; }
        .motif-absence { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .motif-indiscipline { background: #ffedd5; color: #9a3412; border-left: 4px solid #f97316; }

        .status-badge {
            padding: 5px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        }
        .status-du { background: #ffe4e6; color: #e11d48; }
        .status-paye { background: #dcfce7; color: #166534; }

        /* BUTTONS */
        .btn-fab {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border: none; border-radius: 50px;
            padding: 10px 20px; box-shadow: 0 4px 15px rgba(6, 95, 70, 0.4);
            font-weight: 600; display: flex; align-items: center; gap: 8px;
            transition: all 0.3s; white-space: nowrap;
        }
        .btn-fab:hover { transform: scale(1.05); color: white; }

        .action-btn {
            border: none; background: transparent; width: 32px; height: 32px;
            border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .btn-pay:hover { background: #dcfce7; color: #166534; }
        .btn-undo:hover { background: #fff7ed; color: #f59e0b; }
        .btn-delete:hover { background: #fee2e2; color: #ef4444; }

        /* MODAL */
        .modal-premium .modal-content { border-radius: 20px; border: none; }
        .modal-premium .modal-header { background: var(--bg-soft); border-bottom: none; }
    </style>
</head>
<body>

    <div class="premium-header">
        <div class="container">
            <div class="mb-4">
                <a href="javascript:history.back()" class="btn-glass-back">
                    <i class="fa-solid fa-arrow-left me-2"></i>Retour
                </a>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                <div>
                    <h1 class="fw-bold display-6 mb-0">Gestion des Sanctions</h1>
                    <p class="mb-0 opacity-75 small">Suivi des amendes, retards et disciplines</p>
                    <p class="mb-0 opacity-50 small mt-1"><i class="fa-solid fa-hashtag me-1"></i>Séance active : <?php echo $current_seance_id; ?></p>
                </div>
                <button class="btn-fab align-self-start align-self-md-auto" data-bs-toggle="modal" data-bs-target="#addSanctionModal">
                    <i class="fa-solid fa-gavel"></i> Nouvelle Sanction
                </button>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> shadow-sm rounded-4 border-0 d-flex align-items-center mt-4" role="alert">
                <i class="fa-solid <?php echo $message_type == 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?> fs-4 me-3"></i>
                <div><?php echo $message; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row kpi-container g-3">
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <h6 class="text-uppercase text-muted fw-bold small">Reste à Recouvrer</h6>
                    <h2 class="display-6 fw-bold text-danger mb-0"><?php echo number_format($reste_a_payer, 0, ',', ' '); ?> <small class="fs-6">FCFA</small></h2>
                    <i class="fa-solid fa-hand-holding-dollar kpi-icon"></i>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <h6 class="text-uppercase text-muted fw-bold small">Total Encaissé</h6>
                    <h2 class="display-6 fw-bold text-success mb-0"><?php echo number_format($total_recu, 0, ',', ' '); ?> <small class="fs-6">FCFA</small></h2>
                    <i class="fa-solid fa-vault kpi-icon"></i>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <h6 class="text-uppercase text-muted fw-bold small">Infractions</h6>
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <span class="badge bg-warning text-dark"><i class="fa-regular fa-clock me-1"></i> <?php echo $nb_retards; ?> Retards</span>
                        <span class="badge bg-danger"><i class="fa-solid fa-user-xmark me-1"></i> <?php echo $nb_absences; ?> Absences</span>
                    </div>
                    <i class="fa-solid fa-chart-pie kpi-icon"></i>
                </div>
            </div>
        </div>

        <div class="card table-card">
            <div class="card-header bg-white py-3 px-4 border-bottom-0">
                <h5 class="fw-bold mb-0 text-dark">Historique de la séance</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Membre Concerné</th>
                            <th>Motif & Type</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($sanctions) > 0): ?>
                            <?php foreach($sanctions as $row): 
                                $is_paid = ($row['statut_paiement'] == 'paye');
                                $style_class = 'motif-indiscipline';
                                $icon = 'fa-triangle-exclamation';
                                if($row['motif'] == 'retard') { $style_class = 'motif-retard'; $icon = 'fa-clock'; }
                                if($row['motif'] == 'absence') { $style_class = 'motif-absence'; $icon = 'fa-user-slash'; }
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($row['nom_complet']); ?></td>
                                <td>
                                    <div class="<?php echo $style_class; ?> motif-badge">
                                        <i class="fa-solid <?php echo $icon; ?>"></i>
                                        <?php echo ucfirst($row['motif']); ?>
                                    </div>
                                </td>
                                <td class="fw-bold font-monospace"><?php echo number_format($row['montant'], 0, ',', ' '); ?> FCFA</td>
                                <td class="text-muted small"><?php echo date('d/m/Y', strtotime($row['date_sanction'])); ?></td>
                                <td><span class="status-badge <?php echo $is_paid ? 'status-paye' : 'status-du'; ?>"><?php echo $is_paid ? 'RÉGLÉE' : 'NON PAYÉE'; ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_payment">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $row['statut_paiement']; ?>">
                                            <?php if(!$is_paid): ?>
                                                <button type="submit" class="action-btn btn-pay" title="Marquer comme payé"><i class="fa-solid fa-check text-success fs-5"></i></button>
                                            <?php else: ?>
                                                <button type="submit" class="action-btn btn-undo" title="Annuler paiement"><i class="fa-solid fa-rotate-left text-warning fs-5"></i></button>
                                            <?php endif; ?>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette sanction ?');">
                                            <input type="hidden" name="action" value="delete_sanction">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="action-btn btn-delete" title="Supprimer"><i class="fa-regular fa-trash-can text-danger fs-5"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Aucune sanction enregistrée pour cette séance.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade modal-premium" id="addSanctionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header px-4 pt-4">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-gavel me-2 text-warning"></i>Nouvelle Sanction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_sanction">
                        
                        <?php if($current_seance_id == 0): ?>
                            <div class="alert alert-warning small">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> 
                                Aucune séance active trouvée. Veuillez d'abord créer une séance avant d'ajouter des sanctions.
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Membre fautif</label>
                            <select name="membre_id" class="form-select form-select-lg bg-light border-0" required <?php if($current_seance_id == 0) echo 'disabled'; ?>>
                                <option value="" selected disabled>Choisir un membre...</option>
                                <?php foreach($membres as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nom_complet']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Motif</label>
                                <select name="motif" id="selectMotif" class="form-select bg-light border-0" required onchange="suggestAmount()" <?php if($current_seance_id == 0) echo 'disabled'; ?>>
                                    <option value="retard">Retard</option>
                                    <option value="absence">Absence</option>
                                    <option value="indiscipline">Indiscipline</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Montant (FCFA)</label>
                                <input type="number" name="montant" id="inputMontant" class="form-control bg-light border-0 fw-bold" placeholder="0" required <?php if($current_seance_id == 0) echo 'disabled'; ?>>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold small text-muted">Date de l'infraction</label>
                            <input type="date" name="date_sanction" class="form-control bg-light border-0" value="<?php echo date('Y-m-d'); ?>" required <?php if($current_seance_id == 0) echo 'disabled'; ?>>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-dark rounded-pill px-4 fw-bold" <?php if($current_seance_id == 0) echo 'disabled'; ?>>Appliquer la sanction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function suggestAmount() {
            const motif = document.getElementById('selectMotif').value;
            const input = document.getElementById('inputMontant');
            let amount = 0;
            if(motif === 'retard') amount = 500;
            if(motif === 'absence') amount = 1000;
            if(motif === 'indiscipline') amount = 2000;
            input.value = amount;
        }
        window.addEventListener('DOMContentLoaded', suggestAmount);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>