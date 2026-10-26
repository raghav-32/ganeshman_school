<?php
// Use absolute URL base so assets load regardless of what URL triggered the 404
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST'] . '/ganeshman_school/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – Page Not Found | Ganeshman Singh Academy</title>
    <link rel="icon" type="image/png" href="<?= $base ?>assets/images/gmscrop.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:       #1a3c5e;
            --primary-light: #2563a8;
            --accent:        #f59e0b;
            --bg:            #f0f4f8;
            --text-primary:  #1e293b;
            --text-secondary:#64748b;
            --border:        #e2e8f0;
            --radius:        12px;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 20%, rgba(26,60,94,.06) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(245,158,11,.06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 520px;
            width: 100%;
            animation: fadeUp .6s ease both;
        }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(24px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .logo-wrap {
            margin-bottom: 28px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .logo-wrap img {
            width: 52px; height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
        }
        .logo-text h2 { font-size: 1rem; font-weight: 800; color: var(--primary); line-height: 1.2; }
        .logo-text span { font-size: .7rem; color: var(--text-secondary); letter-spacing: .5px; text-transform: uppercase; }

        .error-code {
            font-size: clamp(6rem, 20vw, 9rem);
            font-weight: 900;
            line-height: 1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -4px;
            margin-bottom: 4px;
            user-select: none;
        }

        .error-icon {
            font-size: 2.2rem;
            color: var(--accent);
            margin-bottom: 16px;
            display: block;
            animation: wobble 2.5s ease-in-out infinite;
        }
        @keyframes wobble {
            0%,100% { transform: rotate(-6deg); }
            50%      { transform: rotate(6deg); }
        }

        .error-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 10px; }
        .error-desc  { font-size: .9rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 32px; }

        .btn-row { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 28px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px; border-radius: var(--radius);
            font-size: .875rem; font-weight: 700; text-decoration: none;
            transition: transform .15s, box-shadow .15s, background .2s;
            cursor: pointer; border: none; font-family: inherit;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary {
            background: var(--primary); color: #fff;
            box-shadow: 0 4px 14px rgba(26,60,94,.25);
        }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-outline {
            background: #fff; color: var(--primary);
            border: 1.5px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .btn-outline:hover { border-color: var(--primary); }

        .quick-links {
            background: #fff; border: 1.5px solid var(--border);
            border-radius: var(--radius); padding: 20px 24px; text-align: left;
        }
        .quick-links p {
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .8px; color: var(--text-secondary); margin-bottom: 12px;
        }
        .quick-links ul { list-style: none; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .quick-links ul li a {
            display: flex; align-items: center; gap: 8px;
            font-size: .83rem; color: var(--text-primary); text-decoration: none;
            padding: 8px 10px; border-radius: 8px; font-weight: 500;
            transition: background .15s, color .15s;
        }
        .quick-links ul li a:hover { background: rgba(26,60,94,.06); color: var(--primary-light); }
        .quick-links ul li a i { width: 16px; color: var(--accent); font-size: .8rem; }

        @media (max-width: 480px) {
            .quick-links ul { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">

    <a href="<?= $base ?>home" class="logo-wrap">
        <img src="<?= $base ?>assets/images/gms.png"
             onerror="this.style.display='none'" alt="GMS">
        <div class="logo-text">
            <h2>Ganeshman Singh Academy</h2>
            <span>Jhiljhille · Jhapa · Nepal</span>
        </div>
    </a>

    <div class="error-code">404</div>
    <i class="fas fa-compass-drafting error-icon"></i>
    <h1 class="error-title">Page Not Found</h1>
    <p class="error-desc">
        The page you are looking for doesn't exist, has been moved,<br>
        or the URL may be incorrect.
    </p>

    <div class="btn-row">
        <a href="<?= $base ?>home" class="btn btn-primary">
            <i class="fas fa-home"></i> Go to Homepage
        </a>
        <button onclick="history.back()" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Go Back
        </button>
    </div>

    <div class="quick-links">
        <p>Quick Links</p>
        <ul>
            <li><a href="<?= $base ?>home#about"><i class="fas fa-info-circle"></i> About Us</a></li>
            <li><a href="<?= $base ?>home#programs"><i class="fas fa-graduation-cap"></i> Programs</a></li>
            <li><a href="<?= $base ?>home#notices"><i class="fas fa-bullhorn"></i> Notices</a></li>
            <li><a href="<?= $base ?>home#contact"><i class="fas fa-phone"></i> Contact</a></li>
            <li><a href="<?= $base ?>login"><i class="fas fa-sign-in-alt"></i> Portal Login</a></li>
            <li><a href="<?= $base ?>home#gallery"><i class="fas fa-images"></i> Gallery</a></li>
        </ul>
    </div>

</div>
</body>
</html>