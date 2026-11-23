<?php
require_once __DIR__ . '/includes/init.php';
require_editor(); // hanya Admin & Pengurus yang boleh melihat

$me = current_user();

/* Paksa logout: khusus Admin */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_verify();
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    $st = db()->prepare('SELECT id, nama FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) {
        flash('error', 'Pengguna tidak ditemukan.');
    } elseif ((int)$u['id'] === (int)$me['id']) {
        flash('error', 'Untuk keluar dari akun Anda sendiri, gunakan tombol Keluar di menu.');
    } else {
        $n = cabut_sesi($id);
        flash('success', $n > 0 ? $u['nama'] . ' dikeluarkan paksa dari web.' : $u['nama'] . ' sudah tidak login.');
    }
    redirect('online.php');
}

$st = db()->prepare(
    "SELECT u.id, u.nim, u.nama, u.role, COUNT(s.id) AS jml,
            MAX(s.last_seen) AS last_seen, MAX(s.last_activity) AS last_activity, MIN(s.login_at) AS login_at
     FROM sesi_login s JOIN users u ON u.id = s.user_id
     WHERE u.status = 'aktif' AND s.last_activity >= ?
     GROUP BY u.id, u.nim, u.nama, u.role
     ORDER BY last_activity DESC"
);
$st->execute([online_cutoff()]);
$rows = $st->fetchAll();

$pageTitle = 'Sedang Online';
$active = 'online';
require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Pengguna Sedang Login</h2>
    </div>
    <div data-live="online-list">
    <p class="sub" style="margin:0 0 10px"><strong><?= count($rows) ?></strong> pengguna sedang login</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th>Hak Akses</th><th>Status</th><th>Aktivitas Terakhir</th><th>Login Sejak</th><?php if (is_admin()): ?><th>Aksi</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= is_admin() ? 6 : 5 ?>" class="empty">Tidak ada pengguna yang sedang login.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $layar = (time() - strtotime($r['last_seen'])) <= 45;
                $self  = (int)$r['id'] === (int)$me['id'];
            ?>
                <tr>
                    <td><strong><?= e($r['nama']) ?></strong><?= $self ? ' <span class="muted small">(Anda)</span>' : '' ?>
                        <div class="sub"><?= e($r['nim']) ?><?= (int)$r['jml'] > 1 ? ' &middot; ' . (int)$r['jml'] . ' perangkat' : '' ?></div></td>
                    <td><?= e(role_label($r['role'])) ?></td>
                    <td><?= $layar ? '<span class="badge badge-ok">Online</span>' : '<span class="badge badge-muted">Tab tidak aktif</span>' ?></td>
                    <td><?= e(waktu_lalu($r['last_activity'])) ?></td>
                    <td><?= e(tgl_waktu($r['login_at'])) ?></td>
                    <?php if (is_admin()): ?>
                    <td>
                        <?php if (!$self): ?>
                        <form method="post" data-confirm="Keluarkan paksa <?= e($r['nama']) ?> dari web sekarang?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Paksa Logout</button>
                        </form>
                        <?php else: ?><span class="muted small">-</span><?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>
    <p class="hint">Pengguna otomatis keluar bila tidak ada aktivitas selama <?= (int)(IDLE_TIMEOUT / 60) ?> menit.
        &quot;Tab tidak aktif&quot; = masih login tetapi halaman sedang tidak dibuka di layar.
        <?= is_admin() ? 'Admin dapat memaksa pengguna keluar dengan tombol Paksa Logout.' : '' ?></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
