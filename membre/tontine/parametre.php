<?php
session_start();
date_default_timezone_set('Africa/Douala');

// --- 1. CONNEXION BDD ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) { die("Erreur critique base de données : " . $e->getMessage()); }

// --- 2. SÉCURITÉ & CONTEXTE ---
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
$user_id = $_SESSION['user_id'];

$cercle_id = isset($_GET['cercle_id']) ? (int)$_GET['cercle_id'] : 0;
if ($cercle_id === 0) die("Paramètre manquant : ID Cercle.");

$stmt = $pdo->prepare("SELECT * FROM cercles WHERE id = ?");
$stmt->execute([$cercle_id]);
$cercle = $stmt->fetch();

if (!$cercle) die("Cercle introuvable.");

// VÉRIFICATION ADMIN (Président uniquement)
if ($cercle['president_id'] != $user_id) {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px; color:#ef4444;'>
            <h2>⛔ Accès Interdit</h2>
            <p>Seul le président peut configurer ce cercle.</p>
            <a href='dashboard.php?cercle_id=$cercle_id'>Retour</a>
         </div>");
}

$msg = "";

// --- 3. LOGIQUE MÉTIER ---

// A. Update Général
if (isset($_POST['update_general'])) {
    $nom = trim($_POST['nom_cercle']);
    $forum_bloque = isset($_POST['est_forum_bloque']) ? 1 : 0;

    if(!empty($nom)) {
        $upd = $pdo->prepare("UPDATE cercles SET nom_cercle = ?, est_forum_bloque = ? WHERE id = ?");
        $upd->execute([$nom, $forum_bloque, $cercle_id]);
        $cercle['nom_cercle'] = $nom; 
        $cercle['est_forum_bloque'] = $forum_bloque;
        $msg = "<div class='alert-custom success'><i class='fa-solid fa-check-circle'></i> Paramètres généraux enregistrés.</div>";
    }
}

// B. Update Finances
if (isset($_POST['update_finance'])) {
    $montant_std = (int)$_POST['montant_cotisation_standard'];
    $montant_unit = (int)$_POST['montant_unitaire'];
    $freq = $_POST['frequence'];
    $type_tirage = $_POST['type_tirage'];
    $plafond = (int)$_POST['plafond_parts_membre'];
    
    $upd = $pdo->prepare("UPDATE cercles SET montant_cotisation_standard = ?, montant_unitaire = ?, frequence = ?, type_tirage = ?, plafond_parts_membre = ? WHERE id = ?");
    $upd->execute([$montant_std, $montant_unit, $freq, $type_tirage, $plafond, $cercle_id]);
    
    $cercle['montant_cotisation_standard'] = $montant_std;
    $cercle['montant_unitaire'] = $montant_unit;
    $cercle['frequence'] = $freq;
    $cercle['type_tirage'] = $type_tirage;
    $cercle['plafond_parts_membre'] = $plafond;

    $msg = "<div class='alert-custom success'><i class='fa-solid fa-coins'></i> Configuration financière mise à jour.</div>";
}

// C. Actions Membres
if (isset($_POST['action_membre'])) {
    $inscrip_id = (int)$_POST['inscription_id'];
    $type_action = $_POST['type_action'];
    
    $check = $pdo->prepare("SELECT membre_id FROM inscriptions_cercle WHERE id = ? AND cercle_id = ?");
    $check->execute([$inscrip_id, $cercle_id]);
    $target = $check->fetch();

    if ($target && $target['membre_id'] != $cercle['president_id']) {
        if ($type_action == 'valider') {
            $pdo->prepare("UPDATE inscriptions_cercle SET statut = 'actif' WHERE id = ?")->execute([$inscrip_id]);
            $msg = "<div class='alert-custom success'><i class='fa-solid fa-user-check'></i> Membre activé avec succès.</div>";
        } elseif ($type_action == 'supprimer') {
            $pdo->prepare("DELETE FROM inscriptions_cercle WHERE id = ?")->execute([$inscrip_id]);
            $msg = "<div class='alert-custom warning'><i class='fa-solid fa-user-slash'></i> Membre retiré du cercle.</div>";
        }
    }
}

