<?php
require_once __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$nim = '';
$nama = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nim   = trim(isset($_POST['nim']) ? $_POST['nim'] : '');
    $nama  = trim(isset($_POST['nama']) ? $_POST['nama'] : '');
    $pass  = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $pass2 = isset($_POST['password2']) ? (string)$_POST['password2'] : '';

    if (!preg_match('/^[0-9]{5,20}$/', $nim)) {
        $errors[] = 'NIM harus berupa angka (5-20 digit).';
    }
    if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $errors[] = 'Nama lengkap harus 3-100 karakter.';
    }
    if (strlen($pass) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if ($pass !== $pass2) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT status FROM users WHERE nim = ? LIMIT 1');
        $st->execute([$nim]);
        $exist = $st->fetch();
        if ($exist) {
            $errors[] = $exist['status'] === 'pending'
                ? 'NIM ini sudah mengajukan akun dan masih menunggu persetujuan Admin.'
                : 'NIM ini sudah terdaftar. Silakan masuk atau hubungi Admin.';
        }
    }

    if (!$errors) {
        $ins = db()->prepare("INSERT INTO users (nim, nama, password, role, status, created_at) VALUES (?, ?, ?, 'pengurus', 'pending', ?)");
        $ins->execute([$nim, $nama, password_hash($pass, PASSWORD_DEFAULT), now()]);
        notify_roles(['admin'], 'Pengajuan akun baru', $nama . ' (' . $nim . ') menunggu persetujuan.', 'admin/persetujuan.php');
        flash('success', 'Pengajuan akun berhasil dikirim. Silakan tunggu persetujuan Admin sebelum bisa masuk.');
        redirect('login.php');
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101733">
    <title>Ajukan Akun - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('assets/img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth">
    <section class="auth-hero">
        <div class="hero-top">
            <img class="auth-logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="Logo SIM-HMJ">
            <div class="hero-org"><strong>SIM-HMJ</strong><span>Sistem Informasi Manajemen - Himpunan Mahasiswa Jurusan</span></div>
        </div>
        <div class="hero-main">
            <h2>Bergabung dengan sistem himpunan.</h2>
            <p>Isi data di samping. Akun baru bisa dipakai setelah disetujui oleh Admin HMJ.</p>
            <ul class="hero-points">
                <li><i></i>Username login: NIM Anda</li>
                <li><i></i>Status pengajuan: menunggu persetujuan</li>
            </ul>
        </div>
        <div class="hero-foot">&copy; <?= date('Y') ?> SIM-HMJ</div>
    </section>
    <section class="auth-panel">
        <div class="auth-mobile-brand">
            <img class="auth-logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="Logo SIM-HMJ">
            <strong>SIM-HMJ</strong>
            <span>Sistem Informasi Manajemen - Himpunan Mahasiswa Jurusan</span>
        </div>
        <div class="auth-box">
            <h1>Pengajuan Akun</h1>
            <p class="auth-sub">Pengajuan akan ditinjau oleh Admin.</p>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="nim">NIM <span class="req">*</span></label>
                    <input type="text" id="nim" name="nim" value="<?= e($nim) ?>" inputmode="numeric" required autofocus>
                    <div class="hint" id="angkatan-hint">Angkatan terbaca otomatis dari awal NIM.</div>
                </div>
                <div class="field">
                    <label for="nama">Nama Lengkap <span class="req">*</span></label>
                    <input type="text" id="nama" name="nama" value="<?= e($nama) ?>" required>
                </div>
                <div class="field">
                    <label for="password">Password <span class="req">*</span></label>
                    <input type="password" id="password" name="password" minlength="6" required>
                    <div class="hint">Minimal 6 karakter.</div>
                </div>
                <div class="field">
                    <label for="password2">Ulangi Password <span class="req">*</span></label>
                    <input type="password" id="password2" name="password2" minlength="6" required>
                </div>
                <button class="btn" type="submit">Kirim Pengajuan</button>
            </form>
            <p class="auth-foot">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk</a></p>
        </div>
    </section>
</div>
<script>
(function () {
    var nim = document.getElementById('nim'), hint = document.getElementById('angkatan-hint');
    if (!nim || !hint) { return; }
    var max = new Date().getFullYear() + 1;
    function angkatan(v) {
        v = v.replace(/\D/g, '');
        if (v.length >= 4 && +v.slice(0, 4) >= 2000 && +v.slice(0, 4) <= max) { return +v.slice(0, 4); }
        if (v.length >= 2 && 2000 + +v.slice(0, 2) <= max) { return 2000 + +v.slice(0, 2); }
        return null;
    }
    function show() {
        var a = angkatan(nim.value);
        hint.textContent = a ? 'Terbaca: Angkatan ' + a : 'Angkatan terbaca otomatis dari awal NIM.';
    }
    nim.addEventListener('input', show); show();
})();
</script>
</body>
</html>
