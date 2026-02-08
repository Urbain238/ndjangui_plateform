<?php
// ------------------------------------------------------------------
// LOGIQUE PHP (Backend intégré dans la même page)
// ------------------------------------------------------------------
session_start();

// 1. Si déjà connecté, on redirige directement
if (isset($_SESSION['user_id']) && in_array($_SESSION['user_role'], [1, 2, 3, 4])) {
    header("Location: index.php");
    exit;
}

// 2. Traitement de la soumission du formulaire (Mode AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On indique au navigateur qu'on répond en JSON
    header('Content-Type: application/json');

    // Connexion BDD (Chemin relatif car on est dans /admin)
    require_once '../config/database.php';
    require_once '../core/functions.php'; // Pour cleanInput

    try {
        $pdo = Database::getConnection();
        
        $email = cleanInput($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (empty($email) || empty($pass)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs.']);
            exit;
        }

        // Requête : On cherche un Email qui a un rôle d'administration (1,2,3,4)
        $stmt = $pdo->prepare("SELECT * FROM membres WHERE email = ? AND role_id IN (1, 2, 3, 4) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Vérification SIMPLE (Sans password_verify comme demandé)
        if ($user && $user['code_pin'] === $pass) {
            
            if ($user['statut'] === 'suspendu') {
                echo json_encode(['success' => false, 'message' => 'Ce compte est suspendu.']);
                exit;
            }

            // Création de la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom_complet'];
            $_SESSION['user_role'] = $user['role_id'];

            echo json_encode(['success' => true, 'redirect' => 'index.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé ou identifiants incorrects']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
    }
    exit; // On arrête l'exécution ici pour ne pas renvoyer le HTML
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #0f172a; /* Bleu très sombre (Admin) */
            --accent: #3b82f6; /* Bleu vif pour les actions */
            --secondary: #2ecc71;
            --dark: #0a0f1d;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center; /* CORRECTION : Centre horizontalement */
        }

        .login-card {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(0,0,0,0.5);
            max-width: 1000px;
            width: 100%;
            margin: auto;
            display: flex;
            min-height: 600px;
        }

        .login-image {
            background: linear-gradient(rgba(15, 23, 42, 0.9), rgba(15, 23, 42, 0.9)), 
                        url('https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&q=80&w=800');
            background-size: cover;
            background-position: center;
            width: 50%;
            padding: 50px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .login-form { width: 50%; padding: 60px; }

        .form-label { font-size: 0.75rem; letter-spacing: 1px; color: var(--primary); }

        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 2px solid #f0f0f0;
            background-color: #f8f9fa;
            transition: 0.3s;
        }

        .form-control:focus {
            background-color: #fff;
            border-color: var(--accent);
            box-shadow: none;
        }

        .input-group-text {
            border-radius: 12px 0 0 12px;
            background-color: #f8f9fa;
            border: 2px solid #f0f0f0;
            border-right: none;
            color: var(--primary);
        }

        .form-control { border-left: none; }

        .btn-login {
            background: var(--primary);
            color: white;
            border-radius: 50px;
            padding: 14px;
            font-weight: 700;
            width: 100%;
            border: none;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }

        .btn-login:hover { 
            background: var(--accent); 
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(59,130,246,0.3);
        }

        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            opacity: 0.8;
            transition: 0.3s;
        }
        .back-to-home:hover { opacity: 1; color: var(--accent); }

        /* AJOUT : Animation Shake */
        .shake { animation: shake 0.5s; }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            50% { transform: translateX(10px); }
            75% { transform: translateX(-10px); }
            100% { transform: translateX(0); }
        }

        @media (max-width: 850px) {
            .login-image { display: none; }
            .login-form { width: 100%; padding: 40px 30px; }
            .login-card { max-width: 500px; }
        }
    </style>
</head>
<body>

    <a href="../index.php" class="back-to-home">
        <i class="fa-solid fa-arrow-left me-2"></i> Retour a l'accueil
    </a>

    <div> 
        <div class="login-card">
            <div class="login-image">
                <i class="fa-solid fa-user-shield mb-4" style="font-size: 4rem;"></i>
                <h2 class="fw-800 text-uppercase">Portail Admin</h2>
                <p class="lead opacity-75 mt-3">Accès réservé à la présidence et à la gestion du bureau NDJANGUI.</p>
                
                <div class="mt-5 text-start mx-auto" style="max-width: 250px;">
                    <p class="small mb-2"><i class="fa-solid fa-lock text-info me-2"></i> Sessions monitorées</p>
                    <p class="small mb-2"><i class="fa-solid fa-key text-info me-2"></i> Double authentification</p>
                    <p class="small"><i class="fa-solid fa-terminal text-info me-2"></i> Audit des transactions</p>
                </div>
            </div>

            <div class="login-form d-flex flex-column justify-content-center">
                <div class="mb-4 text-center text-lg-start">
                    <h3 class="fw-800 text-primary">Administration</h3>
                    <p class="text-muted small">Identifiez-vous pour accéder au dashboard.</p>
                </div>

                <div id="errorAlert" class="alert alert-danger d-none animate__animated animate__fadeIn" role="alert"></div>

                <form id="adminLoginForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-uppercase">Identifiant Gestionnaire</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-user-tie"></i></span>
                            <input type="text" name="email" class="form-control" id="adminUser" placeholder="ex: urbain_president" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase">Clé de Sécurité</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                            <input type="password" name="password" class="form-control" id="adminPass" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label small text-muted" for="remember">Session sécurisée</label>
                        </div>
                        <a href="#" class="small text-accent text-decoration-none fw-bold">Clé perdue ?</a>
                    </div>

                    <button type="submit" class="btn btn-login shadow" id="btnSubmit">
                        Accéder au Bureau <i class="fa-solid fa-gauge-high ms-2"></i>
                    </button>
                </form>

                <script>
                    document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
                        e.preventDefault(); 
                        
                        const btn = document.getElementById('btnSubmit');
                        const errorAlert = document.getElementById('errorAlert');
                        const adminUser = document.getElementById('adminUser');
                        const adminPass = document.getElementById('adminPass');
                        const formContainer = document.querySelector('.login-form');

                        // Reset
                        errorAlert.classList.add('d-none');
                        adminUser.classList.remove('is-invalid');
                        adminPass.classList.remove('is-invalid');

                        // Loading
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Vérification...';
                        btn.style.background = '#3b82f6';
                        btn.disabled = true;

                        const formData = new FormData(this);

                        // Appel AJAX vers CE MÊME FICHIER
                        fetch('admin_login.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // SUCCES
                                btn.innerHTML = '<i class="fa-solid fa-check me-2"></i> Accès Autorisé';
                                btn.style.background = '#2ecc71';
                                
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 1000);
                            } else {
                                // ERREUR
                                formContainer.classList.add('shake');
                                setTimeout(() => { formContainer.classList.remove('shake'); }, 500);

                                errorAlert.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i> ' + data.message;
                                errorAlert.classList.remove('d-none');
                                
                                adminUser.classList.add('is-invalid');
                                adminPass.classList.add('is-invalid');

                                btn.innerHTML = 'Accéder au Bureau <i class="fa-solid fa-gauge-high ms-2"></i>';
                                btn.style.background = 'var(--primary)';
                                btn.disabled = false;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            formContainer.classList.add('shake');
                            setTimeout(() => { formContainer.classList.remove('shake'); }, 500);
                            errorAlert.innerHTML = "Erreur de connexion serveur.";
                            errorAlert.classList.remove('d-none');
                            btn.disabled = false;
                            btn.innerHTML = 'Accéder au Bureau';
                        });
                    });
                </script>

                <div class="text-center mt-5">
                    <p class="small text-muted">Accès membre classique ? <br>
                        <a href="../membre/login.php" class="text-primary fw-800 text-decoration-none text-uppercase">Retour au Portail Membre</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>