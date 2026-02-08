<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// --- LOGIQUE DE TRAITEMENT DU RETRAIT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demander_retrait'])) {
    $montant = floatval($_POST['montant']);
    $source = $_POST['source'];
    $disponible = floatval($_POST['max_disponible']);

    if ($montant > 0 && $montant <= $disponible) {
        try {
            $pdo->beginTransaction();

            if ($source === 'projet') {
                // MISE À JOUR : On SOUSTRAIT le montant du champ montant_verse
                $stmtUpd = $pdo->prepare("UPDATE projets 
                                          SET montant_verse = montant_verse - ? 
                                          WHERE membre_id = ? 
                                          AND statut = 'approuve' 
                                          AND montant_verse >= ? 
                                          LIMIT 1");
                $stmtUpd->execute([$montant, $user_id, $montant]);
                $statut_final = 'valide';
            } else {
                $statut_final = 'valide';
            }

            // Enregistrement dans l'historique
            $stmtReq = $pdo->prepare("INSERT INTO demandes_retrait (membre_id, montant, source_fond, statut) VALUES (?, ?, ?, ?)");
            $stmtReq->execute([$user_id, $montant, $source, $statut_final]);

            $pdo->commit();
            $success_msg = "Retrait de " . number_format($montant, 0, ',', ' ') . " FCFA validé.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Erreur : " . $e->getMessage();
        }
    } else {
        $error_msg = "Montant invalide ou solde insuffisant.";
    }
}

