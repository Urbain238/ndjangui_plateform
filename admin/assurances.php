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

$cercle_id = 1; // ID fixe pour l'exemple
$message = "";
$message_type = "";

// --- TRAITEMENT : MODIFICATION DU MONTANT ATTENDU ---
if (isset($_POST['action']) && $_POST['action'] == 'update_montant') {
    $id = $_POST['assurance_id'];
    $new_amount = $_POST['nouveau_montant'];
    
    if ($new_amount >= 0 && !empty($id)) {
        $stmt = $pdo->prepare("UPDATE assurances SET montant_attendu = ? WHERE id = ?");
        if ($stmt->execute([$new_amount, $id])) {
            $message = "Le montant attendu a été mis à jour avec succès.";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la mise à jour.";
            $message_type = "danger";
        }
    }
}

// --- TRAITEMENT : ENCAISSEMENT (MARQUER PAYÉ) ---
if (isset($_POST['action']) && $_POST['action'] == 'payer') {
    $id = $_POST['assurance_id'];
    $montant_a_payer = $_POST['montant_valide'];
    
    $stmt = $pdo->prepare("UPDATE assurances SET statut = 'payé', montant_paye = ?, date_paiement = NOW() WHERE id = ?");
    if ($stmt->execute([$montant_a_payer, $id])) {
        $message = "Assurance encaissée avec succès.";
        $message_type = "success";
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
// Jointure pour avoir le nom du membre
$sql = "SELECT a.*, m.nom_complet, photo_profil_url 
        FROM assurances a 
        JOIN membres m ON a.membre_id = m.id 
        WHERE a.cercle_id = ? 
        ORDER BY FIELD(a.statut, 'en attente', 'partiel', 'en retard', 'payé'), a.date_limite ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$cercle_id]);
$assurances = $stmt->fetchAll();

// --- CALCUL DES STATISTIQUES (KPIs) ---
$total_attendu = 0;
$total_encaisse = 0;
$count_retard = 0;

