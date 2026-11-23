<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$pdo = db();
$totalAnggota = (int)$pdo->query('SELECT COUNT(*) FROM anggota')->fetchColumn();
$anggotaAktif = (int)$pdo->query("SELECT COUNT(*) FROM anggota WHERE status = 'aktif'")->fetchColumn();

$prokerStat = ['rencana' => 0, 'berjalan' => 0, 'selesai' => 0, 'batal' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM program_kerja GROUP BY status') as $r) {
    $prokerStat[$r['status']] = (int)$r['n'];
}
$totalProker = array_sum($prokerStat);

$recent = $pdo->query(
    'SELECT p.*, a.nama AS pj_nama FROM program_kerja p
     LEFT JOIN anggota a ON a.id = p.penanggung_jawab
     ORDER BY p.created_at DESC, p.id DESC LIMIT 5'
)->fetchAll();

// Peringatan jika admin masih memakai password bawaan
$defaultPass = false;
if (is_admin()) {
    $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $st->execute([(int)current_user()['id']]);
    $hash = $st->fetchColumn();
    $defaultPass = $hash && password_verify('admin123', $hash);
}
$pending = is_admin() ? pending_count() : 0;
$pengajuanMenunggu = can_edit() ? pengajuan_pending_count() : 0;
$totalInventaris = (int)$pdo->query('SELECT COALESCE(SUM(jumlah),0) FROM inventaris')->fetchColumn();
$jmlOnline = can_edit() ? online_count() : 0;
$me = current_user();
$roleInfo = [
    'admin'    => 'Anda masuk sebagai Admin: kelola akun, data organisasi, dan verifikasi pengajuan.',
    'pengurus' => 'Anda masuk sebagai Pengurus: kelola data dan verifikasi pengajuan kegiatan.',
    'anggota'  => 'Anda masuk sebagai Anggota: ikuti diskusi, ajukan kegiatan, dan lihat data organisasi.',
    'mahasiswa' => 'Anda masuk sebagai Mahasiswa: ikuti diskusi, ajukan kegiatan, dan lihat informasi kegiatan himpunan.',
];


/* Data tambahan untuk grafik & daftar */
$bulanPendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$perDivisi = $pdo->query(
    "SELECT COALESCE(NULLIF(divisi, ''), 'Belum ditentukan') AS d, COUNT(*) AS n
     FROM anggota GROUP BY d ORDER BY n DESC, d ASC LIMIT 6"
)->fetchAll();
$maxDivisi = 1;
foreach ($perDivisi as $r) {
    $maxDivisi = max($maxDivisi, (int)$r['n']);
}

$segmen = [
    ['rencana',  'Rencana',  '#3b6fe0'],
    ['berjalan', 'Berjalan', '#e0a21b'],
    ['selesai',  'Selesai',  '#14a36d'],
    ['batal',    'Batal',    '#e0475b'],
];
$stops = [];
$acc = 0.0;
foreach ($segmen as $sg) {
    $pct = $totalProker > 0 ? $prokerStat[$sg[0]] / $totalProker * 100 : 0;
    if ($pct > 0) {
        $stops[] = $sg[2] . ' ' . round($acc, 2) . '% ' . round($acc + $pct, 2) . '%';
    }
    $acc += $pct;
}
$donut = $stops ? 'conic-gradient(' . implode(', ', $stops) . ')' : 'conic-gradient(#e6eaf4 0 100%)';

$st = $pdo->prepare(
    "SELECT nama_proker, divisi, tanggal_mulai, status FROM program_kerja
     WHERE status = 'berjalan' OR (status = 'rencana' AND (tanggal_mulai IS NULL OR tanggal_mulai >= ?))
     ORDER BY (tanggal_mulai IS NULL), tanggal_mulai ASC LIMIT 4"
);
$st->execute([date('Y-m-d')]);
$mendatang = $st->fetchAll();

$threads = $pdo->query(
    "SELECT t.id, t.judul, t.last_activity, u.nama, u.avatar,
            (SELECT COUNT(*) FROM forum_posts p WHERE p.thread_id = t.id) AS balasan
     FROM forum_threads t JOIN users u ON u.id = t.user_id
     WHERE " . forum_filter_sql('t') . "
     ORDER BY t.last_activity DESC, t.id DESC LIMIT 4"
)->fetchAll();

