<?php
session_start();
session_unset();
session_destroy();

// Supprimer le cookie de session si nécessaire
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirection vers la page de login de l'admin
header("Location: /ndjangui_plateform/index.php");
exit();
exit;
?>