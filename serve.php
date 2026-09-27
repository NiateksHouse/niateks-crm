<?php
// Niateks House CRM — PHP yerleşik sunucu yönlendiricisi.
// Kullanım: "C:/xampp/php/php.exe" -S 127.0.0.1:8090 serve.php
// cPanel'de bu dosya gerekmez; .htaccess yönlendirmeyi yapar.
if (PHP_SAPI !== 'cli-server') return false;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false; // statik dosyalar doğrudan
if (preg_match('#^/api(/|$)#', $path)) {
    $_SERVER['SCRIPT_NAME'] = '/api.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/api.php';
    require __DIR__ . '/api.php';
    return true;
}
if ($path === '/' || $path === '') { require __DIR__ . '/index.html'; return true; }
if (is_dir($file)) { require __DIR__ . '/index.html'; return true; }
http_response_code(404);
echo '404';
return true;
