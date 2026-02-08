<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
$message = "";

// ==========================================
// 1. LOGIQUE DE TRAITEMENT (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- MISE À JOUR DU PROFIL ---
    if (isset($_POST['update_profile'])) {
        try {
            $sql = "UPDATE membres SET 
                    nom_complet = ?, email = ?, telephone = ?, 
                    num_cni = ?, date_naissance = ?, profession = ?, 
                    adresse_physique = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nom_complet'], $_POST['email'], $_POST['telephone'],
                $_POST['num_cni'], $_POST['date_naissance'], $_POST['profession'],
                $_POST['adresse_physique'], $user_id
            ]);
            $message = "<div class='alert alert-success border-0 shadow-sm rounded-4 animate__animated animate__fadeIn'>Profil mis à jour avec succès !</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger rounded-4'>Erreur : " . $e->getMessage() . "</div>";
        }
    }

    // --- MISE À JOUR SÉCURITÉ (PASSWORD & PIN) ---
    if (isset($_POST['update_security'])) {
        try {
            if (!empty($_POST['new_pin'])) {
                $stmt = $pdo->prepare("UPDATE membres SET code_pin = ? WHERE id = ?");
                $stmt->execute([$_POST['new_pin'], $user_id]);
            }
            if (!empty($_POST['new_pass'])) {
                $hashed = password_hash($_POST['new_pass'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE membres SET mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
            }
            $message = "<div class='alert alert-info border-0 shadow-sm rounded-4'>Sécurité mise à jour !</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger rounded-4'>Erreur sécurité.</div>";
        }
    }

    // --- IMPORTATION DE LA PHOTO ---
    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo_profil']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newName = "avatar_" . $user_id . "_" . time() . "." . $ext;
            $uploadDir = "../uploads/avatars/";
            
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            if (move_uploaded_file($_FILES['photo_profil']['tmp_name'], $uploadDir . $newName)) {
                $stmt = $pdo->prepare("UPDATE membres SET photo_profil_url = ? WHERE id = ?");
                $stmt->execute([$newName, $user_id]);
                $message = "<div class='alert alert-success border-0 shadow-sm rounded-4'>Photo de profil mise à jour !</div>";
            }
        }
    }
}

