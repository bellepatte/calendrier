<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
ini_set('display_errors', '0');
set_exception_handler(function ($e) {
    error_log((string)$e); http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Erreur serveur.']);
});
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));

const YOUNG = ['EA','PO','BE','MI'];

function out($d, int $c = 200) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail(string $m, int $c = 400) { out(['error' => $m], $c); }
function db(): PDO {
    static $p;
    return $p ??= new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function state(): array {
    return ['auth' => !empty($_SESSION['ok']), 'member' => $_SESSION['member'] ?? null,
            'admin' => !empty($_SESSION['admin']), 'csrf' => $_SESSION['csrf']];
}
function ctl(string $s): string { return trim(preg_replace('/\p{C}+/u', ' ', $s)); }

// ---------- Réseau sécurisé (anti-SSRF) ----------
function safeUrl(string $u): ?array {
    $p = parse_url($u);
    if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host']) || isset($p['user'])) return null;
    $port = $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80);
    if (!in_array($port, [80, 443, 8080, 8443], true)) return null;
    $ip = gethostbyname($p['host']);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    return [$p['host'], $port, $ip];
}
function absUrl(string $base, string $rel): string {
    if (preg_match('#^https?://#i', $rel)) return $rel;
    $p = parse_url($base); $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($rel, '//')) return $p['scheme'] . ':' . $rel;
    if (str_starts_with($rel, '/')) return $root . $rel;
    return $root . rtrim(dirname($p['path'] ?? '/') , '/') . '/' . $rel;
}
function http(string $url, int $max = 5000000): ?array {
    for ($i = 0; $i < 4; $i++) {
        $s = safeUrl($url); if (!$s) return null;
        $buf = ''; $hdr = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => ["$s[0]:$s[1]:$s[2]"], CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'Mozilla/5.0 (ClubCalendrier)',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$hdr) { $x = explode(':', $h, 2); if (count($x) == 2) $hdr[strtolower(trim($x[0]))] = trim($x[1]); return strlen($h); },
            CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$buf, $max) { $buf .= $d; return strlen($buf) > $max ? 0 : strlen($d); },
        ]);
        curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code >= 300 && $code < 400 && !empty($hdr['location'])) { $url = absUrl($url, $hdr['location']); continue; }
        return $code === 200 ? ['body' => $buf] : null;
    }
    return null;
}
function grabImage(string $url): ?string {
    $r = http($url); if (!$r) return null;
    $i = @getimagesizefromstring($r['body']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$i[2] ?? 0] ?? null;
    if (!$ext || $i[0] > 6000 || $i[1] > 6000) return null;
    $dir = __DIR__ . '/images/events'; @mkdir($dir, 0755, true);
    $f = bin2hex(random_bytes(10)) . '.' . $ext;
    return file_put_contents("$dir/$f", $r['body']) ? $f : null;
}
function ogImage(string $site): ?string {
    $r = http($site, 800000); if (!$r) return null;
    if (preg_match('/<meta[^>]+(?:property|name)=["\']og:image["\'][^>]*content=["\']([^"\']+)/i', $r['body'], $m)
     || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $r['body'], $m))
        return absUrl($site, html_entity_decode($m[1]));
    return null;
}
function rmImage(?string $f): void { if ($f && preg_match('/^[a-f0-9]{20}\.\w+$/', $f)) @unlink(__DIR__ . '/images/events/' . $f); }

