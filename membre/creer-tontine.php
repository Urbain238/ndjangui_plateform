<?php
session_start();
// Génération d'un code d'invitation unique par défaut
$default_code = "TON-" . strtoupper(substr(md5(uniqid()), 0, 6));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une Sous-Tontine | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root { --primary: #1a237e; --secondary: #00c853; --bg: #f8faff; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: #1e293b; }

        .step-container {
            background: white; border-radius: 28px; padding: 40px;
            box-shadow: 0 20px 50px rgba(26, 35, 126, 0.05); border: 1px solid rgba(0,0,0,0.02);
        }

        .form-label { font-weight: 700; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        
        /* Style des inputs avec icônes */
        .input-group-text {
            background-color: #e8eaf6; /* Bleu très clair */
            border: 2px solid #f1f5f9;
            border-right: none;
            color: var(--primary);
            border-radius: 14px 0 0 14px;
        }
        
        .form-control, .form-select {
            padding: 14px 18px; 
            border: 2px solid #f1f5f9;
            background-color: #f8fafc; 
            transition: 0.3s; 
            font-weight: 600;
        }
        
        /* Ajustement pour les inputs qui suivent une icône */
        .input-group .form-control, .input-group .form-select {
            border-radius: 0 14px 14px 0;
            border-left: none;
        }

        .form-control:focus, .form-select:focus { 
            border-color: var(--primary); 
            box-shadow: none;
            background: white; 
        }
        
        /* Quand on focus l'input, on change la couleur de l'icône aussi */
        .form-control:focus + .input-group-text, 
        .form-select:focus + .input-group-text,
        .input-group:focus-within .input-group-text {
            border-color: var(--primary);
            background-color: var(--primary);
            color: white;
        }

        /* Style des options de fréquence */
        .cycle-option {
            border: 2px solid #f1f5f9; border-radius: 18px; padding: 20px;
            cursor: pointer; transition: 0.3s; text-align: center; background: #f8fafc;
            position: relative; height: 100%;
        }
        .cycle-option:hover { border-color: var(--primary); transform: translateY(-3px); }
        .cycle-option.active { 
            border-color: var(--primary); background: #fff; 
            box-shadow: 0 10px 20px rgba(26, 35, 126, 0.08);
        }
        .cycle-option.active i { color: var(--primary) !important; }
        .cycle-option.active::after {
            content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; top: 12px; right: 12px; color: var(--primary); font-size: 1.2rem;
        }

        .btn-create {
            background: var(--primary); color: white; border-radius: 16px;
            padding: 20px; font-weight: 800; width: 100%; border: none;
            transition: 0.4s; margin-top: 20px; letter-spacing: 1px;
        }
        .btn-create:hover { background: #0d1250; transform: translateY(-3px); box-shadow: 0 15px 30px rgba(26, 35, 126, 0.2); }

        .section-title {
            font-size: 1.1rem; font-weight: 800; color: var(--primary);
            margin-bottom: 25px; display: flex; align-items: center; gap: 10px;
        }
        .section-title::after { content: ""; flex: 1; height: 1px; background: #eee; }

        #dynamic_fields {
            background: #f0f2ff; border-radius: 16px; padding: 25px;
            margin-bottom: 25px; border: 1px dashed var(--primary);
            display: none;
        }
    </style>
</head>
<body>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <a href="index.php" class="btn btn-link text-decoration-none text-muted fw-bold">
                        <i class="fa-solid fa-arrow-left me-2"></i> RETOUR
                    </a>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill">Mode Administrateur</span>
                </div>

                <div class="step-container">
                    <div class="text-center mb-5">
                        <div class="bg-primary text-white rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-lg" style="width: 70px; height: 70px;">
                            <i class="fa-solid fa-people-group fs-2"></i>
                        </div>
                        <h2 class="fw-800 text-primary">Créer une Tontine</h2>
                        <p class="text-muted">Définissez les règles et lancez votre cycle d'entraide</p>
                    </div>

                    <form action="tontine/process_cercle.php" method="POST" id="tontineForm">
                        
                        <div class="section-title"><i class="fa-solid fa-info-circle"></i> Identité du Cercle</div>
                        <div class="row g-4 mb-5">
                            <div class="col-md-7">
                                <label class="form-label">Nom de la Sous-Tontine</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-signature"></i></span>
                                    <input type="text" name="nom_cercle" class="form-control" placeholder="Ex: Grand Cercle des Affaires" required>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Type de Tontine</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-layer-group"></i></span>
                                    <select name="type_tontine" class="form-select">
                                        <option value="classique">Classique (Rotation)</option>
                                        <option value="projet">Spéciale Projet</option>
                                        <option value="sociale">Sociale / Secours</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="section-title"><i class="fa-solid fa-wallet"></i> Paramètres Financiers</div>
                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <label class="form-label">Mise Unitaire (Part)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-money-bill-wave"></i></span>
                                    <input type="number" name="montant_unitaire" class="form-control" placeholder="5000" required>
                                    <span class="input-group-text bg-white fw-bold border-start-0 text-dark">FCFA</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cotisation Standard</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-piggy-bank"></i></span>
                                    <input type="number" name="montant_cotisation_standard" class="form-control" placeholder="Frais de gestion">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Max Parts / Membre</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                    <input type="number" name="plafond_parts_membre" class="form-control" value="1" min="1">
                                </div>
                            </div>
                        </div>

                        <div class="section-title"><i class="fa-solid fa-calendar-alt"></i> Rythme des Tours</div>
                        <input type="hidden" name="frequence" id="frequence_val" value="mensuel">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="cycle-option" onclick="setFrequence('hebdomadaire', this)">
                                    <i class="fa-solid fa-calendar-day fs-3 mb-2 text-muted"></i>
                                    <div class="fw-bold">Hebdomadaire</div>
                                    <small class="text-muted d-block">Chaque semaine</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="cycle-option active" onclick="setFrequence('mensuel', this)">
                                    <i class="fa-solid fa-calendar-week fs-3 mb-2 text-muted"></i>
                                    <div class="fw-bold">Mensuel</div>
                                    <small class="text-muted d-block">Une fois par mois</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="cycle-option" onclick="setFrequence('libre', this)">
                                    <i class="fa-solid fa-calendar-check fs-3 mb-2 text-muted"></i>
                                    <div class="fw-bold">Libre</div>
                                    <small class="text-muted d-block">Dates personnalisées</small>
                                </div>
                            </div>
                        </div>

                        <div id="dynamic_fields">
                            </div>

                        <div class="section-title"><i class="fa-solid fa-gavel"></i> Fonctionnement</div>
                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <label class="form-label">Type de Tirage</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-dice"></i></span>
                                    <select name="type_tirage" class="form-select">
                                        <option value="aleatoire">Aléatoire (Auto)</option>
                                        <option value="manuel">Manuel (Bureau)</option>
                                        <option value="ordre_inscription">Ordre d'inscription</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nombre de Tours Max</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-rotate"></i></span>
                                    <input type="number" name="max_tours" class="form-control" value="12">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Code d'invitation</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white"><i class="fa-solid fa-key"></i></span>
                                    <input type="text" name="code_invitation" class="form-control fw-bold text-primary" value="<?php echo $default_code; ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-4 mb-4 border-start border-primary border-4">
                            <div class="d-flex gap-3 align-items-center text-primary">
                                <i class="fa-solid fa-shield-check fs-4"></i>
                                <small class="fw-bold">En tant que créateur, vous serez désigné <strong>Président</strong> et garant de la sécurité de ce cercle.</small>
                            </div>
                        </div>

                        <button type="submit" class="btn-create shadow">
                            <i class="fa-solid fa-rocket me-2"></i> LANCER LE CERCLE MAINTENANT
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * Gestion de la fréquence en temps réel (Avec Icones)
         */
        function setFrequence(type, element) {
            // UI : Activer la carte
            document.querySelectorAll('.cycle-option').forEach(opt => opt.classList.remove('active'));
            element.classList.add('active');

            // Valeur cachée
            document.getElementById('frequence_val').value = type;

            // Logique de champs dynamiques
            const dynamicDiv = document.getElementById('dynamic_fields');
            dynamicDiv.style.display = 'block';

            if (type === 'hebdomadaire') {
                dynamicDiv.innerHTML = `
                    <label class="form-label">Jour de collecte hebdomadaire</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-calendar-day"></i></span>
                        <select name="frequence_jours" class="form-select">
                            <option value="Monday">Lundi</option>
                            <option value="Tuesday">Mardi</option>
                            <option value="Wednesday">Mercredi</option>
                            <option value="Thursday">Jeudi</option>
                            <option value="Friday">Vendredi</option>
                            <option value="Saturday">Samedi</option>
                            <option value="Sunday">Dimanche</option>
                        </select>
                    </div>
                `;
            } else if (type === 'mensuel') {
                dynamicDiv.innerHTML = `
                    <label class="form-label">Jour du mois (1 à 31)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>
                        <input type="number" name="frequence_jours" class="form-control" min="1" max="31" placeholder="Ex: 5 (pour le 5 du mois)">
                    </div>
                `;
            } else if (type === 'libre') {
                dynamicDiv.innerHTML = `
                    <label class="form-label">Intervalle entre les tours (en jours)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-hourglass-half"></i></span>
                        <input type="number" name="intervalle_libre" class="form-control" placeholder="Ex: 15 (tous les 15 jours)">
                    </div>
                `;
            }
        }

        // Initialisation par défaut (Mensuel)
        window.onload = () => {
            const defaultOpt = document.querySelector('.cycle-option.active');
            setFrequence('mensuel', defaultOpt);
        };
    </script>
</body>
</html>