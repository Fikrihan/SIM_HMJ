<?php
require_once __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$nim = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nim  = trim(isset($_POST['nim']) ? $_POST['nim'] : '');
    $pass = isset($_POST['password']) ? (string)$_POST['password'] : '';

    $lock = isset($_SESSION['login_lock']) ? (int)$_SESSION['login_lock'] : 0;
    if ($lock > time()) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ($lock - time()) . ' detik.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE nim = ? LIMIT 1');
        $st->execute([$nim]);
        $u = $st->fetch();

        if ($u && password_verify($pass, $u['password'])) {
            if ($u['status'] === 'pending') {
                $error = 'Akun Anda masih menunggu persetujuan Admin.';
            } elseif ($u['status'] === 'ditolak') {
                $error = 'Pendaftaran akun Anda ditolak oleh Admin. Hubungi pengurus untuk informasi lebih lanjut.';
            } elseif ($u['status'] === 'nonaktif') {
                $error = 'Akun Anda dinonaktifkan. Hubungi Admin.';
            } else {
                session_regenerate_id(true);
                $token = bin2hex(random_bytes(32));
                buat_sesi((int)$u['id'], $token);
                $_SESSION['uid'] = (int)$u['id'];
                $_SESSION['stoken'] = $token;
                unset($_SESSION['login_fail'], $_SESSION['login_lock']);
                redirect('dashboard.php');
            }
        } else {
            $_SESSION['login_fail'] = (isset($_SESSION['login_fail']) ? $_SESSION['login_fail'] : 0) + 1;
            if ($_SESSION['login_fail'] >= 5) {
                $_SESSION['login_lock'] = time() + 60;
                $_SESSION['login_fail'] = 0;
            }
            $error = 'NIM atau password salah.';
        }
    }
}
$flash = get_flash();
$msgMap = [
    'idle'     => 'Anda keluar otomatis karena tidak ada aktivitas selama ' . (int)(IDLE_TIMEOUT / 60) . ' menit. Silakan masuk kembali.',
    'dicabut'  => 'Sesi Anda diakhiri oleh Admin. Silakan masuk kembali.',
    'nonaktif' => 'Akun Anda dinonaktifkan. Hubungi Admin.',
];
$msgKey = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
if (isset($msgMap[$msgKey]) && !$error) {
    $flash[] = ['type' => 'warning', 'msg' => $msgMap[$msgKey]];
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101733">
    <title>Masuk - <?= e(APP_NAME) ?></title>
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
            <h2>Satu tempat untuk seluruh kegiatan himpunan.</h2>
            <p>Kelola anggota, program kegiatan, pengajuan, inventaris, dan diskusi pengurus secara rapi dan terpusat.</p>
            <ul class="hero-points">
                <li><i></i>Data anggota &amp; kepengurusan</li>
                <li><i></i>Perencanaan &amp; pemantauan program kegiatan</li>
                <li><i></i>Akun baru melalui persetujuan Admin</li>
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
            <h1>Selamat datang</h1>
            <p class="auth-sub">Masuk menggunakan NIM dan password Anda.</p>

            <?php foreach ($flash as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="nim">NIM</label>
                    <div class="input-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="nim" name="nim" value="<?= e($nim) ?>" placeholder="Masukkan NIM" required autofocus>
                    </div>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Tampilkan password">Lihat</button>
                    </div>
                </div>
                <button class="btn" type="submit">Masuk</button>
            </form>
            <p class="auth-foot">Belum punya akun? <a href="<?= e(url('register.php')) ?>">Ajukan akun</a></p>
        </div>
    </section>
</div>
<script>
(function () {
    var t = document.getElementById('pwToggle'), p = document.getElementById('password');
    if (!t || !p) { return; }
    t.addEventListener('click', function () {
        var show = p.type === 'password';
        p.type = show ? 'text' : 'password';
        t.textContent = show ? 'Sembunyi' : 'Lihat';
    });
})();
</script>
</body>
</html>
