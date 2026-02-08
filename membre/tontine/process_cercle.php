<?php
session_start();

// 1. CONNEXION BASE DE DONNEES
// ATTENTION : Ajustez le chemin (../../) selon où se trouve votre dossier config par rapport à ce fichier
require_once '../../config/database.php'; 

// 2. Sécurité : Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Si pas connecté, on renvoie au login
    header("Location: ../../login.php");
    exit;
}

// 3. Vérifier si le formulaire a été soumis via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Connexion PDO
    $pdo = Database::getConnection();
    $user_id = $_SESSION['user_id'];

    // --- RECUPERATION ET NETTOYAGE DES DONNEES ---
    
    $nom_cercle = htmlspecialchars(trim($_POST['nom_cercle']));
    $type_tontine = $_POST['type_tontine'];
    
    // Conversion des montants en nombres
    $montant_unitaire = floatval($_POST['montant_unitaire']);
    $montant_standard = floatval($_POST['montant_cotisation_standard']);
    $plafond = intval($_POST['plafond_parts_membre']);
    
    // Logique de Fréquence (Le fameux tri sélectif)
    $frequence = $_POST['frequence']; // 'hebdomadaire', 'mensuel' ou 'libre'
    
    $jour_collecte = null; // Pour Hebdo ou Mensuel
    $intervalle = null;    // Pour Libre

    if ($frequence === 'libre') {
        $intervalle = intval($_POST['intervalle_libre']);
    } else {
        // Si c'est Hebdo (ex: "Monday") ou Mensuel (ex: "5"), on stocke ça dans jour_collecte
        $jour_collecte = $_POST['frequence_jours'];
    }

    $type_tirage = $_POST['type_tirage'];
    $max_tours = intval($_POST['max_tours']);
    
    // Code d'invitation (Sécurité anti-vide)
    $code_invitation = $_POST['code_invitation'];
    if(empty($code_invitation)) {
        $code_invitation = "TON-" . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    // --- INSERTION EN BASE DE DONNEES ---

    try {
        $pdo->beginTransaction();

        // Requête SQL préparée
        $sql = "INSERT INTO cercles (
            nom_cercle, 
            type_tontine, 
            montant_unitaire, 
            montant_cotisation_standard,
            plafond_parts_membre,
            frequence, 
            frequence_jours, 
            intervalle_libre,
            type_tirage,
            max_tours, 
            code_invitation, 
            president_id, 
            statut, 
            date_creation
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', NOW()
        )";

        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $nom_cercle,
            $type_tontine,
            $montant_unitaire,
            $montant_standard,
            $plafond,
            $frequence,
            $jour_collecte,    // NULL si c'est 'libre'
            $intervalle,       // NULL si c'est 'hebdo' ou 'mensuel'
            $type_tirage,
            $max_tours,
            $code_invitation,
            $user_id           // L'utilisateur connecté devient président
        ]);

        // On valide la transaction
        $pdo->commit();

        // 4. SUCCES : Redirection vers le tableau de bord avec message
        // On remonte d'un dossier (..) pour revenir dans 'membre/'
        header("Location: ../index.php?success=cercle_created&code=" . $code_invitation);
        exit;

    } catch (PDOException $e) {
        // En cas d'erreur, on annule tout
        $pdo->rollBack();
        
        // On renvoie vers le formulaire avec l'erreur
        $error = urlencode("Erreur technique : " . $e->getMessage());
        header("Location: ../creer-tontine.php?error=" . $error);
        exit;
    }

} else {
    // Si quelqu'un essaie d'ouvrir le fichier directement sans passer par le formulaire
    header("Location: ../creer-tontine.php");
    exit;
}
?>