<?php
require_once __DIR__ . '/../config/app.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $stmt = $conn->prepare('SELECT id, password FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$_SESSION['user']]);
    $user = $stmt->fetch();
    $currentIsValid = false;

    if ($user) {
        $passwordInfo = password_get_info($user['password']);
        if (!empty($passwordInfo['algo'])) {
            $currentIsValid = password_verify($currentPassword, $user['password']);
        } elseif (preg_match('/^[a-f0-9]{32}$/i', $user['password'])) {
            $currentIsValid = hash_equals(strtolower($user['password']), md5($currentPassword));
        }
    }

    if (!$currentIsValid) {
        set_flash('error', 'Your current password is incorrect.');
    } elseif (mb_strlen($newPassword) < 10 || strlen($newPassword) > 72) {
        set_flash('error', 'Choose a password with at least 10 characters and no more than 72 bytes.');
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        set_flash('error', 'The new password and confirmation do not match.');
    } elseif (password_verify($newPassword, $user['password']) || (preg_match('/^[a-f0-9]{32}$/i', $user['password']) && hash_equals(strtolower($user['password']), md5($newPassword)))) {
        set_flash('error', 'Your new password must be different from your current password.');
    } else {
        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        set_flash('success', 'Your password has been updated.');
    }
    header('Location: account.php');
    exit;
}

page_header('Account security', 'account');
?>
<div class="page-intro"><div><h2>Protect your account</h2><p>Change the password used to access the administrator workspace.</p></div></div>
<section class="panel account-panel">
    <div class="panel-heading"><div><h3>Change password</h3><p>Use a unique password that you do not use on other websites.</p></div><span class="stat-icon icon-green">✓</span></div>
    <form method="post" class="stack-form account-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        <label for="new_password">New password</label><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required><small class="field-help">At least 10 characters. A mix of words, numbers, and symbols is recommended.</small>
        <label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>
        <div class="form-actions"><button class="button button-primary" type="submit">Update password</button></div>
    </form>
</section>
<?php page_footer(); ?>
