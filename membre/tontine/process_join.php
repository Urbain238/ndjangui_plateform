<?php
// Fichier : membre/tontine/process_join.php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_POST['cercle_id'])) {
    
    $user_id = $_SESSION['user_id'];
    $cercle_id = intval($_POST['cercle_id']);
    $pdo = Database::getConnection();

    try {
        $pdo->beginTransaction();

        // 1. Vérifier si l'utilisateur est déjà inscrit
        $checkSql = "SELECT id FROM inscriptions_cercle WHERE membre_id = ? AND cercle_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$user_id, $cercle_id]);
        
        if ($checkStmt->rowCount() > 0) {
            $pdo->rollBack();
            // Redirection si déjà membre
            header("Location: mes-tontines.php?error=already_member");
            exit;
        }

        // 2. Récupérer le nom de la tontine (cercles.nom_cercle)
        $nom_tontine = "la tontine";
        $stmtName = $pdo->prepare("SELECT nom_cercle FROM cercles WHERE id = ?");
        $stmtName->execute([$cercle_id]);
        $res = $stmtName->fetch();
        if ($res && !empty($res['nom_cercle'])) {
            $nom_tontine = $res['nom_cercle'];
        }

        // 3. Insertion dans inscriptions_cercle
        $sql = "INSERT INTO inscriptions_cercle (
                    cercle_id, 
                    membre_id, 
                    nombre_parts, 
                    date_inscription, 
                    statut, 
                    notification_vue
                ) VALUES (
                    ?, ?, 1, NOW(), 'actif', 0
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cercle_id, $user_id]);

        // 4. Création de la notification (Table notifications)
        // Vérifiez que vos colonnes correspondent bien (membre_id vs user_id, etc.)
        $msg_notif = "Félicitations ! Vous avez rejoint " . $nom_tontine;
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (membre_id, message, type, reference_id, date_creation, statut) 
                                    VALUES (?, ?, 'message_cercle', ?, NOW(), 'non_lu')");
        $stmtNotif->execute([$user_id, $msg_notif, $cercle_id]);

        $pdo->commit();

        header("Location: mes-tontines.php?success=joined");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        // En prod, évitez die(), faites plutôt une redirection avec erreur
        die("Erreur lors de l'inscription : " . $e->getMessage());
    }
} else {
    header("Location: rejoindre-tontine.php");
    exit;
}
?>