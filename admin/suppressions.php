<?php
session_start();
date_default_timezone_set('Africa/Douala');

// =================================================================================
// 1. CONFIGURATION & CONNEXION BDD
// =================================================================================

// Inclusion du fichier de configuration
require_once '../config/database.php';

try {
    // Connexion via la classe Database
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die("<div style='color:white; background:red; text-align:center; padding:20px; font-family:sans-serif;'>
        <strong>Erreur Critique :</strong> Impossible de se connecter à la base de données.<br>
        <em>Vérifiez que WAMP/XAMPP est lancé et que la base 'ndjangui_db' existe.</em><br>
        <small>" . $e->getMessage() . "</small>
        </div>");
}

// Simulation Admin (A remplacer par votre check de session réel)
if (!isset($_SESSION['user_id'])) {
    // header("Location: login.php"); exit;
}
$current_admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; 

$msg = "";
$msgType = "";

// =================================================================================
// FONCTION : VÉRIFICATION HIERARCHIQUE
// =================================================================================
function is_ancestor($pdo, $admin_id, $target_id) {
    $stmt = $pdo->prepare("SELECT parrain_id FROM membres WHERE id = ?");
    $stmt->execute([$target_id]);
    $parent = $stmt->fetchColumn();

    $safety = 0;
    while ($parent && $safety < 20) { 
        if ($parent == $admin_id) {
            return true; 
        }
        $stmt->execute([$parent]);
        $parent = $stmt->fetchColumn();
        $safety++;
    }
    return false;
}

// =================================================================================
// LOGIQUE DE TRAITEMENT (BACKEND)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $target_id = intval($_POST['target_id']);
    
    try {
        // --- CAS 1 : GESTION MEMBRE ---
        if ($_POST['action'] === 'toggle_membre') {
            
            $stmt = $pdo->prepare("SELECT nom_complet, role_id, parrain_id, statut FROM membres WHERE id = ?");
            $stmt->execute([$target_id]);
            $cible = $stmt->fetch();

            if (!$cible) throw new Exception("Ce membre n'existe pas.");

            if ($target_id == $current_admin_id) {
                throw new Exception("Opération impossible : Vous ne pouvez pas modifier votre propre statut.");
            }

            $is_boss = ($cible['parrain_id'] == $current_admin_id) || is_ancestor($pdo, $current_admin_id, $target_id);

            if ($cible['role_id'] == 1 && !$is_boss) {
                throw new Exception("⛔ ACCÈS REFUSÉ : Vous ne pouvez pas toucher à un autre Administrateur hors de votre lignée.");
            }
            
            if (is_ancestor($pdo, $target_id, $current_admin_id)) {
                throw new Exception("⛔ TRAHISON : Vous ne pouvez pas suspendre votre supérieur hiérarchique.");
            }

            $pdo->beginTransaction();

            try {
                $new_statut = ($cible['statut'] === 'suspendu') ? 'actif' : 'suspendu';
                
                $sqlToggle = "UPDATE membres SET statut = ? WHERE id = ?";
                $stmt = $pdo->prepare($sqlToggle);
                $stmt->execute([$new_statut, $target_id]);

                $pdo->commit();
                
                if ($new_statut === 'suspendu') {
                    $msg = "Succès : Le compte de <b>" . htmlspecialchars($cible['nom_complet']) . "</b> a été <span class='text-danger'>SUSPENDU</span>.";
                    $msgType = "warning";
                } else {
                    $msg = "Succès : Le compte de <b>" . htmlspecialchars($cible['nom_complet']) . "</b> a été <span class='text-success'>RÉACTIVÉ</span>.";
                    $msgType = "success";
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e; 
            }
        }
        // --- CAS 2 : SUPPRESSION CERCLE ---
        elseif ($_POST['action'] === 'delete_cercle') {
            // Sécurité supplémentaire backend : on vérifie le nom avant de supprimer
            $check = $pdo->prepare("SELECT nom_cercle FROM cercles WHERE id = ?");
            $check->execute([$target_id]);
            $nomCercle = $check->fetchColumn();

            if ($nomCercle === 'Cercle Principal') {
                throw new Exception("Le Cercle Principal ne peut pas être archivé.");
            }

            $sql = "UPDATE cercles SET statut = 'archive' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$target_id]);
            $msg = "Le cercle a été clôturé et archivé avec succès.";
            $msgType = "success";
        }
        // --- CAS 3 : SUPPRESSION PROJET ---
        elseif ($_POST['action'] === 'delete_projet') {
            try {
                $sql = "DELETE FROM projets WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$target_id]);
                $msg = "Le projet a été supprimé définitivement.";
                $msgType = "success";
            } catch (PDOException $pdoEx) {
                if ($pdoEx->getCode() == '23000') {
                    throw new Exception("Impossible de supprimer ce projet car il contient des cotisations ou des données liées.");
                } else {
                    throw $pdoEx;
                }
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $msg = "Erreur : " . $errorMessage;
        $msgType = "danger";
    }
}

// Récupération des données
$membres = $pdo->query("SELECT id, nom_complet, email, role_id, statut, photo_profil_url FROM membres WHERE statut != 'archive' ORDER BY id DESC LIMIT 50")->fetchAll();

// --- MODIFICATION ICI : On exclut le 'Cercle Principal' de la liste ---
$cercles = $pdo->query("SELECT id, nom_cercle, type_tontine, statut, montant_unitaire FROM cercles WHERE statut != 'archive' AND nom_cercle != 'Cercle Principal' ORDER BY id DESC LIMIT 20")->fetchAll();

$projets = $pdo->query("SELECT p.id, p.titre, p.montant_demande, m.nom_complet as auteur FROM projets p LEFT JOIN membres m ON p.membre_id = m.id ORDER BY p.id DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | Gestion Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Palette Claire et Élégante */
            --bg-body: #f1f5f9;       /* Gris perle très léger */
            --card-bg: #ffffff;       /* Blanc pur */
            --primary: #4f46e5;       /* Indigo vibrant */
            --primary-dark: #4338ca;
            --text-main: #334155;     /* Gris foncé lisible */
            --text-muted: #64748b;    /* Gris moyen */
            --danger-red: #ef4444;
            --success-green: #10b981;
            --header-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
        }

        /* HEADER MODERNE */
        .page-header {
            background: var(--header-gradient);
            padding: 50px 0 60px; /* Un peu plus d'espace en bas pour le chevauchement */
            margin-bottom: -30px; /* Chevauchement avec le contenu */
            color: white;
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.2);
        }
        .page-header h1 { color: white; font-weight: 800; letter-spacing: -0.5px; }
        .page-header p { color: rgba(255,255,255,0.8) !important; font-size: 1.1rem; }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* TABS (NAVIGATION) */
        .nav-pills {
            background: white;
            padding: 8px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: inline-flex;
            margin-bottom: 25px !important;
        }
        .nav-pills .nav-link {
            color: var(--text-muted);
            border-radius: 40px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link:hover {
            color: var(--primary);
            background: #f8fafc;
        }
        .nav-pills .nav-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        /* CARTES & TABLEAUX (STYLE LIGHT) */
        .custom-card {
            background-color: var(--card-bg);
            border-radius: 20px;
            border: none;
            overflow: hidden;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); /* Ombre douce et large */
            margin-top: 10px;
        }

        .card-header-custom {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-custom th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-custom td {
            padding: 18px 30px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
            background: white;
            transition: background 0.2s;
        }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tr:hover td {
            background-color: #f8fafc;
        }

        /* ELEMENTS GRAPHIQUES */
        .avatar-sm {
            width: 45px; height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .text-dark-blue { color: #1e293b; }
        
        /* BOUTONS D'ACTION */
        .btn-action {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-suspend {
            background: #fef2f2;
            color: #ef4444;
        }
        .btn-suspend:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }
        .btn-restore {
            background: #ecfdf5;
            color: #10b981;
        }
        .btn-restore:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }

        /* BADGES */
        .badge-role {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            background: #e0e7ff;
            color: #4338ca;
            border: none;
        }
        .badge-statut {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .badge-actif { background: #dcfce7; color: #166534; }
        .badge-suspendu { background: #fee2e2; color: #991b1b; }

        /* RECHERCHE */
        .form-control-search {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: var(--text-main);
            border-radius: 10px;
            padding-left: 15px;
        }
        .form-control-search:focus {
            background-color: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        /* MODAL */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .modal-header { border-bottom: none; padding-bottom: 0; }
        .modal-footer { border-top: none; }
        .btn-close { opacity: 0.5; }
    </style>
</head>
<body>

    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold mb-1"><i class="fa-solid fa-user-shield me-3"></i>Gestion des Status</h1>
                    <p class="mb-0">Suspension, réactivation et archivage des comptes.</p>
                </div>
                <a href="index.php" class="btn btn-back rounded-pill px-4 fw-bold">
                    <i class="fa-solid fa-arrow-left me-2"></i>Tableau de bord
                </a>
            </div>
        </div>
    </div>

    <div class="container pb-5" style="margin-top: -20px;">
        
        <?php if(!empty($msg)): ?>
        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show border-0 shadow-sm mb-4 rounded-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid <?php echo ($msgType == 'success') ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?> fs-4 me-3"></i>
                <div>
                    <strong><?php echo ($msgType == 'success') ? 'Parfait !' : 'Attention'; ?></strong><br>
                    <?php echo $msg; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <ul class="nav nav-pills" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-membres-tab" data-bs-toggle="pill" data-bs-target="#pills-membres" type="button"><i class="fa-solid fa-users me-2"></i>Membres</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-cercles-tab" data-bs-toggle="pill" data-bs-target="#pills-cercles" type="button"><i class="fa-solid fa-circle-nodes me-2"></i>Cercles</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-projets-tab" data-bs-toggle="pill" data-bs-target="#pills-projets" type="button"><i class="fa-solid fa-lightbulb me-2"></i>Projets</button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent"> 
            
            <div class="tab-pane fade show active" id="pills-membres">
                <div class="custom-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0 fw-bold text-dark-blue">Liste des Membres</h5>
                        <input type="text" id="searchMembre" class="form-control form-control-sm w-auto form-control-search" placeholder="Rechercher un membre...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Identité</th>
                                    <th>Rôle</th>
                                    <th>Statut Actuel</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tableMembres">
                                <?php foreach($membres as $m): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo !empty($m['photo_profil_url']) ? htmlspecialchars($m['photo_profil_url']) : 'assets/img/default.png'; ?>" class="avatar-sm me-3" alt="Photo">
                                            <div>
                                                <div class="fw-bold text-dark-blue"><?php echo htmlspecialchars($m['nom_complet']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($m['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $roles = [1=>'Admin', 2=>'Président', 3=>'Secrétaire', 4=>'Censeur', 5=>'Membre'];
                                            echo '<span class="badge-role">'.($roles[$m['role_id']] ?? 'Inconnu').'</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if($m['statut']=='actif'): ?>
                                            <span class="badge badge-statut badge-actif">Actif</span>
                                        <?php elseif($m['statut']=='suspendu'): ?>
                                            <span class="badge badge-statut badge-suspendu">SUSPENDU</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white"><?php echo htmlspecialchars($m['statut']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if($m['statut'] === 'suspendu'): ?>
                                            <button class="btn-action btn-restore" 
                                                onclick="confirmAction('membre', <?php echo $m['id']; ?>, '<?php echo htmlspecialchars($m['nom_complet'], ENT_QUOTES); ?>', 'restore')"
                                                title="Réactiver le compte">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-suspend" 
                                                onclick="confirmAction('membre', <?php echo $m['id']; ?>, '<?php echo htmlspecialchars($m['nom_complet'], ENT_QUOTES); ?>', 'suspend')"
                                                title="Suspendre le compte">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-cercles">
                <div class="custom-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0 fw-bold text-dark-blue">Gestion des Cercles</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Nom du Cercle</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cercles as $c): ?>
                                <tr>
                                    <td class="fw-bold text-dark-blue"><?php echo htmlspecialchars($c['nom_cercle']); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($c['type_tontine']); ?></td>
                                    <td class="text-success fw-bold"><?php echo number_format($c['montant_unitaire'], 0, ',', ' '); ?> FCFA</td>
                                    <td class="text-end">
                                        <button class="btn-action btn-suspend" onclick="confirmAction('cercle', <?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['nom_cercle'], ENT_QUOTES); ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-projets">
                 <div class="custom-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0 fw-bold text-dark-blue">Projets Soumis</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Titre Projet</th>
                                    <th>Demandeur</th>
                                    <th>Montant</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($projets as $p): ?>
                                <tr>
                                    <td class="fw-bold text-dark-blue"><?php echo htmlspecialchars($p['titre']); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($p['auteur'] ?? 'Inconnu'); ?></td>
                                    <td class="fw-bold text-dark-blue"><?php echo number_format($p['montant_demande'], 0, ',', ' '); ?> FCFA</td>
                                    <td class="text-end">
                                        <button class="btn-action btn-suspend" onclick="confirmAction('projet', <?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['titre'], ENT_QUOTES); ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark-blue" id="modalTitle">CONFIRMATION</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <div class="mb-3">
                            <i id="modalIcon" class="fa-solid fa-circle-exclamation text-warning" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="fw-bold text-dark-blue mb-2" id="deleteTargetName">...</h3>
                        
                        <p id="modalDesc" class="text-muted mb-3">Voulez-vous vraiment effectuer cette action ?</p>
                        
                        <div class="alert small mx-3" id="deleteWarningText">
                            Cette action est sensible.
                        </div>
                        
                        <input type="hidden" name="action" id="deleteAction">
                        <input type="hidden" name="target_id" id="deleteId">
                    </div>
                    <div class="modal-footer justify-content-center pb-4 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold text-muted border" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="confirmBtn">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction Unifiée pour gérer toutes les actions
        function confirmAction(type, id, name, statusMode = null) {
            
            // Éléments du DOM
            const inputId = document.getElementById('deleteId');
            const inputAction = document.getElementById('deleteAction');
            const targetName = document.getElementById('deleteTargetName');
            const warningText = document.getElementById('deleteWarningText');
            const confirmBtn = document.getElementById('confirmBtn');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');

            // Reset UI
            inputId.value = id;
            targetName.textContent = name;
            warningText.style.display = 'block';
            warningText.className = "alert small mx-3"; 

            if (type === 'membre') {
                inputAction.value = 'toggle_membre';
                
                if (statusMode === 'suspend') {
                    // Mode SUSPENSION
                    modalTitle.innerText = "SUSPENSION DU COMPTE";
                    modalIcon.className = "fa-solid fa-ban text-danger mb-3";
                    modalIcon.style.fontSize = "4rem";
                    warningText.className += " alert-danger";
                    warningText.innerHTML = "<strong>Attention :</strong> Ce membre ne pourra plus se connecter.<br>Ses données historiques restent conservées.";
                    confirmBtn.className = "btn btn-danger rounded-pill px-4 fw-bold shadow-sm";
                    confirmBtn.innerText = "Suspendre";
                } else {
                    // Mode REACTIVATION
                    modalTitle.innerText = "RÉACTIVATION DU COMPTE";
                    modalIcon.className = "fa-solid fa-check-circle text-success mb-3";
                    modalIcon.style.fontSize = "4rem";
                    warningText.className += " alert-success";
                    warningText.innerHTML = "Ce membre pourra à nouveau se connecter et participer aux activités.";
                    confirmBtn.className = "btn btn-success rounded-pill px-4 fw-bold shadow-sm";
                    confirmBtn.innerText = "Réactiver";
                }

            } else if (type === 'cercle') {
                inputAction.value = 'delete_cercle';
                modalTitle.innerText = "ARCHIVAGE CERCLE";
                modalIcon.className = "fa-solid fa-box-archive text-warning mb-3";
                modalIcon.style.fontSize = "4rem";
                warningText.className += " alert-warning";
                warningText.innerHTML = "Le cercle sera fermé. Il ne sera plus visible dans la liste active.";
                confirmBtn.className = "btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm";
                confirmBtn.innerText = "Archiver";

            } else if (type === 'projet') {
                inputAction.value = 'delete_projet';
                modalTitle.innerText = "SUPPRESSION DÉFINITIVE";
                modalIcon.className = "fa-solid fa-trash-can text-danger mb-3";
                modalIcon.style.fontSize = "4rem";
                warningText.className += " alert-danger";
                warningText.innerHTML = "<strong>IRRÉVERSIBLE :</strong> Toutes les données de ce projet seront effacées.";
                confirmBtn.className = "btn btn-danger rounded-pill px-4 fw-bold shadow-sm";
                confirmBtn.innerText = "Supprimer";
            }
            
            var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            myModal.show();
        }

        // Script de recherche instantanée
        document.getElementById('searchMembre').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#tableMembres tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>