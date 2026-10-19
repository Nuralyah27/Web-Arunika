<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to((current_user()['role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
}

$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    
    $stmt = $pdo->prepare(
        'SELECT id, name, username, email, password_hash, role
        FROM users
        WHERE email = :email OR username = :username
        LIMIT 1'
    );

    $stmt->execute([
        'email' => $identity,
        'username' => $identity,
    ]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        set_flash('Selamat datang, ' . $user['name'] . '!', 'success');
        redirect_to($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
    }
    $error = 'Email/username atau password tidak sesuai.';
}

$pageTitle = 'Masuk';
require __DIR__ . '/includes/header.php';
?>
<div class="form-wrap card">
    <h1>Masuk ke ARUNIKA</h1>
    <p class="muted">Lanjutkan perjalanan mengenal budaya Nusantara.</p>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="identity">Email atau username</label>
        <input id="identity" name="identity" required value="<?= e($identity) ?>" autocomplete="username">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button type="submit">Masuk</button>
    </form>
    <p>Belum punya akun? <a href="register.php">Daftar sekarang</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
