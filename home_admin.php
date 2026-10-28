<?php
require_once 'includes/auth.php';
requireRole('admin', 'login.php');

$db = db();

// ── Auto-migration ─────────────────────────────────────────────────────────
$db->query("
    CREATE TABLE IF NOT EXISTS landing_settings (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        setting_key   VARCHAR(80)  NOT NULL UNIQUE,
        setting_value TEXT         NOT NULL DEFAULT '',
        updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$cols = $db->fetchAll("SHOW COLUMNS FROM notices LIKE 'show_on_landing'");
if (empty($cols)) {
    $db->query("ALTER TABLE notices ADD COLUMN show_on_landing TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
}
// Programs table
$db->query("
    CREATE TABLE IF NOT EXISTS programs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        title       VARCHAR(255) NOT NULL,
        icon        VARCHAR(80)  DEFAULT 'fa-graduation-cap',
        description TEXT         DEFAULT '',
        badge       VARCHAR(100) DEFAULT '3 Years · 6 Semesters',
        sort_order  INT          DEFAULT 0,
        is_active   TINYINT(1)   DEFAULT 1,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
// Seed default programs if table is empty
$progCount = $db->fetchOne("SELECT COUNT(*) as c FROM programs")['c'] ?? 0;
if ((int)$progCount === 0) {
    $db->execute("INSERT INTO programs (title,icon,description,badge,sort_order) VALUES (?,?,?,?,?)", 'ssssi',
        ['Diploma in IT Engineering','fa-laptop-code',
         'A comprehensive 3-year Diploma program covering computer hardware, software development, networking, database systems, and emerging technologies. Graduates are equipped to work as IT technicians, network administrators, software developers, and system support specialists.',
         '3 Years · 6 Semesters', 1]);
    $db->execute("INSERT INTO programs (title,icon,description,badge,sort_order) VALUES (?,?,?,?,?)", 'ssssi',
        ['Diploma in Civil Engineering','fa-hard-hat',
         'A rigorous 3-year Diploma program encompassing structural engineering, surveying, construction management, AutoCAD, and material science. Graduates pursue careers as junior engineers, site supervisors, quantity surveyors and draftspersons.',
         '3 Years · 6 Semesters', 2]);
}

// Gallery table
$db->query("
    CREATE TABLE IF NOT EXISTS gallery_images (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        filename   VARCHAR(255) NOT NULL,
        caption    VARCHAR(255) DEFAULT '',
        sort_order INT DEFAULT 0,
        is_active  TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Helpers ────────────────────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $rows = db()->fetchAll("SELECT setting_key, setting_value FROM landing_settings");
        $cache = [];
        foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
    }
    return $cache[$key] ?? $default;
}
function saveSetting(string $key, string $value): void {
    db()->execute(
        "INSERT INTO landing_settings (setting_key, setting_value)
         VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        'ss', [$key, $value]
    );
}
function s(string $key, string $default = ''): string {
    return htmlspecialchars(getSetting($key, $default), ENT_QUOTES, 'UTF-8');
}

// ── POST handler ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $goTab  = $_POST['tab']    ?? 'notices';

    if ($action === 'save_settings') {
        $keys = ['prog_it','prog_civil','prog_structure',
                 'contact_address','contact_email','contact_phone','contact_website',
                 'quote_text','quote_cite','hero_video','hero_tagline'];
        foreach ($keys as $k) {
            if (array_key_exists($k, $_POST)) saveSetting($k, sanitize($_POST[$k]));
        }
        setFlash('success', 'Settings saved.');
    }

    elseif ($action === 'pin_notice') {
        $id = (int)($_POST['notice_id'] ?? 0);
        if ($id) { $db->execute("UPDATE notices SET show_on_landing=1 WHERE id=?", 'i', [$id]); setFlash('success','Notice pinned to homepage.'); }
    }

    elseif ($action === 'unpin_notice') {
        $id = (int)($_POST['notice_id'] ?? 0);
        if ($id) { $db->execute("UPDATE notices SET show_on_landing=0 WHERE id=?", 'i', [$id]); setFlash('success','Notice unpinned from homepage.'); }
    }

    elseif ($action === 'add_program') {
        $title = sanitize($_POST['prog_title'] ?? '');
        $icon  = sanitize($_POST['prog_icon']  ?? 'fa-graduation-cap');
        $desc  = sanitize($_POST['prog_desc']  ?? '');
        $badge = sanitize($_POST['prog_badge'] ?? '3 Years · 6 Semesters');
        if ($title) {
            $maxOrder = $db->fetchOne("SELECT COALESCE(MAX(sort_order),0)+1 as n FROM programs")['n'] ?? 1;
            $db->execute("INSERT INTO programs (title,icon,description,badge,sort_order) VALUES (?,?,?,?,?)",
                'ssssi', [$title,$icon,$desc,$badge,(int)$maxOrder]);
            setFlash('success', "Program '$title' added.");
        }
        redirect("home_admin.php?tab=programs");
    }

    elseif ($action === 'edit_program') {
        $id    = (int)($_POST['prog_id'] ?? 0);
        $title = sanitize($_POST['prog_title'] ?? '');
        $icon  = sanitize($_POST['prog_icon']  ?? 'fa-graduation-cap');
        $desc  = sanitize($_POST['prog_desc']  ?? '');
        $badge = sanitize($_POST['prog_badge'] ?? '');
        if ($id && $title) {
            $db->execute("UPDATE programs SET title=?,icon=?,description=?,badge=? WHERE id=?",
                'ssssi', [$title,$icon,$desc,$badge,$id]);
            setFlash('success', "Program updated.");
        }
        redirect("home_admin.php?tab=programs");
    }

    elseif ($action === 'delete_program') {
        $id = (int)($_POST['prog_id'] ?? 0);
        $db->execute("DELETE FROM programs WHERE id=?", 'i', [$id]);
        setFlash('success', "Program deleted.");
        redirect("home_admin.php?tab=programs");
    }

    elseif ($action === 'toggle_program') {
        $id = (int)($_POST['prog_id'] ?? 0);
        $db->execute("UPDATE programs SET is_active = 1 - is_active WHERE id=?", 'i', [$id]);
        redirect("home_admin.php?tab=programs");
    }

    elseif ($action === 'upload_gallery') {
        if (!empty($_FILES['gimg']['name'])) {
            $file = $_FILES['gimg'];
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (in_array($file['type'], $allowed) && $file['size'] < 5*1024*1024) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fname = 'gallery_'.time().'_'.mt_rand(1000,9999).'.'.$ext;
                $dir = __DIR__.'/assets/uploads/gallery/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $dir.$fname)) {
                    $caption = sanitize($_POST['gcaption'] ?? '');
                    $db->execute("INSERT INTO gallery_images (filename,caption) VALUES (?,?)", 'ss', [$fname,$caption]);
                    setFlash('success', 'Image uploaded to gallery.');
                } else { setFlash('danger','Upload failed. Check folder permissions.'); }
            } else { setFlash('danger','Invalid file type or size exceeds 5MB.'); }
        }
        $goTab = 'gallery';
    }

    elseif ($action === 'delete_gallery') {
        $id = (int)($_POST['img_id'] ?? 0);
        $img = $db->fetchOne("SELECT filename FROM gallery_images WHERE id=?", 'i', [$id]);
        if ($img) {
            @unlink(__DIR__.'/assets/uploads/gallery/'.$img['filename']);
            $db->execute("DELETE FROM gallery_images WHERE id=?", 'i', [$id]);
            setFlash('success', 'Image removed from gallery.');
        }
        $goTab = 'gallery';
    }

    redirect("home_admin.php?tab={$goTab}");
}

$activeTab     = $_GET['tab'] ?? 'notices';
$programs = $db->fetchAll("SELECT * FROM programs ORDER BY sort_order, id");
$pinnedNotices = $db->fetchAll("SELECT n.*, COALESCE(u.username,'Admin') as author_name FROM notices n LEFT JOIN users u ON n.posted_by=u.id WHERE n.show_on_landing=1 AND n.is_active=1 ORDER BY n.created_at DESC");
$allNotices    = $db->fetchAll("SELECT n.id,n.title,n.target_role,n.show_on_landing,n.created_at,COALESCE(u.username,'Admin') as author_name FROM notices n LEFT JOIN users u ON n.posted_by=u.id WHERE n.is_active=1 ORDER BY n.created_at DESC LIMIT 60");
$galleryImages = $db->fetchAll("SELECT * FROM gallery_images WHERE is_active=1 ORDER BY sort_order,created_at DESC");
$pinnedCount   = count($pinnedNotices);
$galleryCount  = count($galleryImages);
$user          = currentUser();
?>
<?php
// ── Inline header (home_admin.php is in root, not admin/, so paths differ) ──
$user  = currentUser();
$flash_msg = getFlash();
$notifNotices = db()->fetchAll("
    SELECT title, content, created_at FROM notices
    WHERE is_active=1 AND (target_role='all' OR target_role='admin')
    ORDER BY created_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Control – Admin | Ganeshman Singh Academy</title>
    <link rel="icon" type="image/png" href="assets/images/gmscrop.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">

<aside class="sidebar" id="sidebar">
    <a href="admin/dashboard.php" class="sidebar-brand" style="text-decoration:none;color:inherit;display:flex;align-items:center;">
        <div class="brand-icon"><img src="assets/images/gms.png" alt="gms"></div>
        <div class="brand-text">
            <h3>Ganeshman Singh</h3>
            <span>Academy – Admin</span>
        </div>
    </a>
    <nav class="sidebar-nav">
        <span class="nav-section-title">Homepage</span>
        <a href="home_admin.php?tab=hero"     class="nav-item <?= $activeTab==='hero'?'active':'' ?>"><i class="fas fa-photo-video"></i> Hero</a>
        <a href="home_admin.php?tab=notices"  class="nav-item <?= $activeTab==='notices'?'active':'' ?>"><i class="fas fa-thumbtack"></i> Notices <?php if($pinnedCount>0): ?><span class="badge badge-success" style="margin-left:auto;font-size:.6rem;"><?= $pinnedCount ?></span><?php endif; ?></a>
        <a href="home_admin.php?tab=gallery"  class="nav-item <?= $activeTab==='gallery'?'active':'' ?>"><i class="fas fa-images"></i> Gallery</a>
        <a href="home_admin.php?tab=programs" class="nav-item <?= $activeTab==='programs'?'active':'' ?>"><i class="fas fa-graduation-cap"></i> Programs</a>
        <a href="home_admin.php?tab=contact"  class="nav-item <?= $activeTab==='contact'?'active':'' ?>"><i class="fas fa-phone"></i> Contact</a>
        <a href="home_admin.php?tab=quote"    class="nav-item <?= $activeTab==='quote'?'active':'' ?>"><i class="fas fa-quote-right"></i> Quote</a>
        <span class="nav-section-title">Go To</span>
        <a href="admin/dashboard.php" class="nav-item"><i class="fas fa-arrow-left"></i> Main Admin Panel</a>
        <a href="home.php" target="_blank" class="nav-item"><i class="fas fa-external-link-alt"></i> View Live Page</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
            <div class="user-info">
                <h4><?= htmlspecialchars($user['name']) ?></h4>
                <span><?= $user['role'] ?></span>
            </div>
            <a href="logout.php" class="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>

<div class="main-content" id="mainContent">
    <header class="topbar">
        <button class="hamburger-btn" onclick="toggleSidebar()" title="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <h2>Homepage Control</h2>
            <div class="breadcrumb">Admin Panel › Homepage Control</div>
        </div>
        <div class="topbar-actions">
            <a href="home.php" target="_blank" class="btn btn-primary btn-sm">
                <i class="fas fa-external-link-alt"></i> View Live
            </a>
        </div>
    </header>

    <?php if (!empty($flash_msg)): ?>
    <div style="padding:0 28px;padding-top:16px;">
        <div class="alert alert-<?= $flash_msg['type'] ?>">
            <i class="fas fa-<?= $flash_msg['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash_msg['message']) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="page-content">
<style>
/* Override page title for this sub-page */
.nr{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
.nr:last-child{border-bottom:none;padding-bottom:0;}
.nr-ico{width:36px;height:36px;border-radius:9px;flex-shrink:0;background:rgba(var(--primary-rgb,30,58,138),.06);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.84rem;color:var(--primary);}
.nr-b{flex:1;min-width:0;}
.nr-b h4{font-size:.85rem;font-weight:700;color:var(--text-primary);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.nr-b p{font-size:.72rem;color:var(--text-secondary);}
.nr-a{display:flex;gap:6px;flex-shrink:0;}
.gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;}
.gal-item{position:relative;border-radius:var(--radius-sm);overflow:hidden;border:1.5px solid var(--border);background:#f8fafc;}
.gal-item img{width:100%;height:130px;object-fit:cover;display:block;}
.gal-item-foot{padding:7px 10px;display:flex;align-items:center;justify-content:space-between;gap:6px;}
.gal-caption{font-size:.72rem;color:var(--text-secondary);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.gal-del{width:26px;height:26px;border-radius:6px;background:rgba(239,68,68,.09);border:none;color:#ef4444;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.75rem;transition:background .15s;}
.gal-del:hover{background:rgba(239,68,68,.2);}
.upload-zone{border:2px dashed var(--border);border-radius:var(--radius);padding:32px;text-align:center;transition:border-color .2s,background .2s;cursor:pointer;}
.upload-zone:hover{border-color:var(--primary);background:rgba(var(--primary-rgb,30,58,138),.02);}
.upload-zone i{font-size:2rem;color:var(--text-secondary);display:block;margin-bottom:10px;}
.upload-zone p{font-size:.82rem;color:var(--text-secondary);}
.pinned-badge{display:inline-flex;align-items:center;gap:4px;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:50px;background:rgba(16,185,129,.1);color:#059669;}
</style>

<!-- Page Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="font-size:1.3rem;font-weight:800;color:var(--text-primary);margin-bottom:2px;">
            <i class="fas fa-home" style="color:var(--primary);font-size:1.1rem;margin-right:6px;"></i>Homepage Control
        </h2>
        <p style="font-size:.78rem;color:var(--text-secondary);">
            <?= $pinnedCount ?> notice<?= $pinnedCount!==1?'s':'' ?> pinned &nbsp;·&nbsp;
            <?= $galleryCount ?> gallery image<?= $galleryCount!==1?'s':'' ?>
        </p>
    </div>
    <a href="home.php" target="_blank" class="btn btn-primary btn-sm">
        <i class="fas fa-external-link-alt"></i> View Live Page
    </a>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:22px;flex-wrap:wrap;" id="haTabBar">
    <?php
    $tabs = [
        'hero'     => ['fas fa-photo-video',   'Hero',     0],
        'notices'  => ['fas fa-thumbtack',   'Notices',  $pinnedCount],
        'gallery'  => ['fas fa-images',       'Gallery',  $galleryCount],
        'programs' => ['fas fa-graduation-cap','Programs', count($programs)],
        'contact'  => ['fas fa-phone',        'Contact',  0],
        'quote'    => ['fas fa-quote-right',  'Quote',    0],
    ];
    foreach($tabs as $id => [$ico,$lbl,$cnt]): ?>
    <button onclick="haTab('<?= $id ?>')" id="ha-tab-<?= $id ?>"
        class="ha-tab-btn"
        style="padding:10px 20px;font-size:.84rem;font-weight:700;color:var(--text-secondary);cursor:pointer;border:none;background:none;border-bottom:2.5px solid transparent;margin-bottom:-2px;transition:all .2s;font-family:inherit;display:flex;align-items:center;gap:7px;">
        <i class="fas <?= $ico ?>"></i> <?= $lbl ?>
        <?php if($cnt>0): ?>
        <span style="background:var(--primary);color:#fff;font-size:.6rem;font-weight:800;padding:1px 6px;border-radius:50px;"><?= $cnt ?></span>
        <?php endif; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ══ HERO / VIDEO ═════════════════════════════════════════════════════════ -->
<div class="ha-pane" id="ha-hero" style="display:block;">
    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-photo-video"></i> Hero Background Video</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="tab" value="hero">
                    <div class="form-group">
                        <label>Video URL (Cloudinary or any direct .mp4 link)</label>
                        <input type="text" name="hero_video" id="heroVideoInput" class="form-control"
                               value="<?= s('hero_video','assets/videos/hero.mp4') ?>"
                               placeholder="https://res.cloudinary.com/your-cloud/video/upload/f_mp4/v123456/hero.mp4"
                               oninput="document.getElementById('heroVideoPreview').src=this.value; document.getElementById('heroVideoPreview').load();">
                        <p style="font-size:.76rem;color:var(--text-secondary);margin-top:6px;line-height:1.6;">
                            Paste the direct video file link — it must end in a playable format (usually <code>.mp4</code>).
                            On Cloudinary, use the <strong>Delivery URL</strong> from your Media Library (the one under
                            <code>/video/upload/</code>), not the console page link. If your upload doesn't have a real
                            extension, add <code>f_mp4</code> to the URL to force MP4 delivery, e.g.
                            <code>.../upload/f_mp4/v123456/hero.mp4</code>.
                        </p>
                    </div>
                    <div class="form-group">
                        <label>Tagline (text under the heading)</label>
                        <input type="text" name="hero_tagline" class="form-control" value="<?= s('hero_tagline','Knowledge · Integrity · Excellence') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Hero Settings</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-eye"></i> Live Preview</h3></div>
            <div class="card-body">
                <video id="heroVideoPreview" src="<?= s('hero_video','assets/videos/hero.mp4') ?>"
                       controls muted playsinline
                       style="width:100%;border-radius:10px;background:#0b1320;max-height:280px;object-fit:cover;"
                       onerror="document.getElementById('heroVideoError').style.display='block';"
                       oncanplay="document.getElementById('heroVideoError').style.display='none';">
                </video>
                <p id="heroVideoError" style="display:none;color:var(--danger);font-size:.8rem;margin-top:8px;">
                    <i class="fas fa-exclamation-triangle"></i> This URL didn't load as a playable video. Double-check it's a direct file link (open it in a new browser tab — it should play immediately, not show a webpage).
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ══ NOTICES ══════════════════════════════════════════════════════════════ -->
<div class="ha-pane" id="ha-notices" style="display:none;">
    <div class="grid-2" style="margin-bottom:18px;">
        <!-- Pin / Unpin -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-thumbtack"></i> All Notices — Pin / Unpin</h3>
                <span class="badge badge-info"><?= count($allNotices) ?></span>
            </div>
            <div class="card-body" style="padding:0;max-height:460px;overflow-y:auto;">
                <?php if(empty($allNotices)): ?>
                <div class="empty-state" style="padding:32px;"><i class="fas fa-bell-slash"></i><p>No notices in database yet.</p></div>
                <?php else: ?>
                <?php foreach($allNotices as $n): $pinned=(bool)$n['show_on_landing']; ?>
                <div class="nr" style="padding:12px 16px;">
                    <div class="nr-ico" style="<?= $pinned?'background:rgba(16,185,129,.1);color:#059669;border-color:rgba(16,185,129,.2);':'' ?>">
                        <i class="fas fa-<?= $pinned?'thumbtack':'bell' ?>"></i>
                    </div>
                    <div class="nr-b">
                        <h4><?= htmlspecialchars($n['title']) ?></h4>
                        <p><?= formatDate(substr($n['created_at'], 0, 10)) ?> · <?= htmlspecialchars($n['author_name']) ?>
                        <?php if($pinned): ?> <span class="pinned-badge"><i class="fas fa-thumbtack"></i> Pinned</span><?php endif; ?>
                        </p>
                    </div>
                    <div class="nr-a">
                        <?php if($pinned): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="unpin_notice">
                            <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                            <input type="hidden" name="tab" value="notices">
                            <button type="submit" class="btn btn-danger btn-sm" title="Unpin" onclick="return confirm('Remove from homepage?')">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="pin_notice">
                            <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                            <input type="hidden" name="tab" value="notices">
                            <button type="submit" class="btn btn-success btn-sm" title="Pin to homepage">
                                <i class="fas fa-thumbtack"></i> Pin
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Currently Pinned -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list-check"></i> Currently Pinned on Homepage</h3>
                <span class="badge badge-success"><?= $pinnedCount ?></span>
            </div>
            <div class="card-body" style="padding:0;max-height:460px;overflow-y:auto;">
                <?php if(empty($pinnedNotices)): ?>
                <div class="empty-state" style="padding:32px;"><i class="fas fa-bell-slash"></i><p>No notices pinned yet.</p></div>
                <?php else: ?>
                <?php foreach($pinnedNotices as $n): ?>
                <div class="nr" style="padding:12px 16px;">
                    <div class="nr-ico" style="background:rgba(16,185,129,.1);color:#059669;border-color:rgba(16,185,129,.2);">
                        <i class="fas fa-thumbtack"></i>
                    </div>
                    <div class="nr-b">
                        <h4><?= htmlspecialchars($n['title']) ?></h4>
                        <p style="margin-bottom:3px;"><?= htmlspecialchars(mb_substr(strip_tags($n['content']),0,80)) ?>…</p>
                        <p><?= formatDate(substr($n['created_at'], 0, 10)) ?></p>
                    </div>
                    <div class="nr-a">
                        <form method="POST">
                            <input type="hidden" name="action" value="unpin_notice">
                            <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                            <input type="hidden" name="tab" value="notices">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove from homepage?')">
                                <i class="fas fa-times"></i> Unpin
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══ GALLERY ══════════════════════════════════════════════════════════════ -->
<div class="ha-pane" id="ha-gallery" style="display:none;">
    <div class="grid-2" style="margin-bottom:18px;align-items:start;">
        <!-- Upload -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-cloud-upload-alt"></i> Upload Image</h3></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_gallery">
                    <input type="hidden" name="tab" value="gallery">
                    <div class="form-group">
                        <label>Image File * <small style="color:var(--text-secondary);">JPG, PNG, WEBP · max 5MB</small></label>
                        <input type="file" name="gimg" class="form-control" accept="image/*" required
                            onchange="previewGal(this)">
                    </div>
                    <div id="galPreview" style="display:none;margin-bottom:14px;">
                        <img id="galPreviewImg" style="width:100%;max-height:180px;object-fit:cover;border-radius:var(--radius-sm);border:1.5px solid var(--border);">
                    </div>
                    <div class="form-group">
                        <label>Caption <small style="color:var(--text-secondary);">optional</small></label>
                        <input type="text" name="gcaption" class="form-control" placeholder="e.g. College Annual Day 2082">
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-cloud-upload-alt"></i> Upload to Gallery
                    </button>
                </form>
            </div>
        </div>
        <!-- Stats -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-info-circle"></i> Gallery Info</h3></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div style="background:var(--bg-secondary,#f8fafc);border-radius:var(--radius-sm);padding:16px;text-align:center;border:1.5px solid var(--border);">
                        <div style="font-size:2rem;font-weight:800;color:var(--primary);line-height:1;"><?= $galleryCount ?></div>
                        <div style="font-size:.7rem;color:var(--text-secondary);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Total Images</div>
                    </div>
                    <div style="background:var(--bg-secondary,#f8fafc);border-radius:var(--radius-sm);padding:16px;text-align:center;border:1.5px solid var(--border);">
                        <div style="font-size:2rem;font-weight:800;color:#10b981;line-height:1;"><?= $galleryCount ?></div>
                        <div style="font-size:.7rem;color:var(--text-secondary);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Live on Page</div>
                    </div>
                </div>
                <p style="font-size:.78rem;color:var(--text-secondary);line-height:1.6;">
                    Images are stored in <code>assets/uploads/gallery/</code> and displayed in the Gallery section of the homepage. Delete an image here to remove it from the public page.
                </p>
            </div>
        </div>
    </div>

    <!-- Gallery grid -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-images"></i> Gallery Images</h3>
            <span class="badge badge-info"><?= $galleryCount ?></span>
        </div>
        <div class="card-body">
            <?php if(empty($galleryImages)): ?>
            <div class="empty-state"><i class="fas fa-images"></i><h3>No images yet</h3><p>Upload images using the form above.</p></div>
            <?php else: ?>
            <div class="gal-grid">
                <?php foreach($galleryImages as $img): ?>
                <div class="gal-item">
                    <img src="assets/uploads/gallery/<?= htmlspecialchars($img['filename']) ?>"
                         onerror="this.src='assets/images/gmscrop.png'" alt="">
                    <div class="gal-item-foot">
                        <span class="gal-caption"><?= $img['caption'] ? htmlspecialchars($img['caption']) : '<em>No caption</em>' ?></span>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="delete_gallery">
                            <input type="hidden" name="img_id" value="<?= $img['id'] ?>">
                            <button type="submit" class="gal-del" onclick="return confirm('Delete this image?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ PROGRAMS ══════════════════════════════════════════════════════════════ -->
<div class="ha-pane" id="ha-programs" style="display:none;">

    <div class="grid-2" style="align-items:start;margin-bottom:18px;">

        <!-- Add New Program -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-plus"></i> Add New Program</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_program">
                    <div class="form-group">
                        <label>Program Title *</label>
                        <input type="text" name="prog_title" class="form-control" required
                            placeholder="e.g. Diploma in IT Engineering">
                    </div>
                    <div class="form-group">
                        <label>FontAwesome Icon Class</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="prog_icon" id="addIcon" class="form-control"
                                value="fa-graduation-cap" oninput="previewIcon('addIcon','addIconPrev')">
                            <i id="addIconPrev" class="fas fa-graduation-cap"
                               style="font-size:1.4rem;color:var(--primary);width:28px;text-align:center;flex-shrink:0;"></i>
                        </div>
                        <small style="color:var(--text-secondary);font-size:.72rem;">
                            Any <a href="https://fontawesome.com/icons" target="_blank">FontAwesome 6</a> class e.g. fa-laptop-code, fa-hard-hat, fa-flask
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Badge Text</label>
                        <input type="text" name="prog_badge" class="form-control"
                            value="3 Years · 6 Semesters" placeholder="e.g. 3 Years · 6 Semesters">
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="prog_desc" class="form-control" rows="5" required
                            placeholder="Write a detailed program description…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-plus"></i> Add Program
                    </button>
                </form>
            </div>
        </div>

        <!-- Programs List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> All Programs</h3>
                <span class="badge badge-info"><?= count($programs) ?></span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($programs)): ?>
                <div class="empty-state" style="padding:32px;"><i class="fas fa-graduation-cap"></i><p>No programs yet.</p></div>
                <?php else: ?>
                <?php foreach ($programs as $prog): ?>
                <div class="nr" style="padding:14px 16px;">
                    <div class="nr-ico" style="<?= $prog['is_active'] ? '' : 'opacity:.45;' ?>">
                        <i class="fas <?= htmlspecialchars($prog['icon']) ?>"></i>
                    </div>
                    <div class="nr-b">
                        <h4 style="<?= $prog['is_active'] ? '' : 'opacity:.5;' ?>">
                            <?= htmlspecialchars($prog['title']) ?>
                        </h4>
                        <p><?= htmlspecialchars($prog['badge']) ?>
                            <?php if (!$prog['is_active']): ?>
                            <span class="badge badge-danger" style="font-size:.6rem;margin-left:4px;">Hidden</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="nr-a">
                        <button class="btn btn-info btn-sm"
                            onclick="openEditProgram(<?= htmlspecialchars(json_encode($prog)) ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_program">
                            <input type="hidden" name="prog_id" value="<?= $prog['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $prog['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                title="<?= $prog['is_active'] ? 'Hide from homepage' : 'Show on homepage' ?>">
                                <i class="fas fa-<?= $prog['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete_program">
                            <input type="hidden" name="prog_id" value="<?= $prog['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Delete this program from the homepage?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Program Structure Note -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-info-circle"></i> Program Structure Note</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="tab" value="programs">
                <div class="form-group" style="margin-bottom:0;">
                    <textarea name="prog_structure" class="form-control" rows="2"><?= s('prog_structure','Both diploma programs are affiliated with CTEVT and consist of 6 semesters, each of 6 months duration — totalling 3 years combining theory, practicals, and a final-semester project/internship.') ?></textarea>
                </div>
                <div style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal-overlay" id="editProgModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Program</h3>
            <button class="modal-close" onclick="Modal.close('editProgModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_program">
            <input type="hidden" name="prog_id" id="editProgId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Program Title *</label>
                    <input type="text" name="prog_title" id="editProgTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>FontAwesome Icon</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" name="prog_icon" id="editProgIcon" class="form-control"
                            oninput="previewIcon('editProgIcon','editIconPrev')">
                        <i id="editIconPrev" class="fas fa-graduation-cap"
                           style="font-size:1.4rem;color:var(--primary);width:28px;text-align:center;flex-shrink:0;"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Badge Text</label>
                    <input type="text" name="prog_badge" id="editProgBadge" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Description *</label>
                    <textarea name="prog_desc" id="editProgDesc" class="form-control" rows="6" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editProgModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ CONTACT ══════════════════════════════════════════════════════════════ -->
<div class="ha-pane" id="ha-contact" style="display:none;">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-phone"></i> Edit Contact Details</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="tab" value="contact">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="contact_address" class="form-control" rows="3"><?= s('contact_address','Jhiljhille, Jhapa, Province No. 1, Nepal') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="contact_email" class="form-control" value="<?= s('contact_email','info@gms.edu.np') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?= s('contact_phone') ?>" placeholder="+977-...">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="text" name="contact_website" class="form-control" value="<?= s('contact_website','gms-sms.42web.io') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Contact Info</button>
            </form>
        </div>
    </div>
</div>

<!-- ══ QUOTE ══════════════════════════════════════════════════════════════ -->
<div class="ha-pane" id="ha-quote" style="display:none;">
    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-quote-right"></i> Edit Quote</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="tab" value="quote">
                    <div class="form-group">
                        <label>Quote Text</label>
                        <textarea name="quote_text" class="form-control" rows="4"><?= s('quote_text','The beautiful thing about learning is that nobody can take it away from you.') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Attribution</label>
                        <input type="text" name="quote_cite" class="form-control" value="<?= s('quote_cite','B.B. King') ?>" placeholder="e.g. Nelson Mandela">
                    </div>
                    <button type="submit" class="btn btn-primary btn-full"><i class="fas fa-save"></i> Save Quote</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-eye"></i> Preview</h3></div>
            <div class="card-body">
                <div style="background:#0b1320;border-radius:var(--radius);padding:24px;border:1px solid rgba(184,137,42,.15);">
                    <div style="padding:18px;background:rgba(184,137,42,.1);border-radius:10px;border-left:3px solid #b8892a;">
                        <p style="font-size:.88rem;color:rgba(255,255,255,.7);line-height:1.75;font-style:italic;">"<?= s('quote_text','The beautiful thing about learning is that nobody can take it away from you.') ?>"</p>
                        <cite style="font-size:.75rem;color:#d4a84b;font-weight:700;display:block;margin-top:10px;">— <?= s('quote_cite','B.B. King') ?></cite>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var activeHaTab = '<?= htmlspecialchars($activeTab) ?>';

function haTab(id) {
    document.querySelectorAll('.ha-pane').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.ha-tab-btn').forEach(b => {
        b.style.color = 'var(--text-secondary)';
        b.style.borderBottomColor = 'transparent';
    });
    var pane = document.getElementById('ha-' + id);
    if (pane) pane.style.display = 'block';
    var btn = document.getElementById('ha-tab-' + id);
    if (btn) {
        btn.style.color = 'var(--primary)';
        btn.style.borderBottomColor = 'var(--primary)';
    }
    history.replaceState({}, '', '?tab=' + id);
}

function previewGal(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('galPreviewImg').src = e.target.result;
            document.getElementById('galPreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Init on load
haTab(activeHaTab);

// Preview FontAwesome icon live
function previewIcon(inputId, previewId) {
    var val = document.getElementById(inputId).value.trim();
    var el  = document.getElementById(previewId);
    el.className = 'fas ' + val;
}

// Open edit modal and populate fields
function openEditProgram(prog) {
    document.getElementById('editProgId').value    = prog.id;
    document.getElementById('editProgTitle').value = prog.title;
    document.getElementById('editProgIcon').value  = prog.icon;
    document.getElementById('editProgBadge').value = prog.badge;
    document.getElementById('editProgDesc').value  = prog.description;
    document.getElementById('editIconPrev').className = 'fas ' + prog.icon;
    Modal.open('editProgModal');
}
</script>

    </div><!-- end .page-content -->
</div><!-- end .main-content -->
</div><!-- end .app-wrapper -->
<script src="assets/js/main.js"></script>
</body>
</html>