// ---------- Validation ----------
function normFormats(string $raw): string {
    $raw = trim(preg_replace('/\s+/u', ' ', $raw), ' ;:');
    if ($raw === '') fail('Distances/épreuves : saisie obligatoire.');
    $parts = preg_split('/[;:]/', $raw);
    if (count($parts) > 20) fail('Distances/épreuves : 20 éléments maximum.');
    $seen = []; $o = [];
    foreach ($parts as $t) {
        $t = trim($t, ' ,.-/+');
        if ($t === '') fail('Distances/épreuves : élément vide, vérifiez les séparateurs « ; » et « : ».');
        if (mb_strlen($t) > 50) fail("Distances/épreuves : « $t » est trop long (50 caractères max).");
        if (!preg_match("/^[\p{L}\p{N} ,.'’()\/+×°-]+$/u", $t) || !preg_match('/[\p{L}\p{N}]/u', $t)) fail("Distances/épreuves : caractère non autorisé dans « $t ».");
        if (substr_count($t, '(') !== substr_count($t, ')')) fail("Distances/épreuves : parenthèses non appariées dans « $t ».");
        if (preg_match("/[,.'’\/+-]{2,}/u", $t)) fail("Distances/épreuves : ponctuation répétée dans « $t ».");
        $k = mb_strtolower(str_replace(',', '.', $t));
        if (preg_match('/^(42\.195)(\s*km)?$|^marathon$/', $k)) $t = 'Marathon';
        elseif (preg_match('/^(21\.1|21\.0975|21\.195)(\s*km)?$|^(semi|demi)[\s-]*(marathon)?$|^1\/2[\s-]*marathon$/', $k)) $t = 'Semi-marathon';
        else $t = str_replace('.', ',', $t);
        $key = mb_strtolower(preg_replace('/\s+/u', '', $t));
        if (isset($seen[$key])) continue;
        $seen[$key] = 1; $o[] = $t;
    }
    return implode(' ; ', $o);
}
function dateOk(string $d): bool {
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) return false;
    return $dt >= (new DateTime('today'))->modify('-1 month') && $dt <= (new DateTime('today'))->modify('+3 years');
}
function urlOk(string $u): bool {
    return $u === '' || (mb_strlen($u) <= 1024 && filter_var($u, FILTER_VALIDATE_URL) && in_array(parse_url($u, PHP_URL_SCHEME), ['http', 'https'], true));
}
function clean(array $in): array {
    $s = fn($k) => ctl((string)($in[$k] ?? ''));
    $e = ['name' => $s('name'), 'date' => $s('date'), 'time' => $s('time'), 'place' => $s('place'), 'other' => $s('other'),
          'site' => $s('site'), 'image_url' => $s('image_url'),
          'description' => trim(preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r", '', (string)($in['description'] ?? ''))))];
    if ($e['name'] === '' || mb_strlen($e['name']) > 50) fail('Nom obligatoire (50 caractères max).');
    if ($e['place'] === '' || mb_strlen($e['place']) > 50) fail('Lieu obligatoire (50 caractères max).');
    if ($e['time'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $e['time'])) fail('Heure invalide.');
    $e['time'] = $e['time'] ?: null;
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['disciplines'] ?? [])))));
    $rows = $ids ? db()->query('SELECT id, needs_detail FROM disciplines WHERE id IN (' . implode(',', $ids) . ') ORDER BY priority, id')->fetchAll() : [];
    if (!$rows || count($rows) !== count($ids)) fail('Choisissez au moins une discipline valide.');
    $e['disciplines'] = array_map(fn($r) => (int)$r['id'], $rows);
    if (array_filter($rows, fn($r) => $r['needs_detail'])) {
        if ($e['other'] === '' || mb_strlen($e['other']) > 50) fail('Précisez la discipline (50 caractères max).');
    } else $e['other'] = '';
    if (!urlOk($e['site'])) fail('Site officiel : URL http(s) invalide (1024 caractères max).');
    if (!urlOk($e['image_url'])) fail("Lien de l'image : URL http(s) invalide (1024 caractères max).");
    $raw = ctl((string)($in['formats'] ?? ''));
    if (mb_strlen($raw) > 100) fail('Distances/épreuves : 100 caractères max.');
    $e['formats'] = normFormats($raw);
    if ($e['formats'] === '' || mb_strlen($e['formats']) > 100) fail('Distances/épreuves obligatoires (100 caractères max).');
    $e['young'] = array_values(array_intersect(YOUNG, (array)($in['young'] ?? [])));
    if (mb_strlen($e['description']) > MAX_DESC) fail('Description trop longue (' . MAX_DESC . ' caractères max).');
    return $e;
}
function imgPath(?string $p): string {
    return ($p && preg_match('#^[A-Za-z0-9_\-./]+$#', $p) && !str_contains($p, '..') && !str_starts_with($p, '/')) ? $p : '';
}
function shape(array $r, array $parts, array $dis): array {
    return ['id' => (int)$r['id'], 'name' => $r['name'], 'date' => $r['jour'], 'time' => $r['heure'] ? substr($r['heure'], 0, 5) : '',
        'place' => $r['place'],
        'disciplines' => array_map(fn($d) => ['id' => (int)$d['id'], 'name' => $d['name'], 'detail' => (bool)$d['needs_detail']], $dis),
        'other' => $r['other'], 'site' => $r['site'], 'image_url' => $r['image_url'],
        'image' => $r['image_file'] ? 'images/events/' . $r['image_file'] : imgPath($dis[0]['image'] ?? null),
        'formats' => $r['formats'], 'young' => $r['young'] === '' ? [] : explode(',', $r['young']),
        'description' => $r['description'], 'creator' => $r['creator'], 'participants' => $parts];
}
function getEvent(int $id): array {
    $st = db()->prepare('SELECT * FROM events WHERE id=?'); $st->execute([$id]);
    return $st->fetch() ?: fail('Événement introuvable.', 404);
}