// --- LECTURE DES DONNÉES EN TEMPS RÉEL ---
try {
    // 1. Épargne nette
    $stmtE = $pdo->prepare("SELECT 
        (SELECT COALESCE(SUM(montant_paye), 0) FROM epargnes WHERE membre_id = ? AND statut = 'payé') - 
        (SELECT COALESCE(SUM(montant), 0) FROM demandes_retrait WHERE membre_id = ? AND source_fond = 'epargne' AND statut = 'valide')
    ");
    $stmtE->execute([$user_id, $user_id]);
    $solde_epargne = $stmtE->fetchColumn() ?: 0;

    // 2. FONDS PROJET
    $stmtP = $pdo->prepare("SELECT SUM(COALESCE(montant_verse, 0)) FROM projets WHERE membre_id = ? AND statut = 'approuve'");
    $stmtP->execute([$user_id]);
    $solde_projet = $stmtP->fetchColumn() ?: 0;

    // 3. Sanctions
    $stmtS = $pdo->prepare("SELECT SUM(montant) FROM sanctions WHERE membre_id = ? AND statut_paiement = 'non_payé'");
    $stmtS->execute([$user_id]);
    $total_sanctions = $stmtS->fetchColumn() ?: 0;

    // 4. Historique
    $stmtH = $pdo->prepare("SELECT * FROM demandes_retrait WHERE membre_id = ? ORDER BY date_demande DESC LIMIT 5");
    $stmtH->execute([$user_id]);
    $historique = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    $retrait_max_global = ($solde_epargne + $solde_projet) - $total_sanctions;

} catch (PDOException $e) {
    die("Erreur technique : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Guichet | NDJANGUI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --primary: #1a237e; --bg: #f8faff; --text: #1e293b; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text); overflow-x: hidden; }
        
        /* Bouton Retour */
        .btn-back {
            background: #fff;
            color: var(--primary);
            border-radius: 12px;
            padding: 8px 16px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(26, 35, 126, 0.1);
        }
        .btn-back:hover { background: var(--primary); color: #fff; transform: translateX(-3px); }

        .glass-card { background: #fff; border: none; border-radius: 24px; box-shadow: 0 10px 30px rgba(26, 35, 126, 0.04); height: 100%; }
        
        /* Couleurs conservées */
        .stat-card { border-radius: 20px; padding: 25px; color: #fff; position: relative; overflow: hidden; border: none; }
        .bg-gradient-blue { background: linear-gradient(135deg, #1a237e 0%, #311b92 100%); }
        .bg-gradient-green { background: linear-gradient(135deg, #00c853 0%, #1b5e20 100%); }
        .bg-gradient-red { background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); }
        
        /* Formulaire Modernisé */
        .form-label-custom { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 5px; }
        
        .input-group-custom {
            background: #f1f5f9;
            border-radius: 16px;
            padding: 2px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid #f1f5f9;
            transition: all 0.3s ease;
        }
        .input-group-custom:focus-within {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 8px 20px rgba(26, 35, 126, 0.08);
        }
        .input-group-custom i { color: var(--primary); font-size: 1.1rem; opacity: 0.7; }
        .input-group-custom select, .input-group-custom input {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 14px 0;
            font-weight: 700;
            width: 100%;
            color: var(--text);
            outline: none;
        }

        .btn-retrait { 
            background: var(--primary); 
            color: #fff; 
            border-radius: 16px; 
            padding: 16px; 
            font-weight: 800; 
            border: none; 
            width: 100%; 
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 15px rgba(26, 35, 126, 0.2);
        }
        .btn-retrait:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(26, 35, 126, 0.3); }

        /* Responsivité spécifique */
        @media (max-width: 768px) {
            .display-6 { font-size: 1.5rem; text-align: center; }
            .text-md-end { text-align: center !important; margin-top: 15px; }
            .stat-card { padding: 20px; }
            .container { padding-left: 15px; padding-right: 15px; }
            .btn-back { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container py-3 py-md-5">
    <a href="index.php" class="btn-back">
        <i class="fa-solid fa-chevron-left"></i> Retour au menu
    </a>

    <div class="row mb-4 mb-md-5 align-items-center">
        <div class="col-md-7">
            <h1 class="fw-800 display-6 mb-1">Guichet Express</h1>
            <p class="text-muted mb-0">Effectuez vos retraits en quelques secondes.</p>
        </div>
        <div class="col-md-5 text-md-end">
            <div class="p-3 glass-card d-inline-block border-start border-primary border-4 shadow-sm" style="min-width: 220px;">
                <small class="text-muted d-block fw-bold" style="font-size: 0.7rem;">SOLDE RETIRABLE</small>
                <span class="h3 fw-bold text-primary mb-0"><?php echo number_format($retrait_max_global, 0, ',', ' '); ?> F</span>
            </div>
        </div>
    </div>

    <?php if($success_msg): ?> <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4"><?php echo $success_msg; ?></div> <?php endif; ?>
    <?php if($error_msg): ?> <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4"><?php echo $error_msg; ?></div> <?php endif; ?>

    <div class="row g-3 g-md-4 mb-5">
        <div class="col-12 col-md-4">
            <div class="stat-card bg-gradient-blue shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <i class="fa-solid fa-piggy-bank fa-2x opacity-25"></i>
                    <span class="badge bg-white text-primary rounded-pill px-3 py-2">Épargne</span>
                </div>
                <h3 class="fw-bold mb-1"><?php echo number_format($solde_epargne, 0, ',', ' '); ?> F</h3>
                <small class="opacity-75">Disponible immédiatement</small>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card bg-gradient-green shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <i class="fa-solid fa-rocket fa-2x opacity-25"></i>
                    <span class="badge bg-white text-success rounded-pill px-3 py-2">Projets</span>
                </div>
                <h3 class="fw-bold mb-1"><?php echo number_format($solde_projet, 0, ',', ' '); ?> F</h3>
                <small class="opacity-75">Prêt au retrait</small>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card bg-gradient-red shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <i class="fa-solid fa-triangle-exclamation fa-2x opacity-25"></i>
                    <span class="badge bg-white text-danger rounded-pill px-3 py-2">Sanctions</span>
                </div>
                <h3 class="fw-bold mb-1"><?php echo number_format($total_sanctions, 0, ',', ' '); ?> F</h3>
                <small class="opacity-75">À déduire du total</small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="glass-card p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-4">
                        <i class="fa-solid fa-hand-holding-dollar text-primary fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Nouveau Retrait</h5>
                        <small class="text-muted">Choisissez votre source</small>
                    </div>
                </div>

                <form method="POST" id="formRetrait">
                    <input type="hidden" name="max_disponible" id="max_hidden" value="<?php echo $solde_epargne; ?>">
                    
                    <div class="mb-4">
                        <label class="form-label-custom">Compte source</label>
                        <div class="input-group-custom">
                            <i class="fa-solid fa-building-columns"></i>
                            <select name="source" id="sourceRetrait" required>
                                <option value="epargne" data-max="<?php echo $solde_epargne; ?>">Épargne (<?php echo $solde_epargne; ?> F)</option>
                                <option value="projet" data-max="<?php echo $solde_projet; ?>">Projet (<?php echo $solde_projet; ?> F)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label-custom">Montant du retrait</label>
                        <div class="input-group-custom">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <input type="number" name="montant" id="montantInput" placeholder="Saisir le montant" required>
                        </div>
                    </div>

                    <button type="button" onclick="confirmerRetrait()" class="btn btn-retrait">
                        VALIDER LE RETRAIT
                    </button>
                    <input type="hidden" name="demander_retrait" value="1">
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Derniers Retraits</h5>
                    <i class="fa-solid fa-clock-rotate-left text-muted opacity-50"></i>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="text-muted small">
                            <tr class="border-0">
                                <th class="border-0">DATE</th>
                                <th class="border-0">SOURCE</th>
                                <th class="border-0">MONTANT</th>
                                <th class="border-0 text-end">STATUT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($historique as $row): ?>
                            <tr>
                                <td class="small fw-bold text-muted"><?php echo date('d/m/y', strtotime($row['date_demande'])); ?></td>
                                <td><span class="badge bg-light text-primary rounded-pill px-3"><?php echo ucfirst($row['source_fond']); ?></span></td>
                                <td class="fw-800 text-dark"><?php echo number_format($row['montant'], 0, ',', ' '); ?> F</td>
                                <td class="text-end">
                                    <span class="text-success small fw-bold">
                                        <i class="fa-solid fa-circle-check"></i> Reçu
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($historique)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted small">Aucun mouvement récent</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const sourceSelect = document.getElementById('sourceRetrait');
    const montantInput = document.getElementById('montantInput');
    const maxHidden = document.getElementById('max_hidden');

    sourceSelect.addEventListener('change', function() {
        const max = this.options[this.selectedIndex].getAttribute('data-max');
        montantInput.max = max;
        maxHidden.value = max;
        montantInput.placeholder = "Max: " + max + " F";
    });

    function confirmerRetrait() {
        const montant = parseFloat(montantInput.value);
        const max = parseFloat(maxHidden.value);

        if(!montant || montant <= 0) {
            Swal.fire({
                title: 'Oups !',
                text: 'Veuillez saisir un montant valide.',
                icon: 'warning',
                confirmButtonColor: '#1a237e'
            });
            return;
        }

        if(montant > max) {
            Swal.fire({
                title: 'Solde insuffisant',
                text: 'Vous ne pouvez pas retirer plus que votre solde disponible.',
                icon: 'error',
                confirmButtonColor: '#d32f2f'
            });
            return;
        }

        Swal.fire({
            title: 'Confirmer le retrait ?',
            html: `Vous allez retirer <b>${montant.toLocaleString()} FCFA</b>.<br><small class="text-muted">L'argent sera déposé sur votre compte personnel.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1a237e',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Oui, je retire !',
            cancelButtonText: 'Annuler',
            reverseButtons: true,
            borderRadius: '16px'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formRetrait').submit();
            }
        });
    }
    
    window.onload = () => sourceSelect.dispatchEvent(new Event('change'));
</script>

</body>
</html>