<?php
require_once __DIR__ . '/db.php';

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf()
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page, and try again.');
    }
}

function require_login()
{
    if (empty($_SESSION['user'])) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function show_flash()
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="alert alert-' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
}

function page_header($title, $active)
{
    $navigation = [
        'dashboard' => ['Dashboard', 'dashboard.php', 'grid'],
        'customers' => ['Customers', 'customer.php', 'users'],
        'meters' => ['Meters', 'meter.php', 'meter'],
        'bills' => ['Bills', 'bill.php', 'receipt'],
        'payments' => ['Payments', 'payment.php', 'wallet'],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · VoltFlow</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/style.css?v=20261012-login">
    </head>
    <body class="app-body">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php"><span class="brand-mark">V</span><span>VoltFlow<small>UTILITY MANAGER</small></span></a>
        <div class="nav-caption">WORKSPACE</div>
        <nav class="main-nav">
            <?php foreach ($navigation as $key => $item): ?>
                <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($item[1]) ?>">
                    <span class="nav-icon"><?= e(substr($item[0], 0, 1)) ?></span><?= e($item[0]) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <a class="profile-chip" href="account.php"><span class="avatar"><?= e(strtoupper(substr($_SESSION['user'], 0, 1))) ?></span><span><?= e($_SESSION['user']) ?><small>Account security</small></span></a>
            <form method="post" action="../auth/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="logout-button" type="submit"><span class="nav-icon">↪</span> Sign out</button>
            </form>
        </div>
    </aside>
    <main class="main-content">
        <header class="topbar"><div><span class="eyebrow">ELECTRICITY BILLING</span><h1><?= e($title) ?></h1></div><div class="topbar-user"><span class="status-dot"></span> System online <a href="account.php" class="topbar-avatar" aria-label="Account security"><?= e(strtoupper(substr($_SESSION['user'], 0, 1))) ?></a></div></header>
        <section class="page-content">
            <?php show_flash(); ?>
    <?php
}

function page_footer()
{
    ?>
        </section>
        <footer class="footer">VoltFlow <span>·</span> Electricity billing management</footer>
    </main>
    <script src="../assets/js/main.js"></script>
    </body>
    </html>
    <?php
}
