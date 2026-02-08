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

// =================================================================================
// 2. TRAITEMENT DU FORMULAIRE
// =================================================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        // --- A. Gestion de l'image de profil ---
        $photo_profil_url = 'assets/img/default-avatar.png';
        
        if (isset($_FILES['photo_profil_url']) && $_FILES['photo_profil_url']['error'] == 0) {
            $target_dir = "uploads/profils/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $file_extension = pathinfo($_FILES["photo_profil_url"]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid('profil_') . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["photo_profil_url"]["tmp_name"], $target_file)) {
                $photo_profil_url = $target_file;
            }
        }

        // --- B. Valeurs par défaut ---
        $code_pin = "1234";
        $score_credit = 0;
        $solde_tontine_total = 0.00;
        $statut = 'actif';
        $statut_validation = 'admis'; // ou 'en_attente' selon votre logique
        $date_inscription = date('Y-m-d H:i:s');
        
        $parrain_id = NULL; 
        $code_parrain_utilise = NULL;
        $plaidoyer_parrain = NULL;

        // --- C. GÉNÉRATION DU CODE PROMO ALÉATOIRE ---
        // Format : NDJ- + 6 caractères hexadécimaux aléatoires (Ex: NDJ-A1B2C3)
        $random_hex = strtoupper(bin2hex(random_bytes(3))); 
        $code_promo = "NDJ-" . $random_hex;

        // --- D. Récupération des données formulaire ---
        $nom_complet = htmlspecialchars($_POST['nom_complet']);
        $telephone = htmlspecialchars($_POST['telephone']);
        $email = !empty($_POST['email']) ? htmlspecialchars($_POST['email']) : NULL;
        $num_cni = htmlspecialchars($_POST['num_cni']);
        $date_naissance = $_POST['date_naissance'];
        $adresse_physique = htmlspecialchars($_POST['adresse_physique']);
        $profession = htmlspecialchars($_POST['profession']);
        $role_id = intval($_POST['role_id']);

        // --- E. Requête SQL ---
        $sql = "INSERT INTO membres (
            nom_complet, telephone, email, num_cni, date_naissance, 
            adresse_physique, profession, photo_profil_url, code_pin, role_id, 
            parrain_id, score_credit, solde_tontine_total, statut, 
            date_inscription, statut_validation, code_parrain_utilise, plaidoyer_parrain,
            code_promo, created_by 
        ) VALUES (
            :nom, :tel, :email, :cni, :dnaiss, 
            :adr, :prof, :photo, :pin, :role, 
            :parrain, :score, :solde, :statut, 
            :date_ins, :valid, :code_parr, :plaidoyer,
            :code_promo, :created_by
        )";

        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':nom' => $nom_complet,
            ':tel' => $telephone,
            ':email' => $email,
            ':cni' => $num_cni,
            ':dnaiss' => $date_naissance,
            ':adr' => $adresse_physique,
            ':prof' => $profession,
            ':photo' => $photo_profil_url,
            ':pin' => $code_pin,
            ':role' => $role_id,
            ':parrain' => $parrain_id,
            ':score' => $score_credit,
            ':solde' => $solde_tontine_total,
            ':statut' => $statut,
            ':date_ins' => $date_inscription,
            ':valid' => $statut_validation,
            ':code_parr' => $code_parrain_utilise,
            ':plaidoyer' => $plaidoyer_parrain,
            ':code_promo' => $code_promo, // Le code généré ici
            ':created_by' => $current_admin_id
        ]);

        // Message de succès incluant le code généré pour info
        $message = "Membre <strong>$nom_complet</strong> ajouté avec succès ! <br> Code Promo généré : <span class='badge bg-warning text-dark'>$code_promo</span>";
        $messageType = "success";

    } catch (PDOException $e) {
        // Gestion des erreurs (ex: duplicata email ou tel)
        $message = "Erreur Base de données : " . $e->getMessage();
        $messageType = "danger";
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Membre | NDJANGUI</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Palette Finance Luxe */
            --primary-dark: #022c22;  /* Très sombre vert */
            --primary-green: #065f46; /* Vert Émeraude */
            --accent-gold: #d97706;   /* Or riche */
            --accent-light: #fcd34d;  /* Or clair */
            --bg-soft: #f8fafc;
            --text-dark: #1e293b;
            --card-shadow: 0 20px 40px -10px rgba(2, 44, 34, 0.25);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at top right, #047857, #064e3b, #022c22);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
            color: var(--text-dark);
        }

        /* --- Carte Principale --- */
        .card-add-member {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: none;
            width: 100%;
            max-width: 950px;
            position: relative;
            overflow: hidden;
        }

        /* Liseré doré en haut */
        .card-add-member::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 6px;
            background: linear-gradient(90deg, var(--primary-green), var(--accent-gold), var(--primary-green));
        }

        h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-dark);
            letter-spacing: -0.5px;
        }

        /* --- Inputs & Labels --- */
        .form-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 5px;
        }

        .input-group-text {
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-right: none;
            color: var(--accent-gold);
            border-radius: 10px 0 0 10px;
        }

        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-left: none;
            border-radius: 0 10px 10px 0;
            padding: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: var(--primary-green);
            box-shadow: none;
        }
        
        .input-group:focus-within .input-group-text {
            color: var(--primary-green);
            background: #ecfdf5;
        }

        /* --- Upload Photo (Style Médaillon) --- */
        .profile-wrapper {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 15px;
        }
        
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 0 0 4px var(--accent-gold); /* Double border effect */
            background-color: #f1f5f9;
        }

        .btn-upload-cam {
            position: absolute;
            bottom: 5px;
            right: 0;
            background: var(--primary-green);
            color: white;
            border: 3px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn-upload-cam:hover { transform: scale(1.1); background: var(--accent-gold); }

        /* --- Grille de Rôles (FIX RESPONSIVE) --- */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* 2 par ligne sur mobile */
            gap: 10px;
            margin-top: 15px;
        }

        @media (min-width: 576px) {
            .role-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (min-width: 992px) {
            .role-grid { grid-template-columns: repeat(5, 1fr); } /* 5 sur une ligne PC */
        }

        .role-option input[type="radio"] { display: none; }

        .role-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 5px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
        }

        .role-option label i {
            font-size: 1.2rem;
            margin-bottom: 5px;
            color: #cbd5e1;
        }

        /* État sélectionné */
        .role-option input[type="radio"]:checked + label {
            background: linear-gradient(135deg, var(--primary-green), #047857);
            color: white;
            border-color: var(--primary-green);
            box-shadow: 0 8px 15px -5px rgba(6, 95, 70, 0.4);
            transform: translateY(-2px);
        }
        
        .role-option input[type="radio"]:checked + label i { color: var(--accent-light); }

        /* Cas spécifique Admin */
        .role-option.role-admin input[type="radio"]:checked + label {
            background: linear-gradient(135deg, var(--accent-gold), #b45309);
        }

        /* --- Bouton Submit --- */
        .btn-submit-luxury {
            background: linear-gradient(90deg, var(--primary-dark), var(--primary-green));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.4s;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-submit-luxury::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 0%; height: 100%;
            background: var(--accent-gold);
            z-index: -1;
            transition: all 0.4s;
        }

        .btn-submit-luxury:hover::after { width: 100%; }
        .btn-submit-luxury:hover { color: white; box-shadow: 0 10px 20px rgba(217, 119, 6, 0.3); }

        /* --- Sections Titres --- */
        .section-title {
            display: flex;
            align-items: center;
            color: var(--primary-green);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        .section-title::after {
            content: '';
            flex-grow: 1;
            height: 1px;
            background: #e2e8f0;
            margin-left: 15px;
        }
        .section-title i { margin-right: 10px; color: var(--accent-gold); }

    </style>
</head>
<body>

    <div class="container">
        <div class="card-add-member mx-auto p-4 p-lg-5">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5">
                <div class="text-center text-md-start mb-3 mb-md-0">
                    <h2 class="fw-bold mb-1">Nouveau Membre</h2>
                    <p class="text-muted small mb-0"><i class="fa-solid fa-database me-1 text-success"></i> Système de gestion bancaire</p>
                </div>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">
                    <i class="fa-solid fa-arrow-left me-2"></i>Retour
                </a>
            </div>

            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid <?php echo ($messageType == 'success') ? 'fa-check-circle' : 'fa-circle-exclamation'; ?> fs-4 me-3"></i>
                        <div><?php echo $message; ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="row mb-5 justify-content-center">
                    <div class="col-12 col-md-10 text-center">
                        
                        <div class="profile-wrapper">
                            <img id="preview" src="assets/img/default-avatar.png" class="profile-img" alt="Aperçu">
                            <label for="photo_input" class="btn-upload-cam" title="Changer la photo">
                                <i class="fa-solid fa-camera"></i>
                            </label>
                            <input type="file" id="photo_input" name="photo_profil_url" class="d-none" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div class="text-muted small fw-bold mb-3">PHOTO DE PROFIL</div>

                        <label class="form-label text-center w-100 mt-2">Assigner un Rôle</label>
                        <div class="role-grid">
                            <div class="role-option">
                                <input type="radio" name="role_id" id="role5" value="5" checked>
                                <label for="role5">
                                    <i class="fa-regular fa-user"></i> Membre
                                </label>
                            </div>
                            <div class="role-option">
                                <input type="radio" name="role_id" id="role4" value="4">
                                <label for="role4">
                                    <i class="fa-solid fa-scale-balanced"></i> Censeur
                                </label>
                            </div>
                            <div class="role-option">
                                <input type="radio" name="role_id" id="role3" value="3">
                                <label for="role3">
                                    <i class="fa-solid fa-pen-nib"></i> Secrétaire
                                </label>
                            </div>
                            <div class="role-option">
                                <input type="radio" name="role_id" id="role2" value="2">
                                <label for="role2">
                                    <i class="fa-solid fa-gavel"></i> Président
                                </label>
                            </div>
                            <div class="role-option role-admin">
                                <input type="radio" name="role_id" id="role1" value="1">
                                <label for="role1">
                                    <i class="fa-solid fa-crown"></i> Admin
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row g-4 g-lg-5">
                    
                    <div class="col-lg-6">
                        <div class="section-title"><i class="fa-solid fa-id-card"></i> Identité Civile</div>

                        <div class="mb-3">
                            <label class="form-label">Nom Complet *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                                <input type="text" class="form-control" name="nom_complet" placeholder="Ex: Jean Paul N." required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Date Naissance *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                                    <input type="date" class="form-control" name="date_naissance" required>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Numéro CNI</label>
                                <input type="text" class="form-control" name="num_cni" placeholder="N° Pièce">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Profession</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                                <input type="text" class="form-control" name="profession" placeholder="Secteur d'activité...">
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="section-title"><i class="fa-solid fa-address-book"></i> Coordonnées</div>

                        <div class="mb-3">
                            <label class="form-label">Téléphone (Mobile) *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                <input type="tel" class="form-control" name="telephone" placeholder="6XX XXX XXX" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email (Optionnel)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="client@banque.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Adresse / Résidence</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-map-pin"></i></span>
                                <input type="text" class="form-control" name="adresse_physique" placeholder="Ville, Quartier...">
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded-3 border d-flex align-items-start small text-muted">
                            <i class="fa-solid fa-circle-info text-success me-2 mt-1"></i>
                            <div>
                                Le <strong>Code PIN</strong> par défaut est <span class="badge bg-success">1234</span>. 
                                Un <strong>Code Promo</strong> sera généré automatiquement (Format NDJ-XXXX).
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-2 text-center">
                    <button type="submit" class="btn-submit-luxury w-100 shadow-lg">
                        <i class="fa-solid fa-user-plus me-2"></i> Enregistrer le Membre
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prévisualisation de l'image
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) { document.getElementById('preview').src = e.target.result; }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>