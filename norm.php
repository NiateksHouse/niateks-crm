<?php
/**
 * Niateks House CRM — Türkçe metin normalizasyonu (tek ortak kural seti)
 * JS karşılığı: index.html içindeki /* @norm-core-start ... @norm-core-end *​/ bloğu (normTR).
 * Kurallar iki dilde birebir aynıdır; bir taraf değişirse diğeri de değişmelidir.
 *
 * Formatlar:
 *   title    → kelime başları büyük (Türkçe kurallarla; istisna/bağlaç/birim korumalı)
 *   sentence → cümle başı + . ! ? … sonrası büyük; gerisine dokunma
 *   upper    → tamamı büyük harf (Türkçe i→İ, ı→I)
 *   none     → sadece boşluk düzeltmesi (e-posta, URL, telefon, kod alanları)
 *
 * Bu dosya saf fonksiyonlardır; DB/oturum içermez. api.php require eder.
 * mbstring'in Türkçe bug'ı nedeniyle (mb_strtolower('İ') = "i̇" YANLIŞ)
 * büyük/küçük harf dönüşümleri elle eşleme tablosuyla yapılır.
 */

const NX_TR_UPPER_MAP = [
    'ç'=>'Ç','ğ'=>'Ğ','ı'=>'I','i'=>'İ','ö'=>'Ö','ş'=>'Ş','ü'=>'Ü',
    'a'=>'A','b'=>'B','c'=>'C','d'=>'D','e'=>'E','f'=>'F','g'=>'G','h'=>'H',
    'j'=>'J','k'=>'K','l'=>'L','m'=>'M','n'=>'N','o'=>'O','p'=>'P','q'=>'Q',
    'r'=>'R','s'=>'S','t'=>'T','u'=>'U','v'=>'V','w'=>'W','x'=>'X','y'=>'Y','z'=>'Z',
];
const NX_TR_LOWER_MAP = [
    'Ç'=>'ç','Ğ'=>'ğ','I'=>'ı','İ'=>'i','Ö'=>'ö','Ş'=>'ş','Ü'=>'ü',
    'A'=>'a','B'=>'b','C'=>'c','D'=>'d','E'=>'e','F'=>'f','G'=>'g','H'=>'h',
    'J'=>'j','K'=>'k','L'=>'l','M'=>'m','N'=>'n','O'=>'o','P'=>'p','Q'=>'q',
    'R'=>'r','S'=>'s','T'=>'t','U'=>'u','V'=>'v','W'=>'w','X'=>'x','Y'=>'y','Z'=>'z',
];

function nx_tr_upper(string $s): string { return strtr($s, NX_TR_UPPER_MAP); }
function nx_tr_lower(string $s): string { return strtr($s, NX_TR_LOWER_MAP); }

/** Varsayılan istisna listesi (ADIM 2'de DB text_exceptions tablosundan okunacak) */
function nx_default_exceptions(): array {
    return ['OEKO-TEX', 'GOTS', 'BCI', 'AQL', 'A.Ş.', 'Ltd. Şti.', 'PVC', 'EUR', 'USD', 'iPhone', 'eBay'];
}
/** Bağlaçlar (title formatında başlık içinde küçük kalır; opts['connectors']=false ile kapatılır) */
function nx_default_connectors(): array {
    return ['ve', 'ile', 'için', 'ya', 'da', 'veya'];
}
/** Sayıya bitişik ölçü birimleri (title formatında olduğu gibi kalır) */
function nx_default_units(): array {
    return ['gr', 'kg', 'mg', 'lt', 'cm', 'mm', 'm', 'km', 'adet', 'çift'];
}

/**
 * İstisna terimi için büyük/küçük harfe duyarsız regex üretir.
 * Her harf [küçükBÜYÜK] sınıfına çevrilir; kelime sınırı korunur.
 */