foreach ($assurances as $a) {
    $total_attendu += $a['montant_attendu'];
    if ($a['statut'] == 'payé') {
        $total_encaisse += $a['montant_paye'];
    }
    if ($a['statut'] == 'en retard') {
        $count_retard++;
    }
}
$pourcentage = ($total_attendu > 0) ? round(($total_encaisse / $total_attendu) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Assurances | Ndjangui Premium</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-dark: #022c22;
            --primary: #065f46;
            --primary-light: #34d399;
            --accent: #f59e0b;
            --bg-soft: #f8fafc;
            --text-dark: #1e293b;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }
        body { background-color: var(--bg-soft); font-family: 'Poppins', sans-serif; color: var(--text-dark); }
        
        /* HEADER */
        .premium-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            padding: 2.5rem 0 4rem;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: -3rem;
        }
        .premium-header::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%);
            transform: rotate(30deg); pointer-events: none;
        }

        /* KPI CARDS */
        .kpi-card {
            background: white; border-radius: 20px; padding: 1.5rem;
            box-shadow: var(--card-shadow); border: 1px solid rgba(0,0,0,0.03);
            height: 100%; transition: transform 0.3s;
        }
        .kpi-card:hover { transform: translateY(-5px); }
        .icon-box {
            width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; margin-bottom: 1rem;
        }

        /* TABLEAU */
        .table-card {
            background: white; border-radius: 24px; box-shadow: var(--card-shadow);
            overflow: hidden; border: none; margin-top: 2rem;
        }
        .table-premium thead th {
            background: #f1f5f9; text-transform: uppercase; font-size: 0.75rem;
            letter-spacing: 1px; color: #64748b; font-weight: 700; padding: 1.25rem; border: none;
        }
        .table-premium tbody td {
            padding: 1.25rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }
        .table-premium tbody tr:last-child td { border-bottom: none; }
        .table-premium tbody tr:hover { background-color: #f8fafc; }

        /* BOUTONS & BADGES */
        .btn-edit-amount {
            background: #fff7ed; color: var(--accent); border: 1px solid #ffedd5;
            width: 32px; height: 32px; border-radius: 8px; display: inline-flex;
            align-items: center; justify-content: center; transition: all 0.2s;
            margin-left: 10px; cursor: pointer;
        }
        .btn-edit-amount:hover { background: var(--accent); color: white; border-color: var(--accent); }

        .badge-status { padding: 0.5rem 1rem; border-radius: 30px; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-payé { background: #dcfce7; color: #166534; }
        .status-attente { background: #fff7ed; color: #9a3412; }
        .status-retard { background: #fee2e2; color: #991b1b; }
        .status-partiel { background: #e0f2fe; color: #075985; }

        .btn-encaisser {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; border: none; border-radius: 30px; padding: 0.5rem 1.2rem;
            font-size: 0.85rem; font-weight: 500; box-shadow: 0 4px 6px -1px rgba(6, 95, 70, 0.2);
            transition: all 0.2s;
        }
        .btn-encaisser:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(6, 95, 70, 0.3); color: white; }

        /* MODALE */
        .modal-header-premium { background: var(--primary); color: white; border: none; }
        .modal-content { border-radius: 20px; overflow: hidden; border: none; }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
    </style>
</head>
<body>

    <div class="premium-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="javascript:history.back()" class="text-white text-decoration-none small opacity-75 hover-opacity-100">
                    <i class="fa-solid fa-arrow-left me-2"></i>Retour au tableau de bord
                </a>
                <span class="badge bg-white bg-opacity-10 backdrop-blur text-white fw-light px-3 py-2 rounded-pill border border-white border-opacity-25">
                    <i class="fa-solid fa-calendar-day me-2"></i>Session Actuelle
                </span>
            </div>
            <div class="row align-items-end">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-1">Assurances & Solidarité</h1>
                    <p class="lead opacity-75 mb-0 fs-6">Gérez les fonds de secours et cotisations exceptionnelles.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> shadow rounded-4 border-0 d-flex align-items-center mb-4" style="margin-top: -10px; z-index: 5;">
                <i class="fa-solid <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> fs-4 me-3"></i>
                <div><?php echo $message; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4" style="position: relative; z-index: 2;">
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold small">Total Attendu</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($total_attendu, 0, ',', ' '); ?> <span class="fs-6 text-muted">FCFA</span></h3>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div class="icon-box bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-vault"></i>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold small">Total Encaissé</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo number_format($total_encaisse, 0, ',', ' '); ?> <span class="fs-6 text-muted">FCFA</span></h3>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning mb-0">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <?php if($count_retard > 0): ?>
                            <span class="badge bg-danger rounded-pill">
                                <i class="fa-solid fa-clock me-1"></i><?php echo $count_retard; ?> Retard(s)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <h6 class="text-uppercase text-muted fw-bold small">Taux de Recouvrement</h6>
                        <div class="d-flex align-items-end">
                            <h3 class="fw-bold mb-0"><?php echo $pourcentage; ?>%</h3>
                            <div class="progress flex-grow-1 ms-3" style="height: 6px;">
                                <div class="progress-bar bg-gradient-success" role="progressbar" style="width: <?php echo $pourcentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-premium mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Membre</th>
                            <th>Montant Attendu</th>
                            <th>Statut</th>
                            <th>Date Limite</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($assurances)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">Aucune assurance trouvée pour ce cercle.</td></tr>
                        <?php else: ?>
                            <?php foreach($assurances as $row): 
                                $is_paid = ($row['statut'] === 'payé');
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-primary fw-bold me-3" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                            <?php echo strtoupper(substr($row['nom_complet'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['nom_complet']); ?></div>
                                            <div class="small text-muted">ID: #<?php echo $row['membre_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold font-monospace fs-6" id="montant_display_<?php echo $row['id']; ?>">
                                            <?php echo number_format($row['montant_attendu'], 0, ',', ' '); ?> FCFA
                                        </span>
                                        
                                        <?php if(!$is_paid): ?>
                                        <button class="btn-edit-amount" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal" 
                                                data-id="<?php echo $row['id']; ?>" 
                                                data-nom="<?php echo htmlspecialchars($row['nom_complet']); ?>"
                                                data-amount="<?php echo $row['montant_attendu']; ?>"
                                                title="Modifier le montant attendu">
                                            <i class="fa-solid fa-pencil-alt small"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = 'status-attente'; // Default
                                        if($row['statut'] == 'payé') $statusClass = 'status-payé';
                                        if($row['statut'] == 'en retard') $statusClass = 'status-retard';
                                        if($row['statut'] == 'partiel') $statusClass = 'status-partiel';
                                    ?>
                                    <span class="badge-status <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($row['statut']); ?>
                                    </span>
                                    <?php if($is_paid): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                            <i class="fa-solid fa-check-double me-1"></i>
                                            <?php echo date('d/m/Y', strtotime($row['date_paiement'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-muted small fw-medium">
                                        <i class="fa-regular fa-calendar me-2"></i>
                                        <?php echo date('d M Y', strtotime($row['date_limite'])); ?>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if(!$is_paid): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer le paiement de <?php echo $row['montant_attendu']; ?> FCFA ?');">
                                            <input type="hidden" name="action" value="payer">
                                            <input type="hidden" name="assurance_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="montant_valide" value="<?php echo $row['montant_attendu']; ?>">
                                            <button type="submit" class="btn-encaisser">
                                                <i class="fa-solid fa-hand-holding-dollar me-2"></i>Encaisser
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light text-muted border-0" disabled>
                                            <i class="fa-solid fa-lock me-2"></i>Clôturé
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header modal-header-premium p-4">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-sliders me-2"></i>Ajuster l'Assurance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_montant">
                        <input type="hidden" name="assurance_id" id="modal_id">
                        
                        <div class="text-center mb-4">
                            <div class="bg-light rounded-circle d-inline-flex p-3 mb-2 text-primary">
                                <i class="fa-solid fa-user-shield fa-2x"></i>
                            </div>
                            <h5 class="fw-bold text-dark" id="modal_nom">Membre</h5>
                            <p class="text-muted small">Modification du montant attendu pour l'assurance.</p>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label fw-bold text-uppercase small text-muted">Nouveau Montant</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-money-bill text-muted"></i></span>
                                <input type="number" name="nouveau_montant" id="modal_amount" class="form-control bg-light border-start-0 fw-bold" required min="0">
                                <span class="input-group-text bg-light border-start-0 text-muted">FCFA</span>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning border-0 d-flex align-items-center small py-2">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Cette action modifiera la dette du membre.
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script pour passer les données à la modale
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            // Bouton qui a déclenché la modale
            const button = event.relatedTarget;
            
            // Récupération des infos data-*
            const id = button.getAttribute('data-id');
            const nom = button.getAttribute('data-nom');
            const amount = button.getAttribute('data-amount');
            
            // Injection dans les champs
            document.getElementById('modal_id').value = id;
            document.getElementById('modal_nom').textContent = nom;
            document.getElementById('modal_amount').value = amount;
        });
    </script>
</body>
</html>