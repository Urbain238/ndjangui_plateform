<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NDJANGUI | La Solidarité Digitale Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"> <style>
        :root {
            --primary: #1a237e;
            --primary-dark: #0d1250;
            --secondary: #2ecc71;
            --secondary-dark: #27ae60;
            --accent: #f39c12;
            --dark: #0a0f1d;
            --darker: #050810;
            --light: #f8f9fa;
            --chat-width: 380px;
            --chat-btn-size: 70px;
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #4a5568; 
            overflow-x: hidden; 
            background-color: #fdfdfd;
        }

        /* --- Navbar Améliorée --- */
        .navbar { 
            background: rgba(255, 255, 255, 0.9); 
            backdrop-filter: blur(15px); 
            z-index: 1000; 
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .nav-link { 
            position: relative; 
            color: var(--primary) !important;
            transition: 0.3s;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background-color: var(--secondary);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .nav-link:hover::after { width: 80%; }

        /* --- Hero Section Améliorée --- */
        .hero-section {
            padding: 140px 0 100px;
            background: rgb(245,247,250);
            background: linear-gradient(135deg, rgba(245,247,250,1) 0%, rgba(214,224,240,0.6) 100%);
            position: relative;
            overflow: hidden;
        }
        /* Cercle décoratif arrière plan */
        .hero-bg-circle {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(46,204,113,0.1) 0%, rgba(255,255,255,0) 70%);
            top: -100px;
            right: -100px;
            border-radius: 50%;
            z-index: 0;
        }

        .btn-primary-custom {
            background: var(--primary);
            background: linear-gradient(45deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 15px 35px;
            border-radius: 50px;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(26, 35, 126, 0.2);
        }
        .btn-primary-custom:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 30px rgba(26, 35, 126, 0.3); 
        }

        /* Image Hero avec éléments flottants */
        .hero-image-wrapper {
            position: relative;
            z-index: 1;
        }
        .floating-badge {
            position: absolute;
            background: white;
            padding: 15px 20px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: float 6s ease-in-out infinite;
            z-index: 2;
        }
        .badge-1 { top: 10%; left: -20px; animation-delay: 0s; }
        .badge-2 { bottom: 15%; right: -20px; animation-delay: 2s; }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }

        /* --- Stats Section (Nouveau) --- */
        .stats-section {
            background: white;
            margin-top: -50px;
            position: relative;
            z-index: 10;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            padding: 40px;
        }

        /* --- Features Section --- */
        .feature-card {
            border: 1px solid rgba(0,0,0,0.03);
            border-radius: 20px;
            padding: 40px 30px;
            transition: 0.4s;
            background: #fff;
            height: 100%;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 0;
            background: var(--primary);
            transition: 0.4s ease;
            z-index: -1;
            opacity: 0.03;
        }
        .feature-card:hover { 
            transform: translateY(-10px); 
            box-shadow: 0 20px 40px rgba(0,0,0,0.08); 
            border-color: transparent;
        }
        .feature-card:hover::before { height: 100%; }
        
        .icon-box {
            width: 80px;
            height: 80px;
            background: rgba(26, 35, 126, 0.05);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 24px;
            font-size: 32px;
            margin-bottom: 25px;
            transition: 0.3s;
        }
        .feature-card:hover .icon-box {
            background: var(--primary);
            color: white;
            transform: rotate(5deg);
        }

        /* --- Section "Comment ça marche" (Nouveau) --- */
        .step-number {
            font-size: 4rem;
            font-weight: 800;
            color: rgba(26, 35, 126, 0.05);
            line-height: 1;
            position: absolute;
            top: 0;
            right: 20px;
        }

        /* --- Footer Premium --- */
        footer { 
            background: var(--darker); 
            color: #fff; 
            padding: 100px 0 30px; 
            position: relative;
            overflow: hidden;
        }
        /* Ligne dégradée top footer */
        footer::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--primary));
        }
        .footer-bg-map {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/World_map_blank_black_lines_4500px_monochrome.png/1280px-World_map_blank_black_lines_4500px_monochrome.png'); /* Carte monde subtile */
            background-size: cover;
            opacity: 0.03;
            pointer-events: none;
        }
        .footer-heading {
            color: #fff;
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }
        .footer-link { 
            color: #8fa1b3; 
            text-decoration: none; 
            transition: 0.3s; 
            display: block;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        .footer-link:hover { 
            color: var(--secondary); 
            padding-left: 8px; 
        }
        .footer-newsletter-box {
            background: rgba(255,255,255,0.05);
            padding: 30px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .social-btn {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            color: white;
            transition: 0.3s;
            text-decoration: none;
        }
        .social-btn:hover {
            background: var(--secondary);
            transform: translateY(-3px);
            color: white;
        }

        /* --- Chat Widget Styles (Conservés et polis) --- */
        #chat-widget-container { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; flex-direction: column; align-items: flex-end; }
        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(46, 204, 113, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
        }
        #chat-button {
            width: var(--chat-btn-size); height: var(--chat-btn-size);
            background: linear-gradient(135deg, var(--secondary), #218c74);
            color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; cursor: pointer;
            box-shadow: 0 10px 25px rgba(39, 174, 96, 0.4);
            transition: all 0.4s; border: 4px solid white;
            animation: pulse-border 2s infinite;
        }
        #chat-button:hover { transform: scale(1.1); animation: none; }
        .chat-tooltip {
            position: absolute; right: 85px; top: 50%; transform: translateY(-50%);
            background: white; padding: 8px 15px; border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); font-size: 13px; font-weight: 700;
            color: var(--primary); white-space: nowrap; pointer-events: none;
        }
        .chat-tooltip::after {
            content: ''; position: absolute; right: -6px; top: 50%; transform: translateY(-50%);
            border-width: 6px; border-style: solid; border-color: transparent transparent transparent white;
        }
        #chat-window {
            position: absolute; bottom: 90px; right: 0; width: var(--chat-width); height: 550px;
            background: #fff; border-radius: 25px; box-shadow: 0 25px 80px rgba(0,0,0,0.3);
            display: none; flex-direction: column; overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05); transform-origin: bottom right;
        }
        .chat-header {
            background: linear-gradient(to right, var(--primary), #2c3e50); color: white;
            padding: 20px; display: flex; align-items: center; gap: 15px;
            border-bottom: 4px solid var(--secondary); flex-shrink: 0;
        }
        .bot-avatar-circle {
            width: 50px; height: 50px; background: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: var(--primary);
            font-size: 24px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); position: relative;
        }
        .bot-status-dot {
            position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px;
            background: var(--secondary); border: 2px solid white; border-radius: 50%;
        }
        #chat-messages {
            flex: 1; padding: 20px; overflow-y: auto; background: #fdfdfd;
            background-image: radial-gradient(#e0e0e0 1px, transparent 1px); background-size: 20px 20px;
            display: flex; flex-direction: column; gap: 15px;
        }
        .message {
            max-width: 85%; padding: 14px 18px; border-radius: 18px; font-size: 14.5px;
            line-height: 1.5; animation: fadeIn 0.3s ease; position: relative;
        }
        .message.bot { background: white; color: #333; align-self: flex-start; border-bottom-left-radius: 2px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .message.user { background: var(--primary); color: white; align-self: flex-end; border-bottom-right-radius: 2px; box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3); }
        .chat-input-area {
            padding: 15px; background: white; border-top: 1px solid #eee; display: flex; gap: 10px; align-items: center; flex-shrink: 0;
        }
        .chat-input-area input {
            flex: 1; border: 2px solid #f1f3f9; padding: 12px 20px; border-radius: 30px;
            outline: none; font-size: 15px; transition: 0.3s; background: #f9f9f9;
        }
        .chat-input-area input:focus { border-color: var(--primary); background: #fff; }
        .chat-input-area button {
            background: var(--primary); color: white; border: none; width: 48px; height: 48px;
            border-radius: 50%; cursor: pointer; transition: 0.3s; display: flex;
            align-items: center; justify-content: center; font-size: 18px;
        }
        
        @media (max-width: 991px) {
            .navbar-nav { padding-top: 20px; }
            .nav-item { margin-bottom: 10px; width: 100%; text-align: center; }
            .ms-lg-3, .ms-lg-2 { margin-left: 0 !important; }
            .dropdown-menu { text-align: center; border: 1px solid #eee !important; }
            .btn { width: 100%; margin-right: 0 !important; }
            .hero-section { padding-top: 100px; text-align: center; }
            .hero-image-wrapper { margin-top: 50px; }
            .stats-section { margin-top: 30px; }
        }
        @media (max-width: 576px) {
            #chat-widget-container { bottom: 20px; right: 20px; }
            #chat-window { position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; max-height: 100vh; border-radius: 0; z-index: 10000; margin: 0; }
            .chat-header { padding-top: 50px; border-radius: 0; }
            #chat-button { width: 60px; height: 60px; font-size: 26px; }
            .chat-tooltip { display: none; }
        }
    </style>
</head>
<body>

   <nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 45px; height: 45px; color: var(--primary);">
                <i class="fa-solid fa-handshake-simple fs-4"></i>
            </div>
            <span class="fw-bold fs-3 text-uppercase" style="letter-spacing: 1px; color: var(--primary);">NDJANGUI<span style="color:var(--secondary)">.</span></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                
                <li class="nav-item">
                    <a class="nav-link px-3 fw-semibold" href="membre/concept.php">
                        <i class="fa-solid fa-lightbulb me-2"></i>Concept
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link px-3 fw-semibold" href="membre/securite.php">
                        <i class="fa-solid fa-lock me-2"></i>Sécurité
                    </a>
                </li>

                <li class="nav-item dropdown ms-lg-3">
                    <a class="btn btn-outline-primary rounded-pill px-4 me-2 fw-bold dropdown-toggle d-inline-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Connexion
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-3 p-2 rounded-4 animate__animated animate__fadeInUp">
                        <li><a class="dropdown-item rounded-3 py-2" href="membre/login.php"><i class="fa-solid fa-user me-2 text-primary"></i> Espace Membre</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-3 py-2" href="admin/admin_login.php"><i class="fa-solid fa-shield-halved me-2 text-danger"></i> Administration</a></li>
                    </ul>
                </li>

                <li class="nav-item ms-lg-2">
                    <a class="btn btn-primary-custom text-white shadow d-inline-flex align-items-center" href="membre/rejoindre.php">
                        <i class="fa-solid fa-user-plus me-2"></i>REJOINDRE
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>

    <header class="hero-section">
        <div class="hero-bg-circle"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0 animate__animated animate__fadeInLeft">
                    <div class="d-inline-block px-3 py-1 rounded-pill bg-white border border-secondary text-secondary fw-bold small mb-4 shadow-sm">
                        <i class="fa-solid fa-star me-1"></i> Plateforme Tontine N°1 au Cameroun
                    </div>
                    <h1 class="display-3 fw-bold mb-4" style="color: var(--primary); line-height: 1.1;">
                        La tontine <span style="position:relative; z-index:1; color: var(--secondary);">solidaire <svg style="position:absolute; bottom:5px; left:0; width:100%; height:10px; z-index:-1;" viewBox="0 0 100 10" preserveAspectRatio="none"><path d="M0 5 Q 50 10 100 5" stroke="#2ecc71" stroke-width="3" fill="none" opacity="0.3"/></svg></span><br>& connectée.
                    </h1>
                    <p class="lead mb-5 pe-lg-5" style="color: #636e72;">
                        Fini les carnets perdus et les déplacements risqués. Digitalisez votre cercle de confiance avec <strong>NDJANGUI</strong>. Sécurisez vos cotisations et réalisez vos projets.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="membre/login.php" class="btn btn-primary-custom text-white btn-lg d-flex align-items-center">
                            <span>COTISER MAINTENANT</span>
                            <i class="fa-solid fa-arrow-right ms-2 bg-white text-primary rounded-circle p-1" style="font-size: 12px;"></i>
                        </a>
                        <a href="membre/concept.php" class="btn btn-white bg-white shadow-sm text-dark btn-lg rounded-pill px-4 fw-bold d-flex align-items-center border">
                            <i class="fa-regular fa-circle-play me-2 text-secondary fs-4"></i> Comment ça marche ?
                        </a>
                    </div>
                    <div class="mt-5 d-flex align-items-center gap-3">
                        <div class="d-flex">
                            <img src="https://randomuser.me/api/portraits/women/44.jpg" class="rounded-circle border border-2 border-white" width="40" alt="">
                            <img src="https://randomuser.me/api/portraits/men/32.jpg" class="rounded-circle border border-2 border-white" width="40" alt="" style="margin-left: -15px;">
                            <img src="https://randomuser.me/api/portraits/women/65.jpg" class="rounded-circle border border-2 border-white" width="40" alt="" style="margin-left: -15px;">
                            <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center border border-2 border-white" style="width:40px; height:40px; margin-left: -15px; font-size:12px;">+2k</div>
                        </div>
                        <p class="mb-0 small fw-bold text-muted">Membres actifs nous font confiance.</p>
                    </div>
                </div>
                <div class="col-lg-6 animate__animated animate__fadeInRight">
                    <div class="hero-image-wrapper">
                        <div class="floating-badge badge-1">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <div>
                                <div class="small text-muted">Paiement Reçu</div>
                                <div class="fw-bold text-dark">50,000 FCFA</div>
                            </div>
                        </div>
                        <div class="floating-badge badge-2">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">100% Sécurisé</div>
                                <div class="small text-muted">Cryptage SSL</div>
                            </div>
                        </div>

                        <img src="https://img.freepik.com/fotos-premium/maos-de-pessoas-de-negocios-e-sucesso-da-visao-superior-da-motivacao-da-celebracao-da-equipe-e-apoio-na-reuniao-de-confianca-ou-colaboracao-trabalhadores-da-diversidade-maos-juntas-e-metas-de-inicializacao-visao-e-parceria_590464-129988.jpg" 
                             class="img-fluid rounded-5 shadow-lg" 
                             alt="Collaboration NDJANGUI"
                             style="object-fit: cover; height: 500px; width: 100%; border: 8px solid rgba(255,255,255,0.8);">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="stats-section d-flex flex-wrap justify-content-between text-center align-items-center">
                    <div class="p-3 w-100 w-md-auto">
                        <h2 class="fw-bold text-primary mb-0 display-6">2,500+</h2>
                        <p class="text-muted small mb-0 text-uppercase fw-bold ls-1">Membres</p>
                    </div>
                    <div class="d-none d-md-block" style="width: 1px; height: 50px; background: #eee;"></div>
                    <div class="p-3 w-100 w-md-auto">
                        <h2 class="fw-bold text-secondary mb-0 display-6">150M+</h2>
                        <p class="text-muted small mb-0 text-uppercase fw-bold ls-1">FCFA Cotisés</p>
                    </div>
                    <div class="d-none d-md-block" style="width: 1px; height: 50px; background: #eee;"></div>
                    <div class="p-3 w-100 w-md-auto">
                        <h2 class="fw-bold text-primary mb-0 display-6">300+</h2>
                        <p class="text-muted small mb-0 text-uppercase fw-bold ls-1">Cercles Actifs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="text-secondary fw-bold text-uppercase small ls-2">Pourquoi nous choisir ?</span>
                <h2 class="fw-bold display-5 mt-2">Le digital au service de l'entraide</h2>
                <p class="text-muted lead mx-auto" style="max-width: 600px;">Une plateforme conçue pour la transparence totale et la croissance collective de votre communauté.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card shadow-sm">
                        <div class="icon-box shadow-sm"><i class="fa-solid fa-fingerprint"></i></div>
                        <h4 class="fw-bold mb-3">Identité Certifiée</h4>
                        <p class="text-muted">Chaque membre est rigoureusement vérifié via son compte Mobile Money (KYC) et parrainé par le cercle pour une confiance absolue.</p>
                        <a href="#" class="text-decoration-none fw-bold small text-primary mt-2 d-inline-block">En savoir plus <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card shadow-sm">
                        <div class="icon-box shadow-sm"><i class="fa-solid fa-money-bill-transfer"></i></div>
                        <h4 class="fw-bold mb-3">Paiements Automatisés</h4>
                        <p class="text-muted">Collecte automatique via <strong>MoMo & OM</strong>. Fini les calculs manuels, les erreurs de caisse et les risques liés au transport d'espèces.</p>
                        <a href="#" class="text-decoration-none fw-bold small text-primary mt-2 d-inline-block">Voir les partenaires <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card shadow-sm">
                        <div class="icon-box shadow-sm"><i class="fa-solid fa-chart-line"></i></div>
                        <h4 class="fw-bold mb-3">Crédit Équitable</h4>
                        <p class="text-muted">Un système de <strong>Credit Scoring</strong> innovant basé sur votre historique de cotisations pour accéder aux prêts sans paperasse administrative.</p>
                        <a href="#" class="text-decoration-none fw-bold small text-primary mt-2 d-inline-block">Simuler un prêt <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light position-relative">
        <div class="container py-4">
            <div class="row align-items-center mb-5">
                <div class="col-lg-6">
                    <h2 class="fw-bold display-6">Simple comme bonjour.</h2>
                    <p class="text-muted">Commencez votre expérience tontinière en 3 étapes.</p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <a href="membre/register.php" class="btn btn-outline-primary rounded-pill px-4">Créer un compte</a>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="bg-white p-4 rounded-4 shadow-sm h-100 position-relative border-bottom border-4 border-primary">
                        <span class="step-number">01</span>
                        <h4 class="fw-bold mt-3">Rejoindre</h4>
                        <p class="text-muted small">Créez votre profil et intégrez un cercle existant ou créez votre propre groupe de tontine.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-white p-4 rounded-4 shadow-sm h-100 position-relative border-bottom border-4 border-secondary">
                        <span class="step-number">02</span>
                        <h4 class="fw-bold mt-3">Cotiser</h4>
                        <p class="text-muted small">Recevez une notification et validez votre cotisation via Mobile Money en 1 clic.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-white p-4 rounded-4 shadow-sm h-100 position-relative border-bottom border-4 border-warning">
                        <span class="step-number">03</span>
                        <h4 class="fw-bold mt-3">Bénéficier</h4>
                        <p class="text-muted small">Recevez votre lot directement dans votre portefeuille électronique le jour J.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 position-relative overflow-hidden" style="background: var(--primary);">
        <div style="position: absolute; top:0; left:0; width:100%; height:100%; background: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
        <div class="container text-center py-5 position-relative z-index-1">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold text-white mb-4 display-5">Prêt à moderniser votre épargne ?</h2>
                    <p class="text-white-50 lead mb-5">Rejoignez des milliers de Camerounais qui font confiance à NDJANGUI pour sécuriser leur avenir financier.</p>
                    
                    <div class="bg-white rounded-4 p-2 d-inline-flex align-items-center shadow-lg mx-auto" style="max-width: 500px; width: 100%;">
                        <div class="input-group">
                            <input type="text" class="form-control border-0 ps-3" placeholder="Entrez votre numéro de téléphone...">
                            <button class="btn btn-secondary rounded-pill px-4 fw-bold m-1">Commencer</button>
                        </div>
                    </div>
                    <p class="small text-white-50 mt-3"><i class="fa-solid fa-lock me-1"></i> Vos données sont cryptées et confidentielles.</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-bg-map"></div>
        <div class="container position-relative z-index-1">
            <div class="row g-5">
                <div class="col-lg-4">
                    <a class="d-flex align-items-center mb-4 text-decoration-none" href="#">
                        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px; color: var(--primary);">
                            <i class="fa-solid fa-handshake-simple fs-5"></i>
                        </div>
                        <span class="fw-bold fs-4 text-white letter-spacing-1">NDJANGUI</span>
                    </a>
                    <p class="text-secondary small fw-bold mb-3">LA SOLIDARITÉ DIGITALE</p>
                    <p class="text-white-50 mb-4 pe-lg-4" style="line-height: 1.8; font-size: 0.9rem;">
                        Nous réinventons la tontine traditionnelle africaine en la rendant plus sûre, transparente et accessible à tous grâce à la technologie.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="social-btn"><i class="fa-brands fa-twitter"></i></a>
                        <a href="#" class="social-btn"><i class="fa-brands fa-linkedin-in"></i></a>
                        <a href="#" class="social-btn"><i class="fa-brands fa-whatsapp"></i></a>
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h5 class="footer-heading">Plateforme</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="footer-link">Accueil</a></li>
                        <li><a href="membre/concept.php" class="footer-link">Comment ça marche</a></li>
                        <li><a href="membre/securite.php" class="footer-link">Sécurité & Confiance</a></li>
                        <li><a href="#" class="footer-link">Tarifs & Frais</a></li>
                        <li><a href="#" class="footer-link">Application Mobile</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h5 class="footer-heading">Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="footer-link">Centre d'aide</a></li>
                        <li><a href="#" class="footer-link">FAQ</a></li>
                        <li><a href="#" class="footer-link">Contactez-nous</a></li>
                        <li><a href="#" class="footer-link">Mentions Légales</a></li>
                        <li><a href="#" class="footer-link">Politique de confidentialité</a></li>
                    </ul>
                </div>

                <div class="col-lg-4">
                    <div class="footer-newsletter-box mb-4">
                        <h5 class="fw-bold mb-2 fs-6">Restez informé</h5>
                        <p class="small text-white-50 mb-3">Recevez nos conseils financiers.</p>
                        <div class="input-group">
                            <input type="email" class="form-control bg-dark border-secondary text-white small" placeholder="Votre email" style="border-right: none;">
                            <button class="btn btn-secondary border-secondary" type="button"><i class="fa-solid fa-paper-plane"></i></button>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold text-white small text-uppercase mb-3">Moyens de paiement acceptés</h6>
                    <div class="d-flex flex-wrap gap-2">
                         <div class="bg-white rounded px-2 py-1 d-flex align-items-center" style="height: 35px;">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/9/93/Mtn-logo.ce068660.png" style="height: 20px;" alt="MTN">
                        </div>
                        <div class="bg-white rounded px-2 py-1 d-flex align-items-center" style="height: 35px;">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c8/Orange_logo.svg" style="height: 20px;" alt="Orange">
                        </div>
                        <div class="bg-white rounded px-2 py-1 d-flex align-items-center" style="height: 35px;">
                            <i class="fa-brands fa-cc-visa text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="mt-5 mb-4" style="border-color: rgba(255,255,255,0.05);">
            
            <div class="row align-items-center small text-white-50">
                <div class="col-md-6 text-center text-md-start">
                    &copy; 2026 <strong>NDJANGUI Technologies</strong>. Tous droits réservés.<br>
                    Agrément Fintech CEMAC n° 234/Fintech/CAM.
                </div>
                <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                    <span class="d-inline-flex align-items-center justify-content-center px-3 py-1 rounded-pill bg-dark border border-secondary text-secondary">
                        <i class="fa-solid fa-server me-2"></i> Système Sécurisé
                    </span>
                    <span class="ms-3">Made with <i class="fa-solid fa-heart text-danger"></i> in Yaoundé</span>
                </div>
            </div>
        </div>
    </footer>

    <div id="chat-widget-container">
        <div class="chat-tooltip animate__animated animate__fadeIn">Besoin d'aide ? 💬</div>
        <div id="chat-button" onclick="toggleChat()">
            <i class="fa-solid fa-comment-dollar"></i> 
        </div>
        <div id="chat-window" class="animate__animated">
            <div class="chat-header">
                <div class="bot-avatar-circle">
                    <i class="fa-solid fa-headset"></i>
                    <div class="bot-status-dot"></div>
                </div>
                <div>
                    <div class="fw-bold" style="font-size: 16px;">Assistant NDJANGUI</div>
                    <div class="d-flex align-items-center gap-1">
                        <small style="font-size: 12px; opacity: 0.9; font-weight: 300;">En ligne 24/7</small>
                    </div>
                </div>
                <div class="ms-auto" onclick="toggleChat()" style="cursor:pointer; padding: 10px;">
                    <i class="fa-solid fa-chevron-down" style="font-size: 18px;"></i>
                </div>
            </div>
            <div id="chat-messages">
                <div class="message bot">
                    👋 Bonjour ! Je suis l'assistant <strong>NDJANGUI</strong>.<br>
                    Je peux vous aider à :<br>
                    💰 Comprendre le système de tontine<br>
                    🔐 Sécuriser vos transactions<br>
                    📝 Vous inscrire<br><br>
                    Quelle est votre question ?
                </div>
            </div>
            <div id="typing-indicator" class="typing-indicator px-4 pb-2 small text-muted" style="display:none; font-style: italic;">
                <i class="fa-solid fa-circle-notch fa-spin me-2 text-success"></i>L'assistant écrit...
            </div>
            <div class="chat-input-area">
                <input type="text" id="user-input" placeholder="Posez votre question ici..." onkeypress="handleKeyPress(event)">
                <button onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialisation des animations au scroll
        AOS.init({
            duration: 800,
            once: true
        });

        // Chat Script (Ton code original)
        const API_KEY = "AIzaSyCbQ7OesbUB73MGqXM_KFnXAtg_rXMv1yg"; // Ta clé API
        const chatWindow = document.getElementById('chat-window');
        const chatMessages = document.getElementById('chat-messages');
        const userInput = document.getElementById('user-input');
        const typingIndicator = document.getElementById('typing-indicator');
        const chatTooltip = document.querySelector('.chat-tooltip');
        const chatButton = document.getElementById('chat-button');
        const body = document.body;

        function toggleChat() {
            const isMobile = window.innerWidth <= 576;
            if (chatWindow.style.display === 'none' || chatWindow.style.display === '') {
                chatWindow.style.display = 'flex';
                chatWindow.classList.remove('animate__zoomOut');
                if (isMobile) {
                    chatWindow.classList.add('animate__fadeIn');
                    body.style.overflow = 'hidden';
                } else {
                    chatWindow.classList.add('animate__zoomIn');
                }
                userInput.focus();
                chatTooltip.style.opacity = '0';
                chatButton.style.display = 'none';
            } else {
                if (isMobile) {
                    chatWindow.classList.remove('animate__fadeIn');
                    chatWindow.classList.add('animate__fadeOut');
                } else {
                    chatWindow.classList.remove('animate__zoomIn');
                    chatWindow.classList.add('animate__zoomOut');
                }
                setTimeout(() => { 
                    chatWindow.style.display = 'none'; 
                    chatWindow.classList.remove('animate__fadeOut', 'animate__zoomOut');
                    chatButton.style.display = 'flex';
                    body.style.overflow = '';
                }, 300);
            }
        }

        function handleKeyPress(e) { if (e.key === 'Enter') sendMessage(); }

        async function sendMessage() {
            const message = userInput.value.trim();
            if (!message) return;
            appendMessage(message, 'user');
            userInput.value = '';
            typingIndicator.style.display = 'block';
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            try {
                // Appel API Gemini (inchangé)
                const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        contents: [{ parts: [{ text: `Tu es l'assistant virtuel expert de NDJANGUI... (prompt original) Question : ${message}` }] }]
                    })
                });
                const data = await response.json();
                if (data.error) throw new Error(data.error.message);
                if (data.candidates && data.candidates[0].content.parts[0].text) {
                    appendMessage(data.candidates[0].content.parts[0].text, 'bot');
                }
            } catch (error) {
                console.error("Erreur:", error);
                appendMessage("⚠️ Erreur de connexion.", 'bot');
            } finally {
                typingIndicator.style.display = 'none';
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        function appendMessage(text, side) {
            const div = document.createElement('div');
            div.className = `message ${side}`;
            div.innerHTML = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
</body>
</html>