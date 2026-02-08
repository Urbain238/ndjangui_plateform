<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rejoindre | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #0f172a;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.6);
            --blur: 40px;
            --accent-gradient: linear-gradient(135deg, #4f46e5 0%, #ec4899 100%);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* --- BACKGROUND ANIMÉ (Orbes) --- */
        .ambient-light {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            overflow: hidden;
            background: #f8fafc;
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: float 10s infinite ease-in-out;
        }
        .orb-1 { width: 300px; height: 300px; background: #60a5fa; top: -50px; left: -50px; animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; background: #c084fc; bottom: -100px; right: -50px; animation-delay: -5s; }
        .orb-3 { width: 200px; height: 200px; background: #f472b6; top: 40%; left: 40%; animation-duration: 15s; opacity: 0.4; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        /* --- CONTAINER PRINCIPAL --- */
        .main-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            perspective: 1000px;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            -webkit-backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 40px 30px;
            box-shadow: var(--shadow-xl), inset 0 0 0 1px rgba(255,255,255,0.5);
            text-align: center;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.4s ease, height 0.4s ease;
        }

        /* Header Style */
        .icon-halo {
            width: 80px; height: 80px;
            background: white;
            border-radius: 24px;
            display: grid;
            place-items: center;
            font-size: 2rem;
            margin: 0 auto 20px;
            color: #4f46e5;
            box-shadow: 0 10px 30px -10px rgba(79, 70, 229, 0.3);
            position: relative;
            z-index: 2;
        }
        .icon-halo::after {
            content: ''; position: absolute; inset: -5px;
            background: var(--accent-gradient);
            border-radius: 28px; z-index: -1;
            opacity: 0.3; filter: blur(10px);
        }

        h2 { font-weight: 800; color: var(--primary); margin-bottom: 8px; letter-spacing: -0.5px; }
        p.desc { color: #64748b; font-size: 0.95rem; margin-bottom: 30px; line-height: 1.5; }

        /* --- INPUT HERO --- */
        .input-hero-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .code-input {
            width: 100%;
            height: 72px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            font-family: 'Space Mono', monospace; /* Police monospace pour le code */
            font-size: 1.6rem;
            text-align: center;
            color: var(--primary);
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        
        .code-input::placeholder { color: #cbd5e1; font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: 0; font-size: 1rem; font-weight: 500; }
        
        .code-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 6px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
        }

        /* Animation de chargement (Barre de scan) */
        .scan-line {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, transparent, rgba(99, 102, 241, 0.2), transparent);
            transform: translateY(-100%);
            pointer-events: none;
            display: none;
            z-index: 10;
        }
        .scanning .scan-line { display: block; animation: scan 1.5s infinite linear; }
        @keyframes scan { 0% { transform: translateY(-100%); } 100% { transform: translateY(100%); } }

        /* --- TICKET WALLET STYLE --- */
        .wallet-pass {
            display: none;
            margin-top: 30px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: left;
            position: relative;
            border: 1px solid rgba(0,0,0,0.05);
            animation: slideUpFade 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUpFade { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }

        /* Effet découpe (Punch hole) */
        .pass-divider {
            height: 20px;
            background: transparent;
            position: relative;
            margin: 0 10px;
        }
        .pass-divider::before, .pass-divider::after {
            content: ''; position: absolute; top: 50%; width: 24px; height: 24px;
            background: var(--glass-bg); backdrop-filter: blur(var(--blur)); /* Match background */
            border-radius: 50%; transform: translateY(-50%);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .pass-divider::before { left: -22px; }
        .pass-divider::after { right: -22px; }
        .pass-line {
            position: absolute; top: 50%; left: 15px; right: 15px;
            border-top: 2px dashed #e2e8f0;
        }

        .pass-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pass-body { padding: 25px 20px 20px; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item label { display: block; font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-item div { font-weight: 700; color: var(--primary); font-size: 0.95rem; }

        .btn-join {
            width: 100%;
            padding: 16px;
            background: var(--accent-gradient);
            color: white;
            font-weight: 700;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 10px 20px -5px rgba(236, 72, 153, 0.4);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .btn-join:hover { box-shadow: 0 15px 30px -5px rgba(236, 72, 153, 0.5); transform: translateY(-2px); }
        .btn-join::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }
        .btn-join:hover::after { left: 100%; }

        /* Footer Link */
        .footer-link {
            margin-top: 25px;
            display: inline-block;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: color 0.2s;
        }
        .footer-link:hover { color: var(--primary); }

        /* Error Toast */
        .error-toast {
            background: #fee2e2; color: #ef4444;
            padding: 12px; border-radius: 12px; font-size: 0.9rem; font-weight: 600;
            margin-top: 15px; display: none;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

<div class="ambient-light">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
</div>

<div class="main-wrapper">
    <div class="glass-card animate__animated animate__fadeInUp">
        
        <div class="icon-halo animate__animated animate__bounceIn delay-1s">
            <i class="fa-solid fa-ticket-simple"></i>
        </div>

        <h2>Rejoindre une Tontine</h2>
        <p class="desc">Vous avez reçu un code d'invitation ?<br>Saisissez-le ci-dessous pour accéder à votre espace.</p>

        <div class="input-hero-container" id="inputContainer">
            <div class="scan-line"></div>
            <input type="text" id="code" class="code-input" maxlength="10" placeholder="Entrez le code" autocomplete="off">
        </div>

        <div id="errorMsg" class="error-toast animate__animated animate__headShake"></div>

        <div id="preview" class="wallet-pass">
            <div class="pass-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check text-white"></i>
                    <span class="fw-bold small" style="letter-spacing: 1px;">CODE VALIDE</span>
                </div>
                <div class="badge bg-white bg-opacity-25 rounded-pill px-2 py-1 font-monospace" style="font-size: 0.7rem;" id="resCode">#CODE</div>
            </div>

            <div class="pass-divider">
                <div class="pass-line"></div>
            </div>

            <div class="pass-body">
                <h4 class="fw-800 text-dark mb-4" id="resName">Nom du Groupe</h4>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label>Contribution</label>
                        <div class="text-primary"><span id="resAmount">0</span> FCFA</div>
                    </div>
                    <div class="info-item">
                        <label>Fréquence</label>
                        <div class="text-dark" id="resFreq">Mensuel</div>
                    </div>
                    <div class="info-item" style="grid-column: span 2;">
                        <label>Président</label>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 24px; height: 24px; background: #e2e8f0; border-radius: 50%; display: grid; place-items: center; font-size: 0.7rem;"><i class="fa-solid fa-user"></i></div>
                            <span id="resPres">Nom Prénom</span>
                        </div>
                    </div>
                </div>

                <form action="process_join.php" method="POST">
                    <input type="hidden" name="cercle_id" id="valId">
                    <button class="btn-join">
                        Confirmer l'adhésion <i class="fa-solid fa-arrow-right-long ms-2"></i>
                    </button>
                </form>
            </div>
        </div>

        <a href="mes-tontines.php" class="footer-link">
            <i class="fa-solid fa-arrow-left me-1"></i> Retour au tableau de bord
        </a>

    </div>
</div>

<script>
    const input = document.getElementById('code');
    const container = document.getElementById('inputContainer');
    const preview = document.getElementById('preview');
    const errorMsg = document.getElementById('errorMsg');

    input.addEventListener('input', (e) => {
        // Auto-formatting: Uppercase + Remove spaces
        let val = e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
        e.target.value = val;

        // Reset UI if deleted
        if(val.length < 10) {
            preview.style.display = 'none';
            errorMsg.style.display = 'none';
            container.classList.remove('scanning');
            return;
        }

        // --- TRIGGER ANIMATION & FETCH ---
        if(val.length === 10) {
            input.blur(); // Hide keyboard to see animation
            input.disabled = true;
            container.classList.add('scanning'); // Start scan effect
            errorMsg.style.display = 'none';

            // Simulate a tiny delay for the "Scan" effect (UX)
            setTimeout(() => {
                fetch('ajax_verif_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code: val })
                })
                .then(r => r.json())
                .then(data => {
                    container.classList.remove('scanning');
                    input.disabled = false;
                    
                    if(data.success) {
                        // Populate Data
                        document.getElementById('resName').innerText = data.cercle.nom;
                        document.getElementById('resAmount').innerText = new Intl.NumberFormat('fr-FR').format(data.cercle.montant);
                        document.getElementById('resFreq').innerText = data.cercle.frequence.charAt(0).toUpperCase() + data.cercle.frequence.slice(1);
                        document.getElementById('resPres').innerText = data.cercle.president;
                        document.getElementById('resCode').innerText = val;
                        document.getElementById('valId').value = data.cercle.id;
                        
                        // Show Wallet Pass
                        preview.style.display = 'block';
                        
                        // Optional: Small vibration on mobile
                        if(navigator.vibrate) navigator.vibrate(50);
                    } else {
                        preview.style.display = 'none';
                        errorMsg.innerHTML = `<i class="fa-solid fa-circle-xmark me-2"></i> ${data.message}`;
                        errorMsg.style.display = 'block';
                        input.focus();
                        if(navigator.vibrate) navigator.vibrate([50, 50, 50]); // Error vibe
                    }
                })
                .catch((err) => {
                    console.error(err);
                    container.classList.remove('scanning');
                    input.disabled = false;
                    errorMsg.innerText = "Erreur de connexion.";
                    errorMsg.style.display = 'block';
                });
            }, 800); // 800ms artificial delay for the "Cool Factor"
        }
    });
</script>
</body>
</html>