$onlineList = [];
if (can_edit()) {
    $st = $pdo->prepare(
        "SELECT u.id, u.nama, u.role, u.avatar, MAX(s.last_seen) AS last_seen
         FROM sesi_login s JOIN users u ON u.id = s.user_id
         WHERE u.status = 'aktif' AND s.last_activity >= ?
         GROUP BY u.id, u.nama, u.role, u.avatar ORDER BY last_seen DESC LIMIT 8"
    );
    $st->execute([online_cutoff()]);
    $onlineList = $st->fetchAll();
}

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-text">
        <span class="hero-chip"><?= icon_svg('calendar', 14) ?> <?= e(hari_tanggal()) ?></span>
        <h2><?= e(greeting()) ?>, <?= e($me['nama']) ?>! &#128075;</h2>
        <p>Selamat datang di <?= e(APP_FULL_NAME) ?>.</p>
        <div class="hero-role"><?= e($roleInfo[$me['role']]) ?></div>
    </div>
    <img class="hero-logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="">
</section>

<div data-live="dashboard">
<?php if ($defaultPass): ?>
    <div class="alert alert-warning">
        <strong>Keamanan:</strong> Anda masih memakai password bawaan (admin123).
        <a href="<?= e(url('akun.php')) ?>">Ganti sekarang</a>.
    </div>
<?php endif; ?>
<?php if ($pending > 0): ?>
    <div class="alert alert-info">
        Ada <strong><?= $pending ?></strong> pengajuan akun yang menunggu persetujuan.
        <a href="<?= e(url('admin/persetujuan.php')) ?>">Tinjau sekarang</a>
    </div>
<?php endif; ?>
<?php if ($pengajuanMenunggu > 0): ?>
    <div class="alert alert-info">
        Ada <strong><?= $pengajuanMenunggu ?></strong> pengajuan kegiatan yang menunggu verifikasi.
        <a href="<?= e(url('pengajuan/index.php?status=menunggu')) ?>">Verifikasi sekarang</a>
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat stat-i"><div class="stat-ico c-blue"><?= icon_svg('anggota', 22) ?></div><div><div class="num"><?= $totalAnggota ?></div><div class="lbl">Total Anggota</div></div></div>
    <div class="stat stat-i"><div class="stat-ico c-green"><?= icon_svg('userok', 22) ?></div><div><div class="num"><?= $anggotaAktif ?></div><div class="lbl">Anggota Aktif</div></div></div>
    <div class="stat stat-i"><div class="stat-ico c-violet"><?= icon_svg('kegiatan', 22) ?></div><div><div class="num"><?= $totalProker ?></div><div class="lbl">Program Kegiatan</div></div></div>
    <div class="stat stat-i"><div class="stat-ico c-amber"><?= icon_svg('activity', 22) ?></div><div><div class="num"><?= $prokerStat['berjalan'] ?></div><div class="lbl">Sedang Berjalan</div></div></div>
    <div class="stat stat-i"><div class="stat-ico c-green"><?= icon_svg('checkc', 22) ?></div><div><div class="num"><?= $prokerStat['selesai'] ?></div><div class="lbl">Kegiatan Selesai</div></div></div>
    <div class="stat stat-i"><div class="stat-ico c-blue"><?= icon_svg('clock', 22) ?></div><div><div class="num"><?= $prokerStat['rencana'] ?></div><div class="lbl">Direncanakan</div></div></div>
    <div class="stat stat-i"><div class="stat-ico c-gold"><?= icon_svg('inventaris', 22) ?></div><div><div class="num"><?= $totalInventaris ?></div><div class="lbl">Unit Inventaris</div></div></div>
    <?php if (can_edit()): ?>
    <a class="stat stat-i stat-link" href="<?= e(url('online.php')) ?>"><div class="stat-ico c-green"><?= icon_svg('online', 22) ?></div><div><div class="num"><?= $jmlOnline ?></div><div class="lbl">Sedang Online</div></div></a>
    <?php endif; ?>
</div>

<h3 class="sec-title">Akses Cepat</h3>
<div class="quick">
    <a class="quick-item" href="<?= e(url('pengajuan/form.php')) ?>"><span class="q-ico c-violet"><?= icon_svg('plus', 20) ?></span><strong>Ajukan Kegiatan</strong><small>Usulkan program baru</small></a>
    <a class="quick-item" href="<?= e(url('forum/index.php')) ?>"><span class="q-ico c-blue"><?= icon_svg('forum', 20) ?></span><strong>Forum Diskusi</strong><small>Ngobrol bersama</small></a>
    <a class="quick-item" href="<?= e(url('kegiatan/index.php')) ?>"><span class="q-ico c-amber"><?= icon_svg('kegiatan', 20) ?></span><strong>Program Kegiatan</strong><small>Pantau semua program</small></a>
    <a class="quick-item" href="<?= e(url('inventaris/index.php')) ?>"><span class="q-ico c-gold"><?= icon_svg('inventaris', 20) ?></span><strong>Inventaris</strong><small>Barang organisasi</small></a>
    <?php if (is_admin()): ?>
    <a class="quick-item" href="<?= e(url('admin/persetujuan.php')) ?>"><span class="q-ico c-green"><?= icon_svg('persetujuan', 20) ?></span><strong>Persetujuan Akun</strong><small><?= $pending > 0 ? $pending . ' menunggu' : 'Tidak ada antrean' ?></small></a>
    <?php else: ?>
    <a class="quick-item" href="<?= e(url('anggota/index.php')) ?>"><span class="q-ico c-green"><?= icon_svg('anggota', 20) ?></span><strong>Data Anggota</strong><small>Lihat daftar anggota</small></a>
    <?php endif; ?>
