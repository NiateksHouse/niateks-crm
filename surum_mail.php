<?php
// Niateks House CRM — otomatik sürüm raporu maili (surumle.sh tarafından çağrılır).
// Ortam değişkenleri: NX_RELEASE_VER, NX_RELEASE_PREV, NX_RELEASE_ZIP, NX_RELEASE_PREVZIP, NX_RELEASE_NOTE, [NX_RELEASE_TO]
// SMTP ayarları nx_data/mail_config.php'den okunur; dosya/ayar yoksa mail atlanır, paketleme etkilenmez.

define('NX_APP', 1);
$ROOT = __DIR__;
$cfgFile = $ROOT . '/nx_data/mail_config.php';
$VER  = getenv('NX_RELEASE_VER') ?: '';
$PREV = getenv('NX_RELEASE_PREV') ?: '';
$ZIP  = getenv('NX_RELEASE_ZIP') ?: '';
$PZIP = getenv('NX_RELEASE_PREVZIP') ?: '';
$NOTE = getenv('NX_RELEASE_NOTE') ?: '';
$TO   = getenv('NX_RELEASE_TO') ?: 'tunc@niateks.com';
$DATE = date('d.m.Y H:i');

if ($VER === '') { echo "surum_mail: surum bilgisi yok, atlandi\n"; exit(0); }
if (!is_file($cfgFile)) { echo "surum_mail: mail_config.php yok, mail atlandi (paket etkilenmedi)\n"; exit(0); }
$mc = include $cfgFile;
if (!is_array($mc) || empty($mc['user']) || empty($mc['pass']) || empty($mc['from'])) { echo "surum_mail: SMTP ayarlari eksik, mail atlandi\n"; exit(0); }

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- paket farki: onceki zip ile yeni zip karsilastirilmasi ----
$added = $removed = $changed = [];
$diffDone = false;
if (class_exists('ZipArchive') && $ZIP !== '' && is_file($ZIP)) {
    $listZip = function ($path) {
        $out = [];
        $z = new ZipArchive();
        if ($z->open($path) === true) {
            for ($i = 0; $i < $z->numFiles; $i++) {
                $n = $z->getNameIndex($i);
                if ($n === false || substr($n, -1) === '/') continue;
                $n = str_replace('\\', '/', $n);
                if ($n === 'VERSION.txt' || $n === 'nx_data/setup_code.txt') continue; // her surumde sabit degisenler: gurultu
                $st = $z->statIndex($i);
                // CRC ile karsilastir: ayni boyutlu degisiklikler (surum stringleri vb.) de yakalansin.
                $out[$n] = (int)($st['crc'] ?? 0);
            }
            $z->close();
        }
        ksort($out);
        return $out;
    };
    $new = $listZip($ZIP);
    if ($PZIP !== '' && is_file($PZIP)) {
        $old = $listZip($PZIP);
        foreach ($new as $n => $sz) {
            if (!array_key_exists($n, $old)) $added[] = $n;
            elseif ($old[$n] !== $sz) $changed[] = $n;
        }
        foreach ($old as $n => $sz) if (!array_key_exists($n, $new)) $removed[] = $n;
        $diffDone = true;
    }
}

function listHtml($arr) {
    if (!$arr) return '<li style="color:#8a8272">yok</li>';
    return implode('', array_map(function ($x) {
        return '<li style="margin:2px 0"><code style="background:#f3eee4;padding:1px 5px;border-radius:4px;font-size:12px">' . e($x) . '</code></li>';
    }, $arr));
}

// ---- son surumler: CHANGELOG.md'den ilk 5 baslik ----
$recent = [];
if (is_file($ROOT . '/CHANGELOG.md')) {
    foreach (file($ROOT . '/CHANGELOG.md', FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^## \\[(\\d+\\.\\d+\\.\\d+)\\] - (\\d{4}-\\d{2}-\\d{2})/', $line, $m)) {
            $recent[] = [$line, $m[1], $m[2]];
            if (count($recent) >= 5) break;
        }
    }
}

// ---- HTML rapor ----
$html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f3eee4;border-radius:12px">'
    . '<h2 style="color:#232b18;margin:0 0 2px">Niateks House CRM — ' . e($VER) . ' yayında</h2>'
    . '<p style="color:#5a5344;margin:0 0 16px">Önceki sürüm: <b>' . e($PREV ?: '—') . '</b> · ' . e($DATE) . '</p>'
    . '<div style="background:#fbf8f1;border-radius:8px;padding:14px 16px;margin-bottom:12px">'
    . '<h3 style="color:#c4673c;margin:0 0 8px;font-size:14px">BU SÜRÜMDE YAPILANLAR</h3>'
    . '<p style="color:#232b18;margin:0;white-space:pre-wrap;font-size:14px">' . e($NOTE !== '' ? $NOTE : 'Not girilmedi.') . '</p>'
    . '</div>';

