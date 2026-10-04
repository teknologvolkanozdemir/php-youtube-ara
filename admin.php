<?php
require __DIR__ . '/inc.php';
$msg = '';
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (empty($_SESSION['admin'])) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        usleep(500000);
        if (password_verify($_POST['password'] ?? '', setting('admin_password'))) {
            session_regenerate_id(true); $_SESSION['admin'] = true; header('Location: admin.php'); exit;
        }
        $err = 'Hatalı şifre.';
    }
    layout_head('Yönetim girişi'); ?>
    <div class="box"><h2>Yönetim Girişi</h2><?= $err ? '<p>' . h($err) . '</p>' : '' ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Şifre (varsayılan: admin)</label><input type="password" name="password" autofocus> <button>Giriş</button></form></div>
    <?php layout_foot(); exit;
}
$tables = ['searches' => 'Arama', 'watches' => 'İzleme'];
$tab = $_GET['tab'] ?? 'searches';
if (!in_array($tab, ['searches', 'watches', 'settings'], true)) $tab = 'searches';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    $t = $_POST['table'] ?? '';
    if (isset($tables[$t])) {
        $id = (int)($_POST['id'] ?? 0);
        switch ($a) {
            case 'delete': db()->prepare("DELETE FROM $t WHERE id = ?")->execute([$id]); $msg = 'Kayıt silindi.'; break;
            case 'archive': db()->prepare("UPDATE $t SET archived = 1 - archived WHERE id = ?")->execute([$id]); $msg = 'Arşiv durumu değişti.'; break;
            case 'clear_unarchived': db()->exec("DELETE FROM $t WHERE archived = 0"); $msg = 'Arşivlenmeyenler silindi.'; break;
            case 'archive_all': db()->exec("UPDATE $t SET archived = 1"); $msg = 'Tümü arşivlendi.'; break;
            case 'clear_all': db()->exec("DELETE FROM $t"); $msg = 'Tüm geçmiş (arşiv dahil) silindi.'; break;
        }
        $tab = $t;
    } elseif ($a === 'settings') {
        set_setting('site_title', mb_substr(trim($_POST['site_title'] ?? '') ?: 'YouTube Ara', 0, 80));
        set_setting('api_key', trim($_POST['api_key'] ?? ''));
        set_setting('results_per_page', (string)max(1, min(50, (int)($_POST['results_per_page'] ?? 12))));
        set_setting('save_search_history', isset($_POST['save_search_history']) ? '1' : '0');
        set_setting('save_watch_history', isset($_POST['save_watch_history']) ? '1' : '0');
        set_setting('auto_delete_days', (string)max(0, (int)($_POST['auto_delete_days'] ?? 0)));
        if (($_POST['new_password'] ?? '') !== '') {
            if (strlen($_POST['new_password']) < 6) $msg = 'Şifre en az 6 karakter olmalı. ';
            else set_setting('admin_password', password_hash($_POST['new_password'], PASSWORD_DEFAULT));
        }
        $msg .= 'Ayarlar kaydedildi.';
        $tab = 'settings';
    }
}
if (isset($_GET['export']) && isset($tables[$_GET['export']])) {
    $rows = db()->query("SELECT * FROM {$_GET['export']} ORDER BY id DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $_GET['export'] . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    if ($rows) fputcsv($o, array_keys($rows[0]));
    foreach ($rows as $r) fputcsv($o, array_map(fn($c) => preg_match('/^[=+\-@]/', (string)$c) ? "'" . $c : $c, $r));
    exit;
}
layout_head('Yönetim Paneli');
$counts = [];
foreach ($tables as $t => $_) $counts[$t] = db()->query("SELECT COUNT(*) total FROM $t")->fetch();
?>
<p><a class="btn" href="?tab=searches">Arama Geçmişi (<?= $counts['searches']['total'] ?>)</a>
<a class="btn" href="?tab=watches">İzleme Geçmişi (<?= $counts['watches']['total'] ?>)</a>
<a class="btn" href="?tab=settings">Ayarlar</a> <a class="btn" href="?logout=1">Çıkış</a></p>
<?php if ($msg) echo '<div class="msg">' . h($msg) . '</div>';
if ($tab === 'settings'): ?>
<div class="box"><h2>Ayarlar</h2><form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="settings">
<label>Site başlığı</label><input type="text" name="site_title" value="<?= h(setting('site_title')) ?>">
<label>YouTube Data API v3 anahtarı (boşsa sayfa verisi kullanılır)</label><input type="text" name="api_key" value="<?= h(setting('api_key')) ?>">
<label>Sayfa başına sonuç (1-50)</label><input type="number" name="results_per_page" min="1" max="50" value="<?= h(setting('results_per_page')) ?>">
<label><input type="checkbox" name="save_search_history" <?= setting('save_search_history') === '1' ? 'checked' : '' ?>> Arama geçmişini kaydet</label>
<label><input type="checkbox" name="save_watch_history" <?= setting('save_watch_history') === '1' ? 'checked' : '' ?>> İzleme geçmişini kaydet</label>
<label>Arşivlenmemiş kayıtları şu kadar gün sonra otomatik sil (0 = hiç)</label><input type="number" name="auto_delete_days" min="0" value="<?= h(setting('auto_delete_days')) ?>">
<label>Yeni yönetici şifresi (boş = değişmez)</label><input type="password" name="new_password" autocomplete="new-password">
<p><button>Kaydet</button></p></form></div>
<?php else:
$rows = db()->query("SELECT * FROM $tab ORDER BY id DESC LIMIT 500")->fetchAll(); ?>
<div class="box"><h2><?= h($tables[$tab]) ?> Geçmişi</h2>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="table" value="<?= $tab ?>">
<button name="action" value="archive_all">Tümünü arşivle</button>
<button name="action" value="clear_unarchived" onclick="return confirm('Arşivlenmeyenler silinsin mi?')">Arşivlenmeyenleri sil</button>
<button name="action" value="clear_all" onclick="return confirm('Her şey silinsin mi?')">Hepsini sil</button>
<a class="btn" href="?export=<?= $tab ?>">CSV indir</a></form></div>
<table><tr><th>#</th><th><?= $tab === 'searches' ? 'Sorgu' : 'Video' ?></th><th>IP</th><th>Tarih</th><th>Durum</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= $r['id'] ?></td>
<td><?php if ($tab === 'searches'): ?><a href="index.php?q=<?= urlencode($r['query']) ?>"><?= h($r['query']) ?></a>
<?php else: ?><a href="watch.php?v=<?= h($r['video_id']) ?>&t=<?= urlencode($r['title']) ?>"><?= h($r['title'] ?: $r['video_id']) ?></a><?php endif; ?></td>
<td><?= h($r['ip']) ?></td><td><?= h($r['created_at']) ?></td><td class="arch"><?= $r['archived'] ? 'Arşivde' : '' ?></td>
<td><form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="table" value="<?= $tab ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<button name="action" value="archive"><?= $r['archived'] ? 'Arşivden çıkar' : 'Arşivle' ?></button>
<button name="action" value="delete" onclick="return confirm('Silinsin mi?')">Sil</button></form></td></tr>
<?php endforeach; ?></table>
<?php endif; layout_foot();