// ---------- Routage ----------
$in = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$act = (string)($_GET['action'] ?? $in['action'] ?? '');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($act === 'state') out(state());
if ($post && !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF'] ?? '')) fail('Session expirée, rechargez la page.', 403);

if ($act === 'login_global') {
    if (hash_equals(GLOBAL_PASSWORD, (string)($in['password'] ?? ''))) { session_regenerate_id(true); $_SESSION['ok'] = true; out(state()); }
    sleep(1); fail('Mot de passe incorrect.', 401);
}
if (empty($_SESSION['ok'])) fail('Accès refusé.', 401);

$me = $_SESSION['member'] ?? null; $admin = !empty($_SESSION['admin']);
switch ($act) {
case 'login_member':
    session_regenerate_id(true);
    $n = trim(preg_replace('/\s+/u', ' ', (string)($in['name'] ?? '')));
    if ($n === ADMIN_CODE) { $_SESSION['admin'] = true; out(state()); }
    if (mb_strlen($n) > 100 || !preg_match("/^[\p{L}'’-]+( [\p{L}'’-]+)+$/u", $n)) fail('Saisissez votre prénom et votre nom.');
    $_SESSION['member'] = mb_convert_case($n, MB_CASE_TITLE, 'UTF-8'); out(state());
case 'logout_member': unset($_SESSION['member']); out(state());
case 'logout_admin':  unset($_SESSION['admin']);  out(state());

case 'events':
    $f = (string)($_GET['from'] ?? ''); $t = (string)($_GET['to'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) fail('Période invalide.');
    $st = db()->prepare('SELECT * FROM events WHERE jour BETWEEN ? AND ? ORDER BY jour, heure IS NULL, heure, id');
    $st->execute([$f, $t]); $rows = $st->fetchAll(); $parts = []; $dis = [];
    if ($rows) {
        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
        foreach (db()->query("SELECT * FROM participants WHERE event_id IN ($ids) ORDER BY id") as $p)
            $parts[$p['event_id']][] = ['id' => (int)$p['id'], 'name' => $p['name'], 'epreuves' => json_decode($p['epreuves'], true) ?: [], 'added_by' => $p['added_by']];
        foreach (db()->query("SELECT ed.event_id, d.id, d.name, d.image, d.needs_detail FROM event_disciplines ed JOIN disciplines d ON d.id = ed.discipline_id WHERE ed.event_id IN ($ids) ORDER BY d.priority, d.id") as $d)
            $dis[$d['event_id']][] = $d;
    }
    $dl = db()->query('SELECT id, name, needs_detail FROM disciplines ORDER BY priority, id')->fetchAll();
    out(['events' => array_map(fn($r) => shape($r, $parts[$r['id']] ?? [], $dis[$r['id']] ?? []), $rows),
         'disciplines' => array_map(fn($d) => ['id' => (int)$d['id'], 'name' => $d['name'], 'detail' => (bool)$d['needs_detail']], $dl)]);

case 'event_save':
    if (!$me && !$admin) fail('Connexion adhérent requise.', 403);
    $id = (int)($in['id'] ?? 0); $e = clean($in); $old = null;
    if ($id) {
        $old = getEvent($id);
        if (!$admin && $old['creator'] !== $me) fail('Seul le créateur peut modifier cet événement.', 403);
    }
    if ((!$old || $old['jour'] !== $e['date']) && !dateOk($e['date'])) fail('Date hors limites (1 mois dans le passé à 3 ans dans le futur).');
    // image
    $file = null;
    if ($old && $old['image_url'] === $e['image_url'] && $old['site'] === $e['site'] && $old['image_file']) $file = $old['image_file'];
    elseif ($e['image_url'] !== '') { $file = grabImage($e['image_url']) ?: fail('Image inaccessible ou invalide (JPEG, PNG, GIF ou WebP, 5 Mo max).', 422); }
    elseif ($e['site'] !== '' && ($u = ogImage($e['site']))) $file = grabImage($u);
    $v = [$e['name'], $e['date'], $e['time'], $e['place'], $e['other'], $e['site'], $e['image_url'], $file, $e['formats'], implode(',', $e['young']), $e['description']];
    $pdo = db(); $pdo->beginTransaction();
    if ($id) {
        $pdo->prepare('UPDATE events SET name=?,jour=?,heure=?,place=?,other=?,site=?,image_url=?,image_file=?,formats=?,young=?,description=? WHERE id=?')->execute([...$v, $id]);
        $pdo->prepare('DELETE FROM event_disciplines WHERE event_id=?')->execute([$id]);
    } else {
        $pdo->prepare('INSERT INTO events (name,jour,heure,place,other,site,image_url,image_file,formats,young,description,creator) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$v, $me ?? 'Admin']);
        $id = (int)$pdo->lastInsertId();
    }
    $ins = $pdo->prepare('INSERT INTO event_disciplines (event_id, discipline_id) VALUES (?,?)');
    foreach ($e['disciplines'] as $d) $ins->execute([$id, $d]);
    $pdo->commit();
    if ($old && $old['image_file'] && $old['image_file'] !== $file) rmImage($old['image_file']);
    out(['ok' => true, 'id' => $id]);

case 'event_delete':
    $old = getEvent((int)($in['id'] ?? 0));
    if (!$admin) {
        if (!$me || $old['creator'] !== $me) fail('Seul le créateur peut supprimer cet événement.', 403);
        $c = db()->prepare('SELECT COUNT(*) FROM participants WHERE event_id=? AND added_by<>?'); $c->execute([$old['id'], $me]);
        if ($c->fetchColumn() > 0) fail("Des participations d'autres adhérents existent : seul un admin peut supprimer.", 403);
    }
    rmImage($old['image_file']);
    db()->prepare('DELETE FROM events WHERE id=?')->execute([$old['id']]); out(['ok' => true]);

case 'month_delete':
    if (!$admin) fail('Réservé aux admins.', 403);
    $m = (string)($in['month'] ?? ''); if (!preg_match('/^\d{4}-\d{2}$/', $m)) fail('Mois invalide.');
    $st = db()->prepare('SELECT image_file FROM events WHERE jour BETWEEN ? AND LAST_DAY(?)'); $st->execute(["$m-01", "$m-01"]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $f) rmImage($f);
    $d = db()->prepare('DELETE FROM events WHERE jour BETWEEN ? AND LAST_DAY(?)'); $d->execute(["$m-01", "$m-01"]);
    out(['ok' => true, 'deleted' => $d->rowCount()]);

case 'part_save':
    if (!$me && !$admin) fail('Connexion adhérent requise.', 403);
    $ev = getEvent((int)($in['event_id'] ?? 0)); $pid = (int)($in['id'] ?? 0);
    $name = ctl((string)($in['name'] ?? '')); if ($name === '' || mb_strlen($name) > 100) fail('Nom du participant obligatoire (100 caractères max).');
    $opts = array_map('trim', explode(';', $ev['formats']));
    $ep = array_values(array_intersect($opts, (array)($in['epreuves'] ?? []))); if (!$ep) fail('Choisissez au moins une épreuve.');
    if ($pid) {
        $st = db()->prepare('SELECT * FROM participants WHERE id=? AND event_id=?'); $st->execute([$pid, $ev['id']]); $p = $st->fetch() ?: fail('Participant introuvable.', 404);
        if (!$admin && $p['added_by'] !== $me) fail("Seul l'adhérent qui l'a ajouté peut modifier cette participation.", 403);
        db()->prepare('UPDATE participants SET name=?,epreuves=? WHERE id=?')->execute([$name, json_encode($ep, JSON_UNESCAPED_UNICODE), $pid]);
    } else {
        db()->prepare('INSERT INTO participants (event_id,name,epreuves,added_by) VALUES (?,?,?,?)')->execute([$ev['id'], $name, json_encode($ep, JSON_UNESCAPED_UNICODE), $me ?? 'Admin']);
    }
    out(['ok' => true]);

case 'part_delete':
    $st = db()->prepare('SELECT * FROM participants WHERE id=?'); $st->execute([(int)($in['id'] ?? 0)]); $p = $st->fetch() ?: fail('Participant introuvable.', 404);
    $mine = $me && ($p['added_by'] === $me || mb_strtolower(trim($p['name'])) === mb_strtolower($me));
    if (!$admin && !$mine) fail("Vous ne pouvez supprimer que vos participations ou celles que vous avez ajoutées.", 403);
    db()->prepare('DELETE FROM participants WHERE id=?')->execute([$p['id']]); out(['ok' => true]);
}
fail('Action inconnue.', 404);
