<?php
// Démarrage automatique de la session sur toutes les pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Nettoyage des données (Anti-XSS)
function cleanInput($data) {
    if (is_null($data)) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// 2. Réponse JSON standardisée
function jsonResponse($success, $message, $redirect = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect_url' => $redirect
    ]);
    exit;
}

// 3. Vérification de connexion
function isLogged() {
    return isset($_SESSION['user_id']);
}

// 4. Vérification Admin (Rôles 1, 2, 3, 4 sont considérés bureau/admin)
function isAdmin() {
    return isLogged() && in_array($_SESSION['user_role'], [1, 2, 3, 4]);
}

// 5. Middleware de protection : Admin
function requireAdmin() {
    if (!isLogged()) {
        header('Location: ../index.php');
        exit;
    }
    if (!isAdmin()) {
        header('Location: ../membre/index.php'); // Redirige les curieux vers leur espace
        exit;
    }
}

// 6. Middleware de protection : Membre
function requireMember() {
    if (!isLogged()) {
        header('Location: ../index.php');
        exit;
    }
}
?>