// --- 4. DATA FETCHING ---
$sql_membres = "SELECT i.*, m.nom_complet, m.email, m.photo_profil_url 
                FROM inscriptions_cercle i 
                JOIN membres m ON i.membre_id = m.id 
                WHERE i.cercle_id = ? 
                ORDER BY FIELD(i.statut, 'en_attente', 'actif', 'rejete'), m.nom_complet ASC";
$stmt_m = $pdo->prepare($sql_membres);
$stmt_m->execute([$cercle_id]);
$membres = $stmt_m->fetchAll();

$path_dash = "dashboard.php?cercle_id=" . $cercle_id;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration | <?= htmlspecialchars($cercle['nom_cercle']) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #0f766e; /* Un beau vert/bleu profond */
            --primary-hover: #0d9488;
            --secondary: #64748b;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-dark: #1e293b;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-dark);
            padding-top: 80px; /* Navbar space */
        }

        /* Navbar Styling */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            height: 70px;
        }
        .brand-logo {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
            display: flex; align-items: center; gap: 0.75rem;
        }
        .brand-icon-box {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white; border-radius: 8px;
            display: grid; place-items: center;
        }

        /* Alerts */
        .alert-custom {
            border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.75rem;
            font-weight: 500; font-size: 0.95rem;
            animation: slideDown 0.4s ease-out;
        }
        @keyframes slideDown { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .alert-custom.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-custom.warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

        /* Modern Cards */
        .settings-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            height: 100%;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .settings-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .card-header-styled {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 1rem;
            background: linear-gradient(to right, #ffffff, #fcfcfc);
        }
        .header-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: grid; place-items: center;
            font-size: 1.1rem;
        }
        .icon-blue { background: #eff6ff; color: #3b82f6; }
        .icon-amber { background: #fffbeb; color: #d97706; }
        .icon-indigo { background: #eef2ff; color: #6366f1; }

        .card-body-styled { padding: 1.5rem; }

        /* Forms */
        .form-label {
            font-size: 0.85rem; font-weight: 600; color: var(--secondary);
            margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-weight: 500; color: var(--text-dark);
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1);
        }
        .input-group-text {
            background: #f8fafc; border-color: var(--border); color: var(--secondary); font-weight: 600;
        }

        /* Toggle Switch */
        .form-check-input {
            width: 3em; height: 1.5em; cursor: pointer;
            background-color: #cbd5e1; border: none;
        }
        .form-check-input:checked { background-color: var(--primary); }

        /* Buttons */
        .btn-primary-custom {
            background: var(--primary); color: white;
            padding: 0.75rem 1.5rem; border-radius: 10px;
            font-weight: 600; border: none;
            transition: all 0.2s;
        }
        .btn-primary-custom:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        .btn-outline-custom {
            background: transparent; border: 2px solid var(--border);
            color: var(--text-dark); padding: 0.5rem 1rem; border-radius: 8px;
            font-weight: 600; font-size: 0.9rem;
        }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); background: #f0fdfa; }

        /* Table */
        .table-custom th {
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-muted); font-weight: 600; padding-bottom: 1rem;
        }
        .avatar-img {
            width: 42px; height: 42px; border-radius: 50%; object-fit: cover;
            border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 0.35rem 0.85rem; border-radius: 50px;
            font-size: 0.75rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        @media (max-width: 768px) {
            .settings-card { border-radius: 0; border-left: none; border-right: none; }
            .container { padding-left: 0; padding-right: 0; }
            .col-lg-5, .col-lg-7 { padding: 0 10px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container">
        <div class="brand-logo">
            <div class="brand-icon"><i class="fa-solid fa-hands-holding-circle"></i></div>
            NDJANGUI
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <a href="admin-seances.php?cercle_id=<?= $cercle_id ?>" class="btn btn-outline-custom">
    <i class="fa-solid fa-chevron-left me-2"></i> Retour aux Séances
</a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <div class="row mb-4 align-items-end">
        <div class="col-md-8">
            <h6 class="text-uppercase text-primary fw-bold mb-2 ls-1">Administration</h6>
            <h2 class="fw-bold text-dark mb-0">Paramètres du Cercle</h2>
        </div>
    </div>

    <?= $msg ?>

    <div class="row g-4">
        
        <div class="col-lg-5">
            <form method="POST">
                <div class="settings-card">
                    <div class="card-header-styled">
                        <div class="header-icon icon-blue"><i class="fa-regular fa-id-card"></i></div>
                        <div>
                            <h5 class="fw-bold m-0 text-dark">Identité & Accès</h5>
                            <small class="text-muted">Informations générales</small>
                        </div>
                    </div>
                    
                    <div class="card-body-styled">
                        <div class="mb-4">
                            <label class="form-label">Nom du cercle</label>
                            <input type="text" name="nom_cercle" class="form-control" value="<?= htmlspecialchars($cercle['nom_cercle']) ?>" placeholder="Ex: Tontine des Amis" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Code d'invitation</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light text-primary fw-bold" value="<?= htmlspecialchars($cercle['code_invitation']) ?>" readonly id="codeInv">
                                <button class="btn btn-light border" type="button" onclick="navigator.clipboard.writeText(document.getElementById('codeInv').value); this.innerHTML='<i class=\'fa-solid fa-check text-success\'></i>'; setTimeout(()=>this.innerHTML='<i class=\'fa-regular fa-copy\'></i>', 2000);">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>
                            <div class="form-text mt-2"><i class="fa-solid fa-circle-info me-1"></i> Partagez ce code pour ajouter des membres.</div>
                        </div>

                        <hr class="border-light my-4">

                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                            <div>
                                <label class="fw-bold text-dark mb-1 d-block" for="forumSwitch">Forum Bloqué</label>
                                <small class="text-muted">Si actif, seuls les admins parlent.</small>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" name="est_forum_bloque" id="forumSwitch" <?= $cercle['est_forum_bloque'] == 1 ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="mt-4 pt-2">
                            <button type="submit" name="update_general" class="btn btn-primary-custom w-100">
                                Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-7">
            <form method="POST">
                <div class="settings-card">
                    <div class="card-header-styled">
                        <div class="header-icon icon-amber"><i class="fa-solid fa-scale-balanced"></i></div>
                        <div>
                            <h5 class="fw-bold m-0 text-dark">Règles Financières</h5>
                            <small class="text-muted">Structure de la tontine</small>
                        </div>
                    </div>
                    
                    <div class="card-body-styled">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Cotisation Standard</label>
                                <div class="input-group">
                                    <input type="number" name="montant_cotisation_standard" class="form-control fw-bold text-dark" value="<?= $cercle['montant_cotisation_standard'] ?>" min="0">
                                    <span class="input-group-text">FCFA</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prix d'une part</label>
                                <div class="input-group">
                                    <input type="number" name="montant_unitaire" class="form-control" value="<?= $cercle['montant_unitaire'] ?>" min="0">
                                    <span class="input-group-text">FCFA</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Fréquence</label>
                                <div class="position-relative">
                                    <i class="fa-regular fa-calendar position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                                    <select name="frequence" class="form-select ps-5">
                                        <option value="hebdomadaire" <?= $cercle['frequence'] == 'hebdomadaire' ? 'selected' : '' ?>>Hebdomadaire</option>
                                        <option value="mensuel" <?= $cercle['frequence'] == 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                                        <option value="bimensuel" <?= $cercle['frequence'] == 'bimensuel' ? 'selected' : '' ?>>Bi-Mensuel</option>
                                        <option value="libre" <?= $cercle['frequence'] == 'libre' ? 'selected' : '' ?>>Libre</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Attribution</label>
                                <select name="type_tirage" class="form-select">
                                    <option value="aleatoire" <?= $cercle['type_tirage'] == 'aleatoire' ? 'selected' : '' ?>>🎲 Hasard / Aléatoire</option>
                                    <option value="fixe" <?= $cercle['type_tirage'] == 'fixe' ? 'selected' : '' ?>>📅 Calendrier Fixe</option>
                                    <option value="enchere" <?= $cercle['type_tirage'] == 'enchere' ? 'selected' : '' ?>>🔨 Enchères</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="p-3 bg-light rounded border">
                                    <label class="form-label mb-2">Plafond de parts par membre</label>
                                    <div class="d-flex gap-3 align-items-center">
                                        <input type="number" name="plafond_parts_membre" class="form-control w-25 fw-bold text-center" value="<?= $cercle['plafond_parts_membre'] ?>">
                                        <div class="text-muted small lh-sm">
                                            Limite le nombre de "mains" qu'une seule personne peut acheter dans ce cycle.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 text-end border-top">
                            <button type="submit" name="update_finance" class="btn btn-dark px-4 py-2 fw-bold" style="border-radius: 10px;">
                                <i class="fa-solid fa-check me-2"></i> Mettre à jour
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12 pb-5">
            <div class="settings-card">
                <div class="card-header-styled justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon icon-indigo"><i class="fa-solid fa-users-gear"></i></div>
                        <div>
                            <h5 class="fw-bold m-0 text-dark">Gestion des Membres</h5>
                            <small class="text-muted"><?= count($membres) ?> participants inscrits</small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead class="bg-light border-bottom">
                            <tr>
                                <th class="ps-4" style="width: 40%;">Membre</th>
                                <th>Parts & Date</th>
                                <th>Statut</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($membres as $m): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                            $photoPath = !empty($m['photo_profil_url']) ? '../../uploads/'.$m['photo_profil_url'] : '';
                                            $avatarSrc = (empty($photoPath) || !file_exists($photoPath)) 
                                                ? 'https://ui-avatars.com/api/?name=' . urlencode($m['nom_complet']) . '&background=random&color=fff' 
                                                : $photoPath;
                                        ?>
                                        <img src="<?= $avatarSrc ?>" class="avatar-img me-3" alt="Avatar">
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($m['nom_complet']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($m['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= $m['nombre_parts'] ?> part(s)</div>
                                    <div class="small text-muted text-uppercase" style="font-size: 0.7rem;">Inscrit le <?= date('d/m/y', strtotime($m['date_inscription'])) ?></div>
                                </td>
                                <td>
                                    <?php if($m['statut'] == 'actif'): ?>
                                        <span class="status-badge status-active"><i class="fa-solid fa-check"></i> Actif</span>
                                    <?php elseif($m['statut'] == 'en_attente'): ?>
                                        <span class="status-badge status-pending"><i class="fa-solid fa-hourglass-half"></i> En attente</span>
                                    <?php else: ?>
                                        <span class="status-badge status-rejected"><i class="fa-solid fa-ban"></i> Rejeté</span>
                                    <?php endif; ?>

                                    <?php if($m['membre_id'] == $cercle['president_id']): ?>
                                        <span class="badge bg-dark text-white ms-1 rounded-pill px-2">ADMIN</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if($m['membre_id'] != $cercle['president_id']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer cette action ?');">
                                            <input type="hidden" name="inscription_id" value="<?= $m['id'] ?>">
                                            
                                            <?php if($m['statut'] == 'en_attente'): ?>
                                                <button type="submit" name="action_membre" value="1" onclick="this.form.type_action.value='valider'" class="btn btn-sm btn-success me-1 shadow-sm" style="border-radius: 6px;">
                                                    <i class="fa-solid fa-check"></i> Valider
                                                </button>
                                                <input type="hidden" name="type_action" id="type_action">
                                            <?php endif; ?>

                                            <button type="submit" name="action_membre" value="1" onclick="this.form.type_action.value='supprimer'" class="btn btn-sm btn-white border text-danger shadow-sm" style="border-radius: 6px;" title="Retirer">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if(empty($membres)): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-users-slash fa-3x text-light mb-3" style="color: #cbd5e1 !important;"></i>
                            <p class="text-muted fw-medium">Aucun membre inscrit pour le moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>