function nx_exception_pattern(string $e): string {
    $chars = preg_split('//u', $e, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach ($chars as $ch) {
        if (preg_match('/\p{L}/u', $ch)) $out .= '[' . nx_tr_lower($ch) . nx_tr_upper($ch) . ']';
        else $out .= preg_quote($ch, '/');
    }
    return '/(?<![\p{L}\p{N}])' . $out . '(?![\p{L}])/u';
}

/**
 * Ana giriş noktası: nx_norm($metin, $format, $opts)
 * $opts: ['exceptions' => string[], 'connectors' => bool]
 */
function nx_norm(string $s, string $format = 'none', array $opts = []): string {
    $format = in_array($format, ['title', 'sentence', 'upper', 'none'], true) ? $format : 'none';
    // Genel boşluk düzeltmesi: trim + ardışık boşluk teke (tüm formatlar)
    $s = str_replace(["\u{00A0}", "\t", "\r"], ' ', $s);
    $s = trim((string)preg_replace('/ {2,}/u', ' ', preg_replace('/\s+/u', ' ', $s) ?? $s));
    if ($s === '' || $format === 'none') return $s;

    $exceptions  = $opts['exceptions'] ?? nx_default_exceptions();
    $connOn      = $opts['connectors'] ?? true;
    $connectors  = nx_default_connectors();
    $units       = nx_default_units();

    // İstisnaları yer tutucuyla koru (nokta içerenler cümle sınırı bozmasın)
    $placeholders = [];
    foreach ($exceptions as $i => $e) {
        if ($e === '') continue;
        $ph = "\x01" . mb_chr(0xE000 + $i, 'UTF-8') . "\x01";
        $s2 = preg_replace_callback(nx_exception_pattern($e), function () use (&$placeholders, $i, $ph, $e) {
            $placeholders[$i] = $e;
            return $ph;
        }, $s);
        if ($s2 !== null) $s = $s2;
    }

    if ($format === 'upper') {
        $s = nx_tr_upper($s);
    } elseif ($format === 'title') {
        $s = nx_title($s, $connOn, $connectors, $units);
    } elseif ($format === 'sentence') {
        $s = nx_sentence($s);
    }

    // Yer tutucuları geri koy (kanonik yazımla)
    foreach ($placeholders as $i => $canon) {
        $s = str_replace("\x01" . mb_chr(0xE000 + $i, 'UTF-8') . "\x01", $canon, $s);
    }
    return $s;
}

/** title: kelime başları büyük. Karışık yazılışlara (iPhone), rakamlara, birimlere dokunulmaz. */
function nx_title(string $s, bool $connOn, array $connectors, array $units): string {
    $re = "/((?:[\\p{L}\\p{M}\\p{N}\\x01\\x{E000}-\\x{F8FF}])+(?:['’][\\p{L}\\p{M}\\p{N}\\x01\\x{E000}-\\x{F8FF}]+)*)/u";
    $parts = preg_split($re, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    // token indeksleri (harf VEYA rakamla baslayan) ve rakam icerme durumu
    $tokenIdx = []; $hasDigit = [];
    foreach ($parts as $i => $p) {
        $hasDigit[$i] = (bool)preg_match('/\\p{N}/u', $p);
        if (preg_match('/^[\\p{L}\\p{N}\\x01\\x{E000}-\\x{F8FF}]/u', $p)) $tokenIdx[] = $i;
    }
    foreach ($tokenIdx as $k => $i) {
        if (!preg_match('/^\p{L}/u', $parts[$i])) continue; // rakam token'ina dokunma
        $w  = $parts[$i];
        $lw = nx_tr_lower($w); $uw = nx_tr_upper($w);
        $isLower = ($lw === $w); $isUpper = ($uw === $w);
        $mixed = !$isLower && !$isUpper;
        if ($hasDigit[$i]) continue;
        // olcu birimi ve sayiya komsu ("500 gr") → oldugu gibi
        if (!$mixed && in_array($lw, $units, true)) {
            $prevT = $k > 0 ? $tokenIdx[$k - 1] : -1;
            $nextT = $k + 1 < count($tokenIdx) ? $tokenIdx[$k + 1] : -1;
            if (($prevT >= 0 && $hasDigit[$prevT]) || ($nextT >= 0 && $hasDigit[$nextT])) continue;
        }
        if ($mixed) continue; // iPhone, eBay, A.Ş. gibi karışık yazımlar
        if ($connOn && $k > 0 && in_array($lw, $connectors, true)) { $parts[$i] = $lw; continue; }
        $first = mb_substr($w, 0, 1, 'UTF-8');
        $rest  = mb_substr($w, 1, null, 'UTF-8');
        $parts[$i] = nx_tr_upper($first) . nx_tr_lower($rest);
    }
    return implode('', $parts);
}

/** sentence: cümle başı ve . ! ? … sonrası ilk harf büyük; gerisi olduğu gibi. */
function nx_sentence(string $s): string {
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = ''; $boundary = true;
    foreach ($chars as $ch) {
        if (preg_match('/[.!?…]/u', $ch)) { $out .= $ch; $boundary = true; continue; }
        if ($boundary) {
            if (preg_match('/\\p{L}/u', $ch)) { $out .= nx_tr_upper($ch); $boundary = false; continue; }
            if (preg_match('/\\s/u', $ch)) { $out .= $ch; continue; }
            $out .= $ch; $boundary = false; continue; // rakam vb. sınırı kapatır ("2.5 adet")
        }
        $out .= $ch;
    }
    return $out;
}
