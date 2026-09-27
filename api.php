<?php
// Niateks House CRM — JSON API (PHP 7.4+ / SQLite). Tek dosya sunucu katmanı.
// Gereksinim: pdo_sqlite, mbstring, session.

if (!defined('NX_DATA')) define('NX_DATA', __DIR__ . '/nx_data');
if (!defined('NX_APP')) define('NX_APP', 1); // mail_config.php dogrudan web'den cagirilirse 403 dondurmek icin

function j($code, $obj = []) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($code === 204) exit;
    echo json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function body_json($max = 262144) {
    $d = json_decode((string)file_get_contents('php://input', false, null, 0, $max), true);
    return is_array($d) ? $d : [];
}
function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }
function s($v, $n) { return mb_substr(trim((string)$v), 0, $n); }

// ---------- storage ----------
if (!extension_loaded('pdo_sqlite')) j(500, ['error' => 'Sunucuda pdo_sqlite yok.']);
if (!is_dir(NX_DATA)) @mkdir(NX_DATA, 0775, true);
if (!is_dir(NX_DATA . '/sessions')) @mkdir(NX_DATA . '/sessions', 0775, true);
@file_put_contents(NX_DATA . '/.htaccess', "# Niateks House CRM — veri klasörü (web'den erişilemez)\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");

// ---------- posta hesabi (gizli bilgiler nx_data/mail_config.php icinde) ----------
// Dosya yoksa kimlik bilgisi bos kalir; nx_smtp_send SMTP'yi atlayip PHP mail() yedegini kullanir.
$NX_MAIL = ['host' => 'tls://mail.niateks.com', 'port' => 465, 'user' => '', 'pass' => '', 'from' => '', 'fromName' => 'Niateks House CRM'];
if (is_file(NX_DATA . '/mail_config.php')) {
    $nxMailCfg = include NX_DATA . '/mail_config.php';
    if (is_array($nxMailCfg)) $NX_MAIL = array_merge($NX_MAIL, array_intersect_key($nxMailCfg, $NX_MAIL));
}

