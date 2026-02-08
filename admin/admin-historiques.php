<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // Si nous sommes déjà dans le dossier "admin", on appelle juste le fichier voisin
    header("Location: admin_login.php"); 
    exit(); 
}
// La vérification du rôle (role_id) a été supprimée ici.

require_once '../config/database.php'; 

try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// --- KPI ---
$stmt = $pdo->query("SELECT SUM(montant_paye) FROM cotisations WHERE statut='paye'");
$total_cotisations = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT SUM(montant_paye) FROM epargnes WHERE statut='paye'");
$total_epargnes = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT SUM(montant_accorde) FROM prets WHERE statut_pret='accorde'");
$total_prets = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT SUM(montant) FROM sanctions WHERE statut_paiement='paye'");
$total_sanctions = $stmt->fetchColumn() ?: 0;

$solde_global = ($total_cotisations + $total_epargnes + $total_sanctions) - $total_prets;
$pie_data = [$total_cotisations, $total_epargnes, $total_sanctions];

// --- GRAPHIQUE ---
$sql_chart = "
    SELECT DATE_FORMAT(date_paiement, '%Y-%m') as mois, SUM(montant_paye) as total
    FROM cotisations 
    WHERE statut='paye' AND date_paiement >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mois
    ORDER BY mois ASC
";
$stmt_chart = $pdo->query($sql_chart);
$history_rows = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

$months = [];
$amounts = [];
for ($i = 5; $i >= 0; $i--) {
    $k = date('Y-m', strtotime("-$i months"));
    $months[$k] = 0; 
}
foreach ($history_rows as $row) {
    if(isset($months[$row['mois']])) {
        $months[$row['mois']] = $row['total'];
    }
}
$chart_labels = array_keys($months);
$chart_values = array_values($months);

// --- FILTRES ET LISTE ---
$filter_date_debut = $_GET['date_debut'] ?? '';
$filter_date_fin = $_GET['date_fin'] ?? '';
$filter_type = $_GET['type'] ?? '';

$sql_base = "
    SELECT * FROM (
        (SELECT 'cotisation' as type, montant_paye as montant, date_paiement as date_action, 'Paiement Tontine' as label, membre_id FROM cotisations WHERE montant_paye > 0)
        UNION ALL
        (SELECT 'epargne' as type, montant_paye as montant, date_paiement as date_action, 'Dépôt Épargne' as label, membre_id FROM epargnes WHERE montant_paye > 0)
        UNION ALL
        (SELECT 'pret' as type, montant_accorde as montant, date_demande as date_action, 'Prêt Accordé' as label, membre_id FROM prets WHERE statut_pret = 'accorde')
        UNION ALL
        (SELECT 'sanction' as type, montant as montant, date_sanction as date_action, 'Sanction Appliquée' as label, membre_id FROM sanctions)
    ) AS global_flux
    WHERE 1=1
";

$params = [];

if (!empty($filter_date_debut)) {
    $sql_base .= " AND date_action >= :date_debut";
    $params[':date_debut'] = $filter_date_debut . " 00:00:00";
}
if (!empty($filter_date_fin)) {
    $sql_base .= " AND date_action <= :date_fin";
    $params[':date_fin'] = $filter_date_fin . " 23:59:59";
}
if (!empty($filter_type) && $filter_type !== 'all') {
    $sql_base .= " AND type = :type";
    $params[':type'] = $filter_type;
}

$sql_base .= " ORDER BY date_action DESC LIMIT 100"; 

$stmt_flux = $pdo->prepare($sql_base);
$stmt_flux->execute($params);
$flux_items = $stmt_flux->fetchAll(PDO::FETCH_ASSOC);

