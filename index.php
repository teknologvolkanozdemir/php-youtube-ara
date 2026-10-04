<?php
require __DIR__ . '/inc.php';
purge_old();
$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    if (setting('save_search_history') === '1') {
        db()->prepare("INSERT INTO searches (query, ip) VALUES (?, ?)")->execute([mb_substr($q, 0, 200), $_SERVER['REMOTE_ADDR'] ?? '']);
    }
    $results = yt_search($q, max(1, min(50, (int)setting('results_per_page'))));
}
layout_head(setting('site_title'));
if ($q === '') echo '<div class="box">Yukarıdaki kutudan YouTube\'da video arayın.</div>';
elseif (!$results) echo '<div class="box">Sonuç bulunamadı (API anahtarını ayarlardan kontrol edin).</div>';
?>
<div class="grid">
<?php foreach ($results as $r): ?>
  <div class="card"><a href="watch.php?v=<?= h($r['id']) ?>&t=<?= urlencode($r['title']) ?>"><img loading="lazy" src="https://i.ytimg.com/vi/<?= h($r['id']) ?>/mqdefault.jpg" alt=""></a>
  <div><a href="watch.php?v=<?= h($r['id']) ?>&t=<?= urlencode($r['title']) ?>"><?= h($r['title']) ?></a><br><small><?= h($r['channel']) ?></small></div></div>
<?php endforeach; ?>
</div>
<?php layout_foot();
