<?php
session_start();
require_once '../config/database.php';

$pdo = Database::getConnection();
$message_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $nom_complet = htmlspecialchars($_POST['nom_complet']);
    $telephone = htmlspecialchars($_POST['telephone']);
    $email = htmlspecialchars($_POST['email']);
    $num_cni = htmlspecialchars($_POST['num_cni']);
    $date_naissance = $_POST['date_naissance'];
    $adresse = htmlspecialchars($_POST['adresse_physique']);
    $profession = htmlspecialchars($_POST['profession']);
    
    // Correction : Nettoyage du code saisi (espaces et casse)
    $code_parrain_saisi = strtoupper(trim($_POST['code_promo']));
    $code_pin = $_POST['code_pin']; 

    // 1. Validation du numéro de téléphone (9 chiffres)
    if (!preg_match("/^[0-9]{9}$/", $telephone)) {
        $message_error = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
    } else {
        try {
            // 2. Vérification du code parrain
            // MISE À JOUR : On vérifie que le parrain est 'admis' car un membre 'actif' (cotisant) 
            // est d'abord 'admis' par le vote.
            $stmtParrain = $pdo->prepare("SELECT id, nom_complet FROM membres WHERE code_promo = ? AND (statut = 'actif' OR statut_validation = 'admis')");
            $stmtParrain->execute([$code_parrain_saisi]);
            $parrain = $stmtParrain->fetch();

            if (!$parrain) {
                $message_error = "Le code promo saisi est invalide ou le parrain n'est pas autorisé à parrainer.";
            } else {
                $parrain_id = $parrain['id'];

                // 3. Insertion du nouveau membre
                // Note : On utilise 'en_attente_parrain' comme premier état du workflow
                $sql = "INSERT INTO membres (
                            nom_complet, telephone, email, num_cni, date_naissance, 
                            adresse_physique, profession, code_pin, role_id, 
                            parrain_id, statut, statut_validation, code_parrain_utilise, date_inscription
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 4, ?, 'inactif', 'en_attente_parrain', ?, NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nom_complet, $telephone, $email, $num_cni, $date_naissance,
                    $adresse, $profession, $code_pin, $parrain_id, $code_parrain_saisi
                ]);

                // Récupération de l'ID pour la session
                $new_user_id = $pdo->lastInsertId();

                // 4. Notification au Parrain
                try {
                    $msg_notif = "Action requise : $nom_complet a utilisé votre code promo pour rejoindre la tontine. Veuillez valider son profil.";
                    $stmtNotif = $pdo->prepare("INSERT INTO notifications (membre_id, message, date_creation) VALUES (?, ?, NOW())");
                    $stmtNotif->execute([$parrain_id, $msg_notif]);
                } catch (Exception $e_notif) {
                    // Erreur silencieuse pour les notifications
                }

                // Initialisation de la session pour la salle d'attente
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_nom'] = $nom_complet;
                $_SESSION['statut_validation'] = 'en_attente_parrain';
                $_SESSION['waiting_name'] = $nom_complet;

                // Succès : Vers la salle d'attente
                header("Location: waiting_room.php");
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                if (strpos($e->getMessage(), 'telephone') !== false) {
                    $message_error = "Ce numéro de téléphone est déjà associé à un compte existant.";
                } elseif (strpos($e->getMessage(), 'num_cni') !== false) {
                    $message_error = "Ce numéro de CNI est déjà enregistré.";
                } elseif (strpos($e->getMessage(), 'email') !== false) {
                    $message_error = "Cette adresse email est déjà utilisée.";
                } else {
                    $message_error = "Une information saisie existe déjà.";
                }
            } else {
                $message_error = "Erreur technique : " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adhésion | NDJANGUI PLATEFORME</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #1a237e;
            --primary-light: #3949ab;
            --accent: #f39c12;
            --success: #2ecc71;
            --bg-body: #f8fafc;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-body);
            padding: 40px 0;
            color: #1e293b;
        }

        .grand-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.05);
            max-width: 900px;
            margin: auto;
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .header-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 50px 30px;
            text-align: center;
        }

        .header-banner i.main-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            background: rgba(255,255,255,0.15);
            width: 80px;
            height: 80px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .section-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            margin: 35px 0 20px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
            margin-left: 15px;
        }

        .input-group-text {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
            color: #64748b;
            border-radius: 12px 0 0 12px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .form-control {
            border-radius: 0 12px 12px 0;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            background-color: #f8fafc;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--primary-light);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(57, 73, 171, 0.1);
        }

        .form-label { font-weight: 600; color: #475569; font-size: 0.85rem; margin-bottom: 8px; }

        .notice-box {
            background: #f0f4ff;
            border-radius: 16px;
            padding: 20px;
            border-left: 5px solid var(--primary);
        }

        .btn-send {
            background: var(--primary);
            color: white;
            padding: 18px;
            border-radius: 16px;
            font-weight: 700;
            width: 100%;
            border: none;
            transition: 0.3s;
            box-shadow: 0 10px 25px rgba(26, 35, 126, 0.25);
        }

        .btn-send:hover {
            background: #000;
            transform: translateY(-2px);
        }

        .back-link {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s;
        }

        .back-link:hover { color: var(--accent); }
    </style>
</head>
<body>

    <div class="container">
        <div class="text-center mb-4">
            <a href="login.php" class="back-link animate__animated animate__fadeInLeft">
                <i class="fa-solid fa-circle-chevron-left me-2"></i> Retour à la page de connexion
            </a>
        </div>

        <div class="grand-card animate__animated animate__fadeInUp">
            <div class="header-banner">
                <i class="fa-solid fa-users-viewfinder main-icon shadow-lg"></i>
                <h2 class="fw-800">Candidature NDJANGUI</h2>
                <p class="opacity-75 mb-0">Remplissez vos informations pour rejoindre la Grande Tontine</p>
            </div>

            <div class="p-4 p-md-5">
                
                <?php if($message_error): ?>
                    <div class="alert alert-danger border-0 rounded-4 animate__animated animate__shakeX shadow-sm d-flex align-items-center mb-4">
                        <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
                        <span class="fw-600"><?php echo $message_error; ?></span>
                    </div>
                <?php endif; ?>

                <div class="notice-box d-flex align-items-center mb-5">
                    <i class="fa-solid fa-circle-info fs-3 text-primary me-3"></i>
                    <p class="small mb-0 fw-500">
                        <strong>Important :</strong> Votre adhésion est soumise à un vote de la communauté. Assurez-vous que votre <strong>parrain</strong> est prêt à valider votre profil dès réception de sa notification.
                    </p>
                </div>

                <form action="" method="POST">
                    
                    <div class="section-title"><i class="fa-solid fa-fingerprint me-2 text-accent"></i>Identité du Candidat</div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Nom Complet</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="nom_complet" class="form-control" placeholder="Nom et Prénom" value="<?php echo isset($_POST['nom_complet']) ? htmlspecialchars($_POST['nom_complet']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date de Naissance</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>
                                <input type="date" name="date_naissance" class="form-control" value="<?php echo isset($_POST['date_naissance']) ? $_POST['date_naissance'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profession / Activité</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                                <input type="text" name="profession" class="form-control" placeholder="Votre métier" value="<?php echo isset($_POST['profession']) ? htmlspecialchars($_POST['profession']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Numéro de CNI</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                                <input type="text" name="num_cni" class="form-control" placeholder="Numéro d'identité" value="<?php echo isset($_POST['num_cni']) ? htmlspecialchars($_POST['num_cni']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Adresse de Résidence</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                                <input type="text" name="adresse_physique" class="form-control" placeholder="Ville et Quartier" value="<?php echo isset($_POST['adresse_physique']) ? htmlspecialchars($_POST['adresse_physique']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="section-title"><i class="fa-solid fa-shield-halved me-2 text-accent"></i>Contact & Sécurité</div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Email Personnel</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="nom@exemple.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone Mobile</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                <input type="tel" name="telephone" class="form-control" placeholder="6XXXXXXXX" maxlength="9" value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Code PIN de connexion (Secret)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                                <input type="password" name="code_pin" class="form-control" placeholder="Définissez votre code secret" required>
                            </div>
                        </div>
                    </div>

                    <div class="section-title"><i class="fa-solid fa-user-plus me-2 text-accent"></i>Parrainage</div>
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label">Code Promo du Parrain</label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning text-white border-warning"><i class="fa-solid fa-ticket"></i></span>
                                <input type="text" name="code_promo" class="form-control border-warning" placeholder="Entrez le code unique de parrainage" value="<?php echo isset($_POST['code_promo']) ? htmlspecialchars($_POST['code_promo']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mt-5 mb-4 bg-light p-3 rounded-3 border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="checkAgree" required style="cursor:pointer">
                        <label class="form-check-label small fw-600" for="checkAgree" style="cursor:pointer">
                            Je confirme que toutes les informations fournies sont exactes et sincères. Toute fausse déclaration entraînera le rejet de ma candidature.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-send animate__animated animate__pulse animate__infinite animate__slower">
                        SOUMETTRE MA CANDIDATURE <i class="fa-solid fa-paper-plane ms-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>