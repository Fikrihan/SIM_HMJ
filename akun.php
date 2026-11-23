<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$me = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'avatar') {
    csrf_verify();
    $pilih = isset($_POST['avatar']) ? (string)$_POST['avatar'] : '';
    if ($pilih === '' || avatar_valid($pilih)) {
        db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$pilih === '' ? null : $pilih, (int)$me['id']]);
        flash('success', $pilih === '' ? 'Foto profil dihapus, kembali memakai inisial nama.' : 'Foto profil berhasil diganti.');
    } else {
        flash('error', 'Pilihan foto profil tidak valid.');
    }
    redirect('akun.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old  = isset($_POST['old']) ? (string)$_POST['old'] : '';
    $new  = isset($_POST['new']) ? (string)$_POST['new'] : '';
    $new2 = isset($_POST['new2']) ? (string)$_POST['new2'] : '';

    $st = db()->prepare('SELECT password FROM users WHERE id = ?');
    $st->execute([(int)$me['id']]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($old, $hash)) {
        $errors[] = 'Password lama salah.';
    }
    if (strlen($new) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    }
    if ($new !== $new2) {
        $errors[] = 'Konfirmasi password baru tidak sama.';
    }
    if (!$errors && $new === $old) {
        $errors[] = 'Password baru harus berbeda dari password lama.';
    }

    if (!$errors) {
        $up = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $up->execute([password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
        session_regenerate_id(true);
        flash('success', 'Password berhasil diubah.');
        redirect('akun.php');
    }
}

$pageTitle = 'Akun Saya';
$active = 'akun';
require __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:720px">
    <div class="card-head"><h2>Foto Profil</h2></div>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="avatar">
        <div class="avatar-current">
            <?= avatar_html($me['nama'], $me['avatar'], 'lg') ?>
            <div><strong><?= e($me['nama']) ?></strong><div class="sub">Pilih karakter kartun favoritmu. Foto tampil di forum, dashboard, dan menu.</div></div>
        </div>
        <div class="avatar-grid">
            <?php foreach (avatar_list() as $k => $label): ?>
                <label class="avatar-opt">
                    <input type="radio" name="avatar" value="<?= e($k) ?>" <?= $me['avatar'] === $k ? 'checked' : '' ?>>
                    <img src="<?= e(asset('assets/img/avatars/' . $k . '.svg')) ?>" alt="<?= e($label) ?>" loading="lazy">
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
            <label class="avatar-opt">
                <input type="radio" name="avatar" value="" <?= !avatar_valid($me['avatar']) ? 'checked' : '' ?>>
                <span class="avatar av-none"><?= e(mb_strtoupper(mb_substr($me['nama'], 0, 1))) ?></span>
                <span>Inisial saja</span>
            </label>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Simpan Foto Profil</button></div>
    </form>
</div>

<div class="card" style="max-width:560px">
    <div class="card-head"><h2>Informasi Akun</h2></div>
    <table>
        <tr><th style="width:140px">NIM</th><td><?= e($me['nim']) ?></td></tr>
        <tr><th>Nama</th><td><?= e($me['nama']) ?></td></tr>
        <tr><th>Hak Akses</th><td><?= e(role_label($me['role'])) ?></td></tr>
    </table>
</div>

<div class="card" style="max-width:560px">
    <div class="card-head"><h2>Ganti Password</h2></div>
    <?php if ($errors): ?>
        <div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="full"><label for="old">Password Lama</label><input type="password" id="old" name="old" required></div>
            <div><label for="new">Password Baru</label><input type="password" id="new" name="new" minlength="6" required></div>
            <div><label for="new2">Ulangi Password Baru</label><input type="password" id="new2" name="new2" minlength="6" required></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Simpan Password</button></div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
