<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$me  = current_user();
$pdo = db();

/* Buka notifikasi: tandai dibaca lalu pindah ke tautannya */
if (isset($_GET['go'])) {
    $st = $pdo->prepare('SELECT id, link FROM notifikasi WHERE id = ? AND user_id = ?');
    $st->execute([(int)$_GET['go'], (int)$me['id']]);
    $n = $st->fetch();
    if ($n) {
        $pdo->prepare('UPDATE notifikasi SET dibaca = 1 WHERE id = ?')->execute([(int)$n['id']]);
        redirect($n['link'] ? $n['link'] : 'notifikasi.php');
    }
    redirect('notifikasi.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'baca_semua') {
        $pdo->prepare('UPDATE notifikasi SET dibaca = 1 WHERE user_id = ?')->execute([(int)$me['id']]);
        flash('success', 'Semua notifikasi ditandai sudah dibaca.');
    } elseif ($action === 'hapus_dibaca') {
        $pdo->prepare('DELETE FROM notifikasi WHERE user_id = ? AND dibaca = 1')->execute([(int)$me['id']]);
        flash('success', 'Notifikasi yang sudah dibaca dihapus.');
    }
    redirect('notifikasi.php');
}

$cnt = $pdo->prepare('SELECT COUNT(*) FROM notifikasi WHERE user_id = ?');
$cnt->execute([(int)$me['id']]);
$pg = paginate((int)$cnt->fetchColumn(), 20, isset($_GET['page']) ? $_GET['page'] : 1);

$st = $pdo->prepare('SELECT * FROM notifikasi WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset']);
$st->execute([(int)$me['id']]);
$rows = $st->fetchAll();

$pageTitle = 'Notifikasi';
$active = 'notifikasi';
require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Notifikasi Saya</h2>
        <div class="actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="baca_semua">
                <button class="btn btn-light btn-sm" type="submit">Tandai semua dibaca</button></form>
            <form method="post" data-confirm="Hapus semua notifikasi yang sudah dibaca?"><?= csrf_field() ?><input type="hidden" name="action" value="hapus_dibaca">
                <button class="btn btn-light btn-sm" type="submit">Hapus yang sudah dibaca</button></form>
        </div>
    </div>

    <div data-live="notif-list">
        <?php if (!$rows): ?>
            <div class="empty">Belum ada notifikasi.</div>
        <?php endif; ?>
        <?php foreach ($rows as $n): ?>
            <a class="notif-item<?= $n['dibaca'] ? '' : ' unread' ?>" href="<?= e(url('notifikasi.php?go=' . (int)$n['id'])) ?>">
                <div>
                    <strong><?= e($n['judul']) ?></strong>
                    <?php if ($n['pesan']): ?><div class="sub"><?= e($n['pesan']) ?></div><?php endif; ?>
                </div>
                <small class="muted"><?= e(waktu_lalu($n['created_at'])) ?></small>
            </a>
        <?php endforeach; ?>
        <?= pagination_html($pg) ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