// ==========================================
// 2. RÉCUPÉRATION DES DONNÉES (JOIN & SUM)
// ==========================================
// Membre + Nom du parrain
$stmt = $pdo->prepare("
    SELECT m.*, p.nom_complet as nom_parrain 
    FROM membres m 
    LEFT JOIN membres p ON m.parrain_id = p.id 
    WHERE m.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Solde réel basé sur la table cotisations
$stmtSolde = $pdo->prepare("SELECT SUM(montant_paye) as total FROM cotisations WHERE membre_id = ? AND statut = 'payé'");
$stmtSolde->execute([$user_id]);
$solde_total = $stmtSolde->fetch()['total'] ?? 0;

// Gestion de l'image
$photo_url = !empty($user['photo_profil_url']) 
    ? "../uploads/avatars/" . $user['photo_profil_url'] 
    : "https://ui-avatars.com/api/?name=".urlencode($user['nom_complet'])."&background=4f46e5&color=fff&size=128";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil Premium | NDJANGUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4f46e5; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; }
        .profile-header-bg { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); height: 180px; border-radius: 0 0 40px 40px; }
        .profile-card { margin-top: -80px; border: none; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); background: #fff; }
        .avatar-container { position: relative; display: inline-block; margin-top: -70px; }
        .avatar-profile { width: 130px; height: 130px; border-radius: 35px; border: 5px solid white; object-fit: cover; background: white; }
        .stat-box { padding: 15px; border-radius: 18px; background: #f1f5f9; transition: all 0.3s; border: 1px solid transparent; }
        .stat-box:hover { background: #fff; border-color: var(--primary); transform: translateY(-3px); }
        .form-label { font-weight: 700; font-size: 0.8rem; color: #64748b; text-uppercase; letter-spacing: 0.5px; }
        .form-control { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background: #fdfdfd; }
        .form-control:focus { box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); border-color: var(--primary); }
        .btn-premium { background: var(--primary); color: white; border-radius: 12px; padding: 12px 25px; border: none; font-weight: 700; transition: all 0.3s; }
        .btn-premium:hover { box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2); transform: translateY(-2px); color: white; }
        .section-title { font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; }
        .section-title i { margin-right: 10px; color: var(--primary); }
    </style>
</head>
<body>

<div class="profile-header-bg">
    <div class="container pt-4">
        <a href="index.php" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-2"></i>Tableau de bord
        </a>
    </div>
</div>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            
            <?php echo $message; ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card profile-card text-center p-4 mb-4">
                        <div class="avatar-container mb-3">
                            <img src="<?php echo $photo_url; ?>" class="avatar-profile shadow-md">
                            <form id="photoForm" method="POST" enctype="multipart/form-data" class="position-absolute bottom-0 end-0">
                                <input type="file" name="photo_profil" id="fileInput" hidden onchange="document.getElementById('photoForm').submit()">
                                <button type="button" onclick="document.getElementById('fileInput').click()" class="btn btn-primary btn-sm rounded-circle border-3 border-white">
                                    <i class="fa-solid fa-camera p-1"></i>
                                </button>
                            </form>
                        </div>
                        <h4 class="fw-800 mb-1"><?php echo htmlspecialchars($user['nom_complet']); ?></h4>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($user['profession']); ?></p>
                        
                        <div class="badge bg-success-subtle text-success rounded-pill px-3 py-2 mb-4">
                            <i class="fa-solid fa-circle-check me-1"></i> <?php echo ucfirst($user['statut_validation']); ?>
                        </div>

                        <div class="stat-box mb-3 text-start">
                            <small class="text-muted d-block fw-bold">MON PARRAIN</small>
                            <span class="text-dark fw-bold"><i class="fa-solid fa-user-tie me-2 text-primary"></i><?php echo htmlspecialchars($user['nom_parrain'] ?? 'Système'); ?></span>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="stat-box text-start">
                                    <small class="text-muted d-block">Score</small>
                                    <span class="fw-bold text-primary"><?php echo $user['score_credit']; ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box text-start">
                                    <small class="text-muted d-block">Épargne</small>
                                    <span class="fw-bold text-success"><?php echo number_format($solde_total, 0, ',', ' '); ?> F</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-4 border-0 shadow-sm p-4">
                        <h6 class="section-title"><i class="fa-solid fa-shield-halved"></i>Sécurité</h6>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Code PIN (4 chiffres)</label>
                                <input type="password" name="new_pin" class="form-control" maxlength="4" placeholder="••••">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nouveau Mot de passe</label>
                                <input type="password" name="new_pass" class="form-control" placeholder="Laisser vide si inchangé">
                            </div>
                            <button type="submit" name="update_security" class="btn btn-outline-primary w-100 rounded-3 fw-bold btn-sm py-2">Mettre à jour l'accès</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card profile-card p-4 border-0 mb-4">
                        <h5 class="section-title"><i class="fa-solid fa-id-card"></i>Informations Générales</h5>
                        <form action="" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nom Complet</label>
                                    <input type="text" name="nom_complet" class="form-control" value="<?php echo htmlspecialchars($user['nom_complet']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Personnel</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" name="telephone" class="form-control" value="<?php echo htmlspecialchars($user['telephone']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Numéro CNI</label>
                                    <input type="text" name="num_cni" class="form-control" value="<?php echo htmlspecialchars($user['num_cni']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Profession actuelle</label>
                                    <input type="text" name="profession" class="form-control" value="<?php echo htmlspecialchars($user['profession']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date de Naissance</label>
                                    <input type="date" name="date_naissance" class="form-control" value="<?php echo $user['date_naissance']; ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Adresse de résidence</label>
                                    <input type="text" name="adresse_physique" class="form-control" value="<?php echo htmlspecialchars($user['adresse_physique']); ?>">
                                </div>
                            </div>

                            <h5 class="section-title mt-5"><i class="fa-solid fa-gift"></i>Parrainage & Promo</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Code Promo Appliqué</label>
                                    <div class="form-control bg-light text-muted"><?php echo htmlspecialchars($user['code_promo'] ?: 'Aucun'); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Code Parrain Utilisé</label>
                                    <div class="form-control bg-light text-muted"><?php echo htmlspecialchars($user['code_parrain_utilise'] ?: 'Aucun'); ?></div>
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-top text-end">
                                <button type="submit" name="update_profile" class="btn btn-premium px-5 shadow-sm">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>