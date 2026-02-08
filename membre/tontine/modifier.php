<?php
session_start();
date_default_timezone_set('Africa/Douala');

// --- 1. CONNEXION BDD ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) { die("Erreur critique."); }

// --- 2. RÉCUPÉRATION & AUTO-RÉPARATION ---
$user_id = (int)($_SESSION['user_id'] ?? 27);
$cercle_id = (int)($_REQUEST['cercle_id'] ?? 3);

// Récupération infos cercle
$stmt_c = $pdo->prepare("SELECT * FROM cercles WHERE id = ?");
$stmt_c->execute([$cercle_id]);
$cercle = $stmt_c->fetch();

// Récupération ou création de l'inscription
$stmt_i = $pdo->prepare("SELECT * FROM inscriptions_cercle WHERE membre_id = ? AND cercle_id = ?");
$stmt_i->execute([$user_id, $cercle_id]);
$inscription = $stmt_i->fetch();

if (!$inscription) {
    $ins = $pdo->prepare("INSERT INTO inscriptions_cercle (cercle_id, membre_id, nombre_parts, statut, date_inscription) VALUES (?, ?, 1, 'actif', NOW())");
    $ins->execute([$cercle_id, $user_id]);
    $nb_parts = 1;
} else {
    $nb_parts = $inscription['nombre_parts'];
}

$prix_part = $cercle['montant_unitaire'] ?? 0;
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_parts'])) {
    $nouveau_nb = (int)$_POST['nombre_parts'];
    if ($nouveau_nb > 0) {
        $upd = $pdo->prepare("UPDATE inscriptions_cercle SET nombre_parts = ? WHERE membre_id = ? AND cercle_id = ?");
        $upd->execute([$nouveau_nb, $user_id, $cercle_id]);
        $nb_parts = $nouveau_nb;
        $message = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuster ma part | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --glass: rgba(255, 255, 255, 0.95);
            --primary: #0f172a;
            --accent: #2563eb;
            --soft-bg: #f8fafc;
        }

        body {
            background: radial-gradient(circle at top left, #e2e8f0 0%, #f8fafc 50%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }

        .main-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 35px;
            padding: 2.5rem;
            width: 100%; max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255,255,255,0.3);
            position: relative; overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .circle-decoration {
            position: absolute; top: -50px; right: -50px;
            width: 150px; height: 150px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, transparent 100%);
            border-radius: 50%; z-index: 0;
        }

        .icon-header {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white; border-radius: 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin: 0 auto 1.5rem;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
        }

        .summary-box {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .total-amount {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -1px;
            display: block;
        }

        .input-group-parts {
            background: #f1f5f9;
            border-radius: 20px;
            padding: 10px;
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            border: 2px solid transparent;
            transition: 0.3s;
        }

        .input-group-parts:focus-within {
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .control-btn {
            width: 50px; height: 50px;
            border-radius: 15px; border: none;
            background: white; color: var(--primary);
            font-weight: bold; font-size: 1.2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: 0.2s;
        }

        .control-btn:active { transform: scale(0.9); }

        .parts-input {
            border: none; background: transparent;
            text-align: center; font-size: 1.8rem;
            font-weight: 800; width: 80px;
            color: var(--primary); outline: none;
        }

        .btn-confirm {
            background: var(--primary);
            color: white; border: none;
            width: 100%; padding: 18px;
            border-radius: 20px; font-weight: 700;
            font-size: 1.1rem; transition: 0.3s;
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.2);
        }

        .btn-confirm:hover { transform: translateY(-3px); box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.2); }

        .success-toast {
            background: #ecfdf5; color: #059669;
            padding: 12px; border-radius: 15px;
            font-size: 0.9rem; font-weight: 600;
            margin-bottom: 1.5rem; text-align: center;
            border: 1px solid #d1fae5;
        }
    </style>
</head>
<body>

<div class="main-card">
    <div class="circle-decoration"></div>
    
    <div class="icon-header">
        <i class="fa-solid fa-wallet"></i>
    </div>

    <div class="text-center mb-4" style="position: relative; z-index: 1;">
        <h3 class="fw-800 mb-1" style="letter-spacing: -0.5px;">Ajuster ma part</h3>
        <p class="text-muted small fw-500">Cercle : <span class="text-primary fw-bold"><?= htmlspecialchars($cercle['nom_cercle']) ?></span></p>
    </div>

    <?php if($message === "success"): ?>
        <div class="success-toast">
            <i class="fa-solid fa-circle-check me-2"></i> Modification enregistrée
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="cercle_id" value="<?= $cercle_id ?>">

        <div class="summary-box text-center">
            <span class="text-muted small fw-600 text-uppercase" style="letter-spacing: 1px;">Montant Total</span>
            <span class="total-amount mt-2"><span id="totalDisplay"><?= number_format($prix_part * $nb_parts, 0, ',', ' ') ?></span> <small class="text-muted" style="font-size: 0.4em;">FCFA</small></span>
            <div class="mt-2 py-1 px-3 bg-light rounded-pill d-inline-block">
                <span class="small text-muted">Prix/part : <strong><?= number_format($prix_part, 0, ',', ' ') ?></strong></span>
            </div>
        </div>

        <label class="form-label small fw-800 text-muted ms-2 mb-2">NOMBRE DE PARTS</label>
        <div class="input-group-parts">
            <button type="button" class="control-btn" onclick="step(-1)"><i class="fa-solid fa-minus"></i></button>
            <input type="number" name="nombre_parts" id="partsInput" class="parts-input" value="<?= $nb_parts ?>" min="1" readonly>
            <button type="button" class="control-btn" onclick="step(1)"><i class="fa-solid fa-plus"></i></button>
        </div>

        <button type="submit" name="update_parts" class="btn-confirm">
            Confirmer le changement
        </button>

        <div class="text-center mt-4">
            <a href="admin-seances.php?cercle_id=<?= $cercle_id ?>" class="text-muted small text-decoration-none fw-700">
                <i class="fa-solid fa-arrow-left-long me-2"></i> Annuler
            </a>
        </div>
    </form>
</div>

<script>
    const input = document.getElementById('partsInput');
    const display = document.getElementById('totalDisplay');
    const prixUnitaire = <?= (int)$prix_part ?>;

    function step(val) {
        let current = parseInt(input.value);
        if (current + val >= 1) {
            input.value = current + val;
            updateTotal();
        }
    }

    function updateTotal() {
        let total = parseInt(input.value) * prixUnitaire;
        display.innerText = new Intl.NumberFormat('fr-FR').format(total);
    }
</script>

</body>
</html>