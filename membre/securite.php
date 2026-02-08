<?php
session_start();
date_default_timezone_set('Africa/Douala');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sécurité & Confiance | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --brand-green: #10b981;
            --brand-dark: #0f172a;
            --brand-shield: #0ea5e9;
            --bg-soft: #f8fafc;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--brand-dark);
            background-color: #fff;
            line-height: 1.8;
        }

        /* --- Navbar --- */
        .navbar-ndjangui {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 15px 0;
        }
        .logo-icon { color: var(--brand-green); }

        /* --- Hero Sécurité --- */
        .hero-security {
            padding: 180px 0 100px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero-security::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 50%; height: 100%;
            background: url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1000&q=80') center/cover;
            opacity: 0.1;
            mask-image: linear-gradient(to left, rgba(0,0,0,1), transparent);
        }

        /* --- Shield Card --- */
        .shield-card {
            border: none;
            border-radius: 40px;
            padding: 50px;
            background: #ffffff;
            box-shadow: 0 40px 100px rgba(0,0,0,0.05);
            transition: 0.4s;
        }
        .shield-card:hover { transform: translateY(-10px); }

        /* --- Icon Glow --- */
        .icon-box {
            width: 80px;
            height: 80px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 30px;
            background: #ecfdf5;
            color: var(--brand-green);
        }

        /* --- Escrow Section (Compte Séquestre) --- */
        .escrow-section {
            background: var(--bg-soft);
            border-radius: 60px;
            padding: 80px 0;
        }

        /* --- Protection Steps --- */
        .step-pill {
            background: white;
            padding: 20px 30px;
            border-radius: 25px;
            border-left: 5px solid var(--brand-green);
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }

        /* --- Images --- */
        .img-secure {
            border-radius: 50px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }

        /* --- Buttons --- */
        .btn-ndjangui { padding: 18px 45px; border-radius: 100px; font-weight: 700; transition: 0.4s; }
        .btn-green { background: var(--brand-green); color: white; border: none; }
        .btn-green:hover { background: #059669; color: white; transform: scale(1.05); }

        .text-gradient-blue {
            background: linear-gradient(90deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-ndjangui fixed-top">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center" href="concept.php">
                <i class="fa-solid fa-handshake-simple fs-2 me-2 logo-icon"></i>
                <span class="fw-bold fs-3 text-uppercase" style="letter-spacing: 1px;">NDJANGUI</span>
            </a>
            <div class="d-flex gap-2">
                <a href="login.php" class="btn btn-outline-dark btn-ndjangui py-2 px-4">Connexion</a>
                <a href="rejoindre.php" class="btn btn-green btn-ndjangui py-2 px-4">S'inscrire</a>
            </div>
        </div>
    </nav>

    <header class="hero-security">
        <div class="container position-relative" style="z-index: 2;">
            <div class="row align-items-center">
                <div class="col-lg-7" data-aos="fade-right">
                    <span class="badge bg-primary mb-3 px-3 py-2 rounded-pill">SÉCURITÉ BANCAIRE</span>
                    <h1 class="display-3 fw-bold mb-4">Votre confiance est notre <span class="text-success">priorité absolue.</span></h1>
                    <p class="lead opacity-75 fs-4 mb-5">Nous utilisons des protocoles de sécurité de niveau militaire pour garantir que chaque centime de votre tontine arrive à bon port.</p>
                    <div class="d-flex align-items-center gap-4 text-white-50">
                        <div><i class="fas fa-lock me-2"></i> SSL 256-bit</div>
                        <div><i class="fas fa-shield-check me-2"></i> Certifié KYC</div>
                        <div><i class="fas fa-server me-2"></i> Serveurs Redondants</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="py-5 mt-n5">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="shield-card h-100">
                        <div class="icon-box"><i class="fa-solid fa-fingerprint"></i></div>
                        <h4 class="fw-bold">Identité Vérifiée</h4>
                        <p class="text-muted">Fini les prête-noms. Chaque membre doit valider son identité (KYC) via une pièce officielle avant de rejoindre un cercle de tontine.</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="shield-card h-100">
                        <div class="icon-box" style="background: #eff6ff; color: #3b82f6;"><i class="fa-solid fa-vault"></i></div>
                        <h4 class="fw-bold">Compte Séquestre</h4>
                        <p class="text-muted">L'argent ne dort pas sur un compte personnel. Il est bloqué techniquement jusqu'au jour de la remise automatique au bénéficiaire.</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="shield-card h-100">
                        <div class="icon-box" style="background: #fff7ed; color: #f97316;"><i class="fa-solid fa-file-contract"></i></div>
                        <h4 class="fw-bold">Valeur Légale</h4>
                        <p class="text-muted">Chaque cycle génère un contrat numérique opposable. En cas de litige, vous disposez de preuves irréfutables et tracées.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <h2 class="display-5 fw-bold mb-4">Des transactions <span class="text-gradient-blue">inviolables</span></h2>
                    <p class="text-muted fs-5 mb-5">Nous collaborons avec les plus grands opérateurs de Mobile Money pour assurer une fluidité totale sans jamais compromettre vos données bancaires.</p>
                    
                    <div class="step-pill d-flex align-items-center" data-aos="fade-up" data-aos-delay="100">
                        <div class="me-4 fs-2 text-success"><i class="fa-solid fa-comment-sms"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Double Authentification (2FA)</h6>
                            <p class="small text-muted mb-0">Chaque retrait nécessite une confirmation par code unique envoyé sur votre numéro privé.</p>
                        </div>
                    </div>

                    <div class="step-pill d-flex align-items-center" data-aos="fade-up" data-aos-delay="200">
                        <div class="me-4 fs-2 text-primary"><i class="fa-solid fa-user-shield"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Protection Anti-Fraude</h6>
                            <p class="small text-muted mb-0">Nos algorithmes détectent les comportements suspects et bloquent les comptes en cas d'anomalie.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center" data-aos="zoom-in">
                    <img src="https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=800&q=80" class="img-fluid img-secure" alt="Security Shield">
                </div>
            </div>
        </div>
    </section>

    <section class="escrow-section mx-3 my-5">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">Zéro Fuite, Zéro Partage</h2>
                <p class="text-muted">Vos données personnelles sont cryptées et ne seront jamais vendues à des tiers.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-md-5" data-aos="fade-right">
                    <div class="p-4 bg-white rounded-5 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=600&q=80" class="img-fluid rounded-4 mb-4" alt="Data Protection">
                        <h5 class="fw-bold">Cryptage de bout en bout</h5>
                        <p class="small text-muted">Toutes vos communications et transactions sont chiffrées. Même nos administrateurs ne peuvent pas voir vos codes secrets.</p>
                    </div>
                </div>
                <div class="col-md-5" data-aos="fade-left">
                    <div class="p-4 bg-white rounded-5 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1454165833767-027ffea9e778?auto=format&fit=crop&w=600&q=80" class="img-fluid rounded-4 mb-4" alt="Audit">
                        <h5 class="fw-bold">Audits Réguliers</h5>
                        <p class="small text-muted">Nous soumettons notre plateforme à des tests d'intrusion par des experts en cybersécurité chaque trimestre.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="zoom-in">
                    <div class="p-5 bg-success rounded-5 text-white shadow-lg">
                        <h1 class="display-1 fw-bold mb-0">99.9%</h1>
                        <p class="fs-4">De transactions réussies</p>
                        <hr class="my-4 opacity-25">
                        <p class="opacity-75">Notre infrastructure est bâtie sur les services Cloud les plus fiables au monde, garantissant une disponibilité totale 24h/24 et 7j/7.</p>
                    </div>
                </div>
                <div class="col-lg-6 ps-lg-5" data-aos="fade-left">
                    <h2 class="display-6 fw-bold mb-4 mt-4 mt-lg-0">La solidarité sous contrôle</h2>
                    <p class="text-muted mb-4">Le système de "Ndjangui Score" agit comme un filtre de sécurité sociale. Les membres qui respectent leurs engagements voient leur plafond de tontine augmenter, créant une communauté d'élite financière.</p>
                    <ul class="list-unstyled">
                        <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Bannissement immédiat des fraudeurs</li>
                        <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Historique de paiement transparent</li>
                        <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Support client Priorité Secu 24/7</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 text-center">
        <div class="container py-5" data-aos="zoom-in">
            <div class="p-5 rounded-5 bg-dark text-white shadow-2xl">
                <h2 class="display-5 fw-bold mb-4">Prêt à épargner l'esprit tranquille ?</h2>
                <p class="mb-5 opacity-75">Rejoignez NDJANGUI aujourd'hui et faites l'expérience de la tontine sans stress.</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="rejoindre.php" class="btn btn-green btn-ndjangui btn-lg px-5">S'inscrire en toute sécurité</a>
                    <a href="concept.php" class="btn btn-outline-light btn-ndjangui btn-lg px-5">Retour au concept</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-5 border-top bg-white">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-md-6 text-center text-md-start">
                    <a class="navbar-brand d-flex align-items-center justify-content-center justify-content-md-start" href="#">
                        <i class="fa-solid fa-handshake-simple fs-4 me-2 logo-icon"></i>
                        <span class="fw-bold fs-5 text-uppercase">NDJANGUI</span>
                    </a>
                </div>
                <div class="col-md-6 text-center text-md-end text-muted small">
                    &copy; <?php echo date('Y'); ?> NDJANGUI - Sécurité Certifiée. <br>L'argent de la communauté, protégé par la technologie.
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });
    </script>
</body>
</html>