</div>

<div class="dash-grid">
    <div class="card">
        <div class="card-head"><h2>Status Program Kegiatan</h2></div>
        <div class="donut-wrap">
            <div class="donut" style="background: <?= e($donut) ?>"><div class="donut-hole"><strong><?= $totalProker ?></strong><small>program</small></div></div>
            <ul class="legend">
                <?php foreach ($segmen as $sg): ?>
                    <li><i style="background: <?= e($sg[2]) ?>"></i><span><?= e($sg[1]) ?></span><b><?= (int)$prokerStat[$sg[0]] ?></b></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Anggota per Divisi</h2></div>
        <?php if (!$perDivisi): ?><div class="empty">Belum ada data anggota.</div><?php endif; ?>
        <div class="bars">
            <?php foreach ($perDivisi as $r): ?>
                <div class="bar-row">
                    <div class="bar-top"><span><?= e($r['d']) ?></span><b><?= (int)$r['n'] ?></b></div>
                    <div class="bar"><span style="width: <?= max(4, round($r['n'] / $maxDivisi * 100)) ?>%"></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Kegiatan Mendatang</h2><a class="btn btn-light btn-sm" href="<?= e(url('kegiatan/index.php')) ?>">Lihat semua</a></div>
        <?php if (!$mendatang): ?><div class="empty">Belum ada kegiatan mendatang.</div><?php endif; ?>
        <ul class="list">
            <?php foreach ($mendatang as $k): $t = $k['tanggal_mulai'] ? strtotime($k['tanggal_mulai']) : null; ?>
                <li>
                    <div class="date-chip"><?= $t ? '<b>' . date('j', $t) . '</b><span>' . $bulanPendek[(int)date('n', $t)] . '</span>' : '<b>-</b><span>TBA</span>' ?></div>
                    <div class="list-main"><strong><?= e($k['nama_proker']) ?></strong><div class="sub"><?= e($k['divisi'] ?: 'Tanpa divisi') ?></div></div>
                    <?= badge($k['status']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card">
        <div class="card-head"><h2>Diskusi Terbaru</h2><a class="btn btn-light btn-sm" href="<?= e(url('forum/index.php')) ?>">Buka forum</a></div>
        <?php if (!$threads): ?><div class="empty">Belum ada diskusi. Mulai yang pertama!</div><?php endif; ?>
        <ul class="list">
            <?php foreach ($threads as $th): ?>
                <li>
                    <?= avatar_html($th['nama'], $th['avatar'], 'sm') ?>
                    <div class="list-main">
                        <a href="<?= e(url('forum/thread.php?id=' . (int)$th['id'])) ?>"><strong><?= e($th['judul']) ?></strong></a>
                        <div class="sub">oleh <?= e($th['nama']) ?> &middot; <?= e(waktu_lalu($th['last_activity'])) ?></div>
                    </div>
                    <span class="tag"><?= (int)$th['balasan'] ?> balasan</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if (can_edit()): ?>
<div class="card">
    <div class="card-head"><h2>Sedang Online</h2><a class="btn btn-light btn-sm" href="<?= e(url('online.php')) ?>">Detail</a></div>
    <?php if (!$onlineList): ?><div class="empty">Tidak ada pengguna yang sedang login.</div><?php endif; ?>
    <div class="chips">
        <?php foreach ($onlineList as $o): $layar = (time() - strtotime($o['last_seen'])) <= 45; ?>
            <span class="chip"><?= avatar_html($o['nama'], $o['avatar'], 'sm') ?><?= e($o['nama']) ?>
                <i class="dot <?= $layar ? 'on' : '' ?>" title="<?= $layar ? 'Online' : 'Tab tidak aktif' ?>"></i></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>Program Kegiatan Terbaru</h2>
        <a class="btn btn-light btn-sm" href="<?= e(url('kegiatan/index.php')) ?>">Lihat semua</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Program Kegiatan</th><th>Divisi</th><th>Waktu</th><th>Penanggung Jawab</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="5" class="empty">Belum ada program kegiatan.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $p): ?>
                <tr>
                    <td><strong><?= e($p['nama_proker']) ?></strong></td>
                    <td><?= e($p['divisi'] ?: '-') ?></td>
                    <td><?= e(tgl($p['tanggal_mulai'])) ?></td>
                    <td><?= e($p['pj_nama'] ?: '-') ?></td>
                    <td><?= badge($p['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
