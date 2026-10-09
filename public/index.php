<?php
require_once '../includes/security.php';
$dashboardUrl = isset($_SESSION['user_id']) ? 'dashboard.php' : 'register.php';
$primaryLabel = isset($_SESSION['user_id']) ? 'Open your dashboard' : 'Create your account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="LiteUp brings wallet funding and everyday utility requests into one clear place.">
    <title>LiteUp | Everyday utilities, clearly managed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        :root { --forest: #102820; --green: #147d59; --mint: #d9f3e8; --paper: #f4f7f3; --ink: #17251e; --line: #dce5df; }
        body { min-height: 100vh; display: flex; flex-direction: column; background: #fff; color: var(--ink); font-family: "Trebuchet MS", "Segoe UI", sans-serif; }
        body > main { flex: 1 0 auto; }
        body > .site-footer { flex-shrink: 0; margin-top: auto; }
        .site-nav { background: var(--forest); }
        .site-nav .nav-link { color: rgba(255,255,255,.8); }
        .site-nav .nav-link:hover { color: white; }
        .hero { position: relative; isolation: isolate; min-height: min(690px, calc(100vh - 66px)); display: grid; align-items: end; overflow: hidden; background: var(--forest); color: white; }
        .hero-photo { position: absolute; z-index: -2; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center 52%; }
        .hero::after { content: ""; position: absolute; z-index: -1; inset: 0; background: linear-gradient(90deg, rgba(8,28,20,.92), rgba(8,28,20,.65) 48%, rgba(8,28,20,.1)); }
        .hero-copy { max-width: 760px; padding-top: 5rem; padding-bottom: 5rem; }
        .hero h1 { max-width: 680px; font-size: clamp(3rem, 7vw, 6.5rem); line-height: .98; font-weight: 800; }
        .hero p { max-width: 570px; font-size: 1.2rem; color: rgba(255,255,255,.83); }
        .eyebrow { color: #b9efd8; font-weight: 700; text-transform: uppercase; font-size: .78rem; }
        .service-section { background: var(--paper); }
        .service-tile { height: 100%; background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 1.25rem; }
        .tile-mark { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 10px; background: var(--mint); color: var(--green); font-weight: 800; font-size: 1.2rem; }
        .trust-strip { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .site-footer { background: var(--forest); color: rgba(255,255,255,.76); }
        @media (prefers-reduced-motion: no-preference) { .hero-copy { animation: enter .55s ease-out both; } @keyframes enter { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark site-nav">
    <div class="container py-1">
        <a class="navbar-brand" href="index.php"><img src="../asset/images/liteup-logo.svg" alt="LiteUp" height="38"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Open navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How it works</a></li>
                <li class="nav-item"><a class="nav-link" href="login.php">Sign in</a></li>
                <li class="nav-item"><a class="btn btn-warning fw-semibold ms-lg-2" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8') ?></a></li>
            </ul>
        </div>
    </div>
</nav>
<main>
    <section class="hero" aria-labelledby="hero-title">
        <img class="hero-photo" src="../asset/images/liteup-utility-hero.jpg" alt="High-voltage transmission lines against a warm evening sky" fetchpriority="high">
        <div class="container hero-copy">
            <p class="eyebrow mb-3">Everyday utilities, clearly managed</p>
            <h1 id="hero-title">LiteUp</h1>
            <p class="mt-4">Keep your wallet, utility requests, and service status together in one straightforward place.</p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-warning btn-lg fw-semibold"><?= htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8') ?></a>
                <a href="#services" class="btn btn-outline-light btn-lg">Explore services</a>
            </div>
        </div>
    </section>

    <section id="services" class="service-section py-5">
        <div class="container py-lg-3">
            <div class="row align-items-end mb-4 g-3"><div class="col-md-8"><p class="eyebrow text-success mb-2">LiteUp services</p><h2 class="display-6 fw-bold mb-0">A clearer way to manage everyday requests.</h2></div><div class="col-md-4 text-md-end"><span class="text-secondary">Requests and wallet activity in one account.</span></div></div>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3"><article class="service-tile"><span class="tile-mark" aria-hidden="true">⚡</span><h3 class="h5 mt-3">Electricity</h3><p class="text-secondary mb-0">Submit a meter payment request and follow its status.</p></article></div>
                <div class="col-sm-6 col-xl-3"><article class="service-tile"><span class="tile-mark" aria-hidden="true">◉</span><h3 class="h5 mt-3">Airtime</h3><p class="text-secondary mb-0">Request a top-up for a supported Nigerian network.</p></article></div>
                <div class="col-sm-6 col-xl-3"><article class="service-tile"><span class="tile-mark" aria-hidden="true">▤</span><h3 class="h5 mt-3">Mobile data</h3><p class="text-secondary mb-0">Choose an available bundle and track fulfillment.</p></article></div>
                <div class="col-sm-6 col-xl-3"><article class="service-tile"><span class="tile-mark" aria-hidden="true">TV</span><h3 class="h5 mt-3">Cable TV</h3><p class="text-secondary mb-0">Coming soon, pending provider integration.</p></article></div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="container py-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5"><p class="eyebrow text-success mb-2">Designed for clarity</p><h2 class="display-6 fw-bold">Know what happens next.</h2><p class="text-secondary">Fund your LiteUp wallet, submit a request, then follow its status. If an operator marks an order as failed, the reserved amount is returned to your wallet.</p></div>
            <div class="col-lg-7"><div class="row g-3"><div class="col-sm-4"><div class="p-3 border rounded-3 h-100"><span class="badge text-bg-success mb-3">01</span><h3 class="h6">Fund</h3><p class="small text-secondary mb-0">Submit a funding request for review.</p></div></div><div class="col-sm-4"><div class="p-3 border rounded-3 h-100"><span class="badge text-bg-success mb-3">02</span><h3 class="h6">Request</h3><p class="small text-secondary mb-0">Choose a service and enter its details.</p></div></div><div class="col-sm-4"><div class="p-3 border rounded-3 h-100"><span class="badge text-bg-success mb-3">03</span><h3 class="h6">Track</h3><p class="small text-secondary mb-0">See pending, completed, or refunded status.</p></div></div></div></div>
        </div>
    </section>

    <section class="trust-strip py-4"><div class="container d-flex flex-wrap justify-content-between gap-3"><span><strong>Protected accounts</strong> · email verification and new-device checks</span><span><strong>Clear status</strong> · pending requests are not shown as delivered</span><span><strong>Reviewable actions</strong> · refund trail recorded in wallet activity</span></div></section>
    <section class="container py-5 text-center"><h2 class="h2 fw-bold">Make your utility requests easier to follow.</h2><p class="text-secondary">Create an account to view your wallet and request history.</p><a class="btn btn-success btn-lg" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8') ?></a></section>
</main>
<footer class="site-footer py-4"><div class="container d-flex flex-wrap justify-content-between gap-2"><span>LiteUp</span><span class="small">© <?= date('Y') ?> LiteUp. Service fulfillment is subject to provider availability.</span></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
