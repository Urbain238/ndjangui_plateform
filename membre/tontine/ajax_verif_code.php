<?php
// Fichier : membre/tontine/ajax_verif_code.php
require_once '../../config/database.php';
session_start();

// Indispensable pour que le JavaScript comprenne la réponse
header('Content-Type: application/json');

// Désactiver l'affichage des erreurs HTML pour ne pas corrompre le JSON
ini_set('display_errors', 0); 

// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée. Rechargez la page.']);
    exit;
}

// 1. Récupération des données JSON
$input = json_decode(file_get_contents('php://input'), true);
$code = isset($input['code']) ? trim($input['code']) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Code vide.']);
    exit;
}

$pdo = Database::getConnection();

try {
    // 2. Requête corrigée avec 'nom_complet'
    $sql = "SELECT 
                c.id, 
                c.nom_cercle, 
                c.montant_unitaire, 
                c.frequence, 
                c.president_id, 
                m.nom_complet  -- C'est ici que ça bloquait avant
            FROM cercles c 
            LEFT JOIN membres m ON c.president_id = m.id 
            WHERE c.code_invitation = ? 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$code]);
    $cercle = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cercle) {
        // 3. Vérification si l'utilisateur est déjà dans le cercle
        // Note: Assurez-vous que la table 'inscriptions_cercle' existe bien
        $chk = $pdo->prepare("SELECT id FROM inscriptions_cercle WHERE cercle_id = ? AND membre_id = ?");
        $chk->execute([$cercle['id'], $_SESSION['user_id']]);
        
        if($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Vous êtes déjà membre de cette tontine.']);
            exit;
        }

        // 4. Préparation des données pour l'affichage
        $nomPresident = !empty($cercle['nom_complet']) ? $cercle['nom_complet'] : 'Inconnu';

        echo json_encode([
            'success' => true,
            'cercle' => [
                'id' => $cercle['id'],
                'nom' => $cercle['nom_cercle'],
                'montant' => number_format($cercle['montant_unitaire'], 0, ',', ' '),
                'frequence' => ucfirst($cercle['frequence']),
                'president' => htmlspecialchars($nomPresident)
            ]
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Ce code ne correspond à aucune tontine.']);
    }

} catch (PDOException $e) {
    // En cas d'erreur SQL, on renvoie le message technique pour le débogage
    echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
}
?>