if ($diffDone) {
    $html .= '<div style="background:#fbf8f1;border-radius:8px;padding:14px 16px;margin-bottom:12px">'
        . '<h3 style="color:#c4673c;margin:0 0 8px;font-size:14px">PAKET DEĞİŞİMİ</h3>'
        . '<p style="margin:4px 0 2px;color:#232b18"><b>Eklenen dosyalar</b> (' . count($added) . ')</p>'
        . '<ul style="margin:0 0 10px;padding-left:18px">' . listHtml($added) . '</ul>'
        . '<p style="margin:4px 0 2px;color:#232b18"><b>Çıkarılan dosyalar</b> (' . count($removed) . ')</p>'
        . '<ul style="margin:0 0 10px;padding-left:18px">' . listHtml($removed) . '</ul>'
        . '<p style="margin:4px 0 2px;color:#232b18"><b>Değişen dosyalar</b> (' . count($changed) . ')</p>'
        . '<ul style="margin:0;padding-left:18px">' . listHtml($changed) . '</ul>'
        . '</div>';
} else {
    $html .= '<div style="background:#fbf8f1;border-radius:8px;padding:14px 16px;margin-bottom:12px">'
        . '<h3 style="color:#c4673c;margin:0 0 8px;font-size:14px">PAKET DEĞİŞİMİ</h3>'
        . '<p style="color:#8a8272;margin:0;font-size:13px">Karşılaştırma yapılamadı (önceki paket bulunamadı). Paket: ' . e($ZIP) . '</p></div>';
}

if ($recent) {
    $html .= '<div style="background:#fbf8f1;border-radius:8px;padding:14px 16px;margin-bottom:12px">'
        . '<h3 style="color:#c4673c;margin:0 0 8px;font-size:14px">SON SÜRÜMLER</h3><ul style="margin:0;padding-left:18px">';
    foreach ($recent as $m) $html .= '<li style="margin:3px 0;color:#232b18"><b>' . e($m[1]) . '</b> <span style="color:#8a8272">(' . e($m[2]) . ')</span></li>';
    $html .= '</ul></div>';
}

$html .= '<p style="color:#8a8272;font-size:12px;margin:8px 0 0">Bu mail surumle.sh tarafından otomatik gönderildi · Ayrıntılı günlük: koza.niateks.com/CHANGELOG.md · Lütfen yanıtlamayın.</p></div>';

// ---- SMTP gonderim (api.php ile ayni yontem) ----
$host = !empty($mc['host']) ? $mc['host'] : 'tls://mail.niateks.com';
$port = (int)(!empty($mc['port']) ? $mc['port'] : 465);
$user = $mc['user']; $pass = $mc['pass'];
$from = !empty($mc['from']) ? $mc['from'] : $user;
$fromName = !empty($mc['fromName']) ? $mc['fromName'] : 'Niateks House CRM';

$sock = @fsockopen($host, $port, $errNo, $errStr, 10);
if (!$sock) { echo "surum_mail: SMTP baglanti hatasi: $errStr\n"; exit(0); }
stream_set_timeout($sock, 15);
$read = function () use ($sock) {
    $out = '';
    while (($l = fgets($sock, 1024)) !== false) { $out .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
    return $out;
};
$ok = false;
try {
    $r = $read(); if (strncmp($r, '220', 3) !== 0) throw new Exception('banner');
    $cmd = function ($c, $expect) use ($sock, $read) {
        fwrite($sock, $c . "\r\n"); $r = $read();
        if (strncmp($r, $expect, strlen($expect)) !== 0) throw new Exception(substr($c, 0, 4) . ': ' . trim($r));
        return $r;
    };
    $cmd('EHLO koza.niateks.com', '250');
    $cmd('AUTH LOGIN', '334');
    $cmd(base64_encode($user), '334');
    $cmd(base64_encode($pass), '235');
    $cmd('MAIL FROM:<' . $from . '>', '250');
    $cmd('RCPT TO:<' . $TO . '>', '250');
    $cmd('DATA', '354');
    $headers = 'From: ' . $fromName . ' <' . $from . '>' . "\r\n"
        . 'To: <' . $TO . '>' . "\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode('Niateks House CRM — ' . $VER . ' yayında') . '?=' . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@niateks.com>' . "\r\n";
    $body = preg_replace('/^\./m', '..', $html);
    fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
    $r = $read();
    if (strncmp($r, '250', 3) !== 0) throw new Exception('DATA: ' . trim($r));
    fwrite($sock, "QUIT\r\n");
    $ok = true;
} catch (Throwable $ex) {
    echo "surum_mail: gonderilemedi (" . $ex->getMessage() . ")\n";
}
@fclose($sock);
echo $ok ? "surum_mail: rapor $TO adresine gonderildi\n" : "surum_mail: mail basarisiz\n";
exit(0);
