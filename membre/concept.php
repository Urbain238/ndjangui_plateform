<?php
session_start();
// Exemple de titre de page pour le SEO
$pageTitle = "Notre Concept | NDJANGUI";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary: #10b981;       /* Vert Émeraude (Confiance) */
            --primary-dark: #059669;
            --primary-light: #d1fae5;
            --secondary: #0f172a;     /* Bleu Nuit (Sérieux) */
            --text-dark: #1e293b;
            --text-grey: #64748b;
            --bg-light: #f8fafc;      /* Fond très clair */
            --white: #ffffff;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.8;
            overflow-x: hidden;
        }

        /* --- NAVBAR STYLE (Version Claire) --- */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            padding: 15px 0;
        }
        .nav-link {
            color: var(--text-dark);
            font-weight: 600;
            transition: 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--primary);
        }

        /* --- HERO SECTION --- */
        .concept-hero {
            padding: 140px 0 100px;
            background: linear-gradient(180deg, #fff 0%, #f0fdf4 100%);
            position: relative;
        }
        .hero-icon-bg {
            position: absolute;
            right: -5%;
            top: 20%;
            font-size: 25rem;
            color: var(--primary);
            opacity: 0.03;
            transform: rotate(-15deg);
            z-index: 0;
        }

        /* --- CARDS & BOXES --- */
        .feature-box {
            background: var(--white);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.03);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid rgba(0,0,0,0.02);
            height: 100%;
        }
        .feature-box:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(16, 185, 129, 0.1);
            border-color: var(--primary-light);
        }
        
        .icon-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 25px;
            transition: 0.3s;
        }
        .feature-box:hover .icon-circle {
            background: var(--primary);
            color: white;
            transform: scale(1.1) rotate(10deg);
        }

        /* --- COMPARISON TABLE --- */
        .comparison-section {
            background: var(--white);
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .comp-header {
            background: var(--secondary);
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .comp-row {
            display: flex;
            align-items: center;
            padding: 25px 20px;
            border-bottom: 1px solid #eee;
        }
        .comp-row:last-child { border-bottom: none; }
        .comp-icon { font-size: 1.5rem; width: 40px; text-align: center; }
        
        /* --- PROCESS STEPS --- */
        .step-number {
            font-size: 4rem;
            font-weight: 800;
            color: rgba(16, 185, 129, 0.1);
            line-height: 1;
            position: absolute;
            top: 20px;
            right: 20px;
        }

        /* --- TEXT HIGHLIGHTS --- */
        .text-gradient {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* --- CTA --- */
        .cta-box {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 30px;
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .concept-hero { padding-top: 100px; text-align: center; }
            .hero-img-container { margin-top: 40px; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 45px; height: 45px; color: white;">
                    <i class="fa-solid fa-handshake-simple fs-4"></i>
                </div>
                <span class="fw-bold fs-3 text-uppercase" style="letter-spacing: 1px; color: var(--secondary);">NDJANGUI<span style="color:var(--primary)">.</span></span>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link px-3 active" href="#">Concept</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="securite.php">Sécurité</a></li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-dark rounded-pill px-4" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-primary text-white rounded-pill px-4 shadow-sm" href="rejoindre.php">Rejoindre</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="concept-hero overflow-hidden">
        <i class="fa-solid fa-handshake-simple hero-icon-bg"></i>

        <div class="container position-relative z-1">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <span class="badge bg-green-soft text-success border border-success border-opacity-25 rounded-pill px-3 py-2 mb-3 fw-bold" style="background: var(--primary-light); color: var(--primary-dark);">
                        <i class="fa-solid fa-lightbulb me-2"></i> L'idée derrière Ndjangui
                    </span>
                    <h1 class="display-4 fw-bolder mb-4 text-dark">
                        La solidarité africaine, <br>
                        <span class="text-gradient">la puissance technologique.</span>
                    </h1>
                    <p class="lead text-secondary mb-4">
                        Nous n'avons pas inventé la tontine, nous l'avons <strong>perfectionnée</strong>. Ndjangui garde l'esprit de communauté mais supprime les risques liés au cash, les oublis et le manque de transparence.
                    </p>
                    <div class="d-flex gap-3 flex-column flex-sm-row">
                        <a href="#how-it-works" class="btn btn-primary btn-lg rounded-pill px-5 py-3 shadow-lg text-white">
                            Comment ça marche ?
                        </a>
                        <a href="#comparison" class="btn btn-outline-dark btn-lg rounded-pill px-5 py-3">
                            Comparer
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 hero-img-container" data-aos="fade-left">
                    <div class="position-relative">
                        <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Meeting" class="img-fluid rounded-4 shadow-lg border border-4 border-white">
                        
                        <div class="position-absolute top-100 start-0 translate-middle-y bg-white p-3 rounded-4 shadow-lg d-flex align-items-center gap-3 animate__animated animate__fadeInUp" style="margin-left: -20px; max-width: 250px;">
                            <div class="bg-success rounded-circle p-2 text-white">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Sécurité</small>
                                <span class="fw-bold text-dark">Fonds garantis</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="py-5">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-box">
                        <div class="icon-circle">
                            <i class="fa-solid fa-users-viewfinder"></i>
                        </div>
                        <h3 class="h4 fw-bold mb-3">Transparence Totale</h3>
                        <p class="text-muted">
                            Fini le "cahier" que seul le président consulte. Sur l'application, chaque membre voit l'état de la caisse, qui a payé, et qui est en retard, en temps réel.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-box">
                        <div class="icon-circle">
                            <i class="fa-solid fa-vault"></i>
                        </div>
                        <h3 class="h4 fw-bold mb-3">Séquestre Bancaire</h3>
                        <p class="text-muted">
                            L'argent ne transite pas de main en main. Il est stocké sur un compte séquestre inviolable jusqu'au jour du "bénéfice". Personne ne peut fuir avec la caisse.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-box">
                        <div class="icon-circle">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h3 class="h4 fw-bold mb-3">Crédit Scoring</h3>
                        <p class="text-muted">
                            Votre régularité crée votre réputation financière. Un bon score Ndjangui vous permet d'accéder à des micro-crédits sans garanties matérielles lourdes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-5 bg-white">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h6 class="text-uppercase text-success fw-bold ls-2">Processus Simplifié</h6>
                <h2 class="display-5 fw-bold text-dark">La tontine en 4 étapes</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up">
                    <div class="p-4 rounded-4 bg-light h-100 position-relative border border-light">
                        <span class="step-number">01</span>
                        <div class="mb-4 text-primary fs-1"><i class="fa-solid fa-user-plus"></i></div>
                        <h4 class="fw-bold">Création</h4>
                        <p class="text-muted small">Le président crée le groupe, définit le montant de la cotisation (ex: 50.000 FCFA) et la fréquence.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="p-4 rounded-4 bg-light h-100 position-relative border border-light">
                        <span class="step-number">02</span>
                        <div class="mb-4 text-primary fs-1"><i class="fa-solid fa-envelope-open-text"></i></div>
                        <h4 class="fw-bold">Invitation</h4>
                        <p class="text-muted small">Les membres reçoivent un lien d'invitation (SMS/WhatsApp) et créent leur profil sécurisé.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="p-4 rounded-4 bg-light h-100 position-relative border border-light">
                        <span class="step-number">03</span>
                        <div class="mb-4 text-primary fs-1"><i class="fa-regular fa-credit-card"></i></div>
                        <h4 class="fw-bold">Cotisation</h4>
                        <p class="text-muted small">Chaque mois, l'appli prélève automatiquement via Mobile Money ou Carte. Plus de retards !</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="p-4 rounded-4 bg-light h-100 position-relative border border-light">
                        <span class="step-number">04</span>
                        <div class="mb-4 text-primary fs-1"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                        <h4 class="fw-bold">Bénéfice</h4>
                        <p class="text-muted small">L'algorithme (ou le tirage au sort) désigne le bénéficiaire qui reçoit instantanément la cagnotte.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="comparison" class="py-5" style="background-color: var(--bg-light);">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold">Pourquoi changer ?</h2>
                        <p class="text-muted">Comparaison directe entre la méthode classique et notre solution.</p>
                    </div>

                    <div class="row g-0 align-items-center">
                        <div class="col-md-5 order-2 order-md-1">
                            <div class="bg-white p-4 rounded-start-4 text-muted border h-100 opacity-75">
                                <h4 class="text-center fw-bold mb-4"><i class="fa-solid fa-book-dead me-2"></i>Tontine Classique</h4>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-xmark text-danger me-3 fs-5"></i>
                                    <span>Risque de vol de la caisse</span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-xmark text-danger me-3 fs-5"></i>
                                    <span>Obligation de se déplacer</span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-xmark text-danger me-3 fs-5"></i>
                                    <span>Erreurs de calcul (Cahier)</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-xmark text-danger me-3 fs-5"></i>
                                    <span>Conflits et palabres</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 order-1 order-md-2 text-center my-3 my-md-0 position-relative z-1">
                            <div class="bg-white rounded-circle shadow-lg d-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px; border: 4px solid var(--primary-light);">
                                <span class="fw-bold fs-4 text-dark">VS</span>
                            </div>
                        </div>

                        <div class="col-md-5 order-3 order-md-3">
                            <div class="bg-white p-5 rounded-4 shadow-lg border-primary border h-100 position-relative overflow-hidden">
                                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                                    <i class="fa-solid fa-handshake-simple fs-1 text-success"></i>
                                </div>
                                <h3 class="text-center fw-bold mb-4 text-primary">NDJANGUI</h3>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-check text-success me-3 fs-4"></i>
                                    <span class="fw-bold text-dark">Sécurisation Bancaire</span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-check text-success me-3 fs-4"></i>
                                    <span class="fw-bold text-dark">Paiement Mobile (Partout)</span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-check text-success me-3 fs-4"></i>
                                    <span class="fw-bold text-dark">Comptabilité Automatisée</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-check text-success me-3 fs-4"></i>
                                    <span class="fw-bold text-dark">Harmonie & Confiance</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="zoom-in">
            <div class="cta-box position-relative overflow-hidden">
                <i class="fa-solid fa-coins position-absolute text-white opacity-25" style="font-size: 15rem; left: -50px; bottom: -50px;"></i>
                
                <div class="position-relative z-1">
                    <h2 class="display-5 fw-bold mb-3">Votre première tontine numérique</h2>
                    <p class="fs-5 mb-5 opacity-90">Créez votre groupe gratuitement en moins de 2 minutes. Invitez vos proches et commencez à bâtir.</p>
                    <a href="rejoindre.php" class="btn btn-light text-success fw-bold px-5 py-3 rounded-pill shadow-lg hover-scale">
                        <i class="fa-solid fa-rocket me-2"></i> Lancer mon Ndjangui
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-5 bg-white border-top">
        <div class="container text-center">
            <div class="d-flex justify-content-center align-items-center mb-3 text-secondary">
                 <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; color: white;">
                    <i class="fa-solid fa-handshake-simple fs-6"></i>
                </div>
                <span class="fw-bold fs-5">NDJANGUI.</span>
            </div>
            <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> Ndjangui Inc. Innovation financière pour l'Afrique.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>
</body>
</html>