function db() {
    static $p = null;
    if ($p) return $p;
    $p = new PDO('sqlite:' . NX_DATA . '/niateks.sqlite');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $p->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON;');
    $p->exec("CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE COLLATE NOCASE,
        name TEXT NOT NULL,
        email TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL DEFAULT 'agent',
        color TEXT NOT NULL DEFAULT '#3f4a2e',
        hash TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL,
        last_login INTEGER,
        must_change INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS log(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ts INTEGER NOT NULL,
        user_id INTEGER,
        username TEXT NOT NULL DEFAULT '',
        event TEXT NOT NULL,
        entity TEXT NOT NULL DEFAULT '',
        entity_id INTEGER NOT NULL DEFAULT 0,
        entity_name TEXT NOT NULL DEFAULT '',
        changes TEXT NOT NULL DEFAULT '',
        detail TEXT NOT NULL DEFAULT '',
        ip TEXT NOT NULL DEFAULT '',
        ua TEXT NOT NULL DEFAULT '',
        device TEXT NOT NULL DEFAULT ''
    );
    CREATE INDEX IF NOT EXISTS log_ts ON log(ts);
    CREATE INDEX IF NOT EXISTS log_ent ON log(entity, entity_id);
    CREATE TABLE IF NOT EXISTS fails(ip TEXT NOT NULL, ts INTEGER NOT NULL);
    CREATE INDEX IF NOT EXISTS fails_ip ON fails(ip);
    CREATE TABLE IF NOT EXISTS password_resets(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ip TEXT NOT NULL DEFAULT '',
        ts INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS presets_user ON password_resets(user_id, ts);
    CREATE TABLE IF NOT EXISTS companies(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact TEXT NOT NULL DEFAULT '',
        contact_title TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        country TEXT NOT NULL DEFAULT '',
        source TEXT NOT NULL DEFAULT 'Referans',
        owner_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'Aday',
        first_date TEXT NOT NULL DEFAULT '',
        need TEXT NOT NULL DEFAULT '',
        deleted INTEGER NOT NULL DEFAULT 0, del_by INTEGER, del_at INTEGER,
        created_at INTEGER NOT NULL, created_by INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS comp_o ON companies(owner_id);
    CREATE TABLE IF NOT EXISTS company_contacts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        title TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        phone TEXT NOT NULL DEFAULT '',
        is_primary INTEGER NOT NULL DEFAULT 0,
        ord INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS ccontacts_c ON company_contacts(company_id);
    CREATE TABLE IF NOT EXISTS company_phones(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER NOT NULL,
        type TEXT NOT NULL DEFAULT 'Cep',
        cc TEXT NOT NULL DEFAULT '+90',
        number TEXT NOT NULL DEFAULT ''
    );
    CREATE INDEX IF NOT EXISTS cphones_c ON company_phones(company_id);
    CREATE TABLE IF NOT EXISTS deals(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        value REAL NOT NULL DEFAULT 0,
        currency TEXT NOT NULL DEFAULT 'USD',
        stage TEXT NOT NULL DEFAULT 'İhtiyaç',
        owner_id INTEGER NOT NULL,
        close_date TEXT NOT NULL DEFAULT '',
        note TEXT NOT NULL DEFAULT '',
        reference TEXT NOT NULL DEFAULT '',
        loss TEXT NOT NULL DEFAULT '',
        won_at INTEGER, lost_at INTEGER,
        created_at INTEGER NOT NULL, created_by INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS deals_c ON deals(company_id);
    CREATE TABLE IF NOT EXISTS tasks(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        company_id INTEGER NOT NULL DEFAULT 0,
        deal_id INTEGER NOT NULL DEFAULT 0,
        project_id INTEGER NOT NULL DEFAULT 0,
        owner_id INTEGER NOT NULL,
        due_date TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'Yapılacak',
        waiting TEXT NOT NULL DEFAULT 'Biz',
        note TEXT NOT NULL DEFAULT '',
        done_at INTEGER,
        created_at INTEGER NOT NULL, created_by INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS tasks_o ON tasks(owner_id);
    CREATE INDEX IF NOT EXISTS tasks_c ON tasks(company_id);
    CREATE TABLE IF NOT EXISTS projects(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        company_id INTEGER NOT NULL DEFAULT 0,
        deal_id INTEGER NOT NULL DEFAULT 0,
        owner_id INTEGER NOT NULL,
        due_date TEXT NOT NULL DEFAULT '',
        type TEXT NOT NULL DEFAULT 'Numune',
        currency TEXT NOT NULL DEFAULT 'USD',
        note TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL, created_by INTEGER NOT NULL
    );
    CREATE TABLE IF NOT EXISTS project_steps(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        done INTEGER NOT NULL DEFAULT 0,
        ord INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS psteps_p ON project_steps(project_id);
    CREATE TABLE IF NOT EXISTS project_images(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        file TEXT NOT NULL,
        orig_name TEXT NOT NULL DEFAULT '',
        caption TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        deleted INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS pimgs_p ON project_images(project_id);
    CREATE TABLE IF NOT EXISTS product_types(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        parent_id INTEGER,
        active INTEGER NOT NULL DEFAULT 1,
        system INTEGER NOT NULL DEFAULT 0,
        ord INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS attribute_definitions(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        dtype TEXT NOT NULL DEFAULT 'text',
        unit TEXT NOT NULL DEFAULT '',
        options TEXT NOT NULL DEFAULT '',
        minv REAL, maxv REAL,
        grp TEXT NOT NULL DEFAULT 'Diğer',
        archived INTEGER NOT NULL DEFAULT 0,
        ord INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS product_type_attributes(
        product_type_id INTEGER NOT NULL,
        attribute_id INTEGER NOT NULL,
        required INTEGER NOT NULL DEFAULT 0,
        ord INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY(product_type_id, attribute_id)
    );
    CREATE TABLE IF NOT EXISTS project_products(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        product_type_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'taslak',
        price REAL NOT NULL DEFAULT 0,
        delivery_date TEXT NOT NULL DEFAULT '',
        incoterm TEXT NOT NULL DEFAULT 'FOB',
        payment_term TEXT NOT NULL DEFAULT '30 gün',
        packaging TEXT NOT NULL DEFAULT 'Koli (dökme)',
        per_box INTEGER NOT NULL DEFAULT 0,
        sample_status TEXT NOT NULL DEFAULT 'Yok',
        created_at INTEGER NOT NULL, created_by INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS pprods_p ON project_products(project_id);
    CREATE TABLE IF NOT EXISTS project_product_dist(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        label TEXT NOT NULL DEFAULT '',
        qty INTEGER NOT NULL DEFAULT 0,
        ord INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS pdist_p ON project_product_dist(product_id);
    CREATE TABLE IF NOT EXISTS project_product_values(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        attribute_id INTEGER NOT NULL,
        value TEXT NOT NULL DEFAULT '',
        label_snapshot TEXT NOT NULL DEFAULT '',
        unit_snapshot TEXT NOT NULL DEFAULT '',
        UNIQUE(product_id, attribute_id)
    );
    CREATE TABLE IF NOT EXISTS project_product_custom(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        label TEXT NOT NULL DEFAULT '',
        value TEXT NOT NULL DEFAULT '',
        unit TEXT NOT NULL DEFAULT '',
        ord INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS pcustom_p ON project_product_custom(product_id);
    CREATE TABLE IF NOT EXISTS notes(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER NOT NULL,
        deal_id INTEGER NOT NULL DEFAULT 0,
        type TEXT NOT NULL DEFAULT 'Not',
        text TEXT NOT NULL,
        owner_id INTEGER NOT NULL,
        note_date TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS notes_c ON notes(company_id);
    CREATE TABLE IF NOT EXISTS activity(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ts INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        text TEXT NOT NULL DEFAULT ''
    );
    CREATE INDEX IF NOT EXISTS act_ts ON activity(ts);");
    return $p;
}
// Migration: eski veritabanlarina yeni kolonlar (varsa atlama).
$ccols = array_column(db()->query('PRAGMA table_info(companies)')->fetchAll(), 'name');
if (!in_array('contact_title', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN contact_title TEXT NOT NULL DEFAULT ''");
if (!in_array('address1', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN address1 TEXT NOT NULL DEFAULT ''");
if (!in_array('address2', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN address2 TEXT NOT NULL DEFAULT ''");
if (!in_array('city', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN city TEXT NOT NULL DEFAULT ''");
if (!in_array('postal_code', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN postal_code TEXT NOT NULL DEFAULT ''");
if (!in_array('district', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN district TEXT NOT NULL DEFAULT ''");
if (!in_array('website', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN website TEXT NOT NULL DEFAULT ''");
if (!in_array('tax_id', $ccols, true)) db()->exec("ALTER TABLE companies ADD COLUMN tax_id TEXT NOT NULL DEFAULT ''");
// log tablosuna device kolonu (eski kurulumlarda)
$lcols = array_column(db()->query('PRAGMA table_info(log)')->fetchAll(), 'name');
if (!in_array('device', $lcols, true)) db()->exec("ALTER TABLE log ADD COLUMN device TEXT NOT NULL DEFAULT ''");
// projects.currency (urun kartlari icin proje duzeyinde para birimi)
$pcols = array_column(db()->query('PRAGMA table_info(projects)')->fetchAll(), 'name');
if (!in_array('currency', $pcols, true)) db()->exec("ALTER TABLE projects ADD COLUMN currency TEXT NOT NULL DEFAULT 'USD'");
// users.must_change (gecici sifreyle giriste sifre degistirtme zorunlulugu) — eski kurulumlarda
$ucols = array_column(db()->query('PRAGMA table_info(users)')->fetchAll(), 'name');
if (!in_array('must_change', $ucols, true)) db()->exec("ALTER TABLE users ADD COLUMN must_change INTEGER NOT NULL DEFAULT 0");

// ---------- urun modulu seed (bir kez) ----------
function seed_products_if_empty() {
    if ((int)q1('SELECT COUNT(*) c FROM product_types')['c'] > 0) return;
    $types = [
        ['Ev Tekstili', null, 1],
        ['Havlu', 'Ev Tekstili', 2],
        ['Nevresim', 'Ev Tekstili', 3],
        ['Bornoz', 'Ev Tekstili', 4],
        ['Apparel', null, 5],
        ['Tişört', 'Apparel', 6],
        ['Sweatshirt', 'Apparel', 7],
        ['Promosyon Tekstil', null, 8],
        ['Manuel / Diğer', null, 9, 1],
    ];
    $tid = [];
    foreach ($types as $t) {
        $sys = $t[3] ?? 0;
        qx('INSERT INTO product_types(name,parent_id,ord,system) VALUES(?,?,?,?)', [$t[0], $t[1] ? $tid[$t[1]] : null, $t[2], $sys]);
        $tid[$t[0]] = (int)db()->lastInsertId();
    }
    // Alan kutuphanesi (prototipteki A tanimlari)
    $attrs = [
        ['fiber', 'Elyaf karışımı', 'blend', '', 'Kumaş', null, null, 1],
        ['structure', 'Kumaş yapısı', 'select', '', 'Kumaş', 'Terry|Saten|Percale|Jarse|Ribana|Waffle|Polar', null, 2],
        ['gsm', 'Gramaj', 'number', 'gr/m²', 'Kumaş', null, 50, 900, 3],
        ['thread_count', 'Tel sayısı', 'number', 'tel', 'Kumaş', null, 100, 1000, 4],
        ['cert', 'Sertifikalar', 'multi', '', 'Kumaş', 'OEKO-TEX|GOTS|BCI', null, null, 5],
        ['size', 'Ölçü (en x boy)', 'text', 'cm', 'Ölçü', null, null, null, 6],
        ['fit', 'Kalıp', 'select', '', 'Ölçü', 'Regular|Slim|Oversize', null, null, 7],
        ['color', 'Renk (Pantone / numune)', 'text', '', 'Renk', null, null, null, 8],
        ['print', 'Baskı / nakış', 'select', '', 'Renk', 'Yok|Dijital baskı|Rotasyon baskı|Nakış|Transfer', null, null, 9],
        ['edge', 'Kenar / dikiş tipi', 'select', '', 'Konstrüksiyon', 'Overlok|Kapalı kenar|Oyalı|Zincir', null, null, 10],
        ['finish', 'Bitim işlemleri', 'multi', '', 'Konstrüksiyon', 'Yumuşatma|Antibakteriyel|Yanmaz|Su itici|Ön yıkama', null, null, 11],
        ['private_label', 'Private label', 'bool', '', 'Marka', null, null, null, 12],
        ['logo', 'Logo yeri / ölçüsü', 'text', '', 'Marka', null, null, null, 13],
        ['label_type', 'Etiket tipi', 'select', '', 'Marka', 'Dokuma|Baskılı|Bakım etiketi', null, null, 14],
        ['quality', 'Kalite standardı', 'select', '', 'Test', 'AQL 1.5|AQL 2.5|AQL 4.0', null, null, 15],
        ['shrink', 'Çekme toleransı', 'number', '%', 'Test', null, 0, 15, 16],
    ];
    $aid = [];
    foreach ($attrs as $a) {
        qx('INSERT INTO attribute_definitions(key,label,dtype,unit,options,minv,maxv,grp,ord) VALUES(?,?,?,?,?,?,?,?,?)',
            [$a[0], $a[1], $a[2], $a[3], $a[5] ?? '', $a[6], $a[7], $a[4], $a[8] ?? 99]);
        $aid[$a[0]] = (int)db()->lastInsertId();
    }
    // Tip-alan eslemesi (prototipteki T tanimlari: [alan, zorunlu])
    $map = [
        'Ev Tekstili' => [['fiber',1],['structure',0],['gsm',1],['cert',0],['size',1],['color',1],['edge',0],['finish',0],['private_label',0],['logo',0],['quality',0]],
        'Havlu' => [['shrink',0],['structure',1]],
        'Nevresim' => [['thread_count',1],['gsm',0]],
        'Bornoz' => [['shrink',0]],
        'Apparel' => [['fiber',1],['structure',0],['gsm',1],['fit',1],['color',1],['print',0],['finish',0],['private_label',0],['logo',0],['label_type',0],['quality',0],['shrink',0]],
        'Sweatshirt' => [['structure',1]],
        'Promosyon Tekstil' => [['fiber',0],['size',1],['color',1],['print',1],['logo',1]],
    ];
    foreach ($map as $tn => $list) {
        foreach ($list as $i => $pair) {
            qx('INSERT INTO product_type_attributes(product_type_id,attribute_id,required,ord) VALUES(?,?,?,?)',
                [$tid[$tn], $aid[$pair[0]], $pair[1], $i]);
        }
    }
}
seed_products_if_empty();

function q1($sql, $a = []) { return nx_db_retry(function () use ($sql, $a) { $st = db()->prepare($sql); $st->execute($a); return $st->fetch(); }); }
function qa($sql, $a = []) { return nx_db_retry(function () use ($sql, $a) { $st = db()->prepare($sql); $st->execute($a); return $st->fetchAll(); }); }
function qx($sql, $a = []) { return nx_db_retry(function () use ($sql, $a) { $st = db()->prepare($sql); $st->execute($a); return $st->rowCount(); }); }

// ---------- dayanikli DB cagri sarmalayicisi ----------
// Paylasimli hosting'de SQLite gecici "disk I/O error" / "database is locked" verebilir;
// bu hatalar kalicı olmadigindan kisa beklemeyle otomatik yeniden denenir.
// NOT: q1/qa/qx tek SQL cumlesi calistirir; basarisiz (istisna atan) cumle veri yazmadigindan
// cumle bazinda yeniden deneme guvenlidir (cift yazma olusmaz).
function nx_db_retry(callable $fn, int $tries = 3) {
    $delayUs = 150000; // 0.15s, her denemede x2
    for ($attempt = 1; ; $attempt++) {
        try {
            return $fn();
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $transient = stripos($msg, 'locked') !== false || stripos($msg, 'disk I/O') !== false
                || stripos($msg, 'disk image is malformed') !== false || stripos($msg, 'interrupted') !== false;
            if (!$transient || $attempt >= $tries) throw $e;
            usleep($delayUs);
            $delayUs *= 2;
        }
    }
}

// User-Agent'i kisa cihaz tarifiyle ozetle.
function ua_device($ua) {
    $ua = (string)$ua;
    $os = 'Bilinmeyen işletim sistemi';
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
    elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';
    $br = 'Bilinmeyen tarayıcı';
    if (stripos($ua, 'Edg/') !== false) $br = 'Edge';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $br = 'Opera';
    elseif (stripos($ua, 'Chrome/') !== false) $br = 'Chrome';
    elseif (stripos($ua, 'Safari/') !== false) $br = 'Safari';
    elseif (stripos($ua, 'Firefox/') !== false) $br = 'Firefox';
    $m = stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false;
    return $br . ' · ' . $os . ($m ? ' · mobil' : '');
}
function write_log($event, $user = null, $detail = '', $entity = '', $entity_id = 0, $entity_name = '', $changes = []) {
    // changes: ['alan' => ['eski' => x, 'yeni' => y], ...] - JSON'a cevrilir.
    $chg = '';
    if ($changes) {
        $chg = json_encode($changes, JSON_UNESCAPED_UNICODE);
        if (strlen($chg) > 4000) $chg = substr($chg, 0, 4000);
    }
    qx('INSERT INTO log(ts,user_id,username,event,entity,entity_id,entity_name,changes,detail,ip,ua,device) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
        [time(), $user['id'] ?? null, $user['name'] ?? '', $event, $entity, (int)$entity_id, s($entity_name, 120), $chg, s($detail, 300), client_ip(), s($_SERVER['HTTP_USER_AGENT'] ?? '', 200), s(ua_device($_SERVER['HTTP_USER_AGENT'] ?? ''), 80)]);
}
// Iki kaydin alanlarini karsilastir, degisenleri ['alan'=>['eski','yeni']] olarak dondur.
function diff_fields($old, $new, $fields) {
    $diff = [];
    foreach ($fields as $f) {
        $o = (string)($old[$f] ?? '');
        $n = (string)($new[$f] ?? '');
        if ($o !== $n) $diff[$f] = ['eski' => mb_substr($o, 0, 200), 'yeni' => mb_substr($n, 0, 200)];
    }
    return $diff;
}
function userNameById($id) {
    $r = q1('SELECT name FROM users WHERE id=?', [(int)$id]);
    return $r ? $r['name'] : ('#' . (int)$id);
}
function companyNameById($id) {
    $r = q1('SELECT name FROM companies WHERE id=?', [(int)$id]);
    return $r ? $r['name'] : ('#' . (int)$id);
}
function add_activity($user_id, $text) {
    qx('INSERT INTO activity(ts,user_id,text) VALUES(?,?,?)', [time(), $user_id, s($text, 200)]);
}

// ---------- session ----------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '43200');
session_name('NXSID');
session_save_path(NX_DATA . '/sessions');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);

function current_user() {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['uid'])) return null;
    $u = q1('SELECT * FROM users WHERE id=? AND active=1', [(int)$_SESSION['uid']]);
    if (!$u) { unset($_SESSION['uid']); return null; }
    return $u;
}
function start_session_for($u) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
}
function require_user() {
    $u = current_user();
    if (!$u) j(401, ['error' => 'Oturum yok. Lütfen giriş yapın.']);
    return $u;
}
function require_admin() {
    $u = require_user();
    if ($u['role'] !== 'manager') j(403, ['error' => 'Bu işlem için yönetici yetkisi gerekir.']);
    return $u;
}

// ---------- domain helpers ----------
const STAGES = ['İhtiyaç', 'Teklif hazırlanıyor', 'Teklif gönderildi', 'Müzakere', 'Sipariş onayı'];
const TASK_STATUSES = ['Yapılacak', 'Devam ediyor', 'Bekliyor', 'Tamamlandı'];
const WAITING = ['Biz', 'Müşteri', 'Tedarikçi'];
const COMPANY_STATUS = ['Aday', 'Aktif müşteri', 'Pasif'];
const SOURCES = ['Referans', 'Fuar', 'Web sitesi', 'E-posta', 'Telefon', 'İş ortağı', 'Diğer'];
const PROJECT_TYPES = ['Numune', 'Sipariş', 'İç geliştirme'];
const NOTE_TYPES = ['E-posta', 'Telefon', 'Toplantı', 'Fuar', 'WhatsApp', 'Not'];
const CURRENCIES = ['USD', 'EUR', 'TRY'];
const ROLES = ['manager', 'agent'];
const PALETTE = ['#204e40', '#a16627', '#526b89', '#8f4a6a', '#7a6a3f', '#5a7d5a', '#6b5b95', '#b5651d', '#2f6f6f'];

function is_open_stage($st) { return in_array($st, STAGES, true); }
function in_scalar($v, $list) { return in_array((string)$v, $list, true); }
function valid_date($d) { return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; }
function valid_id($id) { return is_numeric($id) && (int)$id > 0; }
function pw_ok($p) { return is_string($p) && strlen($p) >= 10; }

// Otomatik gecici sifre: karisik karakter seti (0/O/1/l cikarilmis), guclu rastgelelik.
function gen_pw($len = 12) {
    $sets = ['abcdefghijkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!#%+*?_-'];
    $pw = '';
    foreach ($sets as $set) $pw .= $set[random_int(0, strlen($set) - 1)];
    $all = implode('', $sets);
    for ($i = strlen($pw); $i < $len; $i++) $pw .= $all[random_int(0, strlen($all) - 1)];
    return str_shuffle($pw);
}

// ---------- mail (SMTP) ----------
// cPanel e-posta hesabi, 465 numarali port (implicit TLS). Sorun olursa yazilim loglarina dusar, akisi bozmaz.
function nx_smtp_send($to, $subject, $html) {
    $mc = $GLOBALS['NX_MAIL'] ?? [];
    if (empty($mc['user']) || empty($mc['pass']) || empty($mc['from'])) return false; // yapilandirma yok -> mail() yedegi
    $host = $mc['host'] ?: 'tls://mail.niateks.com';
    $port = (int)($mc['port'] ?: 465);
    $user = $mc['user']; $pass = $mc['pass'];
    $from = $mc['from']; $fromName = $mc['fromName'] ?: 'Niateks House CRM';
    $errNo = 0; $errStr = '';
    $sock = @fsockopen($host, $port, $errNo, $errStr, 10);
    if (!$sock) { error_log('nx_smtp: connect ' . $errStr); return false; }
    stream_set_timeout($sock, 15);
    $read = function () use ($sock) {
        $out = '';
        while (($l = fgets($sock, 1024)) !== false) { $out .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        return $out;
    };
    $cmd = function ($c, $expect = '250') use ($sock, $read) {
        fwrite($sock, $c . "\r\n");
        $r = $read();
        if (strncmp($r, $expect, strlen($expect)) !== 0) { error_log('nx_smtp: ' . substr($c, 0, 4) . ' -> ' . trim($r)); return false; }
        return $r;
    };
    try {
        $r = $read(); if (strncmp($r, '220', 3) !== 0) throw new Exception('banner: ' . trim($r));
        if (!$cmd('EHLO koza.niateks.com')) throw new Exception('EHLO');
        if (!$cmd('AUTH LOGIN', '334')) throw new Exception('AUTH');
        if (!$cmd(base64_encode($user), '334')) throw new Exception('AUTH user');
        if (!$cmd(base64_encode($pass), '235')) throw new Exception('AUTH pass');
        if (!$cmd('MAIL FROM:<' . $from . '>', '250')) throw new Exception('MAIL FROM');
        if (!$cmd('RCPT TO:<' . $to . '>', '250')) throw new Exception('RCPT TO: ' . trim($r));
        if (!$cmd('DATA', '354')) throw new Exception('DATA');
        $headers = 'From: ' . $fromName . ' <' . $from . '>' . "\r\n"
            . 'To: <' . $to . '>' . "\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=' . "\r\n"
            . 'MIME-Version: 1.0' . "\r\n"
            . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@niateks.com>' . "\r\n";
        $body = preg_replace('/^\./m', '..', $html);
        fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
        $r = $read();
        if (strncmp($r, '250', 3) !== 0) throw new Exception('DATA yaniti: ' . trim($r));
        fwrite($sock, "QUIT\r\n");
        fclose($sock);
        return true;
    } catch (Throwable $e) {
        error_log('nx_smtp: ' . $e->getMessage());
        @fclose($sock);
        return false;
    }
}

// Basit HTML mail (paylasimli hosting: PHP mail()). Basarisizsa false doner; akis mail hatasina takilmaz.
function nx_send_mail($to, $subject, $html) {
    $ok = nx_smtp_send($to, $subject, $html);
    if ($ok) return true;
    $from = ($GLOBALS['NX_MAIL']['from'] ?? '') ?: 'web@niateks.com';
    $headers = 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'From: Niateks House CRM <' . $from . '>' . "\r\n"
        . 'Reply-To: ' . $from . "\r\n"
        . 'X-Mailer: NiateksHouseCRM';
    try {
        return (bool)@mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers, '-f' . $from);
    } catch (\Throwable $e) {
        return false;
    }
}

// ---------- captcha (giris guvenlik kodu) ----------
// Karisabilecek karakterler tamamen disarida: O/0, I/1/l yok.
const CAPTCHA_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

function captcha_make() {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $code = '';
    for ($i = 0; $i < 5; $i++) $code .= CAPTCHA_CHARS[random_int(0, strlen(CAPTCHA_CHARS) - 1)];
    $_SESSION['captcha'] = ['code' => $code, 'ts' => time()];
    return $code;
}
function captcha_check($input) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $c = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']); // tek kullanimlik
    if (!$c || !is_string($input)) return false;
    if ((time() - (int)$c['ts']) > 600) return false; // 10 dk gecerli
    return hash_equals((string)$c['code'], strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input)));
}
function captcha_svg($code) {
    $w = 190; $h = 62;
    $colors = ['#c4673c', '#204e40', '#526b89', '#8f4a6a', '#7a6a3f'];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Güvenlik kodu">';
    $svg .= '<rect width="' . $w . '" height="' . $h . '" rx="10" fill="#f3eee4"/>';
    for ($i = 0; $i < 5; $i++) $svg .= '<circle cx="' . random_int(6, $w - 6) . '" cy="' . random_int(6, $h - 6) . '" r="' . (random_int(10, 22) / 10) . '" fill="' . $colors[array_rand($colors)] . '" opacity="0.18"/>';
    for ($i = 0; $i < 3; $i++) {
        $x1 = random_int(0, (int)($w / 3)); $x2 = random_int((int)($w / 2), $w);
        $svg .= '<path d="M' . $x1 . ' ' . random_int(4, $h - 4) . ' Q ' . (int)(($x1 + $x2) / 2) . ' ' . random_int(4, $h - 4) . ' ' . $x2 . ' ' . random_int(4, $h - 4) . '" fill="none" stroke="' . $colors[array_rand($colors)] . '" stroke-width="1.2" opacity="0.35" stroke-linecap="round"/>';
    }
    $x = 26;
    foreach (str_split($code) as $i => $ch) {
        $cx = $x + 12;
        $svg .= '<text x="' . $x . '" y="' . (34 + random_int(-30, 30) / 10) . '" transform="rotate(' . (random_int(-160, 160) / 10) . ' ' . $cx . ' 34)" font-family="Georgia, Times New Roman, serif" font-size="' . random_int(26, 31) . '" font-weight="600" fill="' . $colors[($i + random_int(0, 4)) % 5] . '">' . htmlspecialchars($ch) . '</text>';
        $x += random_int(30, 35);
    }
    return $svg . '</svg>';
}

// Kullanici karsilama / gecici sifre maili (hesap olusturma ve 'sifremi unuttum' ortak sablon).
function pw_welcome_mail($name, $idn, $pw, $forgot = false) {
    $host = 'https://koza.niateks.com/';
    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;padding:24px;background:#f3eee4;border-radius:12px">'
        . '<h2 style="color:#232b18;margin:0 0 4px">Niateks House CRM</h2>'
        . '<p style="color:#5a5344;margin:0 0 16px">Merhaba ' . htmlspecialchars($name) . ',</p>'
        . '<p style="color:#232b18;margin:0 0 12px">' . ($forgot
            ? 'Şifreniz sıfırlandı. Aşağıdaki geçici şifreyle giriş yapabilirsiniz:'
            : 'CRM hesabınız oluşturuldu. Aşağıdaki bilgilerle giriş yapabilirsiniz:') . '</p>'
        . '<table style="background:#fbf8f1;border-radius:8px;padding:12px 16px;width:100%;border-collapse:separate">'
        . '<tr><td style="color:#5a5344;padding:4px 0">Giriş (kullanıcı adı veya e-posta)</td><td style="text-align:right"><b>' . htmlspecialchars($idn) . '</b></td></tr>'
        . '<tr><td style="color:#5a5344;padding:4px 0">Geçici şifre</td><td style="text-align:right"><b style="color:#c4673c;font-size:16px">' . htmlspecialchars($pw) . '</b></td></tr>'
        . '</table>'
        . '<p style="color:#5a5344;margin:12px 0 4px">Giriş yaptığınızda yeni bir şifre belirlemeniz istenecek.</p>'
        . '<p style="margin:16px 0"><a href="' . htmlspecialchars($host) . '" style="background:#c4673c;color:#ffffff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block">Giriş yapmak için tıklayın</a></p>'
        . '<p style="color:#8a8272;font-size:12px;margin:8px 0 0">Bu e-posta bilgilendirme amaçlıdır; lütfen yanıtlamayın.</p>'
        . '</div>';
}
function today() { return date('Y-m-d'); }
function day_offset($n) { return date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days')); }

// Telefon listesini normalize et (en fazla 5 kayıt).
function phones_normalize($arr) {
    $out = [];
    if (!is_array($arr)) return $out;
    foreach ($arr as $p) {
        if (!is_array($p)) continue;
        $type = in_scalar($p['type'] ?? '', ['Cep', 'İş', 'Diğer']) ? $p['type'] : 'Cep';
        $num = preg_replace('/[^0-9\+\-\(\) \/]/', '', (string)($p['number'] ?? ''));
        if ($num === '' || trim($num) === '') continue;
        $out[] = ['type' => $type, 'cc' => s($p['cc'] ?? '+90', 6), 'number' => s($num, 24)];
        if (count($out) >= 5) break;
    }
    return $out;
}
function save_phones($company_id, $arr) {
    $list = phones_normalize($arr);
    qx('DELETE FROM company_phones WHERE company_id=?', [(int)$company_id]);
    foreach ($list as $p) {
        qx('INSERT INTO company_phones(company_id,type,cc,number) VALUES(?,?,?,?)', [(int)$company_id, $p['type'], $p['cc'], $p['number']]);
    }
    return count($list);
}

// ---------- proje görselleri ----------
if (!defined('NX_UPLOADS')) define('NX_UPLOADS', NX_DATA . '/uploads');
if (!is_dir(NX_UPLOADS)) @mkdir(NX_UPLOADS, 0775, true);
@file_put_contents(NX_UPLOADS . '/.htaccess', "Require all denied\n");
function img_ext_ok($name) { $e = strtolower(pathinfo($name, PATHINFO_EXTENSION)); return in_array($e, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true); }
function project_images_list($pid) {
    $rows = qa('SELECT id, file, orig_name, caption, created_at, created_by FROM project_images WHERE project_id=? AND deleted=0 ORDER BY id', [(int)$pid]);
    $umap = []; foreach (qa('SELECT id, name FROM users') as $x) $umap[(int)$x['id']] = $x['name'];
    return array_map(fn($r) => ['id' => (int)$r['id'], 'file' => $r['file'], 'orig_name' => $r['orig_name'], 'caption' => $r['caption'],
        'created_at' => (int)$r['created_at'], 'created_by_name' => $umap[(int)$r['created_by']] ?? '—'], $rows);
}

// Yetkilileri kaydet: isim dolu olanlar tutulur; is_primary en fazla 1; en fazla 10 kayıt.
function save_contacts($company_id, $arr) {
    $out = [];
    if (!is_array($arr)) return 0;
    foreach ($arr as $k => $p) {
        if (!is_array($p)) continue;
        $name = s($p['name'] ?? '', 120);
        if ($name === '') continue;
        $out[] = [
            'name' => $name,
            'title' => s($p['title'] ?? '', 60),
            'email' => s($p['email'] ?? '', 120),
            'phone' => preg_replace('/[^0-9\+\-\(\) \/]/', '', (string)($p['phone'] ?? '')),
            'is_primary' => !empty($p['is_primary']) ? 1 : 0,
            'ord' => is_numeric($k) ? (int)$k : 0,
        ];
        if (count($out) >= 10) break;
    }
    // Birincil yoksa ilk kişiyi birincil yap; birden çoksa ilki kalır.
    if ($out && !count(array_filter($out, fn($x) => $x['is_primary']))) $out[0]['is_primary'] = 1;
    $seen = false;
    foreach ($out as &$o) { if ($o['is_primary'] && !$seen) { $seen = true; } else { $o['is_primary'] = 0; } }
    unset($o);
    qx('DELETE FROM company_contacts WHERE company_id=?', [(int)$company_id]);
    foreach ($out as $o) {
        qx('INSERT INTO company_contacts(company_id,name,title,email,phone,is_primary,ord) VALUES(?,?,?,?,?,?,?)',
            [(int)$company_id, $o['name'], $o['title'], $o['email'], $o['phone'], $o['is_primary'], $o['ord']]);
    }
    return count($out);
}

function pub_user($u) {
    return ['id' => (int)$u['id'], 'name' => $u['name'], 'username' => $u['username'], 'email' => $u['email'],
            'role' => $u['role'], 'color' => $u['color'], 'last_login' => $u['last_login'] ? (int)$u['last_login'] : null,
            'must_change' => !empty($u['must_change'])];
}

// Kayıt görünürlüğü: temsilci yalnız kendi kayıtlarını görür; yönetici her şeyi.
function visible_companies($u) {
    if ($u['role'] === 'manager') return qa('SELECT * FROM companies WHERE deleted=0 ORDER BY name COLLATE NOCASE');
    return qa('SELECT * FROM companies WHERE deleted=0 AND (owner_id=? OR created_by=?) ORDER BY name COLLATE NOCASE', [(int)$u['id'], (int)$u['id']]);
}
function company_ids_of($u) {
    return array_map(fn($c) => (int)$c['id'], visible_companies($u));
}
function can_see_company($u, $company_id) {
    if ($u['role'] === 'manager') return true;
    $c = q1('SELECT id FROM companies WHERE id=? AND deleted=0', [(int)$company_id]);
    if (!$c) return false;
    $row = q1('SELECT owner_id, created_by FROM companies WHERE id=?', [(int)$company_id]);
    return (int)$row['owner_id'] === (int)$u['id'] || (int)$row['created_by'] === (int)$u['id'];
}

// ---------- routing ----------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
if ($path === '/api.php') $path = '/api';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base !== '' && strpos($path, $base) === 0) $path = substr($path, $base) ?: '/api';
if (strpos($path, '/api') !== 0) j(404, ['error' => 'Bilinmeyen yol.']);

// CSRF: JSON gövdeli her POST'ta özel başlık zorunlu.
if ($method === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'niateks') j(403, ['error' => 'Geçersiz istek.']);

// ---------- setup / status ----------
if ($path === '/api/status' && $method === 'GET') {
    $n = (int)q1('SELECT COUNT(*) c FROM users')['c'];
    $u = current_user();
    j(200, ['setup_needed' => $n === 0, 'user' => $u ? pub_user($u) : null]);
}

if ($path === '/api/setup' && $method === 'POST') {
    if ((int)q1('SELECT COUNT(*) c FROM users')['c'] > 0) j(403, ['error' => 'Kurulum zaten yapılmış.']);
    $b = body_json();
    $codeFile = NX_DATA . '/setup_code.txt';
    $code = is_file($codeFile) ? trim((string)file_get_contents($codeFile)) : '';
    if ($code === '' || !hash_equals($code, trim((string)($b['code'] ?? '')))) j(403, ['error' => 'Kurulum kodu hatalı.']);
    $un = s($b['username'] ?? '', 32);
    if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $un)) j(400, ['error' => 'Kullanıcı adı 3–32 karakter (harf, rakam, . _ -).']);
    if (!pw_ok($b['password'] ?? '')) j(400, ['error' => 'Şifre en az 10 karakter olmalı.']);
    $name = s($b['name'] ?? '', 80) ?: $un;
    $em = s($b['email'] ?? '', 120);
    if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) j(400, ['error' => 'E-posta geçersiz.']);
    qx('INSERT INTO users(username,name,email,role,color,hash,created_at) VALUES(?,?,?,?,?,?,?)',
        [$un, $name, $em, 'manager', PALETTE[0], password_hash($b['password'], PASSWORD_DEFAULT), time()]);
    $u = q1('SELECT * FROM users WHERE username=?', [$un]);
    @unlink($codeFile);
    write_log('setup', $u, 'İlk yönetici oluşturuldu');
    start_session_for($u);
    j(200, ['ok' => true, 'user' => pub_user($u)]);
}

// ---------- login / logout ----------
if ($path === '/api/captcha' && $method === 'GET') {
    $code = captcha_make();
    j(200, ['svg' => captcha_svg($code)]);
}

if ($path === '/api/login' && $method === 'POST') {
    $b = body_json();
    // Guvenlik kodu: tek kullanimlik, 10 dk gecerli. Sifre denemesi sayilmadan once kontrol edilir.
    if (!captcha_check((string)($b['captcha'] ?? ''))) {
        write_log('login_captcha', null, 'Güvenlik kodu hatalı');
        j(400, ['error' => 'Güvenlik kodu hatalı veya süresi doldu.']);
    }
    $ip = client_ip(); $now = time();
    // Basit IP bazlı hız sınırı: 10 dakikada 8 hatalı deneme.
    qx('DELETE FROM fails WHERE ts < ?', [$now - 600]);
    $fails = (int)q1('SELECT COUNT(*) c FROM fails WHERE ip=?', [$ip])['c'];
    if ($fails >= 8) { write_log('login_rate', null, 'Çok fazla hatalı deneme'); j(429, ['error' => 'Çok fazla hatalı deneme. 10 dakika sonra tekrar deneyin.']); }
    $idn = s($b['username'] ?? '', 120);
    $u = q1('SELECT * FROM users WHERE (username=? OR email=?) AND active=1 COLLATE NOCASE', [$idn, $idn]);
    if (!$u || !password_verify((string)($b['password'] ?? ''), $u['hash'])) {
        qx('INSERT INTO fails(ip,ts) VALUES(?,?)', [$ip, $now]);
        write_log('login_fail', $u, 'Hatalı giriş: ' . $idn);
        j(401, ['error' => 'Kullanıcı adı/e-posta veya şifre hatalı.']);
    }
    $_SESSION['uid'] = (int)$u['id'];
    start_session_for($u);
    qx('UPDATE users SET last_login=? WHERE id=?', [$now, (int)$u['id']]);
    write_log('login', $u, 'Giriş yapıldı');
    j(200, ['ok' => true, 'user' => pub_user($u)]);
}

if ($path === '/api/logout' && $method === 'POST') {
    $u = current_user();
    if ($u) write_log('logout', $u, 'Çıkış yapıldı');
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION = [];
    session_destroy();
    j(200, ['ok' => true]);
}

if ($path === '/api/password' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    if (!password_verify((string)($b['old'] ?? ''), $u['hash'])) j(400, ['error' => 'Mevcut şifre hatalı.']);
    if (!pw_ok($b['new'] ?? '')) j(400, ['error' => 'Yeni şifre en az 10 karakter olmalı.']);
    qx('UPDATE users SET hash=?, must_change=0 WHERE id=?', [password_hash((string)$b['new'], PASSWORD_DEFAULT), (int)$u['id']]);
    write_log('password', $u, 'Şifre değiştirildi');
    j(200, ['ok' => true]);
}

// ---------- users (admin) ----------
if ($path === '/api/users' && $method === 'GET') {
    require_admin();
    j(200, ['users' => array_map('pub_user', qa('SELECT * FROM users WHERE active=1 ORDER BY name COLLATE NOCASE'))]);
}

if ($path === '/api/users' && $method === 'POST') {
    $me = require_admin(); $b = body_json();
    $un = s($b['username'] ?? '', 32);
    if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $un)) j(400, ['error' => 'Kullanıcı adı 3–32 karakter (harf, rakam, . _ -).']);
    if (q1('SELECT id FROM users WHERE username=?', [$un])) j(409, ['error' => 'Bu kullanıcı adı zaten kullanılıyor.']);
    // Sifre: otomatik uretilmis gecici sifre (maille gider, ilk giriste degistirtilir) veya adminin girdigi manuel sifre.
    $auto = !empty($b['auto_password']);
    $password = $auto ? gen_pw(12) : (string)($b['password'] ?? '');
    if (!pw_ok($password)) j(400, ['error' => 'Şifre en az 10 karakter olmalı.']);
    $name = s($b['name'] ?? '', 80) ?: $un;
    $em = s($b['email'] ?? '', 120);
    if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) j(400, ['error' => 'E-posta geçersiz.']);
    $role = in_scalar($b['role'] ?? '', ROLES) ? $b['role'] : 'agent';
    $color = PALETTE[array_rand(PALETTE)];
    qx('INSERT INTO users(username,name,email,role,color,hash,created_at,must_change) VALUES(?,?,?,?,?,?,?,?)',
        [$un, $name, $em, $role, $color, password_hash($password, PASSWORD_DEFAULT), time(), $auto ? 1 : 0]);
    $u = q1('SELECT * FROM users WHERE username=?', [$un]);
    $mailSent = false;
    if ($auto && $em !== '') {
        $mailSent = nx_send_mail($em, 'Niateks House CRM — hesabınız oluşturuldu', pw_welcome_mail($name, $un, $password));
        if (!$mailSent) error_log('nx_mail: user_add mail gonderilemedi -> ' . $em);
    }
    write_log('user_add', $me, $name . ' (' . ($role === 'manager' ? 'Yönetici' : 'Temsilci') . ') eklendi'
        . ($auto ? ($mailSent ? ' — geçici şifre e-postayla gönderildi' : ' — geçici şifre e-postayla gönderilemedi') : ''), 'user', (int)$u['id'], $name);
    j(200, ['ok' => true, 'user' => pub_user($u), 'password' => $auto ? $password : null, 'mail_sent' => $mailSent]);
}

if ($path === '/api/users/update' && $method === 'POST') {
    $me = require_admin(); $b = body_json();
    $t = q1('SELECT * FROM users WHERE id=? AND active=1', [(int)($b['id'] ?? 0)]);
    if (!$t) j(404, ['error' => 'Kullanıcı bulunamadı.']);
    if ($t['id'] === $me['id'] && ($b['role'] ?? '') !== 'manager') j(400, ['error' => 'Kendi yönetici rolünüzü düşüremezsiniz.']);
    $role = in_scalar($b['role'] ?? '', ROLES) ? $b['role'] : $t['role'];
    $name = s($b['name'] ?? '', 80) ?: $t['name'];
    $em = s($b['email'] ?? '', 120);
    if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) j(400, ['error' => 'E-posta geçersiz.']);
    $active = 1; // Devre dışı bırakma ayrı uçtan yapılır.
    qx('UPDATE users SET name=?, email=?, role=?, active=? WHERE id=?', [$name, $em, $role, $active, (int)$t['id']]);
    write_log('user_update', $me, $name . ' güncellendi', 'user', (int)$t['id'], $name, diff_fields($t, ['name' => $name, 'email' => $em, 'role' => $role], ['name', 'email', 'role']));
    j(200, ['ok' => true, 'user' => pub_user(q1('SELECT * FROM users WHERE id=?', [(int)$t['id']]))]);
}

if ($path === '/api/users/deactivate' && $method === 'POST') {
    $me = require_admin(); $b = body_json();
    $t = q1('SELECT * FROM users WHERE id=? AND active=1', [(int)($b['id'] ?? 0)]);
    if (!$t) j(404, ['error' => 'Kullanıcı bulunamadı.']);
    if ((int)$t['id'] === (int)$me['id']) j(400, ['error' => 'Kendinizi devre dışı bırakamazsınız.']);
    $n = (int)q1('SELECT COUNT(*) c FROM users WHERE role=? AND active=1', ['manager'])['c'];
    if ($t['role'] === 'manager' && $n <= 1) j(400, ['error' => 'Son yönetici devre dışı bırakılamaz.']);
    qx('UPDATE users SET active=0 WHERE id=?', [(int)$t['id']]);
    write_log('user_deactivate', $me, $t['name'] . ' devre dışı bırakıldı', 'user', (int)$t['id'], $t['name']);
    j(200, ['ok' => true]);
}

if ($path === '/api/users/reset' && $method === 'POST') {
    $me = require_admin(); $b = body_json();
    $t = q1('SELECT * FROM users WHERE id=? AND active=1', [(int)($b['id'] ?? 0)]);
    if (!$t) j(404, ['error' => 'Kullanıcı bulunamadı.']);
    if (!pw_ok($b['password'] ?? '')) j(400, ['error' => 'Şifre en az 10 karakter olmalı.']);
    qx('UPDATE users SET hash=?, must_change=0 WHERE id=?', [password_hash((string)$b['password'], PASSWORD_DEFAULT), (int)$t['id']]);
    write_log('user_reset', $me, $t['name'] . ' için şifre sıfırlandı', 'user', (int)$t['id'], $t['name']);
    j(200, ['ok' => true]);
}

// ---------- sifremi unuttum (giris gerektirmez) ----------
if ($path === '/api/forgot' && $method === 'POST') {
    $b = body_json();
    $em = s($b['email'] ?? '', 120);
    if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) j(400, ['error' => 'Geçerli bir e-posta adresi girin.']);
    $ip = client_ip(); $now = time();
    // Hiz siniri: ayni e-posta icin 20 sn'de bir; ayni IP icin 10 dakikada 8 istek.
    $last = q1('SELECT pr.ts ts FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE u.email=? COLLATE NOCASE ORDER BY pr.ts DESC LIMIT 1', [$em]);
    if ($last && ($now - (int)$last['ts']) < 20) j(429, ['error' => 'Çok sık istek. ' . (20 - ($now - (int)$last['ts'])) . ' saniye sonra tekrar deneyin.']);
    qx('DELETE FROM password_resets WHERE ts < ?', [$now - 600]);
    if ((int)q1('SELECT COUNT(*) c FROM password_resets WHERE ip=?', [$ip])['c'] >= 8) {
        write_log('forgot_rate', null, 'Şifremi unuttum: çok fazla istek');
        j(429, ['error' => 'Çok fazla istek. 10 dakika sonra tekrar deneyin.']);
    }
    $u = q1('SELECT * FROM users WHERE email=? AND active=1 COLLATE NOCASE', [$em]);
    if ($u) {
        $pw = gen_pw(12);
        qx('UPDATE users SET hash=?, must_change=1 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), (int)$u['id']]);
        qx('INSERT INTO password_resets(user_id,ip,ts) VALUES(?,?,?)', [(int)$u['id'], $ip, $now]);
        if (!nx_send_mail($u['email'], 'Niateks House CRM — geçici şifreniz', pw_welcome_mail($u['name'], $u['email'], $pw, true))) error_log('nx_mail: forgot mail gonderilemedi -> ' . $u['email']);
        write_log('forgot', $u, 'Şifremi unuttum: geçici şifre e-postayla gönderildi');
    }
    // E-postanin sistemde var olup olmadigi açıklanmasin: her durumda aynı yanıt.
    j(200, ['ok' => true, 'sent' => true]);
}

// ---------- companies ----------
if ($path === '/api/companies' && $method === 'GET') {
    $u = require_user();
    $companies = visible_companies($u);
    $deals = qa('SELECT * FROM deals');
    $tasks = qa("SELECT * FROM tasks WHERE status!='Tamamlandı'");
    $projects = qa('SELECT id, name, company_id FROM projects');
    $users = qa('SELECT id, name, color FROM users WHERE active=1');
    $umap = []; foreach ($users as $x) $umap[(int)$x['id']] = $x;
    $phonesAll = qa('SELECT id, company_id, type, cc, number FROM company_phones');
    $phonesMap = [];
    foreach ($phonesAll as $ph) $phonesMap[(int)$ph['company_id']][] = ['id' => (int)$ph['id'], 'type' => $ph['type'], 'cc' => $ph['cc'], 'number' => $ph['number']];
    $contactsAll = qa('SELECT id, company_id, name, title, email, phone, is_primary FROM company_contacts ORDER BY is_primary DESC, ord, id');
    $contactsMap = [];
    foreach ($contactsAll as $ct) $contactsMap[(int)$ct['company_id']][] = ['id' => (int)$ct['id'], 'name' => $ct['name'], 'title' => $ct['title'], 'email' => $ct['email'], 'phone' => $ct['phone'], 'is_primary' => (int)$ct['is_primary']];
    // Eski tek yetkiliyi otomatik tasi (bir kez): contacts bos VE contact dolu ise yeni tabloya yaz.
    foreach ($companies as $c0) {
        if (empty($contactsMap[(int)$c0['id']]) && trim((string)$c0['contact']) !== '') {
            qx('INSERT INTO company_contacts(company_id,name,title,email,phone,is_primary,ord) VALUES(?,?,?,?,?,1,0)',
                [(int)$c0['id'], s($c0['contact'], 120), s($c0['contact_title'], 60), s($c0['email'], 120), '']);
            $contactsMap[(int)$c0['id']] = [['id' => (int)db()->lastInsertId(), 'name' => $c0['contact'], 'title' => $c0['contact_title'], 'email' => $c0['email'], 'phone' => '', 'is_primary' => 1]];
        }
    }
    $out = [];
    foreach ($companies as $c) {
        $out[] = [
            'id' => (int)$c['id'], 'name' => $c['name'], 'contact' => $c['contact'], 'contact_title' => $c['contact_title'], 'email' => $c['email'],
            'contacts' => $contactsMap[(int)$c['id']] ?? [],
            'country' => $c['country'], 'source' => $c['source'], 'status' => $c['status'],
            'address1' => $c['address1'] ?? '', 'address2' => $c['address2'] ?? '', 'city' => $c['city'] ?? '',
            'postal_code' => $c['postal_code'] ?? '', 'district' => $c['district'] ?? '',
            'website' => $c['website'] ?? '', 'tax_id' => $c['tax_id'] ?? '',
            'phones' => $phonesMap[(int)$c['id']] ?? [],
            'first_date' => $c['first_date'], 'need' => $c['need'],
            'owner' => $umap[(int)$c['owner_id']]['name'] ?? '—',
            'owner_id' => (int)$c['owner_id'],
            'open_deals' => count(array_filter($deals, fn($d) => (int)$d['company_id'] === (int)$c['id'] && is_open_stage($d['stage']))),
            'next_task' => null,
        ];
    }
    // Sonraki adım: şirkete bağlı en yakın açık görev.
    foreach ($out as &$o) {
        $best = null;
        foreach ($tasks as $t) {
            if ((int)$t['company_id'] !== $o['id']) continue;
            if (!$u['role'] || ($u['role'] !== 'manager' && (int)$t['owner_id'] !== (int)$u['id'] && (int)$t['company_id'] !== $o['id'])) { /* görev görünümü şirket bazında ortak */ }
            if ($best === null || $t['due_date'] < $best['due_date']) $best = $t;
        }
        if ($best) $o['next_task'] = ['id' => (int)$best['id'], 'title' => $best['title'], 'due_date' => $best['due_date'], 'status' => $best['status'], 'waiting' => $best['waiting']];
    }
    unset($o);
    j(200, ['companies' => $out, 'users' => array_values($umap)]);
}

if ($path === '/api/companies' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $name = s($b['name'] ?? '', 120);
    if ($name === '') j(400, ['error' => 'Şirket adını girin.']);
    $dup = q1('SELECT id, name FROM companies WHERE deleted=0 AND LOWER(name)=LOWER(?)', [$name]);
    if ($dup) j(409, ['error' => 'Bu isimde bir müşteri zaten var: ' . $dup['name']]);
    $owner = valid_id($b['owner_id'] ?? 0) ? (int)$b['owner_id'] : (int)$u['id'];
    if ($u['role'] !== 'manager') $owner = (int)$u['id'];
    $status = in_scalar($b['status'] ?? '', COMPANY_STATUS) ? $b['status'] : 'Aday';
    $source = in_scalar($b['source'] ?? '', SOURCES) ? $b['source'] : 'Diğer';
    $date = valid_date($b['first_date'] ?? '') ? $b['first_date'] : today();
    qx('INSERT INTO companies(name,contact,contact_title,email,country,source,owner_id,status,first_date,need,address1,address2,city,postal_code,district,website,tax_id,created_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$name, s($b['contact'] ?? '', 120), s($b['contact_title'] ?? '', 60), s($b['email'] ?? '', 120), s($b['country'] ?? '', 60), $source, $owner, $status, $date, s($b['need'] ?? '', 2000), s($b['address1'] ?? '', 200), s($b['address2'] ?? '', 200), s($b['city'] ?? '', 60), s($b['postal_code'] ?? '', 20), s($b['district'] ?? '', 60), s($b['website'] ?? '', 120), s($b['tax_id'] ?? '', 40), time(), (int)$u['id']]);
    $id = (int)db()->lastInsertId();
    save_phones($id, $b['phones'] ?? []);
    save_contacts($id, $b['contacts'] ?? []);
    write_log('company_add', $u, $name, 'company', $id, $name);
    add_activity((int)$u['id'], $name . ' müşteri kartı oluşturuldu.');
    j(200, ['ok' => true, 'id' => $id]);
}

if ($path === '/api/companies/update' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $c = q1('SELECT * FROM companies WHERE id=? AND deleted=0', [(int)($b['id'] ?? 0)]);
    if (!$c) j(404, ['error' => 'Müşteri bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$c['owner_id'] !== (int)$u['id'] && (int)$c['created_by'] !== (int)$u['id']) j(403, ['error' => 'Bu kaydı düzenleme yetkiniz yok.']);
    $name = s($b['name'] ?? '', 120) ?: $c['name'];
    $dup = q1('SELECT id, name FROM companies WHERE deleted=0 AND LOWER(name)=LOWER(?) AND id!=?', [$name, (int)$c['id']]);
    if ($dup) j(409, ['error' => 'Bu isimde başka bir müşteri var: ' . $dup['name']]);
    $status = in_scalar($b['status'] ?? '', COMPANY_STATUS) ? $b['status'] : $c['status'];
    $source = in_scalar($b['source'] ?? '', SOURCES) ? $b['source'] : $c['source'];
    $owner = valid_id($b['owner_id'] ?? 0) ? (int)$b['owner_id'] : (int)$c['owner_id'];
    if ($u['role'] !== 'manager') $owner = (int)$c['owner_id'];
    $date = valid_date($b['first_date'] ?? '') ? $b['first_date'] : $c['first_date'];
    qx('UPDATE companies SET name=?, contact=?, contact_title=?, email=?, country=?, source=?, owner_id=?, status=?, first_date=?, need=?, address1=?, address2=?, city=?, postal_code=?, district=?, website=?, tax_id=? WHERE id=?',
        [$name, s($b['contact'] ?? $c['contact'], 120), s($b['contact_title'] ?? $c['contact_title'], 60), s($b['email'] ?? $c['email'], 120), s($b['country'] ?? $c['country'], 60), $source, $owner, $status, $date, s($b['need'] ?? $c['need'], 2000), s($b['address1'] ?? $c['address1'], 200), s($b['address2'] ?? $c['address2'], 200), s($b['city'] ?? $c['city'], 60), s($b['postal_code'] ?? $c['postal_code'], 20), s($b['district'] ?? $c['district'], 60),        s($b['website'] ?? $c['website'], 120), s($b['tax_id'] ?? $c['tax_id'], 40), (int)$c['id']]);
    save_phones((int)$c['id'], $b['phones'] ?? []);
    save_contacts((int)$c['id'], $b['contacts'] ?? []);
    $newRow = q1('SELECT * FROM companies WHERE id=?', [(int)$c['id']]);
    $diff = diff_fields($c, array_merge($newRow, $b), ['name','contact','contact_title','email','country','source','status','first_date','need','address1','address2','city','postal_code','district','website','tax_id']);
    // Sorumlu değişimini isimle göster
    if ((int)$owner !== (int)$c['owner_id']) {
        $diff['sorumlu'] = ['eski' => userNameById((int)$c['owner_id']), 'yeni' => userNameById((int)$owner)];
    }
    // Telefon/yetkili değişimini özetle
    if (isset($b['phones'])) { $oldP = count(qa('SELECT id FROM company_phones WHERE company_id=?', [(int)$c['id']])); $newP = save_phones((int)$c['id'], $b['phones']); if ($oldP !== $newP) $diff['telefonlar'] = ['eski' => $oldP . ' numara', 'yeni' => $newP . ' numara']; }
    if (isset($b['contacts'])) { $oldC = count(qa('SELECT id FROM company_contacts WHERE company_id=?', [(int)$c['id']])); $newC = save_contacts((int)$c['id'], $b['contacts']); if ($oldC !== $newC) $diff['yetkililer'] = ['eski' => $oldC . ' kişi', 'yeni' => $newC . ' kişi']; }
    write_log('company_update', $u, $name . ($diff ? ' (' . count($diff) . ' alan değişti)' : ' (değişiklik yok)'), 'company', (int)$c['id'], $name, $diff);
    j(200, ['ok' => true]);
}

if ($path === '/api/companies/delete' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $c = q1('SELECT * FROM companies WHERE id=? AND deleted=0', [(int)($b['id'] ?? 0)]);
    if (!$c) j(404, ['error' => 'Müşteri bulunamadı.']);
    if ($u['role'] !== 'manager') j(403, ['error' => 'Müşteri silme yetkisi yalnız yöneticidedir.']);
    qx('UPDATE companies SET deleted=1, del_by=?, del_at=? WHERE id=?', [(int)$u['id'], time(), (int)$c['id']]);
    qx('DELETE FROM company_phones WHERE company_id=?', [(int)$c['id']]);
    qx('DELETE FROM company_contacts WHERE company_id=?', [(int)$c['id']]);
    write_log('company_delete', $u, $c['name'] . ' (soft delete)', 'company', (int)$c['id'], $c['name']);
    j(200, ['ok' => true]);
}

// ---------- deals ----------
if ($path === '/api/deals' && $method === 'GET') {
    $u = require_user();
    $ids = company_ids_of($u);
    if (!$ids) j(200, ['deals' => [], 'users' => []]);
    $in = implode(',', $ids);
    $deals = qa("SELECT * FROM deals WHERE company_id IN ($in) ORDER BY id DESC");
    $users = qa('SELECT id, name, color FROM users WHERE active=1');
    $umap = []; foreach ($users as $x) $umap[(int)$x['id']] = $x;
    $out = [];
    foreach ($deals as $d) {
        $out[] = [
            'id' => (int)$d['id'], 'company_id' => (int)$d['company_id'], 'title' => $d['title'],
            'value' => (float)$d['value'], 'currency' => $d['currency'], 'stage' => $d['stage'],
            'owner' => $umap[(int)$d['owner_id']]['name'] ?? '—', 'owner_id' => (int)$d['owner_id'],
            'close_date' => $d['close_date'], 'note' => $d['note'], 'reference' => $d['reference'], 'loss' => $d['loss'],
            'won_at' => $d['won_at'] ? (int)$d['won_at'] : null, 'lost_at' => $d['lost_at'] ? (int)$d['lost_at'] : null,
        ];
    }
    j(200, ['deals' => $out, 'users' => array_values($umap)]);
}

if ($path === '/api/deals' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $cid = (int)($b['company_id'] ?? 0);
    if (!valid_id($cid) || !can_see_company($u, $cid)) j(400, ['error' => 'Geçerli bir müşteri seçin.']);
    $title = s($b['title'] ?? '', 160);
    if ($title === '') j(400, ['error' => 'Fırsat adını girin.']);
    $value = (float)($b['value'] ?? 0);
    if ($value < 0) j(400, ['error' => 'Geçerli bir tutar girin.']);
    $cur = in_scalar($b['currency'] ?? '', CURRENCIES) ? $b['currency'] : 'USD';
    $stage = in_scalar($b['stage'] ?? '', STAGES) ? $b['stage'] : 'İhtiyaç';
    $owner = ($u['role'] === 'manager' && valid_id($b['owner_id'] ?? 0)) ? (int)$b['owner_id'] : (int)$u['id'];
    $close = valid_date($b['close_date'] ?? '') ? $b['close_date'] : day_offset(14);
    qx('INSERT INTO deals(company_id,title,value,currency,stage,owner_id,close_date,note,reference,created_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
        [$cid, $title, $value, $cur, $stage, $owner, $close, s($b['note'] ?? '', 2000), s($b['reference'] ?? '', 120), time(), (int)$u['id']]);
    $id = (int)db()->lastInsertId();
    write_log('deal_add', $u, $title . ' (' . $value . ' ' . $cur . ')');
    add_activity((int)$u['id'], $title . ' fırsatı oluşturuldu.');
    j(200, ['ok' => true, 'id' => $id]);
}

if ($path === '/api/deals/update' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $d = q1('SELECT * FROM deals WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$d || !can_see_company($u, $d['company_id'])) j(404, ['error' => 'Fırsat bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$d['owner_id'] !== (int)$u['id'] && (int)$d['created_by'] !== (int)$u['id']) j(403, ['error' => 'Bu fırsatı düzenleme yetkiniz yok.']);
    $title = s($b['title'] ?? '', 160) ?: $d['title'];
    $value = (float)($b['value'] ?? $d['value']);
    if ($value < 0) j(400, ['error' => 'Geçerli bir tutar girin.']);
    $cur = in_scalar($b['currency'] ?? '', CURRENCIES) ? $b['currency'] : $d['currency'];
    $stage = in_scalar($b['stage'] ?? '', STAGES) ? $b['stage'] : $d['stage'];
    $wasOpen = is_open_stage($d['stage']);
    if ($stage === 'Kazanıldı' && !$wasOpen && $d['stage'] === 'Kaybedildi') j(400, ['error' => 'Kaybedilen fırsat kazanıldıya çevrilemez.']);
    if ($stage === 'Kazanıldı' && !s($b['reference'] ?? '', 120) && !$d['reference']) j(400, ['error' => 'Kazanıldı için sipariş/onay referansı gerekli.']);
    if ($stage === 'Kaybedildi' && !s($b['loss'] ?? '', 300) && !$d['loss']) j(400, ['error' => 'Kaybedilme nedenini girin.']);
    $owner = ($u['role'] === 'manager' && valid_id($b['owner_id'] ?? 0)) ? (int)$b['owner_id'] : (int)$d['owner_id'];
    $close = valid_date($b['close_date'] ?? '') ? $b['close_date'] : $d['close_date'];
    $won = $stage === 'Kazanıldı' ? ($d['won_at'] ?: time()) : null;
    $lost = $stage === 'Kaybedildi' ? ($d['lost_at'] ?: time()) : null;
    qx('UPDATE deals SET title=?, value=?, currency=?, stage=?, owner_id=?, close_date=?, note=?, reference=?, loss=?, won_at=COALESCE(?,won_at), lost_at=COALESCE(?,lost_at) WHERE id=?',
        [$title, $value, $cur, $stage, $owner, $close, s($b['note'] ?? $d['note'], 2000), s($b['reference'] ?? $d['reference'], 120), s($b['loss'] ?? $d['loss'], 300), $won, $lost, (int)$d['id']]);
    $dnew = q1('SELECT * FROM deals WHERE id=?', [(int)$d['id']]);
    $dDiff = diff_fields($d, $dnew, ['title','value','currency','stage','close_date','note','reference','loss']);
    if ((int)$owner !== (int)$d['owner_id']) $dDiff['sorumlu'] = ['eski' => userNameById((int)$d['owner_id']), 'yeni' => userNameById((int)$owner)];
    if ($stage !== $d['stage']) write_log('deal_stage', $u, $title . ': ' . $d['stage'] . ' → ' . $stage, 'deal', (int)$d['id'], $title, $dDiff);
    else write_log('deal_update', $u, $title . ($dDiff ? ' (' . count($dDiff) . ' alan değişti)' : ''), 'deal', (int)$d['id'], $title, $dDiff);
    j(200, ['ok' => true]);
}

if ($path === '/api/deals/stage' && $method === 'POST') {
    // Kanban drag-drop: hızlı aşama değişimi.
    $u = require_user(); $b = body_json();
    $d = q1('SELECT * FROM deals WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$d || !can_see_company($u, $d['company_id'])) j(404, ['error' => 'Fırsat bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$d['owner_id'] !== (int)$u['id'] && (int)$d['created_by'] !== (int)$u['id']) j(403, ['error' => 'Bu fırsatı taşıma yetkiniz yok.']);
    $stage = (string)($b['stage'] ?? '');
    if (!in_scalar($stage, array_merge(STAGES, ['Kazanıldı', 'Kaybedildi']))) j(400, ['error' => 'Geçersiz aşama.']);
    if ($stage === 'Kazanıldı' && !$d['reference']) j(400, ['error' => 'Kazanıldı aşaması için önce fırsatı düzenleyip sipariş/onay referansı girin.']);
    if ($stage === 'Kaybedildi' && !$d['loss']) j(400, ['error' => 'Kaybedilme aşaması için önce neden girin.']);
    if ($stage !== $d['stage']) {
        qx('UPDATE deals SET stage=? WHERE id=?', [$stage, (int)$d['id']]);
        write_log('deal_stage', $u, $d['title'] . ': ' . $d['stage'] . ' → ' . $stage, 'deal', (int)$d['id'], $d['title'], ['stage' => ['eski' => $d['stage'], 'yeni' => $stage]]);
        add_activity((int)$u['id'], $d['title'] . ' → ' . $stage);
    }
    j(200, ['ok' => true]);
}

if ($path === '/api/deals/delete' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $d = q1('SELECT * FROM deals WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$d || !can_see_company($u, $d['company_id'])) j(404, ['error' => 'Fırsat bulunamadı.']);
    if ($u['role'] !== 'manager') j(403, ['error' => 'Fırsat silme yetkisi yalnız yöneticidedir.']);
    $nt = qx('DELETE FROM tasks WHERE deal_id=?', [(int)$d['id']]);
    qx('DELETE FROM deals WHERE id=?', [(int)$d['id']]);
    write_log('deal_delete', $u, $d['title'] . ' (' . $nt . ' görev silindi)', 'deal', (int)$d['id'], $d['title']);
    j(200, ['ok' => true]);
}

// ---------- tasks ----------
if ($path === '/api/tasks' && $method === 'GET') {
    $u = require_user();
    $ids = company_ids_of($u);
    if (!$ids) j(200, ['tasks' => [], 'users' => [], 'deals' => [], 'projects' => []]);
    $in = implode(',', $ids);
    $tasks = qa("SELECT * FROM tasks WHERE company_id IN ($in) OR company_id=0 ORDER BY due_date");
    $users = qa('SELECT id, name, color FROM users WHERE active=1');
    $umap = []; foreach ($users as $x) $umap[(int)$x['id']] = $x;
    $deals = qa("SELECT d.id, d.company_id, d.title FROM deals d WHERE d.company_id IN ($in)");
    $projects = qa("SELECT p.id, p.company_id, p.name FROM projects p WHERE p.company_id IN ($in) OR p.company_id=0");
    $out = [];
    foreach ($tasks as $t) {
        if (!$u['role'] || $u['role'] !== 'manager') {
            // Temsilci: yalnız kendi görevleri veya kendi müşterilerinin görevleri.
            $comp = q1('SELECT owner_id, created_by FROM companies WHERE id=?', [(int)$t['company_id']]);
            if ((int)$t['owner_id'] !== (int)$u['id'] && (!$comp || ((int)$comp['owner_id'] !== (int)$u['id'] && (int)$comp['created_by'] !== (int)$u['id']))) continue;
        }
        $out[] = [
            'id' => (int)$t['id'], 'title' => $t['title'], 'company_id' => (int)$t['company_id'],
            'deal_id' => (int)$t['deal_id'], 'project_id' => (int)$t['project_id'],
            'owner' => $umap[(int)$t['owner_id']]['name'] ?? '—', 'owner_id' => (int)$t['owner_id'],
            'due_date' => $t['due_date'], 'status' => $t['status'], 'waiting' => $t['waiting'], 'note' => $t['note'],
        ];
    }
    j(200, ['tasks' => $out, 'users' => array_values($umap),
            'deals' => array_map(fn($d) => ['id' => (int)$d['id'], 'company_id' => (int)$d['company_id'], 'title' => $d['title']], $deals),
            'projects' => array_map(fn($p) => ['id' => (int)$p['id'], 'company_id' => (int)$p['company_id'], 'name' => $p['name']], $projects)]);
}

if ($path === '/api/tasks' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $title = s($b['title'] ?? '', 200);
    if ($title === '') j(400, ['error' => 'Kısa bir görev başlığı girin.']);
    $cid = (int)($b['company_id'] ?? 0);
    if ($cid && !can_see_company($u, $cid)) j(400, ['error' => 'Geçerli bir müşteri seçin.']);
    $did = (int)($b['deal_id'] ?? 0);
    if ($did) {
        $d = q1('SELECT company_id FROM deals WHERE id=?', [$did]);
        if (!$d || !can_see_company($u, $d['company_id'])) j(400, ['error' => 'Geçerli bir fırsat seçin.']);
        if ($cid && (int)$d['company_id'] !== $cid) j(400, ['error' => 'Fırsat ile seçilen müşteri aynı olmalı.']);
        $cid = $cid ?: (int)$d['company_id'];
    }
    $pid = (int)($b['project_id'] ?? 0);
    if ($pid) {
        $p = q1('SELECT company_id FROM projects WHERE id=?', [$pid]);
        if (!$p) j(400, ['error' => 'Geçerli bir proje seçin.']);
        if ($cid && (int)$p['company_id'] !== $cid && (int)$p['company_id'] !== 0) j(400, ['error' => 'Proje ile seçilen müşteri aynı olmalı.']);
        if ($cid) $cid = $cid; else $cid = (int)$p['company_id'];
    }
    $status = in_scalar($b['status'] ?? '', TASK_STATUSES) ? $b['status'] : 'Yapılacak';
    $waiting = in_scalar($b['waiting'] ?? '', WAITING) ? $b['waiting'] : 'Biz';
    if ($waiting !== 'Biz' && $status !== 'Tamamlandı') $status = 'Bekliyor';
    if (($status === 'Bekliyor' || $waiting !== 'Biz') && trim((string)($b['note'] ?? '')) === '') j(400, ['error' => 'Bekleme nedenini açıklamaya yazın.']);
    $owner = valid_id($b['owner_id'] ?? 0) ? (int)$b['owner_id'] : (int)$u['id'];
    $due = valid_date($b['due_date'] ?? '') ? $b['due_date'] : today();
    qx('INSERT INTO tasks(title,company_id,deal_id,project_id,owner_id,due_date,status,waiting,note,created_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
        [$title, $cid, $did, $pid, $owner, $due, $status, $waiting, s($b['note'] ?? '', 2000), time(), (int)$u['id']]);
    $id = (int)db()->lastInsertId();
    write_log('task_add', $u, $title, 'task', (int)db()->lastInsertId(), $title);
    j(200, ['ok' => true, 'id' => $id]);
}

if ($path === '/api/tasks/update' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $t = q1('SELECT * FROM tasks WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$t) j(404, ['error' => 'Görev bulunamadı.']);
    $isOwner = (int)$t['owner_id'] === (int)$u['id'];
    $isCreator = (int)$t['created_by'] === (int)$u['id'];
    $comp = $t['company_id'] ? q1('SELECT owner_id, created_by FROM companies WHERE id=?', [(int)$t['company_id']]) : null;
    $compOk = $comp && ((int)$comp['owner_id'] === (int)$u['id'] || (int)$comp['created_by'] === (int)$u['id']);
    if ($u['role'] !== 'manager' && !$isOwner && !$isCreator && !$compOk) j(403, ['error' => 'Bu görevi düzenleme yetkiniz yok.']);
    $title = s($b['title'] ?? '', 200) ?: $t['title'];
    $cid = array_key_exists('company_id', $b) ? (int)$b['company_id'] : (int)$t['company_id'];
    if ($cid && !can_see_company($u, $cid)) j(400, ['error' => 'Geçerli bir müşteri seçin.']);
    $did = array_key_exists('deal_id', $b) ? (int)$b['deal_id'] : (int)$t['deal_id'];
    $pid = array_key_exists('project_id', $b) ? (int)$b['project_id'] : (int)$t['project_id'];
    $status = in_scalar($b['status'] ?? '', TASK_STATUSES) ? $b['status'] : $t['status'];
    $waiting = in_scalar($b['waiting'] ?? '', WAITING) ? $b['waiting'] : $t['waiting'];
    if ($waiting !== 'Biz' && $status !== 'Tamamlandı') $status = 'Bekliyor';
    $note = s($b['note'] ?? $t['note'], 2000);
    if (($status === 'Bekliyor' || $waiting !== 'Biz') && trim($note) === '' && trim((string)$t['note']) === '') j(400, ['error' => 'Bekleme nedenini açıklamaya yazın.']);
    $owner = valid_id($b['owner_id'] ?? 0) ? (int)$b['owner_id'] : (int)$t['owner_id'];
    $due = valid_date($b['due_date'] ?? '') ? $b['due_date'] : $t['due_date'];
    $doneAt = $status === 'Tamamlandı' ? ($t['done_at'] ?: time()) : null;
    qx('UPDATE tasks SET title=?, company_id=?, deal_id=?, project_id=?, owner_id=?, due_date=?, status=?, waiting=?, note=?, done_at=COALESCE(?,done_at) WHERE id=?',
        [$title, $cid, $did, $pid, $owner, $due, $status, $waiting, $note, $doneAt, (int)$t['id']]);
    $tnew = q1('SELECT * FROM tasks WHERE id=?', [(int)$t['id']]);
    $tDiff = diff_fields($t, $tnew, ['title','due_date','status','waiting','note']);
    if ((int)$owner !== (int)$t['owner_id']) $tDiff['sorumlu'] = ['eski' => userNameById((int)$t['owner_id']), 'yeni' => userNameById((int)$owner)];
    write_log('task_update', $u, $title . ' (' . $status . ')' . ($tDiff ? ' [' . implode(', ', array_keys($tDiff)) . ']' : ''), 'task', (int)$t['id'], $title, $tDiff);
    j(200, ['ok' => true]);
}

if ($path === '/api/tasks/toggle' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $t = q1('SELECT * FROM tasks WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$t) j(404, ['error' => 'Görev bulunamadı.']);
    $isOwner = (int)$t['owner_id'] === (int)$u['id'];
    $isCreator = (int)$t['created_by'] === (int)$u['id'];
    $comp = $t['company_id'] ? q1('SELECT owner_id, created_by FROM companies WHERE id=?', [(int)$t['company_id']]) : null;
    $compOk = $comp && ((int)$comp['owner_id'] === (int)$u['id'] || (int)$comp['created_by'] === (int)$u['id']);
    if ($u['role'] !== 'manager' && !$isOwner && !$isCreator && !$compOk) j(403, ['error' => 'Bu görevi değiştirme yetkiniz yok.']);
    if ($t['status'] === 'Tamamlandı') {
        qx("UPDATE tasks SET status='Yapılacak', waiting='Biz', done_at=NULL WHERE id=?", [(int)$t['id']]);
        write_log('task_reopen', $u, $t['title'], 'task', (int)$t['id'], $t['title'], ['status' => ['eski' => 'Tamamlandı', 'yeni' => 'Yapılacak']]);
    } else {
        qx("UPDATE tasks SET status='Tamamlandı', done_at=? WHERE id=?", [time(), (int)$t['id']]);
        write_log('task_done', $u, $t['title'], 'task', (int)$t['id'], $t['title'], ['status' => ['eski' => $t['status'], 'yeni' => 'Tamamlandı']]);
    }
    j(200, ['ok' => true, 'status' => $t['status'] === 'Tamamlandı' ? 'Yapılacak' : 'Tamamlandı']);
}

if ($path === '/api/tasks/delete' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $t = q1('SELECT * FROM tasks WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$t) j(404, ['error' => 'Görev bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$t['owner_id'] !== (int)$u['id'] && (int)$t['created_by'] !== (int)$u['id']) j(403, ['error' => 'Bu görevi silme yetkiniz yok.']);
    qx('DELETE FROM tasks WHERE id=?', [(int)$t['id']]);
    write_log('task_delete', $u, $t['title'], 'task', (int)$t['id'], $t['title']);
    j(200, ['ok' => true]);
}

// ---------- projects ----------
if ($path === '/api/projects' && $method === 'GET') {
    $u = require_user();
    $ids = company_ids_of($u);
    if (!$ids) j(200, ['projects' => [], 'users' => []]);
    $in = implode(',', $ids);
    $projects = qa("SELECT * FROM projects WHERE company_id IN ($in) OR company_id=0");
    $users = qa('SELECT id, name, color FROM users WHERE active=1');
    $umap = []; foreach ($users as $x) $umap[(int)$x['id']] = $x;
    $out = [];
    foreach ($projects as $p) {
        if ($u['role'] !== 'manager') {
            $comp = $p['company_id'] ? q1('SELECT owner_id, created_by FROM companies WHERE id=?', [(int)$p['company_id']]) : null;
            $own = (int)$p['owner_id'] === (int)$u['id'];
            $compOk = $comp && ((int)$comp['owner_id'] === (int)$u['id'] || (int)$comp['created_by'] === (int)$u['id']);
            if (!$own && !$compOk && (int)$p['company_id'] !== 0) continue;
        }
        $steps = qa('SELECT id, title, done, ord FROM project_steps WHERE project_id=? ORDER BY ord, id', [(int)$p['id']]);
        $out[] = [
            'id' => (int)$p['id'], 'name' => $p['name'], 'company_id' => (int)$p['company_id'],
            'deal_id' => (int)$p['deal_id'], 'owner' => $umap[(int)$p['owner_id']]['name'] ?? '—', 'owner_id' => (int)$p['owner_id'],
            'due_date' => $p['due_date'], 'type' => $p['type'], 'note' => $p['note'], 'currency' => $p['currency'] ?? 'USD',
            'steps' => array_map(fn($s) => ['id' => (int)$s['id'], 'title' => $s['title'], 'done' => (bool)$s['done']], $steps),
        ];
    }
    j(200, ['projects' => $out, 'users' => array_values($umap)]);
}

if ($path === '/api/projects' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $name = s($b['name'] ?? '', 160);
    if ($name === '') j(400, ['error' => 'Proje adını girin.']);
    $cid = (int)($b['company_id'] ?? 0);
    if ($cid && !can_see_company($u, $cid)) j(400, ['error' => 'Geçerli bir müşteri seçin.']);
    $type = in_scalar($b['type'] ?? '', PROJECT_TYPES) ? $b['type'] : 'Numune';
    $owner = valid_id($b['owner_id'] ?? 0) ? (int)$b['owner_id'] : (int)$u['id'];
    $due = valid_date($b['due_date'] ?? '') ? $b['due_date'] : day_offset(14);
    $did = (int)($b['deal_id'] ?? 0);
    if ($did) {
        $d = q1('SELECT company_id FROM deals WHERE id=?', [$did]);
        if (!$d || !can_see_company($u, $d['company_id'])) j(400, ['error' => 'Geçerli bir fırsat seçin.']);
        if ($cid && (int)$d['company_id'] !== $cid) j(400, ['error' => 'Fırsat ile seçilen müşteri aynı olmalı.']);
        $cid = $cid ?: (int)$d['company_id'];
    }
    $cur = in_scalar($b['currency'] ?? '', CURRENCIES) ? $b['currency'] : 'USD';
    qx('INSERT INTO projects(name,company_id,deal_id,owner_id,due_date,type,note,currency,created_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',
        [$name, $cid, $did, $owner, $due, $type, s($b['note'] ?? '', 2000), $cur, time(), (int)$u['id']]);
    $id = (int)db()->lastInsertId();
    $tpl = $type === 'Numune' ? ['Teknik ihtiyaç doğrulama', 'Numune hazırlama', 'İç kalite kontrolü', 'Gönderim ve müşteri onayı'] : ['Planlama', 'Uygulama', 'Kontrol', 'Teslim'];
    $i = 0;
    foreach ($tpl as $t) { qx('INSERT INTO project_steps(project_id,title,ord) VALUES(?,?,?)', [$id, $t, $i++]); }
    write_log('project_add', $u, $name, 'project', (int)db()->lastInsertId(), $name);
    add_activity((int)$u['id'], $name . ' projesi oluşturuldu.');
    j(200, ['ok' => true, 'id' => $id]);
}

if ($path === '/api/projects/update' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $p = q1('SELECT * FROM projects WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$p) j(404, ['error' => 'Proje bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$p['owner_id'] !== (int)$u['id'] && (int)$p['created_by'] !== (int)$u['id']) j(403, ['error' => 'Bu projeyi düzenleme yetkiniz yok.']);
    $name = s($b['name'] ?? '', 160) ?: $p['name'];
    $type = in_scalar($b['type'] ?? '', PROJECT_TYPES) ? $b['type'] : $p['type'];
    $owner = valid_id($b['owner_id'] ?? 0) ? (int)$b['owner_id'] : (int)$p['owner_id'];
    $due = valid_date($b['due_date'] ?? '') ? $b['due_date'] : $p['due_date'];
    $cur = in_scalar($b['currency'] ?? '', CURRENCIES) ? $b['currency'] : ($p['currency'] ?? 'USD');
    qx('UPDATE projects SET name=?, type=?, owner_id=?, due_date=?, note=?, currency=? WHERE id=?',
        [$name, $type, $owner, $due, s($b['note'] ?? $p['note'], 2000), $cur, (int)$p['id']]);
    write_log('project_update', $u, $name, 'project', (int)$p['id'], $name, diff_fields($p, array_merge($p, $b), ['name','type','due_date','note']));
    j(200, ['ok' => true]);
}

if ($path === '/api/projects/steps' && $method === 'POST') {
    // Adım ekle veya işaretle: {project_id, add_title} | {step_id, done}
    $u = require_user(); $b = body_json();
    if (isset($b['add_title'])) {
        $p = q1('SELECT * FROM projects WHERE id=?', [(int)($b['project_id'] ?? 0)]);
        if (!$p) j(404, ['error' => 'Proje bulunamadı.']);
        if ($u['role'] !== 'manager' && (int)$p['owner_id'] !== (int)$u['id'] && (int)$p['created_by'] !== (int)$u['id']) j(403, ['error' => 'Bu projeye adım ekleme yetkiniz yok.']);
        $title = s($b['add_title'], 160);
        if ($title === '') j(400, ['error' => 'Adım adını yazın.']);
        $ord = (int)q1('SELECT COALESCE(MAX(ord),0)+1 o FROM project_steps WHERE project_id=?', [(int)$p['id']])['o'];
        qx('INSERT INTO project_steps(project_id,title,ord) VALUES(?,?,?)', [(int)$p['id'], $title, $ord]);
        write_log('step_add', $u, $p['name'] . ': ' . $title);
        j(200, ['ok' => true]);
    }
    $st = q1('SELECT ps.*, p.name pname, p.owner_id powner, p.created_by pcreator FROM project_steps ps JOIN projects p ON p.id=ps.project_id WHERE ps.id=?', [(int)($b['step_id'] ?? 0)]);
    if (!$st) j(404, ['error' => 'Adım bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$st['powner'] !== (int)$u['id'] && (int)$st['pcreator'] !== (int)$u['id']) j(403, ['error' => 'Bu adımı değiştirme yetkiniz yok.']);
    $done = !empty($b['done']) ? 1 : 0;
    qx('UPDATE project_steps SET done=? WHERE id=?', [$done, (int)$st['id']]);
    write_log('step_toggle', $u, $st['pname'] . ': ' . $st['title'] . ($done ? ' tamamlandı' : ' yeniden açıldı'));
    j(200, ['ok' => true]);
}

if ($path === '/api/projects/delete' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $p = q1('SELECT * FROM projects WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$p) j(404, ['error' => 'Proje bulunamadı.']);
    if ($u['role'] !== 'manager') j(403, ['error' => 'Proje silme yetkisi yalnız yöneticidedir.']);
    qx('DELETE FROM project_steps WHERE project_id=?', [(int)$p['id']]);
    // Görselleri de temizle (dosya + kayıt)
    foreach (qa('SELECT id, file FROM project_images WHERE project_id=?', [(int)$p['id']]) as $im) {
        @unlink(NX_UPLOADS . '/' . basename($im['file']));
        qx('DELETE FROM project_images WHERE id=?', [(int)$im['id']]);
    }
    qx('UPDATE tasks SET project_id=0 WHERE project_id=?', [(int)$p['id']]);
    qx('DELETE FROM projects WHERE id=?', [(int)$p['id']]);
    write_log('project_delete', $u, $p['name'], 'project', (int)$p['id'], $p['name']);
    j(200, ['ok' => true]);
}

// ---------- project images ----------
if ($path === '/api/projects/images' && $method === 'POST') {
    $u = require_user();
    $pid = (int)($_POST['project_id'] ?? 0);
    $p = q1('SELECT * FROM projects WHERE id=?', [$pid]);
    if (!$p) j(404, ['error' => 'Proje bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$p['owner_id'] !== (int)$u['id'] && (int)$p['created_by'] !== (int)$u['id']) j(403, ['error' => 'Bu projeye dosya ekleme yetkiniz yok.']);
    if (empty($_FILES['files'])) j(400, ['error' => 'Dosya seçilmedi.']);
    $files = $_FILES['files'];
    $n = count($files['name']);
    $ok = 0; $errors = [];
    for ($i = 0; $i < $n; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { $errors[] = $files['name'][$i] . ': yükleme hatası'; continue; }
        if ($files['size'][$i] > 10 * 1024 * 1024) { $errors[] = $files['name'][$i] . ': 10 MB sınırı aşıldı'; continue; }
        $orig = $files['name'][$i];
        if (!img_ext_ok($orig)) { $errors[] = $orig . ': izinli tipler jpg, png, webp, gif'; continue; }
        // Gerçek içerik doğrulaması (uzantıya güvenme)
        $info = @getimagesize($files['tmp_name'][$i]);
        if ($info === false) { $errors[] = $orig . ': geçerli görsel değil'; continue; }
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') $ext = 'jpg';
        $stored = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = NX_UPLOADS . '/' . $stored;
        if (!move_uploaded_file($files['tmp_name'][$i], $dest)) { $errors[] = $orig . ': sunucuya yazılamadı'; continue; }
        @chmod($dest, 0644);
        qx('INSERT INTO project_images(project_id,file,orig_name,created_at,created_by) VALUES(?,?,?,?,?)', [$pid, $stored, s($orig, 160), time(), (int)$u['id']]);
        $ok++;
    }
    write_log('project_images_add', $u, $p['name'] . ': ' . $ok . ' görsel eklendi' . ($errors ? ' (' . count($errors) . ' hata)' : ''), 'project', $pid, $p['name']);
    j(200, ['ok' => true, 'added' => $ok, 'errors' => $errors, 'images' => project_images_list($pid)]);
}

if ($path === '/api/projects/image' && $method === 'GET') {
    // Güvenli sunum: oturum + görüntü doğrulama + doğru Content-Type
    require_user();
    $r = q1('SELECT * FROM project_images WHERE id=? AND deleted=0', [(int)($_GET['id'] ?? 0)]);
    if (!$r) j(404, ['error' => 'Görsel bulunamadı.']);
    $file = basename($r['file']);
    $fp = NX_UPLOADS . '/' . $file;
    if (!is_file($fp)) j(404, ['error' => 'Dosya yok.']);
    $info = @getimagesize($fp);
    if ($info === false) j(404, ['error' => 'Geçersiz görsel.']);
    $mime = $info['mime'] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fp));
    header('Cache-Control: private, max-age=86400');
    header('Content-Disposition: inline; filename="' . addslashes($r['orig_name'] ?: $file) . '"');
    readfile($fp);
    exit;
}

if ($path === '/api/projects/images/delete' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $r = q1('SELECT pi.*, p.owner_id powner, p.created_by pcreator, p.name pname FROM project_images pi JOIN projects p ON p.id=pi.project_id WHERE pi.id=? AND pi.deleted=0', [(int)($b['id'] ?? 0)]);
    if (!$r) j(404, ['error' => 'Görsel bulunamadı.']);
    if ($u['role'] !== 'manager' && (int)$r['powner'] !== (int)$u['id'] && (int)$r['pcreator'] !== (int)$u['id']) j(403, ['error' => 'Bu görseli silme yetkiniz yok.']);
    qx('UPDATE project_images SET deleted=1 WHERE id=?', [(int)$r['id']]);
    @unlink(NX_UPLOADS . '/' . basename($r['file']));
    write_log('project_image_delete', $u, $r['pname'] . ': ' . $r['orig_name'] . ' silindi', 'project', (int)$r['project_id'], $r['pname']);
    j(200, ['ok' => true]);
}

if ($path === '/api/projects/images' && $method === 'GET') {
    $u = require_user();
    $pid = (int)($_GET['project_id'] ?? 0);
    $p = q1('SELECT * FROM projects WHERE id=?', [$pid]);
    if (!$p) j(404, ['error' => 'Proje bulunamadı.']);
    j(200, ['images' => project_images_list($pid)]);
}

// ---------- urun modulu (dinamik urun kartlari) ----------
const INCOTERMS = ['EXW', 'FCA', 'FOB', 'CIF', 'DAP', 'DDP'];
const PAY_TERMS = ['Peşin', '30 gün', '60 gün', '%30 avans + bakiye sevkiyatta', 'Akreditif'];
const PACKAGING = ['Poşet', 'Kutu', 'Askılı', 'Koli (dökme)'];
const SAMPLE_STATES = ['Yok', 'Referans numune var', 'Ön numune istendi', 'Numune onaylandı'];

function attr_chain($type_id) {
    // Miras zinciri (alt -> ust) ve alanlar (ustten alta zorunluluk override)
    $chain = [];
    $cur = $type_id;
    $guard = 0;
    while ($cur && $guard++ < 10) {
        $t = q1('SELECT * FROM product_types WHERE id=?', [$cur]);
        if (!$t) break;
        $chain[] = $t;
        $cur = $t['parent_id'] ? (int)$t['parent_id'] : null;
    }
    $chain = array_reverse($chain); // kokten alta
    $attrs = [];
    foreach ($chain as $t) {
        foreach (qa('SELECT pta.required, pta.ord, a.* FROM product_type_attributes pta JOIN attribute_definitions a ON a.id=pta.attribute_id WHERE pta.product_type_id=? AND a.archived=0 ORDER BY pta.ord, a.id', [(int)$t['id']]) as $a) {
            $attrs[$a['key']] = ['id' => (int)$a['id'], 'key' => $a['key'], 'label' => $a['label'], 'dtype' => $a['dtype'], 'unit' => $a['unit'],
                'options' => $a['options'] !== '' ? explode('|', $a['options']) : [], 'minv' => $a['minv'] !== null ? (float)$a['minv'] : null,
                'maxv' => $a['maxv'] !== null ? (float)$a['maxv'] : null, 'grp' => $a['grp'],
                'required' => (int)$a['required'], 'ord' => (int)$a['ord'], 'inherited_from' => $t['name']];
        }
    }
    uasort($attrs, fn($x, $y) => [$x['grp'], $x['ord']] <=> [$y['grp'], $y['ord']]);
    return $attrs;
}
function product_row($p, $attrs = null) {
    if ($attrs === null) $attrs = attr_chain((int)$p['product_type_id']);
    $dist = qa('SELECT id, label, qty FROM project_product_dist WHERE product_id=? ORDER BY ord, id', [(int)$p['id']]);
    $vals = [];
    foreach (qa('SELECT * FROM project_product_values WHERE product_id=?', [(int)$p['id']]) as $v) {
        $key = null;
        foreach ($attrs as $a) if ((int)$a['id'] === (int)$v['attribute_id']) { $key = $a['key']; break; }
        if ($key) {
            $raw = (string)$v['value'];
            $dec = $raw !== '' ? json_decode($raw, true) : '';
            // Eski format (duz metin) satirlar da okunabilsin
            $vals[$key] = ($dec === null && $raw !== '' && $raw !== 'null') ? $raw : $dec;
        }
    }
    $custom = array_map(fn($c) => ['id' => (int)$c['id'], 'label' => $c['label'], 'value' => $c['value'], 'unit' => $c['unit']],
        qa('SELECT * FROM project_product_custom WHERE product_id=? ORDER BY ord, id', [(int)$p['id']]));
    $total = array_sum(array_map(fn($d) => (int)$d['qty'], $dist));
    $t = q1('SELECT name, parent_id FROM product_types WHERE id=?', [(int)$p['product_type_id']]);
    return ['id' => (int)$p['id'], 'project_id' => (int)$p['project_id'], 'product_type_id' => (int)$p['product_type_id'],
        'type_name' => $t['name'] ?? '—', 'name' => $p['name'], 'status' => $p['status'],
        'price' => (float)$p['price'], 'delivery_date' => $p['delivery_date'], 'incoterm' => $p['incoterm'],
        'payment_term' => $p['payment_term'], 'packaging' => $p['packaging'], 'per_box' => (int)$p['per_box'],
        'sample_status' => $p['sample_status'], 'dist' => array_map(fn($d) => ['id' => (int)$d['id'], 'label' => $d['label'], 'qty' => (int)$d['qty']], $dist),
        'total_qty' => $total, 'vals' => $vals, 'custom' => $custom, 'attrs' => $attrs];
}
function product_missing($p) {
    // Teklife hazir icin eksikleri hesapla (backend dogrulamasi).
    $m = [];
    $attrs = $p['attrs'];
    foreach ($attrs as $k => $a) {
        if (!$a['required']) continue;
        $v = $p['vals'][$k] ?? null;
        if ($a['dtype'] === 'blend') {
            $sum = 0; if (is_array($v)) foreach ($v as $r) $sum += (float)($r['p'] ?? 0);
            if (!is_array($v) || !count($v) || (int)round($sum) !== 100) $m[] = $a['label'] . ' (toplam %100 olmalı)';
        } elseif ($a['dtype'] === 'multi') { if (!is_array($v) || !count($v)) $m[] = $a['label']; }
        elseif ($a['dtype'] === 'bool') { /* bool icin bos zorunluluk yok */ }
        elseif ($v === null || $v === '') $m[] = $a['label'];
    }
    if ((int)$p['total_qty'] <= 0) $m[] = 'Toplam adet';
    if (!((float)$p['price'] > 0)) $m[] = 'Hedef birim fiyat';
    if ($p['delivery_date'] === '') $m[] = 'Teslim tarihi';
    return $m;
}
function can_edit_product($u, $p) {
    $proj = q1('SELECT owner_id, created_by FROM projects WHERE id=?', [(int)$p['project_id']]);
    if ($u['role'] === 'manager') return true;
    $c = $p['project_id'] ? q1('SELECT owner_id, created_by FROM companies WHERE id=(SELECT company_id FROM projects WHERE id=?)', [(int)$p['project_id']]) : null;
    return (int)$proj['owner_id'] === (int)$u['id'] || (int)$proj['created_by'] === (int)$u['id'] || ($c && ((int)$c['owner_id'] === (int)$u['id'] || (int)$c['created_by'] === (int)$u['id']));
}

if ($path === '/api/product-types' && $method === 'GET') {
    require_user();
    $types = qa('SELECT * FROM product_types ORDER BY ord, id');
    j(200, ['types' => array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name'], 'parent_id' => $t['parent_id'] ? (int)$t['parent_id'] : null,
        'active' => (int)$t['active'], 'system' => (int)$t['system']], $types)]);
}

if ($path === '/api/products' && $method === 'GET') {
    $u = require_user();
    $pid = (int)($_GET['project_id'] ?? 0);
    $proj = q1('SELECT * FROM projects WHERE id=?', [$pid]);
    if (!$proj) j(404, ['error' => 'Proje bulunamadı.']);
    $rows = qa('SELECT * FROM project_products WHERE project_id=? ORDER BY id', [$pid]);
    $out = [];
    foreach ($rows as $p) { $r = product_row($p); $r['missing'] = product_missing($r); $out[] = $r; }
    j(200, ['products' => $out, 'currency' => $proj['currency'] ?: 'USD']);
}

if ($path === '/api/products' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $pid = (int)($b['project_id'] ?? 0);
    $proj = q1('SELECT * FROM projects WHERE id=?', [$pid]);
    if (!$proj) j(404, ['error' => 'Proje bulunamadı.']);
    if (!can_edit_product($u, ['project_id' => $pid])) j(403, ['error' => 'Bu projeye ürün ekleme yetkiniz yok.']);
    $name = s($b['name'] ?? '', 160);
    if ($name === '') j(400, ['error' => 'Ürün adını girin.']);
    $tid = (int)($b['product_type_id'] ?? 0);
    $t = q1('SELECT * FROM product_types WHERE id=? AND active=1', [$tid]);
    if (!$t) j(400, ['error' => 'Geçerli bir ürün tipi seçin.']);
    // Yalnizca aktif yaprak tipler secilebilir
    if (q1('SELECT id FROM product_types WHERE parent_id=? LIMIT 1', [$tid])) j(400, ['error' => 'Alt tipi olan bir grup seçilemez; somut ürün tipi seçin.']);
    qx('INSERT INTO project_products(project_id,product_type_id,name,created_at,created_by) VALUES(?,?,?,?,?)', [$pid, $tid, $name, time(), (int)$u['id']]);
    $id = (int)db()->lastInsertId();
    qx('INSERT INTO project_product_dist(product_id,label,qty,ord) VALUES(?,?,?,0)', [$id, 'Standart', 0]);
    write_log('product_add', $u, $name . ' (' . $t['name'] . ')', 'project', $pid, $proj['name']);
    j(200, ['ok' => true, 'id' => $id]);
}

if ($path === '/api/products/update' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $p = q1('SELECT * FROM project_products WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$p) j(404, ['error' => 'Ürün bulunamadı.']);
    if (!can_edit_product($u, $p)) j(403, ['error' => 'Bu ürünü düzenleme yetkiniz yok.']);
    if (isset($b['product_type_id']) && (int)$b['product_type_id'] !== (int)$p['product_type_id']) j(400, ['error' => 'Ürün tipi sonradan değiştirilemez. Kopyala özelliğini kullanın.']);
    // Alan degerleri
    if (array_key_exists('vals', $b) && is_array($b['vals'])) {
        $attrs = attr_chain((int)$p['product_type_id']);
        foreach ($b['vals'] as $k => $v) {
            if (!isset($attrs[$k])) continue;
            $a = $attrs[$k];
            // Sayisal min/max dogrulamasi
            if ($a['dtype'] === 'number' && $v !== '' && $v !== null) {
                if ($a['minv'] !== null && (float)$v < $a['minv']) j(400, ['error' => $a['label'] . ': en az ' . $a['minv']]);
                if ($a['maxv'] !== null && (float)$v > $a['maxv']) j(400, ['error' => $a['label'] . ': en fazla ' . $a['maxv']]);
            }
            $json = is_array($v) ? json_encode(array_values($v), JSON_UNESCAPED_UNICODE) : json_encode((string)$v, JSON_UNESCAPED_UNICODE);
            qx('INSERT INTO project_product_values(product_id,attribute_id,value,label_snapshot,unit_snapshot) VALUES(?,?,?,?,?) ' .
               'ON CONFLICT(product_id,attribute_id) DO UPDATE SET value=excluded.value, label_snapshot=excluded.label_snapshot, unit_snapshot=excluded.unit_snapshot',
                [(int)$p['id'], (int)$a['id'], $json, $a['label'], $a['unit']]);
        }
    }
    // Dagitim
    if (array_key_exists('dist', $b) && is_array($b['dist'])) {
        $list = [];
        foreach ($b['dist'] as $d) {
            if (!is_array($d)) continue;
            $l = s($d['label'] ?? '', 80); $q = max(0, (int)($d['qty'] ?? 0));
            if ($l === '' && $q === 0) continue;
            $list[] = ['label' => $l ?: 'Standart', 'qty' => $q];
        }
        if (!count($list)) j(400, ['error' => 'Dağılımda en az 1 satır kalmalı.']);
        qx('DELETE FROM project_product_dist WHERE product_id=?', [(int)$p['id']]);
        foreach ($list as $i => $d) qx('INSERT INTO project_product_dist(product_id,label,qty,ord) VALUES(?,?,?,?)', [(int)$p['id'], $d['label'], $d['qty'], $i]);
    }
    // Ortak blok
    $sets = [];$args = [];
    foreach ([['name', 160], ['incoterm', 8], ['payment_term', 60], ['packaging', 40], ['sample_status', 40], ['delivery_date', 10]] as $f) {
        if (array_key_exists($f[0], $b)) { $sets[] = $f[0] . '=?'; $args[] = s($b[$f[0]], $f[1]); }
    }
    if (array_key_exists('price', $b)) { $sets[] = 'price=?'; $args[] = max(0, (float)$b['price']); }
    if (array_key_exists('per_box', $b)) { $sets[] = 'per_box=?'; $args[] = max(0, (int)$b['per_box']); }
    if ($sets) { $args[] = (int)$p['id']; qx('UPDATE project_products SET ' . implode(',', $sets) . ' WHERE id=?', $args); }
    // Ek alanlar
    if (array_key_exists('custom', $b) && is_array($b['custom'])) {
        $list = [];
        foreach ($b['custom'] as $c) {
            if (!is_array($c)) continue;
            $l = s($c['label'] ?? '', 80); if ($l === '') continue;
            $list[] = ['label' => $l, 'value' => s($c['value'] ?? '', 300), 'unit' => s($c['unit'] ?? '', 20)];
        }
        qx('DELETE FROM project_product_custom WHERE product_id=?', [(int)$p['id']]);
        foreach ($list as $i => $c) qx('INSERT INTO project_product_custom(product_id,label,value,unit,ord) VALUES(?,?,?,?,?)', [(int)$p['id'], $c['label'], $c['value'], $c['unit'], $i]);
    }
    // Durum dogrulamasi: teklife_hazir ise ve eksik varsa taslaga don
    $fresh = product_row(q1('SELECT * FROM project_products WHERE id=?', [(int)$p['id']]));
    $missing = product_missing($fresh);
    if ($fresh['status'] === 'teklife_hazir' && count($missing)) {
        qx("UPDATE project_products SET status='taslak' WHERE id=?", [(int)$p['id']]);
        $fresh['status'] = 'taslak';
        write_log('product_autodraft', $u, $fresh['name'] . ' doğrulama bozuldu, taslağa döndü', 'project', (int)$p['project_id'], q1('SELECT name FROM projects WHERE id=?', [(int)$p['project_id']])['name']);
    }
    write_log('product_update', $u, $fresh['name'] . ($missing ? ' (eksik: ' . count($missing) . ')' : ' (tam)'), 'project', (int)$p['project_id'], q1('SELECT name FROM projects WHERE id=?', [(int)$p['project_id']])['name']);
    $fresh['missing'] = $missing;
    j(200, ['ok' => true, 'product' => $fresh]);
}

if ($path === '/api/products/ready' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $p = q1('SELECT * FROM project_products WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$p) j(404, ['error' => 'Ürün bulunamadı.']);
    if (!can_edit_product($u, $p)) j(403, ['error' => 'Yetkiniz yok.']);
    $full = product_row($p);
    $missing = product_missing($full);
    if (count($missing)) j(400, ['error' => 'Teklife hazır için eksikler: ' . implode(', ', $missing)]);
    qx("UPDATE project_products SET status='teklife_hazir' WHERE id=?", [(int)$p['id']]);
    write_log('product_ready', $u, $full['name'] . ' teklife hazır işaretlendi', 'project', (int)$p['project_id'], q1('SELECT name FROM projects WHERE id=?', [(int)$p['project_id']])['name']);
    j(200, ['ok' => true]);
}

if ($path === '/api/products/copy' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $p = q1('SELECT * FROM project_products WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$p) j(404, ['error' => 'Ürün bulunamadı.']);
    if (!can_edit_product($u, $p)) j(403, ['error' => 'Yetkiniz yok.']);
    $newName = s($b['name'] ?? '', 160);
    if ($newName === '') j(400, ['error' => 'Yeni ürün adını girin.']);
    $tid = (int)($b['product_type_id'] ?? $p['product_type_id']);
    $t = q1('SELECT * FROM product_types WHERE id=? AND active=1', [$tid]);
    if (!$t) j(400, ['error' => 'Geçerli bir tip seçin.']);
    if (q1('SELECT id FROM product_types WHERE parent_id=? LIMIT 1', [$tid])) j(400, ['error' => 'Alt tipi olan grup seçilemez.']);
    $srcAttrs = attr_chain((int)$p['product_type_id']);
    $dstAttrs = attr_chain($tid);
    qx('INSERT INTO project_products(project_id,product_type_id,name,price,delivery_date,incoterm,payment_term,packaging,per_box,sample_status,created_at,created_by) ' .
       'SELECT project_id,?,?,price,delivery_date,incoterm,payment_term,packaging,per_box,sample_status,?,? FROM project_products WHERE id=?',
        [$tid, $newName, time(), (int)$u['id'], (int)$p['id']]);
    $nid = (int)db()->lastInsertId();
    // Dagitim + ek alanlar tamamen tasinir
    qx('INSERT INTO project_product_dist(product_id,label,qty,ord) SELECT ?,label,qty,ord FROM project_product_dist WHERE product_id=?', [$nid, (int)$p['id']]);
    qx('INSERT INTO project_product_custom(product_id,label,value,unit,ord) SELECT ?,label,value,unit,ord FROM project_product_custom WHERE product_id=?', [$nid, (int)$p['id']]);
    // Degerler: yalnizca yeni tipte de bulunan alanlar
    $moved = 0;
    foreach (qa('SELECT * FROM project_product_values WHERE product_id=?', [(int)$p['id']]) as $v) {
        foreach ($dstAttrs as $a) {
            if ((int)$a['id'] === (int)$v['attribute_id']) {
                qx('INSERT INTO project_product_values(product_id,attribute_id,value,label_snapshot,unit_snapshot) VALUES(?,?,?,?,?)',
                    [$nid, (int)$v['attribute_id'], $v['value'], $a['label'], $a['unit']]);
                $moved++;
                break;
            }
        }
    }
    write_log('product_copy', $u, $p['name'] . ' → ' . $newName . ' (' . $t['name'] . ', ' . $moved . ' alan taşındı)', 'project', (int)$p['project_id'], q1('SELECT name FROM projects WHERE id=?', [(int)$p['project_id']])['name']);
    j(200, ['ok' => true, 'id' => $nid]);
}

if ($path === '/api/products/delete' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $p = q1('SELECT * FROM project_products WHERE id=?', [(int)($b['id'] ?? 0)]);
    if (!$p) j(404, ['error' => 'Ürün bulunamadı.']);
    if (!can_edit_product($u, $p)) j(403, ['error' => 'Yetkiniz yok.']);
    qx('DELETE FROM project_product_dist WHERE product_id=?', [(int)$p['id']]);
    qx('DELETE FROM project_product_values WHERE product_id=?', [(int)$p['id']]);
    qx('DELETE FROM project_product_custom WHERE product_id=?', [(int)$p['id']]);
    qx('DELETE FROM project_products WHERE id=?', [(int)$p['id']]);
    write_log('product_delete', $u, $p['name'], 'project', (int)$p['project_id'], q1('SELECT name FROM projects WHERE id=?', [(int)$p['project_id']])['name']);
    j(200, ['ok' => true]);
}

// ---------- notes (görüşmeler) ----------
if ($path === '/api/notes' && $method === 'POST') {
    $u = require_user(); $b = body_json();
    $cid = (int)($b['company_id'] ?? 0);
    if (!valid_id($cid) || !can_see_company($u, $cid)) j(400, ['error' => 'Geçerli bir müşteri seçin.']);
    $text = s($b['text'] ?? '', 4000);
    if ($text === '') j(400, ['error' => 'Görüşme içeriğini yazın.']);
    $type = in_scalar($b['type'] ?? '', NOTE_TYPES) ? $b['type'] : 'Not';
    $date = valid_date($b['note_date'] ?? '') ? $b['note_date'] : today();
    $did = (int)($b['deal_id'] ?? 0);
    qx('INSERT INTO notes(company_id,deal_id,type,text,owner_id,note_date,created_at) VALUES(?,?,?,?,?,?,?)',
        [$cid, $did, $type, $text, (int)$u['id'], $date, time()]);
    $next = s($b['next'] ?? '', 200);
    if ($next !== '') {
        if (!valid_date($b['next_date'] ?? '')) j(400, ['error' => 'Sonraki adım için tarih seçin.']);
        $nOwner = valid_id($b['next_owner_id'] ?? 0) ? (int)$b['next_owner_id'] : (int)$u['id'];
        qx('INSERT INTO tasks(title,company_id,deal_id,owner_id,due_date,status,waiting,note,created_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',
            [$next, $cid, $did, $nOwner, $b['next_date'], 'Yapılacak', 'Biz', 'Görüşme kaydından oluşturuldu.', time(), (int)$u['id']]);
        add_activity((int)$u['id'], $text !== '' ? 'Görüşme kaydedildi + sonraki adım: ' . $next : 'Görüşme kaydedildi.');
    } else {
        add_activity((int)$u['id'], 'Görüşme kaydedildi (' . $type . ').');
    }
    write_log('note_add', $u, $type . ' görüşmesi kaydedildi', 'company', $cid, companyNameById($cid));
    j(200, ['ok' => true]);
}

if ($path === '/api/notes/list' && $method === 'GET') {
    $u = require_user();
    $cid = (int)($_GET['company_id'] ?? 0);
    if (!valid_id($cid) || !can_see_company($u, $cid)) j(400, ['error' => 'Geçerli bir müşteri seçin.']);
    $notes = qa('SELECT * FROM notes WHERE company_id=? ORDER BY note_date DESC, id DESC', [$cid]);
    $users = qa('SELECT id, name FROM users WHERE active=1');
    $umap = []; foreach ($users as $x) $umap[(int)$x['id']] = $x;
    j(200, ['notes' => array_map(fn($n) => [
        'id' => (int)$n['id'], 'type' => $n['type'], 'text' => $n['text'],
        'owner' => $umap[(int)$n['owner_id']]['name'] ?? '—', 'note_date' => $n['note_date'],
    ], $notes)]);
}

// ---------- dashboard / reports ----------
if ($path === '/api/dashboard' && $method === 'GET') {
    $u = require_user();
    $ids = company_ids_of($u);
    $in = $ids ? implode(',', $ids) : '0';
    $today = today();
    $tasks = qa("SELECT * FROM tasks WHERE (company_id IN ($in) OR company_id=0) AND status!='Tamamlandı' ORDER BY due_date");
    $outTasks = [];
    foreach ($tasks as $t) {
        if ($u['role'] !== 'manager') {
            $comp = $t['company_id'] ? q1('SELECT owner_id, created_by FROM companies WHERE id=?', [(int)$t['company_id']]) : null;
            $own = (int)$t['owner_id'] === (int)$u['id'];
            $compOk = $comp && ((int)$comp['owner_id'] === (int)$u['id'] || (int)$comp['created_by'] === (int)$u['id']);
            if (!$own && !$compOk) continue;
        }
        $outTasks[] = $t;
    }
    $deals = qa("SELECT * FROM deals WHERE company_id IN ($in)");
    $openDeals = array_values(array_filter($deals, fn($d) => is_open_stage($d['stage'])));
    $users = qa('SELECT id, name, color FROM users WHERE active=1');
    $umap = []; foreach ($users as $x) $umap[(int)$x['id']] = $x;
    $fmt = function ($t) use ($umap) {
        return ['id' => (int)$t['id'], 'title' => $t['title'], 'company_id' => (int)$t['company_id'], 'deal_id' => (int)$t['deal_id'], 'project_id' => (int)$t['project_id'],
                'owner' => $umap[(int)$t['owner_id']]['name'] ?? '—', 'owner_id' => (int)$t['owner_id'],
                'due_date' => $t['due_date'], 'status' => $t['status'], 'waiting' => $t['waiting'], 'note' => $t['note']];
    };
    $projects = qa("SELECT * FROM projects WHERE company_id IN ($in) OR company_id=0 ORDER BY due_date LIMIT 5");
    $outProjects = [];
    foreach ($projects as $p) {
        if ($u['role'] !== 'manager') {
            $comp = $p['company_id'] ? q1('SELECT owner_id, created_by FROM companies WHERE id=?', [(int)$p['company_id']]) : null;
            $compOk = $comp && ((int)$comp['owner_id'] === (int)$u['id'] || (int)$comp['created_by'] === (int)$u['id']);
            if ((int)$p['owner_id'] !== (int)$u['id'] && !$compOk && (int)$p['company_id'] !== 0) continue;
        }
        $outProjects[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'owner' => $umap[(int)$p['owner_id']]['name'] ?? '—', 'due_date' => $p['due_date']];
    }
    j(200, [
        'today' => date('Y-m-d'),
        'tasks_today' => array_map($fmt, array_values(array_filter($outTasks, fn($t) => $t['due_date'] === $today))),
        'tasks_late' => array_map($fmt, array_values(array_filter($outTasks, fn($t) => $t['due_date'] < $today && $t['due_date'] !== ''))),
        'tasks_waiting' => array_map($fmt, array_values(array_filter($outTasks, fn($t) => $t['waiting'] !== 'Biz'))),
        'open_deals_count' => count($openDeals),
        'pipeline' => array_map(fn($st) => ['stage' => $st, 'count' => count(array_filter($openDeals, fn($d) => $d['stage'] === $st))], STAGES),
        'deals_no_next' => count(array_filter($openDeals, function ($d) use ($tasks) {
            return !count(array_filter($tasks, fn($t) => (int)$t['deal_id'] === (int)$d['id'] && $t['due_date'] >= today()));
        })),
        'upcoming_projects' => $outProjects,
    ]);
}

if ($path === '/api/reports' && $method === 'GET') {
    $u = require_user();
    $ids = company_ids_of($u);
    $in = $ids ? implode(',', $ids) : '0';
    $deals = qa("SELECT * FROM deals WHERE company_id IN ($in)");
    $open = array_values(array_filter($deals, fn($d) => is_open_stage($d['stage'])));
    $closed = array_values(array_filter($deals, fn($d) => !is_open_stage($d['stage'])));
    $won = count(array_filter($closed, fn($d) => $d['stage'] === 'Kazanıldı'));
    $users = qa('SELECT id, name, color FROM users WHERE active=1');
    $workload = [];
    foreach ($users as $x) {
        $ts = qa("SELECT COUNT(*) c FROM tasks WHERE owner_id=? AND status!='Tamamlandı'", [(int)$x['id']]);
        $late = qa("SELECT COUNT(*) c FROM tasks WHERE owner_id=? AND status!='Tamamlandı' AND due_date < ?", [(int)$x['id'], today()]);
        $workload[] = ['name' => $x['name'], 'color' => $x['color'], 'open' => (int)$ts[0]['c'], 'late' => (int)$late[0]['c']];
    }
    $amounts = [];
    foreach (CURRENCIES as $cur) {
        $sum = 0; 
        foreach ($open as $d) if ($d['currency'] === $cur) $sum += (float)$d['value'];
        if ($sum > 0) $amounts[$cur] = $sum;
    }
    $sources = [];
    foreach (visible_companies($u) as $c) { $sources[$c['source']] = ($sources[$c['source']] ?? 0) + 1; }
    j(200, [
        'follow_integrity' => count($open) ? round(100 * count(array_filter($open, function ($d) use ($in) {
            $r = q1("SELECT COUNT(*) c FROM tasks WHERE deal_id=? AND due_date >= ?", [(int)$d['id'], today()]);
            return (int)$r['c'] > 0;
        })) / count($open)) : null,
        'completed_tasks' => (int)q1("SELECT COUNT(*) c FROM tasks WHERE status='Tamamlandı'")['c'],
        'total_tasks' => (int)q1('SELECT COUNT(*) c FROM tasks')['c'],
        'win_rate' => count($closed) ? round(100 * $won / count($closed)) : null,
        'active_customers' => count(array_filter(visible_companies($u), fn($c) => $c['status'] === 'Aktif müşteri')),
        'workload' => $workload,
        'amounts' => $amounts,
        'sources' => $sources,
    ]);
}

// ---------- activity / log ----------
if ($path === '/api/activity' && $method === 'GET') {
    require_user();
    j(200, ['activity' => array_map(fn($a) => ['ts' => (int)$a['ts'], 'text' => $a['text']], qa('SELECT * FROM activity ORDER BY id DESC LIMIT 40'))]);
}

if ($path === '/api/log' && $method === 'GET') {
    $u = require_admin();
    // IP'ler yalniz birincil yoneticinin (en dusuk ID'li manager, yani kurulum sahibi) sorgusunda gercek gecer;
    // diger yoneticilerde maskelenir (Giris kayitlari ile ayni kural).
    $firstManager = q1("SELECT id FROM users WHERE role='manager' AND active=1 ORDER BY id LIMIT 1");
    $showIp = $firstManager && (int)$firstManager['id'] === (int)$u['id'];
    // Filtreler: username (kisi bazli), entity+entity_id (kayit bazli), event, q (serbest metin)
    $w = [];$a = [];
    if (!empty($_GET['username'])) { $w[] = 'username=?'; $a[] = s($_GET['username'], 80); }
    if (!empty($_GET['entity']) && in_scalar($_GET['entity'], ['company','deal','task','project','user'])) { $w[] = 'entity=?'; $a[] = $_GET['entity']; }
    if (!empty($_GET['entity_id'])) { $w[] = 'entity_id=?'; $a[] = (int)$_GET['entity_id']; }
    if (!empty($_GET['event'])) { $w[] = 'event=?'; $a[] = s($_GET['event'], 40); }
    if (!empty($_GET['q'])) { $w[] = '(entity_name LIKE ? OR detail LIKE ? OR username LIKE ?)'; $a[] = '%' . s($_GET['q'], 60) . '%'; $a[] = '%' . s($_GET['q'], 60) . '%'; $a[] = '%' . s($_GET['q'], 60) . '%'; }
    $sql = 'SELECT ts, username, event, entity, entity_id, entity_name, changes, detail, ip, device FROM log';
    if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
    $sql .= ' ORDER BY id DESC LIMIT 400';
    $rows = qa($sql, $a);
    j(200, ['log' => array_map(function ($r) use ($showIp) {
        $ch = $r['changes'] ? json_decode($r['changes'], true) : [];
        return ['ts' => (int)$r['ts'], 'username' => $r['username'], 'event' => $r['event'], 'entity' => $r['entity'],
                'entity_id' => (int)$r['entity_id'], 'entity_name' => $r['entity_name'],
                'changes' => $ch ?: null, 'detail' => $r['detail'], 'ip' => $showIp ? $r['ip'] : 'gizli', 'device' => $r['device'] ?? ''];
    }, $rows), 'users' => array_map(fn($x) => $x['name'], qa('SELECT name FROM users ORDER BY name')), 'show_ip' => $showIp]);
}

// ---------- giris kayitlari (alt segment) ----------
if ($path === '/api/logins' && $method === 'GET') {
    $u = require_admin();
    // IP'leri yalniz birincil yoneticinin (en dusuk ID'li manager, yani kurulum sahibi) sorgusunda goster.
    $firstManager = q1("SELECT id FROM users WHERE role='manager' AND active=1 ORDER BY id LIMIT 1");
    $showIp = $firstManager && (int)$firstManager['id'] === (int)$u['id'];
    $w = [];$a = [];
    if (!empty($_GET['username'])) { $w[] = 'l.username=?'; $a[] = s($_GET['username'], 80); }
    if (!empty($_GET['q'])) { $w[] = '(l.username LIKE ? OR l.ip LIKE ? OR l.device LIKE ?)'; $a[] = '%'.s($_GET['q'],60).'%'; $a[] = '%'.s($_GET['q'],60).'%'; $a[] = '%'.s($_GET['q'],60).'%'; }
    $sql = "SELECT l.ts, l.username, l.event, l.detail, l.ip, l.device FROM log l WHERE l.event IN ('login','login_fail','login_rate','logout')";
    if ($w) $sql .= ' AND ' . implode(' AND ', $w);
    $sql .= ' ORDER BY l.id DESC LIMIT 300';
    $rows = qa($sql, $a);
    j(200, ['logins' => array_map(function ($r) use ($showIp) {
        return ['ts' => (int)$r['ts'], 'username' => $r['username'], 'event' => $r['event'],
                'detail' => $r['detail'], 'device' => $r['device'] ?: '—',
                'ip' => $showIp ? $r['ip'] : ($r['event'] === 'login' ? 'gizli' : ''), 'show_ip' => $showIp];
    }, $rows), 'show_ip' => $showIp]);
}

j(404, ['error' => 'Bilinmeyen API yolu.']);
