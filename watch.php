<?php
require __DIR__ . '/inc.php';
$v = $_GET['v'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $v)) { http_response_code(404); exit('Geçersiz video.'); }
$t = mb_substr(trim($_GET['t'] ?? ''), 0, 200);
if (setting('save_watch_history') === '1') {
    db()->prepare("INSERT INTO watches (video_id, title, ip) VALUES (?, ?, ?)")->execute([$v, $t, $_SERVER['REMOTE_ADDR'] ?? '']);
}
layout_head($t ?: 'İzle');
?>
<div class="player"><iframe src="https://www.youtube-nocookie.com/embed/<?= h($v) ?>?autoplay=1" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe></div>
<h2><?= h($t) ?></h2>
<?php layout_foot();