$member_ids = array_unique(array_column($flux_items, 'membre_id'));
$membres_map = [];
if (!empty($member_ids)) {
    $ids_str = implode(',', $member_ids);
    // Note: Idéalement utiliser une requête préparée avec IN (id1, id2...) pour la sécurité, 
    // mais ici on garde la logique de votre code existant.
    $stmt_m = $pdo->query("SELECT id, nom_complet FROM membres WHERE id IN ($ids_str)");
    while ($m = $stmt_m->fetch(PDO::FETCH_ASSOC)) {
        $membres_map[$m['id']] = $m['nom_complet'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique Financier | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    <style>
        :root {
            --primary: #0f392b; /* Vert émeraude */
            --accent: #d4af37;  /* Or */
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        body {
            background-color: var(--bg-light);
            font-family: 'Outfit', sans-serif;
            color: var(--text-dark);
        }
        .page-header {
            background: linear-gradient(135deg, #022c22 0%, #14532d 100%);
            padding: 40px 0 80px;
            color: white;
            position: relative;
        }
        .kpi-container {
            margin-top: -50px;
        }
        .kpi-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: none;
            transition: transform 0.3s;
            height: 100%;
        }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Playfair Display', serif;
        }
        .kpi-label {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            height: 100%;
        }
        .history-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        .table-custom thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            border: none;
            padding: 15px;
        }
        .table-custom tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        .badge-type {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .type-cotisation { background: rgba(16, 185, 129, 0.1); color: #059669; } /* Vert */
        .type-epargne { background: rgba(59, 130, 246, 0.1); color: #2563eb; } /* Bleu */
        .type-pret { background: rgba(245, 158, 11, 0.1); color: #d97706; } /* Orange */
        .type-sanction { background: rgba(239, 68, 68, 0.1); color: #dc2626; } /* Rouge */
        .form-control-sm, .form-select-sm {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold font-serif mb-1">Tableau de Bord Financier</h2>
                    <p class="opacity-75 mb-0">Vue d'ensemble des flux, cotisations et performances.</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-warning rounded-pill px-4">
                        <i class="fa-solid fa-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="container pb-5">
        <div class="row g-4 kpi-container mb-5">
            <div class="col-md-3">
                <div class="kpi-card border-bottom border-4 border-success">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="kpi-value"><?php echo number_format($solde_global, 0, ',', ' '); ?> FCFA</div>
                    <div class="kpi-label">Trésorerie Actuelle</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <div class="kpi-value"><?php echo number_format($total_cotisations, 0, ',', ' '); ?> FCFA</div>
                    <div class="kpi-label">Total Cotisations</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-hand-holding-hand"></i>
                    </div>
                    <div class="kpi-value"><?php echo number_format($total_prets, 0, ',', ' '); ?> FCFA</div>
                    <div class="kpi-label">Volume Prêts Accordés</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-gavel"></i>
                    </div>
                    <div class="kpi-value"><?php echo number_format($total_sanctions, 0, ',', ' '); ?> FCFA</div>
                    <div class="kpi-label">Sanctions Recouvrées</div>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold text-dark mb-0">Flux Cotisations (6 mois)</h5>
                        <span class="badge bg-light text-dark border">Mensuel</span>
                    </div>
                    <canvas id="fluxChart" height="120"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="fw-bold text-dark mb-4">Répartition Entrées</h5>
                    <div style="position: relative; height: 250px;">
                        <canvas id="repartitionChart"></canvas>
                    </div>
                    <div class="mt-3 text-center small text-muted">
                        Comparaison Cotisations vs Épargnes vs Sanctions
                    </div>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm rounded-4 mb-3 p-3 bg-white">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_debut); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_fin); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="all">Tout voir</option>
                        <option value="cotisation" <?php echo ($filter_type == 'cotisation') ? 'selected' : ''; ?>>Cotisation</option>
                        <option value="epargne" <?php echo ($filter_type == 'epargne') ? 'selected' : ''; ?>>Épargne</option>
                        <option value="pret" <?php echo ($filter_type == 'pret') ? 'selected' : ''; ?>>Prêt</option>
                        <option value="sanction" <?php echo ($filter_type == 'sanction') ? 'selected' : ''; ?>>Sanction</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrer</button>
                        <a href="admin-historiques.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        <div class="history-card">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 font-serif"><i class="fa-solid fa-list-ul me-2 text-warning"></i>Dernières Transactions</h5>
                <button onclick="downloadPDF()" class="btn btn-sm btn-outline-danger rounded-pill">
                    <i class="fa-solid fa-file-pdf me-1"></i> Exporter PDF
                </button>
            </div>
            <div class="table-responsive">
                <table id="transactionsTable" class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Membre</th>
                            <th>Type Transaction</th>
                            <th>Détail</th>
                            <th class="text-end pe-4">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($flux_items as $item): 
                            // Détermination du style selon le type
                            $badgeClass = 'type-cotisation';
                            $icon = 'fa-arrow-up';
                            $signe = '+';
                            $colorAmount = 'text-success';
                            $typeLabel = strtoupper($item['type']);

                            if($item['type'] == 'epargne') {
                                $badgeClass = 'type-epargne';
                            } elseif($item['type'] == 'pret') {
                                $badgeClass = 'type-pret';
                                $icon = 'fa-arrow-right'; 
                                $signe = '-';
                                $colorAmount = 'text-muted'; 
                            } elseif($item['type'] == 'sanction') {
                                $badgeClass = 'type-sanction';
                            }
                            $nom_membre = isset($membres_map[$item['membre_id']]) ? $membres_map[$item['membre_id']] : 'Membre inconnu';
                        ?>
                        <tr>
                            <td class="ps-4 text-muted small" data-date="<?php echo $item['date_action']; ?>">
                                <?php echo date('d/m/Y', strtotime($item['date_action'])); ?>
                                <br>
                                <span class="text-xs opacity-50"><?php echo date('H:i', strtotime($item['date_action'])); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($nom_membre); ?></div>
                            </td>
                            <td>
                                <span class="badge-type <?php echo $badgeClass; ?>">
                                    <?php echo $typeLabel; ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?php echo $item['label']; ?>
                            </td>
                            <td class="text-end pe-4 fw-bold <?php echo $colorAmount; ?>">
                                <?php echo $signe . ' ' . number_format($item['montant'], 0, ',', ' '); ?> FCFA
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($flux_items)): ?>
                            <tr><td colspan="5" class="text-center py-4">Aucune transaction trouvée pour ces filtres.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        const ctxFlux = document.getElementById('fluxChart').getContext('2d');
        
        // Gradient pour le remplissage
        let gradient = ctxFlux.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(15, 57, 43, 0.2)'); 
        gradient.addColorStop(1, 'rgba(15, 57, 43, 0)');
        new Chart(ctxFlux, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>, 
                datasets: [{
                    label: 'Montant Cotisé',
                    data: <?php echo json_encode($chart_values); ?>, 
                    borderColor: '#0f392b', 
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#d4af37', 
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4 
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4], color: '#e2e8f0' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
        const ctxPie = document.getElementById('repartitionChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: ['Cotisations', 'Épargnes', 'Sanctions'],
                datasets: [{
                    data: <?php echo json_encode($pie_data); ?>,
                    backgroundColor: [
                        '#0f392b', 
                        '#3b82f6', 
                        '#ef4444'  
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%', 
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8 }
                    }
                }
            }
        });
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(18);
            doc.text("Rapport Financier - Ndjangui", 14, 22);
            doc.setFontSize(11);
            doc.setTextColor(100);
            let today = new Date().toLocaleDateString();
            doc.text("Généré le : " + today, 14, 30);
            doc.autoTable({ 
                html: '#transactionsTable',
                startY: 40,
                theme: 'striped',
                headStyles: { fillColor: [15, 57, 43] }, // Couleur Vert ndjangui
                styles: { fontSize: 9 },
                columnStyles: {
                    4: { halign: 'right', fontStyle: 'bold' } // Colonne Montant alignée à droite
                },
                didParseCell: function (data) {
                    // Nettoyage du texte (enlève les retours à la ligne des dates pour le PDF)
                    if (data.column.index === 0 && data.cell.section === 'body') {
                        let text = data.cell.raw.innerText || data.cell.text[0];
                        // Garde juste la date propre
                        data.cell.text = text.replace(/\s+/g, ' ').trim().substring(0, 10);
                    }
                }
            });

            doc.save('rapport_financier.pdf');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>