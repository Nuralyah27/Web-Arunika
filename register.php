<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to('dashboard.php');
}

$errors = [];
$name = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Nama harus terdiri dari 2–100 karakter.';
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) $errors[] = 'Username harus 3–50 karakter dan hanya boleh berisi huruf, angka, atau garis bawah.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $errors[] = 'Alamat email tidak valid.';
    if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $confirm) $errors[] = 'Konfirmasi password tidak cocok.';

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            $errors[] = 'Username atau email sudah digunakan.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, username, email, password_hash) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $username, $email, $hash]);
            set_flash('Registrasi berhasil. Silakan masuk menggunakan akun baru.', 'success');
            redirect_to('login.php');
        }
    }
}

$pageTitle = 'Daftar';
require __DIR__ . '/includes/header.php';
?>
<div class="form-wrap card">
    <h1>Buat akun ARUNIKA</h1>
    <p class="muted">Bergabung untuk belajar dan mendokumentasikan budaya Nusantara.</p>
    <?php if ($errors): ?>
        <div class="notice error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="name">Nama lengkap</label>
        <input id="name" name="name" maxlength="100" required value="<?= e($name) ?>" autocomplete="name">
        <label for="username">Username</label>
        <input id="username" name="username" maxlength="50" pattern="[a-zA-Z0-9_]{3,50}" required value="<?= e($username) ?>" autocomplete="username">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="150" required value="<?= e($email) ?>" autocomplete="email">
        <label for="password">Password (minimal 8 karakter)</label>
        <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
        <label for="confirm_password">Konfirmasi password</label>
        <input id="confirm_password" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
        <p><label><input type="checkbox" name="consent" value="1" required style="width:auto"> Saya menyetujui ketentuan penggunaan dan kebijakan privasi ARUNIKA.</label></p>
        <button type="submit">Buat akun</button>
    </form>
    <p>Sudah punya akun? <a href="login.php">Masuk di sini</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
