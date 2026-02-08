<?php
// =================================================================================
// 0. SESSION & SÉCURITÉ
// =================================================================================
session_start();

// Vérification : Si l'utilisateur n'est PAS connecté
if (!isset($_SESSION['user_id'])) {
    // On le redirige vers la page de connexion
    header("Location: login.php");
    exit(); // Le script s'arrête ICI. Tout ce qui est en dessous dans le 'if' ne sert à rien.
}

// Si le script arrive ici, c'est que l'utilisateur EST connecté.
// On récupère son rôle. Si le rôle n'est pas défini, on met 1 par défaut (ou 0 pour moins de droits).
$current_user_role = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 1;
// Vérification stricte (Admin (1) ou Président (2) uniquement)
// if ($current_user_role > 2) { die("Accès refusé."); }

// =================================================================================
// 1. CONNEXION BDD & TRAITEMENT
// =================================================================================

// Inclusion du fichier de configuration de la base de données
require_once '../config/database.php';

try {
    // Récupération de l'instance PDO via la classe statique
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$msg = "";
$msgType = "";

// --- TRAITEMENT : MISE À JOUR DES DROITS & STATUTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_rights') {
        $target_id = intval($_POST['target_id']);
        $new_role = intval($_POST['new_role']);
        $new_status = $_POST['new_status']; // actif / banni
        
        // Gestion sécurisée de la validation (si le champ existe dans le formulaire)
        $new_validation = isset($_POST['new_validation']) ? $_POST['new_validation'] : 'en_attente';

        try {
            // Mise à jour complète
            $sql = "UPDATE membres SET role_id = :role, statut = :statut, statut_validation = :validation WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':role' => $new_role,
                ':statut' => $new_status,
                ':validation' => $new_validation,
                ':id' => $target_id
            ]);
            $msg = "Le profil membre a été mis à jour avec succès.";
            $msgType = "success";
        } catch (Exception $e) {
            $msg = "Erreur technique lors de la mise à jour.";
            $msgType = "danger";
        }
    }
}

// --- RÉCUPÉRATION DES DONNÉES & STATISTIQUES ---

// 1. Récupération de la liste complète
// Note: On utilise COALESCE pour statut_validation au cas où la colonne serait NULL (ancienne BDD)
$sql_users = "SELECT id, nom_complet, email, telephone, role_id, statut, photo_profil_url, date_inscription, 
              COALESCE(statut_validation, 'en_attente') as statut_validation 
              FROM membres 
              ORDER BY date_inscription DESC"; // Plus récents en premier
$stmt_users = $pdo->query($sql_users);
$membres = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// 2. Calcul des KPIs (Indicateurs Clés)
$total_membres = count($membres);
$count_pending = 0;
$count_admins = 0;
$count_banned = 0;

foreach ($membres as $m) {
    if ($m['statut_validation'] === 'en_attente') $count_pending++;
    if ($m['role_id'] <= 2) $count_admins++; // Admin & Président
    if ($m['statut'] === 'banni') $count_banned++;
}

