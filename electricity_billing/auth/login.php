<?php
require_once __DIR__ . '/../config/app.php';

if (!empty($_SESSION['user'])) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$error = '';
$username = '';
$attemptKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $attemptKey = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($username));
    $attempt = $_SESSION['login_attempts'][$attemptKey] ?? ['count' => 0, 'since' => time()];

    if (time() - (int) $attempt['since'] >= 300) {
        $attempt = ['count' => 0, 'since' => time()];
    }

    if ((int) $attempt['count'] >= 5) {
        $error = 'Too many attempts. Please wait five minutes before trying again.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $validPassword = false;

        if ($user) {
            $passwordInfo = password_get_info($user['password']);
            if (!empty($passwordInfo['algo'])) {
                $validPassword = password_verify($password, $user['password']);
            } elseif (preg_match('/^[a-f0-9]{32}$/i', $user['password'])) {
                $validPassword = hash_equals(strtolower($user['password']), md5($password));
                if ($validPassword) {
                    $upgrade = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $upgrade->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
            }
        }

        if ($user && $validPassword) {
            unset($_SESSION['login_attempts'][$attemptKey]);
            session_regenerate_id(true);
            $_SESSION['user'] = $user['username'];
            header('Location: ../pages/dashboard.php');
            exit;
        }

        $attempt['count'] = (int) $attempt['count'] + 1;
        $_SESSION['login_attempts'][$attemptKey] = $attempt;
        $error = 'The username or password is incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · VoltFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=20261012-login">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-visual">
            <a class="brand brand-light" href="../index.php"><span class="brand-mark">V</span><span>VoltFlow<small>UTILITY MANAGER</small></span></a>
            <div class="visual-copy"><span class="visual-tag">POWERING BETTER SERVICE</span><h1>One clear view<br>of every connection.</h1><p>Manage customers, meters, billing and collections in one simple workspace.</p></div>
            <div class="visual-foot"><span class="visual-spark">✳</span> Reliable tools for everyday utility operations</div>
        </section>
        <section class="auth-panel">
            <div class="auth-form-wrap">
                <div class="auth-mobile-brand"><span class="brand-mark">V</span> VoltFlow</div>
                <span class="eyebrow">ADMIN WORKSPACE</span>
                <h2>Welcome back</h2>
                <p class="auth-intro">Sign in to continue to your dashboard.</p>
                <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" autocomplete="username" value="<?= e($username) ?>" placeholder="Enter your username" maxlength="100" required autofocus>
                    <label for="password">Password</label>
                    <div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button></div>
                    <button class="button button-primary auth-submit" type="submit">Sign in <span>→</span></button>
                </form>
                <p class="auth-security"><span>●</span> Protected admin access</p>
            </div>
            <div class="auth-copyright">VoltFlow · Electricity billing management</div>
        </section>
    </main>
    <script src="../assets/js/main.js"></script>
</body>
</html>
