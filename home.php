<?php
// ════════════════════════════════════════════════════════════════════
//  Ganeshman Singh Academy — Public Landing Page
//  Fetches all data directly from the database (no JSON)
// ════════════════════════════════════════════════════════════════════
require_once 'includes/auth.php';

// home.php is the public landing page — accessible whether logged in or not.
// (Redirect was removed: logged-in users can still view the public home page.)

// ── Fetch live stats ─────────────────────────────────────────────────
$totalStudents = (int)(db()->fetchOne("SELECT COUNT(*) as c FROM students WHERE is_active=1")['c'] ?? 0);
$totalTeachers = (int)(db()->fetchOne("SELECT COUNT(*) as c FROM teachers WHERE is_active=1")['c'] ?? 0);

// ── Fetch landing notices (show_on_landing=1, active, public) ────────
// show_on_landing column added by home_admin.php migration
$notices = db()->fetchAll(
    "SELECT n.id, n.title, n.content, n.image, n.target_role, n.created_at,
            COALESCE(u.username, 'Admin') as author_name
     FROM notices n
     LEFT JOIN users u ON n.posted_by = u.id
     WHERE n.is_active = 1
       AND n.show_on_landing = 1
     ORDER BY n.created_at DESC
     LIMIT 6"
);

// ── Load landing page settings ────────────────────────────────────────
function landingSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $rows  = db()->fetchAll("SELECT setting_key, setting_value FROM landing_settings");
        $cache = [];
        foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
    }
    return $cache[$key] ?? $default;
}

$ls = function(string $key, string $default = '') use (&$ls): string {
    return landingSetting($key, $default);
};

// ── Fetch gallery images uploaded via home_admin.php ─────────────────
$galleryImages = [];
try {
    $galleryImages = db()->fetchAll(
        "SELECT filename, caption FROM gallery_images WHERE is_active=1 ORDER BY sort_order ASC, created_at DESC"
    );
} catch (Exception $e) {
    $galleryImages = [];
}

