<?php
// ------------------------------------------------------------------
// LOGIQUE PHP (Backend)
// ------------------------------------------------------------------
session_start();

// 1. Gestion de la déconnexion (si on clique sur "Se déconnecter")
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 2. Sécurité : Si l'utilisateur n'est pas connecté, retour au login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Optionnel : On peut revérifier en base de données si le statut est toujours suspendu
// (Ici on se base sur la session pour l'instant pour la rapidité)
$nomUser = $_SESSION['user_nom'] ?? 'Membre';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Suspendu | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #1a237e;
            --danger: #e74c3c;
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

        .suspendu-card {
            background: white;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            max-width: 550px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .suspendu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--danger);
        }

        .icon-box {
            width: 100px;
            height: 100px;
            background: #fdeaea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px auto;
            color: var(--danger);
            font-size: 3rem;
            animation: pulse 2s infinite;
        }

        h2 { color: var(--dark); font-weight: 800; margin-bottom: 15px; }
        
        p { color: #6c757d; line-height: 1.6; }

        .btn-logout {
            background: var(--primary);
            color: white;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            border: none;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-logout:hover {
            background: #0d1250;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 35, 126, 0.2);
        }

        .btn-contact {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            margin-top: 20px;
            display: inline-block;
        }
        .btn-contact:hover { text-decoration: underline; }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .suspendu-card { padding: 40px 25px; }
            h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="suspendu-card animate__animated animate__fadeInUp">
        <div class="icon-box">
            <i class="fa-solid fa-ban"></i>
        </div>

        <h2>Compte Suspendu</h2>
        
        <p class="mb-4">
            Bonjour <strong><?php echo htmlspecialchars($nomUser); ?></strong>,<br>
            Votre accès à la plateforme a été temporairement restreint par l'administration.
        </p>

        <div class="alert alert-warning border-0 bg-opacity-10 bg-warning text-warning small mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Cette mesure peut être due à un retard de cotisation ou une décision disciplinaire.
        </div>

        <div class="d-flex flex-column gap-3 align-items-center">
            <a href="mailto:admin@ndjangui.com" class="btn btn-outline-danger rounded-pill w-100 py-2 fw-bold">
                <i class="fa-solid fa-envelope me-2"></i> Contacter l'administrateur
            </a>

            <a href="?action=logout" class="btn-logout w-100">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Se déconnecter
            </a>
        </div>

        <div class="mt-4 pt-3 border-top">
            <p class="small m-0 text-muted">ID Membre : #<?php echo $_SESSION['user_id']; ?></p>
        </div>
    </div>

</body>
</html>