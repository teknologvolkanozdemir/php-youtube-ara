<?php
session_start();
const DB_FILE = __DIR__ . '/data/app.sqlite';

function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS searches (id INTEGER PRIMARY KEY AUTOINCREMENT, query TEXT, ip TEXT, archived INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS watches (id INTEGER PRIMARY KEY AUTOINCREMENT, video_id TEXT, title TEXT, ip TEXT, archived INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $defaults = ['site_title' => 'YouTube Ara', 'api_key' => '', 'results_per_page' => '12',
        'save_search_history' => '1', 'save_watch_history' => '1', 'auto_delete_days' => '0',
        'admin_password' => password_hash('admin', PASSWORD_DEFAULT)];
    $st = $pdo->prepare("INSERT OR IGNORE INTO settings (k, v) VALUES (?, ?)");
    foreach ($defaults as $k => $v) $st->execute([$k, $v]);
    return $pdo;
}
function setting(string $k): string {
    $st = db()->prepare("SELECT v FROM settings WHERE k = ?");
    $st->execute([$k]);
    return (string)$st->fetchColumn();
}
function set_setting(string $k, string $v): void {
    db()->prepare("INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)")->execute([$k, $v]);
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Geçersiz istek (CSRF).'); }
}
function purge_old(): void {
    $d = (int)setting('auto_delete_days');
    if ($d <= 0) return;
    foreach (['searches', 'watches'] as $t) {
        db()->prepare("DELETE FROM $t WHERE archived = 0 AND created_at < datetime('now', ?)")->execute(["-$d days"]);
    }
}
function http_get(string $url): ?string {
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: Mozilla/5.0\r\nAccept-Language: tr,en\r\n"]]);
    $r = @file_get_contents($url, false, $ctx);
    return $r === false ? null : $r;
}
/** YouTube araması: API anahtarı varsa Data API v3, yoksa sayfa verisi. */
function yt_search(string $q, int $max): array {
    $key = setting('api_key');
    $out = [];
    if ($key !== '') {
        $json = http_get('https://www.googleapis.com/youtube/v3/search?' . http_build_query(
            ['part' => 'snippet', 'type' => 'video', 'maxResults' => $max, 'q' => $q, 'key' => $key]));
        $data = $json ? json_decode($json, true) : null;
        foreach ($data['items'] ?? [] as $i) {
            $out[] = ['id' => $i['id']['videoId'], 'title' => $i['snippet']['title'], 'channel' => $i['snippet']['channelTitle']];
        }
        return $out;
    }
    $html = http_get('https://www.youtube.com/results?search_query=' . urlencode($q));
    if ($html && preg_match('/var ytInitialData = (\{.*?\});<\/script>/s', $html, $m)) {
        $data = json_decode($m[1], true);
        $walk = function ($n) use (&$walk, &$out, $max) {
            if (!is_array($n) || count($out) >= $max) return;
            if (isset($n['videoRenderer']['videoId'])) {
                $v = $n['videoRenderer'];
                $out[] = ['id' => $v['videoId'], 'title' => $v['title']['runs'][0]['text'] ?? '', 'channel' => $v['ownerText']['runs'][0]['text'] ?? ''];
                return;
            }
            foreach ($n as $c) $walk($c);
        };
        $walk($data);
    }
    return $out;
}
function layout_head(string $title): void { ?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f4f4f6;color:#222}
header{background:#c4302b;color:#fff;padding:12px 20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
header a{color:#fff;text-decoration:none}main{max-width:1100px;margin:20px auto;padding:0 16px}
form.s{display:flex;gap:8px;flex:1;min-width:200px}form.s input{flex:1;padding:8px;border:0;border-radius:4px}
button,.btn{background:#c4302b;color:#fff;border:0;padding:8px 14px;border-radius:4px;cursor:pointer;text-decoration:none;font-size:14px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.card{background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px #0002}.card img{width:100%;display:block}
.card div{padding:10px}.card a{color:#222;text-decoration:none;font-weight:600}.card small{color:#666}
table{width:100%;border-collapse:collapse;background:#fff}td,th{padding:8px;border-bottom:1px solid #eee;text-align:left;font-size:14px}
.box{background:#fff;padding:16px;border-radius:6px;margin-bottom:16px}label{display:block;margin:10px 0 4px}
input[type=text],input[type=password],input[type=number]{padding:8px;width:100%;max-width:420px;box-sizing:border-box}
.player{position:relative;padding-top:56.25%}.player iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.msg{background:#e6f4ea;padding:10px;border-radius:4px;margin-bottom:12px}.arch{color:#1a7f37}
</style></head><body>
<header><a href="index.php"><b>▶ <?= h(setting('site_title')) ?></b></a>
<form class="s" action="index.php"><input name="q" placeholder="Video ara..." value="<?= h($_GET['q'] ?? '') ?>"><button>Ara</button></form>
<a href="admin.php">Yönetim</a></header><main>
<?php }
function layout_foot(): void { echo '</main></body></html>'; }