// ── Fetch programs from DB ─────────────────────────────────────────────
$programs = [];
try {
    $programs = db()->fetchAll(
        "SELECT * FROM programs WHERE is_active=1 ORDER BY sort_order, id"
    );
} catch (Exception $e) {
    $programs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ganeshman Singh Academy – Jhiljhille, Jhapa, Nepal | GMS</title>
<meta name="description" content="Ganeshman Singh Academy (GMS) – A premier CTEVT affiliated college in Jhiljhille, Jhapa, Nepal. Diploma in IT Engineering and Civil Engineering.">
<meta name="keywords" content="GMS, Ganeshman Singh Academy, GMS Jhapa, GMS Jhiljhille, GMS Nepal, CTEVT college Jhapa Nepal, Diploma IT Engineering, Civil Engineering Nepal">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://gms-sms.42web.io/">
<meta property="og:type" content="website">
<meta property="og:url" content="https://gms-sms.42web.io/">
<meta property="og:title" content="Ganeshman Singh Academy – Jhiljhille, Jhapa, Nepal">
<meta property="og:image" content="https://gms-sms.42web.io/assets/images/gms1.png">
<link rel="icon" type="image/png" href="assets/images/gmscrop.png">
<link rel="shortcut icon" href="assets/images/gmscrop.png">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<!-- PWA: installable app support -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#1a3c5e">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="GMS App">
<script src="assets/js/pwa-install.js" defer></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --blue:#1a3c5e;
  --blue-dark:#132d46;
  --blue-light:#e6ebf0;
  --orange:#f5a623;
  --dark:#1f2937;
  --gray:#6b7280;
  --gray-light:#f3f4f6;
  --border:#e2e5ea;
  --white:#ffffff;
}
html{scroll-behavior:smooth}
body{font-family:'Poppins',Arial,sans-serif;color:var(--dark);background:var(--white);font-size:15px;line-height:1.6}
a{text-decoration:none;color:inherit}
img{display:block;max-width:100%}
ul{list-style:none}
h1,h2,h3,h4{font-weight:700}
.container{max-width:1180px;margin:0 auto;padding:0 20px}
.btn{display:inline-block;padding:12px 28px;border-radius:6px;font-weight:600;font-size:0.9rem;cursor:pointer;border:2px solid transparent;transition:0.2s}
.btn-primary{background:var(--blue);color:#fff;}
.btn-primary:hover{background:var(--blue-dark)}
.btn-outline{background:transparent;border:2px solid #fff;color:#fff}
.btn-outline:hover{background:#fff;color:var(--blue)}
.btn-orange{background:var(--orange);color:#1f2937}
.btn-orange:hover{background:#e0951a}
.section-label{color:var(--orange);font-weight:700;font-size:0.78rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;display:block}
.section-title{font-size:1.9rem;color:var(--dark);margin-bottom:14px}
.section-desc{color:var(--gray);font-size:0.92rem;max-width:600px}
.text-center{text-align:center}
.mx-auto{margin-left:auto;margin-right:auto}

/* ── NAVBAR ── */
header{background:var(--blue);position:sticky;top:0;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,0.12)}
.navbar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;max-width:1180px;margin:0 auto}
.nav-brand{display:flex;align-items:center;gap:10px;color:#fff}
.nav-brand img{width:42px;height:42px;border-radius:50%;border:2px solid #fff;object-fit:cover}
.nav-brand-text strong{display:block;font-size:1rem;line-height:1.2}
.nav-brand-text span{display:block;font-size:0.68rem;color:#cfe0ff}
.nav-links{display:flex;gap:24px;align-items:center}
.nav-links a{color:#eaf1ff;font-size:0.88rem;font-weight:500;padding:6px 2px;border-bottom:2px solid transparent}
.nav-links a:hover{border-bottom-color:var(--orange);color:#fff}
.nav-login{background:transparent;color:#fff!important;padding:9px 22px;border-radius:5px;font-weight:600;border:1.5px solid rgba(255,255,255,0.5)!important;border-bottom:1.5px solid rgba(255,255,255,0.5)!important;display:inline-flex;align-items:center;gap:8px}
.nav-login:hover{background:#fff;color:var(--blue)!important;border-color:#fff!important}
.hamburger{display:none;background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer}
.mobile-menu{display:none;flex-direction:column;background:var(--blue-dark);padding:10px 20px}
.mobile-menu.open{display:flex}
.mobile-menu a{color:#fff;padding:12px 4px;border-bottom:1px solid rgba(255,255,255,0.12);font-size:0.92rem}

/* ── HERO ── */
.hero{position:relative;height:600px;color:#fff;text-align:center;overflow:hidden;background:var(--blue-dark);display:flex;align-items:center;justify-content:center;padding:0 20px}
.hero-video{position:absolute;top:50%;left:50%;min-width:100%;min-height:100%;width:auto;height:auto;transform:translate(-50%,-50%);object-fit:cover;z-index:0}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(26,60,94,0.55) 0%,rgba(19,45,70,0.65) 100%);z-index:1}
.hero-content{position:relative;z-index:2;max-width:700px;width:100%}
.hero img.hero-logo{width:100px;height:100px;border-radius:50%;border:3px solid var(--orange);margin:0 auto 22px;object-fit:cover}
.hero-badge{display:inline-block;background:rgba(255,255,255,0.15);padding:6px 16px;border-radius:20px;font-size:0.78rem;margin-bottom:16px;border:1px solid rgba(255,255,255,0.25)}
.hero h1{font-size:clamp(1.8rem,4.5vw,2.7rem);line-height:1.25;margin-bottom:12px;text-shadow:0 2px 12px rgba(0,0,0,0.5)}
.hero p.tagline{font-size:1rem;color:#dbe8ff;margin-bottom:22px;text-shadow:0 1px 8px rgba(0,0,0,0.5)}
.hero-tags{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-bottom:30px}
.hero-tag{background:rgba(255,255,255,0.12);padding:6px 14px;border-radius:20px;font-size:0.78rem;display:flex;align-items:center;gap:6px}
.hero-tag i{color:var(--orange)}
.hero-buttons{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}

/* ── STATS ── */
.stats{background:var(--dark);padding:0}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);max-width:1180px;margin:0 auto}
.stat-box{text-align:center;padding:30px 10px;border-right:1px solid rgba(255,255,255,0.08)}
.stat-box:last-child{border-right:none}
.stat-num{font-size:2.1rem;font-weight:800;color:var(--orange)}
.stat-label{font-size:0.75rem;color:#c7ccd4;text-transform:uppercase;letter-spacing:0.5px;margin-top:4px}

/* ── ABOUT ── */
.about{padding:70px 20px}
.about-grid{display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:start;max-width:1180px;margin:0 auto}
.about-grid p{color:var(--gray);font-size:0.92rem;margin-bottom:14px;line-height:1.85}
.value-box{background:var(--gray-light);border:1px solid var(--border);border-radius:8px;padding:26px}
.value-box h3{font-size:1.05rem;margin-bottom:16px;display:flex;align-items:center;gap:8px;color:var(--dark)}
.value-box h3 i{color:var(--blue)}
.value-row{display:flex;gap:12px;padding:11px 0;border-bottom:1px solid var(--border)}
.value-row:last-child{border-bottom:none}
.value-row i{color:var(--blue);margin-top:3px}
.value-row strong{display:block;font-size:0.88rem;color:var(--dark)}
.value-row span{font-size:0.8rem;color:var(--gray)}

/* ── PROGRAMS ── */
.programs{background:var(--gray-light);padding:70px 20px}
.programs-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:22px;margin-top:36px;max-width:1180px;margin-left:auto;margin-right:auto}
.program-card{background:#fff;border:1px solid var(--border);border-radius:8px;padding:30px;position:relative}
.program-card .badge-tag{position:absolute;top:18px;right:18px;background:var(--blue-light);color:var(--blue);font-size:0.65rem;font-weight:700;padding:3px 10px;border-radius:4px;text-transform:uppercase}
.program-icon{width:52px;height:52px;border-radius:8px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:18px}
.program-card h3{font-size:1.2rem;margin-bottom:10px;color:var(--dark)}
.program-card p{color:var(--gray);font-size:0.86rem;margin-bottom:16px}
.program-grade{display:inline-block;background:var(--gray-light);border:1px solid var(--border);color:var(--dark);font-size:0.75rem;font-weight:600;padding:5px 12px;border-radius:4px}
.info-strip{margin-top:30px;max-width:1180px;margin-left:auto;margin-right:auto;background:#fff;border:1px solid var(--border);border-radius:8px;padding:22px 26px;display:flex;gap:16px;align-items:flex-start}
.info-strip i{color:var(--blue);font-size:1.4rem;margin-top:2px}
.info-strip strong{display:block;color:var(--dark);margin-bottom:4px;font-size:0.92rem}
.info-strip span{font-size:0.83rem;color:var(--gray);line-height:1.7}

/* ── NOTICES ── */
.notices{padding:70px 20px}
.notices-head{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;margin-bottom:32px;max-width:1180px;margin-left:auto;margin-right:auto}
.notices-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;max-width:1180px;margin:0 auto}
.notice-card{background:#fff;border:1px solid var(--border);border-left:4px solid var(--blue);border-radius:6px;padding:22px;cursor:pointer;transition:box-shadow 0.15s}
.notice-card:hover{box-shadow:0 4px 14px rgba(0,0,0,0.08)}
.notice-top{display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
.notice-badge{background:var(--blue-light);color:var(--blue);font-size:0.62rem;font-weight:700;padding:3px 10px;border-radius:4px;text-transform:uppercase}
.notice-date{font-size:0.72rem;color:var(--gray)}
.notice-title{font-size:0.98rem;font-weight:700;color:var(--dark);margin-bottom:8px}
.notice-snippet{font-size:0.83rem;color:var(--gray);margin-bottom:10px}
.notice-img-thumb{width:100%;max-height:130px;object-fit:cover;border-radius:4px;margin-bottom:10px}
.notice-footer{display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px solid var(--border);font-size:0.75rem}
.notice-read-more{color:var(--blue);font-weight:600}
.notice-author{color:var(--gray)}
.no-notices{grid-column:1/-1;text-align:center;padding:50px 0;color:var(--gray)}
.no-notices i{font-size:2rem;margin-bottom:10px;display:block;color:var(--border)}

/* ── MODAL ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity 0.2s}
.modal-overlay.open{opacity:1;pointer-events:all}
.notice-modal{background:#fff;border-radius:8px;max-width:640px;width:100%;max-height:88vh;overflow-y:auto}
.modal-header-bar{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid var(--border);position:sticky;top:0;background:#fff}
.modal-header-bar h3{font-size:1rem;color:var(--dark)}
.modal-close-btn{background:var(--gray-light);border:none;width:30px;height:30px;border-radius:50%;cursor:pointer;color:var(--gray)}
.modal-body{padding:22px}
.modal-tag{background:var(--blue-light);color:var(--blue);font-size:0.65rem;font-weight:700;padding:3px 10px;border-radius:4px;text-transform:uppercase}
.modal-title{font-size:1.4rem;color:var(--dark);margin:12px 0}
.modal-meta{display:flex;gap:18px;font-size:0.78rem;color:var(--gray);margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.modal-img{width:100%;border-radius:6px;margin-bottom:16px}
.modal-content-body{font-size:0.9rem;color:var(--dark);line-height:1.85;white-space:pre-wrap}

/* ── WHY US ── */
.why-us{background:var(--gray-light);padding:70px 20px}
.why-grid{display:grid;grid-template-columns:1fr 1fr;gap:44px;margin-top:36px;max-width:1180px;margin-left:auto;margin-right:auto;align-items:center}
.why-feature{display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--border)}
.why-feature:last-child{border-bottom:none}
.why-num{width:36px;height:36px;flex-shrink:0;background:var(--blue);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem}
.why-feature strong{display:block;font-size:0.92rem;color:var(--dark);margin-bottom:3px}
.why-feature span{font-size:0.82rem;color:var(--gray)}
.why-visual{background:var(--dark);color:#fff;border-radius:8px;padding:32px}
.why-visual h3{font-size:1.15rem;margin-bottom:20px}
.why-metrics{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.why-metric{background:rgba(255,255,255,0.07);border-radius:6px;padding:16px;text-align:center}
.why-metric-num{font-size:1.7rem;font-weight:800;color:var(--orange)}
.why-metric-label{font-size:0.68rem;text-transform:uppercase;color:#c7ccd4;margin-top:4px}
.why-quote{margin-top:20px;background:rgba(255,255,255,0.06);border-left:3px solid var(--orange);padding:16px;border-radius:4px}
.why-quote p{font-size:0.82rem;font-style:italic;color:#dbe0e6}
.why-quote cite{display:block;margin-top:6px;font-size:0.72rem;color:var(--orange)}

/* ── GALLERY ── */
.gallery{padding:70px 20px}
.gallery-wrap{position:relative;margin-top:32px;border-radius:8px;overflow:hidden;max-width:1180px;margin-left:auto;margin-right:auto;border:1px solid var(--border)}
.gallery-track{display:flex;transition:transform 0.5s ease}
.gallery-slide{flex:0 0 100%;height:380px;position:relative;background:var(--dark)}
.gallery-slide img{width:100%;height:100%;object-fit:cover}
.gallery-caption{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.6);color:#fff;padding:16px 20px}
.gallery-caption h4{font-size:1.05rem}
.gallery-caption p{font-size:0.78rem;color:#dbe0e6}
.gallery-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;color:rgba(255,255,255,0.35)}
.gallery-placeholder i{font-size:2.4rem;margin-bottom:10px}
.gallery-arrow{position:absolute;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:50%;background:#fff;border:1px solid var(--border);color:var(--dark);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5}
.gallery-arrow.prev{left:12px}
.gallery-arrow.next{right:12px}
.gallery-dots{display:flex;justify-content:center;gap:6px;margin-top:14px}
.gallery-dot{width:8px;height:8px;border-radius:50%;background:var(--border);cursor:pointer}
.gallery-dot.active{background:var(--blue)}
.gallery-thumbs{display:flex;gap:8px;margin-top:12px;overflow-x:auto;max-width:1180px;margin-left:auto;margin-right:auto}
.gallery-thumb{flex-shrink:0;width:64px;height:46px;border-radius:4px;overflow:hidden;cursor:pointer;border:2px solid transparent;opacity:0.6}
.gallery-thumb.active{border-color:var(--blue);opacity:1}
.gallery-thumb img{width:100%;height:100%;object-fit:cover}
.gallery-thumb-placeholder{width:100%;height:100%;background:var(--dark)}

/* ── CTA / CONTACT ── */
.cta{background:var(--blue);color:#fff;padding:70px 20px;text-align:center}
.cta h2{font-size:1.9rem;margin-bottom:12px}
.cta p{color:#dbe8ff;max-width:600px;margin:0 auto 30px;font-size:0.92rem}
.contact-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:800px;margin:0 auto}
.contact-card{background:rgba(255,255,255,0.1);border-radius:8px;padding:22px;border:1px solid rgba(255,255,255,0.18)}
.contact-card i{font-size:1.3rem;color:var(--orange);margin-bottom:10px}
.contact-card h4{font-size:0.85rem;margin-bottom:6px}
.contact-card p{font-size:0.78rem;color:#dbe8ff;margin:0}

/* ── FOOTER ── */
footer{background:var(--dark);color:#c7ccd4;padding:44px 20px 20px}
.footer-top{display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:36px;max-width:1180px;margin:0 auto 30px;padding-bottom:30px;border-bottom:1px solid rgba(255,255,255,0.1)}
.footer-brand{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.footer-brand img{width:42px;height:42px;border-radius:50%}
.footer-brand h3{color:#fff;font-size:1rem}
.footer-top p{font-size:0.82rem;line-height:1.7}
.footer-col h4{color:#fff;font-size:0.85rem;margin-bottom:14px}
.footer-col li{font-size:0.82rem;margin-bottom:9px;display:flex;gap:8px}
.footer-col li i{color:var(--orange);width:14px}
.footer-col a{color:#c7ccd4}
.footer-col a:hover{color:#fff}
.footer-bottom{max-width:1180px;margin:0 auto;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.78rem}
.footer-bottom a{color:var(--orange);font-weight:600}

/* ── RESPONSIVE ── */
@media(max-width:1000px){
  .about-grid,.why-grid{grid-template-columns:1fr}
  .programs-grid{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .stat-box:nth-child(2){border-right:none}
  .stat-box:nth-child(3){border-top:1px solid rgba(255,255,255,0.08)}
  .stat-box:nth-child(4){border-top:1px solid rgba(255,255,255,0.08)}
  .notices-grid{grid-template-columns:1fr}
  .footer-top{grid-template-columns:1fr 1fr}
  .contact-cards{grid-template-columns:1fr}
  .nav-links{display:none}
  .hamburger{display:block}
}
@media(max-width:600px){
  .hero{height:460px;padding:0 16px}
  .about,.programs,.notices,.why-us,.gallery,.cta{padding:50px 16px}
  .footer-top{grid-template-columns:1fr}
  .contact-cards{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- HEADER / NAVBAR -->
<header>
  <div class="navbar">
    <a href="#" class="nav-brand">
      <img src="assets/images/gmscrop.png" alt="GMS">
      <div class="nav-brand-text">
        <strong>Ganeshman Singh Academy</strong>
        <span>Jhiljhille, Jhapa, Nepal</span>
      </div>
    </a>
    <ul class="nav-links">
      <li><a href="#about">About</a></li>
      <li><a href="#programs">Courses</a></li>
      <li><a href="#notices">Notices</a></li>
      <li><a href="#why-us">Why GMS</a></li>
      <li><a href="#contact">Contact</a></li>
      <li><a href="login" class="nav-login"><i class="fas fa-sign-in-alt"></i> Portal Login</a></li>
    </ul>
    <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
  </div>
  <div class="mobile-menu" id="mobileMenu">
    <a href="#about" onclick="closeMobile()">About</a>
    <a href="#programs" onclick="closeMobile()">Courses</a>
    <a href="#notices" onclick="closeMobile()">Notices</a>
    <a href="#why-us" onclick="closeMobile()">Why GMS</a>
    <a href="#contact" onclick="closeMobile()">Contact</a>
    <a href="login" style="color:var(--orange);font-weight:700">Portal Login</a>
  </div>
</header>

<!-- HERO -->
<section class="hero">
  <?php $heroVideo = $ls('hero_video', 'assets/videos/hero.mp4'); ?>
  <video class="hero-video" autoplay muted loop playsinline poster="assets/images/gms1.png" src="<?= htmlspecialchars($heroVideo) ?>">
  </video>
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <img src="assets/images/gmscrop.png" alt="GMS logo" class="hero-logo">
    <h1>Ganeshman Singh Academy</h1>
    <p class="tagline"><?= htmlspecialchars($ls('hero_tagline','Knowledge · Integrity · Excellence')) ?></p>
    <div class="hero-buttons">
      <a href="#programs" class="btn btn-orange">View Courses</a>
      <a href="login" class="btn btn-outline">Portal Login</a>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats">
  <div class="stats-grid">
    <div class="stat-box"><div class="stat-num" id="sStudents">0</div><div class="stat-label">Students Enrolled</div></div>
    <div class="stat-box"><div class="stat-num" id="sTeachers">0</div><div class="stat-label">Faculty Members</div></div>
    <div class="stat-box"><div class="stat-num">2</div><div class="stat-label">Diploma Programs</div></div>
    <div class="stat-box"><div class="stat-num">100%</div><div class="stat-label">Commitment</div></div>
  </div>
</div>
<script>
(function(){
  function anim(id, target){
    const el=document.getElementById(id);if(!el||!target)return;
    let s=0;const dur=1200;
    const step=ts=>{if(!s)s=ts;const p=Math.min((ts-s)/dur,1);el.textContent=Math.floor(p*target)+'+';if(p<1)requestAnimationFrame(step);};
    requestAnimationFrame(step);
  }
  const obs=new IntersectionObserver(entries=>{
    entries.forEach(en=>{if(en.isIntersecting){anim('sStudents',<?= $totalStudents ?>);anim('sTeachers',<?= $totalTeachers ?>);obs.disconnect();}});
  },{threshold:0.3});
  const bar=document.querySelector('.stats');if(bar)obs.observe(bar);
})();
</script>

<!-- ABOUT -->
<section id="about" class="about">
  <div class="about-grid">
    <div>
      <span class="section-label">About Our College</span>
      <h2 class="section-title"><?= htmlspecialchars($ls('about_title',"Building Tomorrow's Engineers in the Heart of Jhapa")) ?></h2>
      <p><?= htmlspecialchars($ls('about_text1','Ganeshman Singh Academy (GMS) is a CTEVT affiliated technical college located in Jhiljhille, Jhapa, Nepal. We are dedicated to delivering quality technical education through our Diploma programs in IT Engineering and Civil Engineering, preparing graduates for the demands of a rapidly evolving workforce.')) ?></p>
      <p><?= htmlspecialchars($ls('about_text2','Each program spans six semesters — each of six months — combining strong theoretical foundations with hands-on, practical training. Our qualified faculty, modern labs, and industry-aligned curriculum ensure students graduate ready to contribute from day one.')) ?></p>
    </div>
    <div class="value-box">
      <h3><i class="fas fa-star"></i> Our Core Values</h3>
      <div class="value-row"><i class="fas fa-microchip"></i><div><strong>Technical Excellence</strong><span>Rigorous, CTEVT-standard curriculum designed to produce skilled and job-ready engineers.</span></div></div>
      <div class="value-row"><i class="fas fa-tools"></i><div><strong>Practical-First Learning</strong><span>Workshops, labs, and real-world projects are central to every semester's experience.</span></div></div>
      <div class="value-row"><i class="fas fa-users"></i><div><strong>Community &amp; Values</strong><span>Rooted in Nepali values, we build a collaborative campus where every student thrives.</span></div></div>
      <div class="value-row"><i class="fas fa-lightbulb"></i><div><strong>Innovation &amp; Industry Link</strong><span>Modern teaching tools and industry partnerships for a forward-thinking technical education.</span></div></div>
    </div>
  </div>
</section>

<!-- PROGRAMS -->
<section id="programs" class="programs">
  <div class="container text-center" style="max-width:600px">
    <span class="section-label">CTEVT Diploma Programs</span>
    <h2 class="section-title">Our Technical Courses</h2>
    <p class="section-desc mx-auto">Two industry-recognized Diploma programs, each with six semesters of six months. Practical, affordable, and career-focused technical education.</p>
  </div>
  <div class="programs-grid">
    <?php if (!empty($programs)): ?>
    <?php foreach ($programs as $prog): ?>
    <div class="program-card">
      <span class="badge-tag">CTEVT</span>
      <div class="program-icon"><i class="fas <?= htmlspecialchars($prog['icon']) ?>"></i></div>
      <h3><?= htmlspecialchars($prog['title']) ?></h3>
      <p><?= htmlspecialchars($prog['description']) ?></p>
      <span class="program-grade"><?= htmlspecialchars($prog['badge']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="program-card">
      <span class="badge-tag">CTEVT</span>
      <div class="program-icon"><i class="fas fa-laptop-code"></i></div>
      <h3>Diploma in IT Engineering</h3>
      <p><?= htmlspecialchars($ls('prog_it','A comprehensive 3-year Diploma program.')) ?></p>
      <span class="program-grade">3 Years · 6 Semesters</span>
    </div>
    <div class="program-card">
      <span class="badge-tag">CTEVT</span>
      <div class="program-icon"><i class="fas fa-hard-hat"></i></div>
      <h3>Diploma in Civil Engineering</h3>
      <p><?= htmlspecialchars($ls('prog_civil','A rigorous 3-year Diploma program.')) ?></p>
      <span class="program-grade">3 Years · 6 Semesters</span>
    </div>
    <?php endif; ?>
  </div>
  <div class="info-strip">
    <i class="fas fa-info-circle"></i>
    <div><strong>Program Structure</strong><span><?= $ls('prog_structure','Both diploma programs are affiliated with <strong style="color:#1a56db">CTEVT (Council for Technical Education and Vocational Training)</strong> and consist of <strong>6 semesters</strong>, each of <strong>6 months</strong> duration — totalling 3 years of technical education combining theory, practicals, and a final-semester project/internship.') ?></span></div>
  </div>
</section>

<!-- NOTICES -->
<section id="notices" class="notices">
  <div class="notices-head">
    <div>
      <span class="section-label">Latest Updates</span>
      <h2 class="section-title" style="margin-bottom:0">College Notices</h2>
    </div>
    <a href="login" class="btn btn-primary">View All Notices</a>
  </div>
  <div class="notices-grid">
    <?php if(empty($notices)): ?>
    <div class="no-notices">
      <i class="fas fa-bell-slash"></i>
      <p>No notices at this time.<br><small style="font-size:0.75rem">Admins can pin notices to this page from the Landing Page Admin.</small></p>
    </div>
    <?php else: ?>
      <?php foreach($notices as $n):
        $snippet  = mb_substr(strip_tags($n['content']), 0, 160);
        $hasMore  = mb_strlen(strip_tags($n['content'])) > 160;
        $dateStr  = date('d M Y', strtotime($n['created_at']));
        $target   = ucfirst($n['target_role']);
        $safeData = htmlspecialchars(json_encode([
          'title'   => $n['title'],
          'content' => $n['content'],
          'date'    => $dateStr,
          'author'  => $n['author_name'],
          'target'  => $target,
          'image'   => $n['image'] ?? '',
        ]), ENT_QUOTES);
      ?>
      <div class="notice-card" onclick="openModal(this)" data-notice="<?= $safeData ?>">
        <div class="notice-top">
          <span class="notice-badge"><?= htmlspecialchars($target) ?></span>
          <span class="notice-date"><i class="fas fa-calendar-alt"></i> <?= $dateStr ?></span>
        </div>
        <div class="notice-title"><?= htmlspecialchars($n['title']) ?></div>
        <div class="notice-snippet"><?= htmlspecialchars($snippet) ?><?= $hasMore ? '…' : '' ?></div>
        <?php if(!empty($n['image'])): ?>
        <img src="assets/uploads/notices/<?= htmlspecialchars($n['image']) ?>" class="notice-img-thumb" alt="Notice image">
        <?php endif; ?>
        <div class="notice-footer">
          <span class="notice-read-more">Read Full Notice →</span>
          <span class="notice-author"><i class="fas fa-user"></i> <?= htmlspecialchars($n['author_name']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- WHY US -->
<section id="why-us" class="why-us">
  <div class="container text-center" style="max-width:560px">
    <span class="section-label">Why Choose GMS</span>
    <h2 class="section-title">A College That Truly Invests in You</h2>
  </div>
  <div class="why-grid">
    <div>
      <div class="why-feature"><div class="why-num">1</div><div><strong>CTEVT Recognized Degree</strong><span>Our diplomas are officially affiliated with CTEVT — Nepal's premier technical and vocational education authority — giving your qualification national recognition.</span></div></div>
      <div class="why-feature"><div class="why-num">2</div><div><strong>Qualified &amp; Experienced Faculty</strong><span>Our lecturers bring real-world engineering and IT experience alongside academic credentials, bridging theory and practice in every class.</span></div></div>
      <div class="why-feature"><div class="why-num">3</div><div><strong>Modern Labs &amp; Infrastructure</strong><span>Well-equipped IT labs, civil engineering drawing rooms, and survey equipment ensure practical skill-building throughout the program.</span></div></div>
      <div class="why-feature"><div class="why-num">4</div><div><strong>Digital College Management</strong><span>An integrated management system keeps students, faculty, and parents connected with notices, results, and schedules in one place.</span></div></div>
    </div>
    <div class="why-visual">
      <h3>Excellence by the Numbers</h3>
      <div class="why-metrics">
        <div class="why-metric"><div class="why-metric-num"><?= $totalStudents ?>+</div><div class="why-metric-label">Students</div></div>
        <div class="why-metric"><div class="why-metric-num"><?= $totalTeachers ?>+</div><div class="why-metric-label">Faculty</div></div>
        <div class="why-metric"><div class="why-metric-num">6</div><div class="why-metric-label">Semesters</div></div>
        <div class="why-metric"><div class="why-metric-num">2</div><div class="why-metric-label">Programs</div></div>
      </div>
      <div class="why-quote">
        <p>"<?= htmlspecialchars($ls('quote_text','The beautiful thing about learning is that nobody can take it away from you.')) ?>"</p>
        <cite>— <?= htmlspecialchars($ls('quote_cite','B.B. King')) ?></cite>
      </div>
    </div>
  </div>
</section>

<!-- GALLERY -->
<section class="gallery">
  <div class="container text-center">
    <span class="section-label">Campus Life</span>
    <h2 class="section-title">Life at GMS</h2>
  </div>
  <div class="gallery-wrap">
    <div class="gallery-track" id="galleryTrack">
      <?php if (!empty($galleryImages)): ?>
      <?php foreach ($galleryImages as $gimg): ?>
      <div class="gallery-slide">
        <img src="assets/uploads/gallery/<?= htmlspecialchars($gimg['filename']) ?>"
             alt="<?= htmlspecialchars($gimg['caption'] ?? '') ?>"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="gallery-placeholder" style="display:none;position:absolute;inset:0;">
          <i class="fas fa-image"></i><span>GMS</span>
        </div>
        <?php if (!empty($gimg['caption'])): ?>
        <div class="gallery-caption"><h4><?= htmlspecialchars($gimg['caption']) ?></h4></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div class="gallery-slide"><div class="gallery-placeholder"><i class="fas fa-laptop-code"></i><span>IT Lab</span></div><div class="gallery-caption"><h4>IT Engineering Lab</h4><p>Hands-on computing and networking sessions</p></div></div>
      <div class="gallery-slide"><div class="gallery-placeholder"><i class="fas fa-drafting-compass"></i><span>Civil Drawing</span></div><div class="gallery-caption"><h4>Civil Engineering Studio</h4><p>AutoCAD, surveying and structural design</p></div></div>
      <div class="gallery-slide"><div class="gallery-placeholder"><i class="fas fa-trophy"></i><span>Sports Day</span></div><div class="gallery-caption"><h4>Annual Sports Day</h4><p>Building teamwork beyond the classroom</p></div></div>
      <div class="gallery-slide"><div class="gallery-placeholder"><i class="fas fa-music"></i><span>Cultural Event</span></div><div class="gallery-caption"><h4>Cultural Programs</h4><p>Celebrating Nepal's rich heritage together</p></div></div>
      <div class="gallery-slide"><div class="gallery-placeholder"><i class="fas fa-book"></i><span>Library</span></div><div class="gallery-caption"><h4>Library &amp; Reading Room</h4><p>A quiet space for deep focus and research</p></div></div>
      <div class="gallery-slide"><div class="gallery-placeholder"><i class="fas fa-graduation-cap"></i><span>Convocation</span></div><div class="gallery-caption"><h4>Graduation Ceremony</h4><p>Celebrating our alumni's achievements</p></div></div>
      <?php endif; ?>
    </div>
    <button class="gallery-arrow prev" id="galleryPrev"><i class="fas fa-chevron-left"></i></button>
    <button class="gallery-arrow next" id="galleryNext"><i class="fas fa-chevron-right"></i></button>
  </div>
  <div class="gallery-dots" id="galleryDots"></div>
  <div class="gallery-thumbs" id="galleryThumbs"></div>
</section>

<!-- CTA + CONTACT -->
<section id="contact" class="cta">
  <span class="section-label">Get In Touch</span>
  <h2><?= htmlspecialchars($ls('cta_title','Ready to Start Your Engineering Journey?')) ?></h2>
  <p><?= htmlspecialchars($ls('cta_text','Contact us for admissions, fee structure, or to learn more about our Diploma programs in IT Engineering and Civil Engineering.')) ?></p>
  <div class="contact-cards">
    <div class="contact-card">
      <i class="fas fa-map-marker-alt"></i>
      <h4>Location</h4>
      <p><?= nl2br(htmlspecialchars($ls('contact_address','Jhiljhille, Jhapa, Province No. 1, Nepal'))) ?></p>
    </div>
    <div class="contact-card">
      <i class="fas fa-envelope"></i>
      <h4>Email</h4>
      <p><?= htmlspecialchars($ls('contact_email','info@gms.edu.np')) ?></p>
    </div>
    <div class="contact-card">
      <i class="fas fa-phone"></i>
      <h4>Phone</h4>
      <p><?= htmlspecialchars($ls('contact_phone','—')) ?></p>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div>
      <div class="footer-brand">
        <img src="assets/images/gmscrop.png" alt="GMS">
        <h3>Ganeshman Singh Academy</h3>
      </div>
      <p><?= htmlspecialchars($ls('footer_about','A CTEVT affiliated technical college committed to producing skilled engineers in Jhiljhille, Jhapa, Nepal.')) ?></p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><i class="fas fa-chevron-right"></i><a href="#about">About College</a></li>
        <li><i class="fas fa-chevron-right"></i><a href="#programs">IT Engineering</a></li>
        <li><i class="fas fa-chevron-right"></i><a href="#programs">Civil Engineering</a></li>
        <li><i class="fas fa-chevron-right"></i><a href="#notices">Notices</a></li>
        <li><i class="fas fa-chevron-right"></i><a href="login">Staff Portal</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <ul>
        <li><i class="fas fa-map-marker-alt"></i> Jhiljhille, Jhapa, Nepal</li>
        <li><i class="fas fa-globe"></i> <?= htmlspecialchars($ls('contact_website','gms-sms.42web.io')) ?></li>
        <li><i class="fas fa-envelope"></i> <?= htmlspecialchars($ls('contact_email','info@gms.edu.np')) ?></li>
        <?php if($ls('contact_phone')): ?><li><i class="fas fa-phone"></i> <?= htmlspecialchars($ls('contact_phone')) ?></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> Ganeshman Singh Academy. All rights reserved.</p>
    <p>Developed by <a href="https://raghavdhakal.com.np" target="_blank">Raghav Dhakal</a>, Bijay Baniya, Sandip Neupane, Mirak Limbu, Pujan BK, Rojina Thapa</p>
  </div>
</footer>

<!-- NOTICE DETAIL MODAL -->
<div class="modal-overlay" id="noticeModal" onclick="if(event.target===this)closeModal()">
  <div class="notice-modal">
    <div class="modal-header-bar">
      <h3><i class="fas fa-bullhorn"></i> Notice Detail</h3>
      <button class="modal-close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <span class="modal-tag"><span id="mTarget">—</span></span>
      <h2 class="modal-title" id="mTitle">—</h2>
      <div class="modal-meta">
        <span><i class="fas fa-calendar-alt"></i> <span id="mDate">—</span></span>
        <span><i class="fas fa-user"></i> <span id="mAuthor">—</span></span>
      </div>
      <img id="mImg" src="" alt="Notice image" class="modal-img" style="display:none">
      <div class="modal-content-body" id="mBody">—</div>
    </div>
  </div>
</div>

<script>
// ── MOBILE MENU ───────────────────────────────────────────────────────────
document.getElementById('hamburger').addEventListener('click',()=>{ document.getElementById('mobileMenu').classList.toggle('open'); });
function closeMobile(){ document.getElementById('mobileMenu').classList.remove('open'); }

// ── NOTICE MODAL ──────────────────────────────────────────────────────────
function openModal(card){
  const n = JSON.parse(card.dataset.notice);
  document.getElementById('mTarget').textContent = n.target || 'General';
  document.getElementById('mTitle').textContent  = n.title;
  document.getElementById('mDate').textContent   = n.date;
  document.getElementById('mAuthor').textContent = n.author || 'Admin';
  document.getElementById('mBody').textContent   = n.content;
  const img = document.getElementById('mImg');
  if(n.image){ img.src='assets/uploads/notices/'+n.image; img.style.display='block'; }
  else { img.style.display='none'; }
  document.getElementById('noticeModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeModal(){
  document.getElementById('noticeModal').classList.remove('open');
  document.body.style.overflow='';
}
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeModal(); });

// ── GALLERY SLIDESHOW ────────────────────────────────────────────────────
(function(){
  const track=document.getElementById('galleryTrack');
  const dotsW=document.getElementById('galleryDots');
  const thumbW=document.getElementById('galleryThumbs');
  const slides=track.querySelectorAll('.gallery-slide');
  const total=slides.length;
  let cur=0,timer=null;

  slides.forEach((_,i)=>{
    const d=document.createElement('div'); d.className='gallery-dot'+(i===0?' active':''); d.onclick=()=>goTo(i); dotsW.appendChild(d);
    const t=document.createElement('div'); t.className='gallery-thumb'+(i===0?' active':'');
    const img=slides[i].querySelector('img');
    if(img){const ti=document.createElement('img');ti.src=img.src;ti.alt='';t.appendChild(ti);}
    else{const ph=document.createElement('div');ph.className='gallery-thumb-placeholder';const ic=slides[i].querySelector('i');ph.innerHTML=ic?ic.outerHTML:'<i class="fas fa-image"></i>';t.appendChild(ph);}
    t.onclick=()=>goTo(i); thumbW.appendChild(t);
  });

  function upd(){
    track.style.transform=`translateX(-${cur*100}%)`;
    dotsW.querySelectorAll('.gallery-dot').forEach((d,i)=>d.classList.toggle('active',i===cur));
    thumbW.querySelectorAll('.gallery-thumb').forEach((t,i)=>t.classList.toggle('active',i===cur));
  }
  function goTo(n){cur=(n+total)%total;upd();clearInterval(timer);timer=setInterval(()=>goTo(cur+1),4500);}
  document.getElementById('galleryPrev').onclick=()=>goTo(cur-1);
  document.getElementById('galleryNext').onclick=()=>goTo(cur+1);
  let sx=0;
  track.addEventListener('touchstart',e=>{sx=e.touches[0].clientX;},{passive:true});
  track.addEventListener('touchend',e=>{const dx=e.changedTouches[0].clientX-sx;if(Math.abs(dx)>40)goTo(dx<0?cur+1:cur-1);});
  timer=setInterval(()=>goTo(cur+1),4500); upd();
})();
</script>
</body>
</html>