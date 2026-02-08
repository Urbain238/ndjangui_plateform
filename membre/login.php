<?php
// ------------------------------------------------------------------
// LOGIQUE PHP INTÉGRÉE (Backend)
// ------------------------------------------------------------------
session_start();

// 1. Si déjà connecté, redirection intelligente
if (isset($_SESSION['user_id'])) {
    // AJOUT DE SÉCURITÉ : Si l'utilisateur en session est marqué comme suspendu
    if (isset($_SESSION['statut']) && $_SESSION['statut'] === 'suspendu') {
        header("Location: suspendu.php");
        exit;
    }

    // Sinon, redirection classique selon le statut de validation
    if (isset($_SESSION['statut_validation']) && $_SESSION['statut_validation'] === 'admis') {
        header("Location: index.php");
    } else {
        header("Location: waiting_room.php");
    }
    exit;
}

// 2. Traitement de la connexion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    require_once '../config/database.php';
    
    try {
        $pdo = Database::getConnection();
        
        $username = $_POST['username'] ?? ''; 
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs.']);
            exit;
        }

        // Recherche du membre par email
        $stmt = $pdo->prepare("SELECT * FROM membres WHERE email = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Vérification du code_pin
        if ($user && $user['code_pin'] === $password) {
            
            // --- MISE À JOUR : GESTION CAS SUSPENDU ---
            if ($user['statut'] === 'suspendu') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['nom_complet'];
                $_SESSION['statut'] = 'suspendu'; // Important pour la vérification en haut de page

                echo json_encode(['success' => true, 'redirect' => 'suspendu.php']);
                exit;
            }

            // --- CAS NORMAL (Non suspendu) ---
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom_complet'];
            $_SESSION['user_role'] = $user['role_id'];
            $_SESSION['statut_validation'] = $user['statut_validation'];
            $_SESSION['statut'] = $user['statut']; // On stocke le statut actif

            // Détermination de la destination
            if ($user['statut_validation'] === 'admis') {
                $target = 'index.php';
            } else {
                // Si 'en_attente_parrain', 'en_vote' ou 'rejete'
                $target = 'waiting_room.php';
            }

            echo json_encode(['success' => true, 'redirect' => $target]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects.']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion au serveur.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #1a237e;
            --secondary: #2ecc71;
            --dark: #0a0f1d;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #d6e0f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            max-width: 1000px;
            width: 100%;
            display: flex;
            min-height: 600px;
            position: relative;
        }

        .login-image {
            background: linear-gradient(rgba(26, 35, 126, 0.85), rgba(26, 35, 126, 0.85)), 
                        url('https://images.unsplash.com/photo-1556742044-3c52d6e88c62?auto=format&fit=crop&q=80&w=800');
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

        .login-form { 
            width: 50%; 
            padding: 60px; 
            position: relative;
        }

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
            border-color: var(--primary);
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
            background: #0d1250; 
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(26,35,126,0.2);
        }

        /* CONFIGURATION DU BOUTON RETOUR */
        .back-to-home {
            position: absolute;
            top: 25px;
            left: 30px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            z-index: 999;
            display: inline-flex;
            align-items: center;
            transition: 0.3s;
            background: rgba(255,255,255,0.8);
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .back-to-home:hover {
            transform: translateX(-5px);
            background: rgba(255,255,255,1);
        }

        .shake { animation: shake 0.5s; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 992px) {
            .login-card {
                flex-direction: column;
                max-width: 550px;
                min-height: auto;
            }
            
            .login-image {
                display: none;
            }
            
            .login-form {
                width: 100%;
                padding: 40px 30px;
                padding-top: 80px;
            }

            .back-to-home {
                top: 20px;
                left: 20px;
            }
        }

        @media (max-width: 480px) {
            body { padding: 15px; }
            .login-form { padding: 30px 20px; padding-top: 70px; }
            .login-card { border-radius: 20px; }
            h3 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        
        <div class="login-card">
            
            <a href="../index.php" class="back-to-home">
                <i class="fa-solid fa-arrow-left me-2"></i> Retour accueil
            </a>

            <div class="login-image">
                <i class="fa-solid fa-shield-halved mb-4" style="font-size: 4rem;"></i>
                <h2 class="fw-800 text-uppercase">Espace Sécurisé</h2>
                <p class="lead opacity-75 mt-3">Connectez-vous pour gérer vos cotisations et suivre l'évolution de votre tontine en temps réel.</p>
                
                <div class="mt-5 text-start mx-auto" style="max-width: 250px;">
                    <p class="small mb-2"><i class="fa-solid fa-check-circle text-secondary me-2"></i> Données cryptées</p>
                    <p class="small mb-2"><i class="fa-solid fa-check-circle text-secondary me-2"></i> Accès multi-dispositifs</p>
                    <p class="small"><i class="fa-solid fa-check-circle text-secondary me-2"></i> Support 24/7</p>
                </div>
            </div>

            <div class="login-form d-flex flex-column justify-content-center">
                <div class="mb-4">
                    <h3 class="fw-800 text-primary">Connexion</h3>
                    <p class="text-muted small">Accédez à votre compte membre.</p>
                </div>

                <div id="errorAlert" class="alert alert-danger d-none animate__animated animate__fadeIn" role="alert"></div>

                <form id="loginForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-uppercase">Email d'utilisateur</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="username" class="form-control" id="username" placeholder="votre@email.com" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase">Mot de passe (PIN)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                            <input type="password" name="password" class="form-control" id="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label small text-muted" for="remember">Rester connecté</label>
                        </div>
                        <a href="#" class="small text-primary text-decoration-none fw-bold">Code oublié ?</a>
                    </div>

                    <button type="submit" class="btn btn-login shadow" id="btnSubmit">
                        Se connecter <i class="fa-solid fa-chevron-right ms-2"></i>
                    </button>
                </form>

                <div class="text-center mt-5">
                    <p class="small text-muted">Nouveau parmi nous ? <br>
                        <a href="/ndjangui_plateform/membre/rejoindre.php" class="text-primary fw-800 text-decoration-none text-uppercase">Créer un compte</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnSubmit');
            const errorAlert = document.getElementById('errorAlert');
            const formCard = document.querySelector('.login-form');
            
            errorAlert.classList.add('d-none');
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> Vérification...';
            btn.disabled = true;

            const formData = new FormData(this);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check me-2"></i> Accès autorisé';
                    btn.style.background = '#2ecc71';
                    setTimeout(() => {
                        // Redirection dynamique gérée par le retour PHP
                        window.location.href = data.redirect;
                    }, 600);
                } else {
                    formCard.classList.add('shake');
                    setTimeout(() => formCard.classList.remove('shake'), 500);

                    errorAlert.innerHTML = '<i class="fa-solid fa-circle-exclamation me-2"></i> ' + data.message;
                    errorAlert.classList.remove('d-none');

                    btn.innerHTML = 'Se connecter <i class="fa-solid fa-chevron-right ms-2"></i>';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                errorAlert.innerHTML = "Erreur réseau. Veuillez réessayer.";
                errorAlert.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = 'Se connecter';
            });
        });
    </script>
</body>
</html>