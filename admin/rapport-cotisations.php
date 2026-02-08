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

$current_cercle_id = 1; 
$current_seance_id = 1; 
$message = "";
$message_type = "";

// --- TRAITEMENTS POST ---
if (isset($_POST['action']) && $_POST['action'] == 'edit_assurance_amount') {
    $id_assurance = $_POST['assurance_id'];
    $nouveau_montant = $_POST['nouveau_montant'];
    if (!empty($id_assurance) && is_numeric($nouveau_montant)) {
        $sql = "UPDATE assurances SET montant_attendu = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if($stmt->execute([$nouveau_montant, $id_assurance])) {
            $message = "Montant de l'assurance mis à jour.";
            $message_type = "success";
        } else { $message = "Erreur maj."; $message_type = "danger"; }
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'marquer_paye') {
    $table = $_POST['table']; $id = $_POST['id']; $montant_recu = $_POST['montant_valide']; 
    if(in_array($table, ['cotisations', 'assurances', 'epargnes'])) {
        $sql = "UPDATE $table SET statut = 'paye', montant_paye = ?, date_paiement = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$montant_recu, $id]);
        $message = "Paiement enregistré."; $message_type = "success";
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'verser_membre') {
    $membre_id = $_POST['membre_id'];
    $stmt = $pdo->prepare("SELECT SUM(montant_paye) FROM cotisations WHERE seance_id = ? AND statut='paye'");
    $stmt->execute([$current_seance_id]);
    $cagnotte = $stmt->fetchColumn() ?: 0;
    $stmt = $pdo->prepare("SELECT SUM(montant_paye) FROM epargnes WHERE membre_id = ? AND statut='paye'");
    $stmt->execute([$membre_id]);
    $epargne = $stmt->fetchColumn() ?: 0;
    $total_a_verser = $cagnotte + $epargne;

    if ($total_a_verser > 0) {
        $stmt_proj = $pdo->prepare("SELECT id FROM projets WHERE membre_id = ? AND statut != 'cloture' ORDER BY date_creation DESC LIMIT 1");
        $stmt_proj->execute([$membre_id]);
        $projet = $stmt_proj->fetch();
        if ($projet) {
            $pdo->prepare("UPDATE projets SET montant_verse = montant_verse + ? WHERE id = ?")->execute([$total_a_verser, $projet['id']]);
            $message = "Versement de " . number_format($total_a_verser) . " FCFA effectué."; $message_type = "success";
        } else { $message = "Aucun projet actif pour ce membre."; $message_type = "danger"; }
    } else { $message = "Fonds insuffisants."; $message_type = "warning"; }
}

// --- RÉCUPÉRATION DONNÉES ---
$membres = $pdo->query("SELECT id, nom_complet FROM membres")->fetchAll();
$membres_map = array_column($membres, 'nom_complet', 'id');
$cotisations = $pdo->prepare("SELECT * FROM cotisations WHERE cercle_id = ? AND seance_id = ?");
$cotisations->execute([$current_cercle_id, $current_seance_id]); $cotisations = $cotisations->fetchAll();
$assurances = $pdo->prepare("SELECT * FROM assurances WHERE cercle_id = ?");
$assurances->execute([$current_cercle_id]); $assurances = $assurances->fetchAll();
$epargnes = $pdo->prepare("SELECT * FROM epargnes WHERE cercle_id = ?");
$epargnes->execute([$current_cercle_id]); $epargnes = $epargnes->fetchAll();
$cagnotte_display = 0; foreach($cotisations as $c) { if($c['statut'] == 'paye') $cagnotte_display += $c['montant_paye']; }
$epargne_data = []; foreach($membres as $m) {
    $stmt = $pdo->prepare("SELECT SUM(montant_paye) FROM epargnes WHERE membre_id = ? AND statut='paye'");
    $stmt->execute([$m['id']]); $epargne_data[$m['id']] = $stmt->fetchColumn() ?: 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilotage Financier | Ndjangui Premium</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-dark: #022c22;
            --primary: #065f46;     /* Vert Émeraude */
            --primary-light: #34d399;
            --accent: #f59e0b;      /* Or mat */
            --bg-soft: #f8fafc;
            --text-dark: #1e293b;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }
        body { background-color: var(--bg-soft); font-family: 'Poppins', sans-serif; color: var(--text-dark); }
        
        /* --- HEADER MODERNE --- */
        .premium-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            padding: 2rem 0 5rem; /* Réduit un peu le padding top sur mobile */
            color: white;
            position: relative;
            overflow: hidden;
        }
        .premium-header::before { 
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%);
            transform: rotate(30deg);
            pointer-events: none;
        }
        .btn-glass-back {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 30px;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex; align-items: center;
        }
        .btn-glass-back:hover { background: rgba(255, 255, 255, 0.2); color: white; transform: translateX(-3px); }
        
        /* --- CARTE PRINCIPALE (HERO CAGNOTTE) --- */
        .hero-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            padding: 2rem; /* Réduit un peu sur mobile */
            margin-top: -3.5rem; 
            position: relative;
            border: 1px solid rgba(255,255,255,0.8);
        }
        
        /* BORDURE RESPONSIVE : Visible uniquement sur grand écran */
        .border-lg-end { border-right: none; }
        @media (min-width: 992px) {
            .border-lg-end { border-right: 1px solid #f1f5f9; }
        }

        .cagnotte-amount {
            background: linear-gradient(45deg, var(--primary-dark), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            letter-spacing: -1px;
            font-size: calc(2.5rem + 1.5vw); /* Taille de police dynamique */
        }
        .versement-section {
            background: #f0fdf4; 
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #dcfce7;
        }
        .btn-gradient-success {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none; color: white; font-weight: 600; padding: 0.8rem;
            border-radius: 12px; transition: transform 0.2s;
        }
        .btn-gradient-success:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(6, 95, 70, 0.3); }

        /* --- ONGLETS & TABLEAUX --- */
        .nav-pills-custom {
            background: white;
            padding: 0.5rem;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            display: inline-flex;
            flex-wrap: wrap; /* Permet aux onglets de passer à la ligne sur mobile */
            justify-content: center;
            gap: 5px;
        }
        .nav-pills-custom .nav-link {
            color: var(--text-dark);
            font-weight: 600;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s;
            white-space: nowrap; /* Empêche le texte de casser */
        }
        .nav-pills-custom .nav-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.2);
        }
        
        .table-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden; border: none;
        }
        .table-premium thead th {
            background: #f1f5f9; text-transform: uppercase; font-size: 0.75rem;
            letter-spacing: 1px; color: #64748b; font-weight: 700; padding: 1.25rem; border: none;
        }
        .table-premium tbody td {
            padding: 1.25rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }
        .table-premium tbody tr:hover { background-color: #f8fafc; }

        /* --- BADGES ET BOUTONS --- */
        .badge-premium { padding: 0.5rem 1rem; border-radius: 30px; font-weight: 600; font-size: 0.75rem; letter-spacing: 0.5px; }
        .bg-paye-soft { background: #dcfce7; color: #166534; }
        .bg-attente-soft { background: #fef3c7; color: #92400e; }

        .btn-icon-edit {
            width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%; background: #fff7ed; color: var(--accent);
            transition: all 0.2s; border: 1px solid #ffedd5;
        }
        .btn-icon-edit:hover { background: var(--accent); color: white; border-color: var(--accent); }
        
        .btn-outline-encaisser {
            border: 2px solid var(--primary); color: var(--primary); font-weight: 600;
            border-radius: 30px; padding: 0.4rem 1.2rem; transition: all 0.2s;
        }
        .btn-outline-encaisser:hover { background: var(--primary); color: white; }
        .btn-outline-collecter {
            border: 2px solid #0ea5e9; color: #0ea5e9; font-weight: 600;
            border-radius: 30px; padding: 0.4rem 1.2rem; transition: all 0.2s;
        }
        .btn-outline-collecter:hover { background: #0ea5e9; color: white; }

        /* --- MODALE --- */
        .modal-header-premium { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-bottom: none; }
        .modal-content-premium { border-radius: 20px; overflow: hidden; border: none; }
    </style>
</head>
<body>

    <div class="premium-header">
        <div class="container">
            <div class="mb-4">
                <a href="javascript:history.back()" class="btn-glass-back">
                    <i class="fa-solid fa-arrow-left-long me-2"></i>Retour
                </a>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                <div>
                    <span class="badge bg-warning text-dark mb-2 px-3 py-2 rounded-pill fw-bold ls-1"><i class="fa-solid fa-circle-play me-2"></i>SÉANCE EN COURS</span>
                    <h1 class="fw-bold display-5 display-md-4 mb-0">Séance N° <?php echo $current_seance_id; ?></h1>
                    <p class="lead mb-0 opacity-75 fs-6 fs-md-5">Pilotage des flux financiers et attribution.</p>
                </div>
                <div class="text-end d-none d-md-block opacity-50">
                    <i class="fa-solid fa-coins fa-4x"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> shadow-lg rounded-4 border-0 d-flex align-items-center mb-4" role="alert" style="margin-top: -20px; z-index: 10; position: relative;">
                <i class="fa-solid <?php echo $message_type == 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?> fs-4 me-3"></i>
                <div class="text-break"><strong>Notification :</strong> <?php echo $message; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="hero-card mb-5">
            <div class="row align-items-center g-4">
                <div class="col-12 col-lg-5 border-lg-end">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3 text-success">
                            <i class="fa-solid fa-vault fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-uppercase text-muted fw-bold small mb-0 ls-1">Cagnotte Disponible</h6>
                            <small class="text-muted">Basé sur les cotisations payées</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mt-3">
                        <h1 class="fw-bolder cagnotte-amount mb-0"><?php echo number_format($cagnotte_display, 0, ',', ' '); ?></h1>
                        <span class="fs-4 fw-bold text-muted ms-2">FCFA</span>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="versement-section">
                        <h5 class="fw-bold mb-3" style="color: var(--primary-dark);">
                            <i class="fa-solid fa-hand-holding-dollar me-2 text-warning"></i>Attribuer la Tontine
                        </h5>
                        <form method="POST" class="row g-3 align-items-end" onsubmit="return confirm('Confirmez-vous le versement ? Les fonds seront ajoutés au projet du membre.');">
                            <input type="hidden" name="action" value="verser_membre">
                            
                            <div class="col-12 col-md-5">
                                <label class="form-label small fw-bold text-muted text-uppercase">Bénéficiaire du tour</label>
                                <select name="membre_id" id="selectMembre" class="form-select form-select-lg border-0 shadow-sm" required onchange="updateTotal()" style="background-color: white;">
                                    <option value="" selected disabled>Choisir un membre...</option>
                                    <?php foreach($membres as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" data-epargne="<?php echo $epargne_data[$m['id']] ?? 0; ?>">
                                            <?php echo htmlspecialchars($m['nom_complet']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Total à verser</label>
                                <input type="text" id="displayTotal" class="form-control form-control-lg border-0 shadow-sm fw-bold text-success" readonly value="0 FCFA" style="background-color: white;">
                            </div>
                            <div class="col-12 col-md-3">
                                <button type="submit" class="btn btn-gradient-success w-100 h-100 d-flex align-items-center justify-content-center py-3 py-md-2">
                                    <span>Valider <i class="fa-solid fa-arrow-right ms-2"></i></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center mb-4">
            <div class="nav-pills-custom" id="pills-tab" role="tablist">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-cotis">
                    <i class="fa-solid fa-sack-dollar me-2"></i>Cotisations
                </button>
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-assur">
                    <i class="fa-solid fa-heart-pulse me-2"></i>Assurances
                </button>
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-epargne">
                    <i class="fa-solid fa-piggy-bank me-2"></i>Épargnes
                </button>
            </div>
        </div>

        <div class="tab-content table-card">
            
            <div class="tab-pane fade show active" id="tab-cotis">
                <div class="table-responsive">
                    <table class="table table-premium mb-0 text-nowrap"> <thead><tr><th class="ps-4">Membre</th><th>Montant Attendu</th><th>Statut</th><th class="text-end pe-4">Action</th></tr></thead>
                        <tbody>
                            <?php foreach($cotisations as $row): $is_paid = ($row['statut'] == 'paye'); ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle p-2 me-3 text-primary"><i class="fa-solid fa-user"></i></div>
                                        <span class="fw-bold text-dark"><?php echo $membres_map[$row['membre_id']] ?? '-'; ?></span>
                                    </div>
                                </td>
                                <td class="font-monospace fw-bold"><?php echo number_format($row['montant_attendu'], 0, ',', ' '); ?> FCFA</td>
                                <td><span class="badge-premium <?php echo $is_paid ? 'bg-paye-soft' : 'bg-attente-soft'; ?>"><?php echo $row['statut']; ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if(!$is_paid): ?>
                                    <form method="POST"><input type="hidden" name="action" value="marquer_paye"><input type="hidden" name="table" value="cotisations"><input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="montant_valide" value="<?php echo $row['montant_attendu']; ?>"><button class="btn btn-sm btn-outline-encaisser"><i class="fa-solid fa-check me-2"></i>Encaisser</button></form>
                                    <?php else: ?><i class="fa-solid fa-circle-check text-success fa-xl opacity-50"></i><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-assur">
                <div class="table-responsive">
                    <table class="table table-premium mb-0 text-nowrap">
                        <thead><tr><th class="ps-4">Membre</th><th>Montant (Modifiable)</th><th>Statut</th><th class="text-end pe-4">Action</th></tr></thead>
                        <tbody>
                            <?php foreach($assurances as $row): $is_paid = ($row['statut'] == 'paye'); ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle p-2 me-3 text-danger bg-opacity-10"><i class="fa-solid fa-shield-heart"></i></div>
                                        <span class="fw-bold text-dark"><?php echo $membres_map[$row['membre_id']] ?? '-'; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="font-monospace fw-bold me-3"><?php echo number_format($row['montant_attendu'], 0, ',', ' '); ?> FCFA</span>
                                        <?php if(!$is_paid): ?>
                                            <button type="button" class="btn-icon-edit shadow-sm" data-bs-toggle="modal" data-bs-target="#editAmountModal" data-id="<?php echo $row['id']; ?>" data-montant="<?php echo $row['montant_attendu']; ?>" title="Modifier le montant">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="badge-premium <?php echo $is_paid ? 'bg-paye-soft' : 'bg-attente-soft'; ?>"><?php echo $row['statut']; ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if(!$is_paid): ?>
                                    <form method="POST"><input type="hidden" name="action" value="marquer_paye"><input type="hidden" name="table" value="assurances"><input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="montant_valide" value="<?php echo $row['montant_attendu']; ?>"><button class="btn btn-sm btn-outline-encaisser"><i class="fa-solid fa-check me-2"></i>Encaisser</button></form>
                                    <?php else: ?><i class="fa-solid fa-circle-check text-success fa-xl opacity-50"></i><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-epargne">
                <div class="table-responsive">
                    <table class="table table-premium mb-0 text-nowrap">
                        <thead><tr><th class="ps-4">Membre</th><th>Montant Prévu</th><th>Statut</th><th class="text-end pe-4">Action</th></tr></thead>
                        <tbody>
                            <?php foreach($epargnes as $row): $is_paid = ($row['statut'] == 'paye'); ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle p-2 me-3 text-info bg-opacity-10"><i class="fa-solid fa-piggy-bank"></i></div>
                                        <span class="fw-bold text-dark"><?php echo $membres_map[$row['membre_id']] ?? '-'; ?></span>
                                    </div>
                                </td>
                                <td class="font-monospace fw-bold"><?php echo number_format($row['montant_attendu'], 0, ',', ' '); ?> FCFA</td>
                                <td><span class="badge-premium <?php echo $is_paid ? 'bg-paye-soft' : 'bg-attente-soft'; ?>"><?php echo $is_paid ? 'DÉPOSÉ' : 'EN ATTENTE'; ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if(!$is_paid): ?>
                                    <form method="POST"><input type="hidden" name="action" value="marquer_paye"><input type="hidden" name="table" value="epargnes"><input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="montant_valide" value="<?php echo $row['montant_attendu']; ?>"><button class="btn btn-sm btn-outline-collecter"><i class="fa-solid fa-download me-2"></i>Collecter</button></form>
                                    <?php else: ?><i class="fa-solid fa-circle-check text-success fa-xl opacity-50"></i><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAmountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-premium shadow-lg">
                <div class="modal-header modal-header-premium p-4">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders me-2"></i>Ajuster le Montant</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_assurance_amount">
                        <input type="hidden" name="assurance_id" id="modal_assurance_id">
                        
                        <label for="modal_nouveau_montant" class="form-label fw-bold text-muted small text-uppercase">Nouveau Montant Attendu</label>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text bg-light border-0"><i class="fa-solid fa-money-bill-wave text-muted"></i></span>
                            <input type="number" class="form-control border-0 bg-light fw-bold" name="nouveau_montant" id="modal_nouveau_montant" required min="0" placeholder="Ex: 5000">
                            <span class="input-group-text bg-light border-0 fw-bold text-muted">FCFA</span>
                        </div>
                        <div class="form-text text-muted"><i class="fa-solid fa-circle-info me-1"></i> Ce changement affectera uniquement la séance en cours avant paiement.</div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-gradient-success rounded-pill px-4 fw-bold">Enregistrer la modification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const cagnotteSession = <?php echo $cagnotte_display; ?>;
        function updateTotal() {
            const select = document.getElementById('selectMembre'); const display = document.getElementById('displayTotal');
            const epargneMembre = parseFloat(select.options[select.selectedIndex].getAttribute('data-epargne')) || 0;
            display.value = new Intl.NumberFormat('fr-FR').format(cagnotteSession + epargneMembre) + " FCFA";
        }
        const editModal = document.getElementById('editAmountModal');
        editModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            document.getElementById('modal_assurance_id').value = button.getAttribute('data-id');
            document.getElementById('modal_nouveau_montant').value = button.getAttribute('data-montant');
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>