// Définition des Rôles (Configuration)
$roles_config = [
    1 => ['label' => 'Administrateur', 'badge' => 'warning', 'icon' => 'fa-crown'],
    2 => ['label' => 'Président', 'badge' => 'danger', 'icon' => 'fa-gavel'],
    3 => ['label' => 'Secrétaire', 'badge' => 'info', 'icon' => 'fa-pen-nib'],
    4 => ['label' => 'Censeur', 'badge' => 'primary', 'icon' => 'fa-scale-balanced'],
    5 => ['label' => 'Membre', 'badge' => 'secondary', 'icon' => 'fa-user']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | NDJANGUI Premium</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0f392b;       /* Vert Profond */
            --primary-light: #185c46;
            --accent: #d4af37;        /* Or Métallique */
            --bg-body: #f3f4f6;
            --text-main: #111827;
            --text-muted: #6b7280;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* --- HEADER DASHBOARD --- */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, #052e16 100%);
            padding: 3rem 0 6rem; /* Grand padding bas pour l'effet de superposition */
            color: white;
            position: relative;
        }
        
        .brand-title {
            font-family: 'Playfair Display', serif;
            letter-spacing: 0.5px;
        }

        /* --- KPI CARDS (Statistiques) --- */
        .kpi-wrapper {
            margin-top: -4rem; /* Remonte sur le header */
            margin-bottom: 2rem;
        }
        
        .kpi-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255,255,255,0.5);
            transition: transform 0.3s ease;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .kpi-card:hover { transform: translateY(-5px); }
        
        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* --- TABLEAU --- */
        .content-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: none;
        }

        .table-custom thead th {
            background-color: #f9fafb;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-custom tbody td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.95rem;
        }

        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tbody tr { transition: background-color 0.2s; }
        .table-custom tbody tr:hover { background-color: #f9fafb; }

        /* --- ÉLÉMENTS UI --- */
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .status-dot {
            height: 8px;
            width: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        /* Bouton Edit stylisé */
        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            background: #f3f4f6;
            transition: all 0.2s;
            border: none;
        }
        .btn-icon:hover {
            background: var(--primary);
            color: var(--accent);
        }

        /* --- MODAL --- */
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }
        .modal-header {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem;
        }
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        
        /* Custom Radio Buttons for Status */
        .selector-group {
            display: flex;
            background: #f3f4f6;
            padding: 4px;
            border-radius: 12px;
        }
        .selector-option {
            flex: 1;
            text-align: center;
        }
        .selector-option input { display: none; }
        .selector-option label {
            display: block;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            transition: all 0.3s;
        }
        /* Active State Logic handled via Checkbox Hack or JS class toggle */
        .selector-option input:checked + label {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            color: var(--primary);
            font-weight: 600;
        }
        
        /* Validation Status Colors */
        .selector-option input:checked + label.opt-valid { color: #059669; }
        .selector-option input:checked + label.opt-wait { color: #d97706; }
        .selector-option input:checked + label.opt-reject { color: #dc2626; }

    </style>
</head>
<body>

    <header class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="badge bg-warning text-dark mb-2 px-3 py-2 rounded-pill fw-bold">
                        <i class="fa-solid fa-star me-1"></i> ESPACE ADMINISTRATION
                    </div>
                    <h1 class="brand-title display-5 fw-bold mb-1">Gestion des Membres</h1>
                    <p class="text-white-50 fs-5 mb-0">Contrôlez les accès, les rôles et les validations.</p>
                </div>
                <a href="index.php" class="btn btn-outline-light rounded-pill px-4 py-2 fw-medium">
                    <i class="fa-solid fa-arrow-left me-2"></i>Retour au Site
                </a>
            </div>
        </div>
    </header>

    <div class="container pb-5">
        
        <div class="row g-4 kpi-wrapper">
            <div class="col-md-4">
                <div class="kpi-card">
                    <div>
                        <p class="text-muted text-uppercase small fw-bold mb-1">Membres Totaux</p>
                        <h2 class="fw-bold text-dark mb-0"><?php echo $total_membres; ?></h2>
                    </div>
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi-card">
                    <div>
                        <p class="text-muted text-uppercase small fw-bold mb-1">Validations en Attente</p>
                        <h2 class="fw-bold text-warning mb-0"><?php echo $count_pending; ?></h2>
                    </div>
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-regular fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi-card">
                    <div>
                        <p class="text-muted text-uppercase small fw-bold mb-1">Équipe Direction</p>
                        <h2 class="fw-bold text-success mb-0"><?php echo $count_admins; ?></h2>
                    </div>
                    <div class="kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!empty($msg)): ?>
            <div class="alert alert-<?php echo $msgType; ?> border-0 shadow-sm rounded-3 d-flex align-items-center mb-4">
                <i class="fa-solid <?php echo ($msgType == 'success') ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?> me-3 fs-4"></i>
                <div><?php echo $msg; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="p-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-light text-dark p-2 rounded-3"><i class="fa-solid fa-filter text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-0 bg-light" style="width: 250px;" placeholder="Rechercher (Nom, Tel)...">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3">Tout</button>
                    <button class="btn btn-sm btn-outline-warning rounded-pill px-3">En attente</button>
                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3">Bannis</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Identité</th>
                            <th>Contact</th>
                            <th>Rôle Système</th>
                            <th>Validation KYC</th>
                            <th>Statut Compte</th>
                            <th class="text-end pe-4">Gérer</th>
                        </tr>
                    </thead>
                    <tbody id="membersTable">
                        <?php foreach($membres as $user): 
                            $rid = $user['role_id'];
                            $r_info = isset($roles_config[$rid]) ? $roles_config[$rid] : $roles_config[5];
                            $photo = !empty($user['photo_profil_url']) ? $user['photo_profil_url'] : 'assets/img/default-avatar.png';
                            
                            // Logique visuelle Validation
                            $val_class = 'bg-secondary';
                            $val_text = 'Inconnu';
                            $val_icon = 'fa-question';
                            
                            if($user['statut_validation'] == 'valide') {
                                $val_class = 'bg-success text-success';
                                $val_text = 'Validé';
                                $val_icon = 'fa-check-circle';
                            } elseif($user['statut_validation'] == 'en_attente') {
                                $val_class = 'bg-warning text-warning';
                                $val_text = 'En attente';
                                $val_icon = 'fa-hourglass-half';
                            } elseif($user['statut_validation'] == 'rejete') {
                                $val_class = 'bg-danger text-danger';
                                $val_text = 'Rejeté';
                                $val_icon = 'fa-times-circle';
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($photo); ?>" class="avatar me-3" alt="p">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['nom_complet']); ?></div>
                                        <div class="small text-muted" style="font-size: 0.75rem;">
                                            ID: #<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="fw-medium text-dark small"><?php echo htmlspecialchars($user['telephone']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                            </td>

                            <td>
                                <span class="badge rounded-pill bg-<?php echo $r_info['badge']; ?> bg-opacity-10 text-<?php echo $r_info['badge']; ?> border border-<?php echo $r_info['badge']; ?> border-opacity-25 px-3 py-2">
                                    <i class="fa-solid <?php echo $r_info['icon']; ?> me-1"></i> <?php echo $r_info['label']; ?>
                                </span>
                            </td>

                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="<?php echo str_replace('bg-', 'text-', $val_class); ?> fs-5 me-2"><i class="fa-solid <?php echo $val_icon; ?>"></i></span>
                                    <span class="fw-medium small text-dark"><?php echo $val_text; ?></span>
                                </div>
                            </td>

                            <td>
                                <?php if($user['statut'] == 'actif'): ?>
                                    <span class="text-success fw-bold small"><span class="status-dot bg-success"></span>Actif</span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold small"><span class="status-dot bg-danger"></span>Banni</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end pe-4">
                                <button class="btn-icon shadow-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal"
                                        data-id="<?php echo $user['id']; ?>"
                                        data-nom="<?php echo htmlspecialchars($user['nom_complet']); ?>"
                                        data-role="<?php echo $user['role_id']; ?>"
                                        data-statut="<?php echo $user['statut']; ?>"
                                        data-validation="<?php echo $user['statut_validation']; ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if(empty($membres)): ?>
                    <div class="text-center py-5">
                        <img src="assets/img/empty.svg" width="100" class="opacity-50 mb-3" onerror="this.style.display='none'">
                        <p class="text-muted">Aucun membre trouvé.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold font-serif">Modifier le Membre</h5>
                        <p class="text-muted small mb-0">Ajustez les permissions et statuts.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_rights">
                        <input type="hidden" name="target_id" id="modal_target_id">

                        <div class="d-flex align-items-center bg-light p-3 rounded-3 mb-4">
                            <div class="bg-white p-2 rounded-circle shadow-sm me-3 text-primary fs-4" style="width:50px; height:50px; display:flex; align-items:center; justify-content:center;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" id="modal_user_name">Chargement...</h6>
                                <span class="small text-muted">Modification administrative</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Rôle Attribué</label>
                            <select class="form-select form-select-lg shadow-none border-secondary-subtle" name="new_role" id="modal_role_select">
                                <option value="5">Membre Standard</option>
                                <option value="4">Censeur</option>
                                <option value="3">Secrétaire</option>
                                <option value="2">Président</option>
                                <option value="1">Administrateur Système</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Validation du Dossier (KYC)</label>
                            <div class="selector-group">
                                <div class="selector-option">
                                    <input type="radio" name="new_validation" id="val_wait" value="en_attente">
                                    <label class="opt-wait" for="val_wait"><i class="fa-solid fa-hourglass me-2"></i>Attente</label>
                                </div>
                                <div class="selector-option">
                                    <input type="radio" name="new_validation" id="val_ok" value="valide">
                                    <label class="opt-valid" for="val_ok"><i class="fa-solid fa-check me-2"></i>Validé</label>
                                </div>
                                <div class="selector-option">
                                    <input type="radio" name="new_validation" id="val_no" value="rejete">
                                    <label class="opt-reject" for="val_no"><i class="fa-solid fa-xmark me-2"></i>Rejeté</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Accès au compte</label>
                            <div class="form-check form-switch p-0 m-0 d-flex align-items-center justify-content-between border p-3 rounded-3">
                                <div>
                                    <span class="fw-bold text-dark d-block">Compte Actif</span>
                                    <span class="small text-muted">L'utilisateur peut se connecter</span>
                                </div>
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="statusSwitch" style="width: 3em; height: 1.5em; cursor: pointer;">
                                <input type="hidden" name="new_status" id="hiddenStatusInput" value="actif">
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4 bg-light">
                        <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="background-color: var(--primary); border:none;">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- 1. SEARCH FILTER ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#membersTable tr');
            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // --- 2. MODAL DYNAMIC FILL ---
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            
            // Récupération des datas
            const id = button.getAttribute('data-id');
            const nom = button.getAttribute('data-nom');
            const role = button.getAttribute('data-role');
            const statut = button.getAttribute('data-statut'); // actif / banni
            const validation = button.getAttribute('data-validation'); // en_attente / valide / rejete

            // Injection
            document.getElementById('modal_target_id').value = id;
            document.getElementById('modal_user_name').textContent = nom;
            document.getElementById('modal_role_select').value = role;

            // Gestion Radio Validation
            if(validation === 'valide') document.getElementById('val_ok').checked = true;
            else if(validation === 'rejete') document.getElementById('val_no').checked = true;
            else document.getElementById('val_wait').checked = true;

            // Gestion Switch Statut Actif/Banni
            const statusSwitch = document.getElementById('statusSwitch');
            const hiddenStatusInput = document.getElementById('hiddenStatusInput');

            if (statut === 'actif') {
                statusSwitch.checked = true;
                hiddenStatusInput.value = 'actif';
            } else {
                statusSwitch.checked = false;
                hiddenStatusInput.value = 'banni';
            }

            // Listener sur le switch pour mettre à jour l'input caché
            statusSwitch.onchange = function() {
                hiddenStatusInput.value = this.checked ? 'actif' : 'banni';
            };
        });
    </script>
</body>
</html>