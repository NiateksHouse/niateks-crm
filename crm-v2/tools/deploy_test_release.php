<?php

/** Private, single-use staging deployment. Run by PHP CLI only, never from public/. */
declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
const APP_ROOT = '/home/niatekscom/koza-crm-test-alpha20';
const EXPECTED_URL = 'https://test.koza.niateks.com';
const EXPECTED_DB = 'niatekscom_koza_test';
$work = __DIR__;
$release = $work.'/koza-crm-v2.1.2-test.1-runtime.zip';
$checksumFile = $work.'/SHA256SUMS.txt';
$report = $work.'/deployment-report.json';
$lock = fopen($work.'/deployment.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    exit;
}
if (is_file($work.'/complete.json')) {
    exit;
}
$state = ['release' => '2.1.2-test.1', 'started_at' => gmdate('c'), 'status' => 'preflight'];
function recordReport(): void
{
    global $report,$state;
    file_put_contents($report, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    chmod($report, 0600);
}
function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function command(array $args): void
{
    global $work, $app;
    // Shared hosting disables proc_open. Run the same allowed Artisan commands
    // through the already bootstrapped framework without changing PHP security settings.
    $name = $args[2] ?? '';
    check(in_array($name, ['down', 'up', 'optimize:clear', 'migrate', 'view:cache'], true), 'Unexpected deployment command');
    $options = [];
    foreach (array_slice($args, 3) as $arg) {
        [$key, $value] = array_pad(explode('=', $arg, 2), 2, true);
        $options[$key] = $value;
    }
    $kernel = $app->make(Kernel::class);
    $code = $kernel->call($name, $options);
    file_put_contents($work.'/commands.log', gmdate('c').' '.$name.' exit='.$code."\n".$kernel->output()."\n", FILE_APPEND);
    chmod($work.'/commands.log', 0600);
    if ($code !== 0) {
        throw new RuntimeException('CLI command failed: '.$name.'; see private log');
    }
}
function zipDirectory(string $source, string $target, array $excluded = []): array
{
    $zip = new ZipArchive;
    check($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) === true, 'Backup archive could not open');
    $manifest = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($source) + 1);
        foreach ($excluded as $prefix) {
            if ($rel === $prefix || str_starts_with($rel, $prefix.'/')) {
                continue 2;
            }
        }
        check(! $file->isLink(), 'Symlink requires manual inventory: '.$rel);
        if (! $file->isFile()) {
            continue;
        }
        check($zip->addFile($file->getPathname(), $rel), 'Backup file could not be added');
        $manifest[$rel] = hash_file('sha256', $file->getPathname());
    }
    $zip->addFromString('BACKUP_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    check($zip->close(), 'Backup archive could not close');
    chmod($target, 0600);
    $verify = new ZipArchive;
    check($verify->open($target, ZipArchive::CHECKCONS) === true, 'Backup archive integrity check failed');
    foreach ($manifest as $path => $sha) {
        $stream = $verify->getStream($path);
        check(is_resource($stream), 'Backup stream missing');
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);
        check(hash_final($hash) === $sha, 'Backup checksum mismatch');
    }$verify->close();

    return ['path' => $target, 'files' => count($manifest), 'sha256' => hash_file('sha256', $target)];
}
function databaseBackup(PDO $pdo, string $path): array
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $counts = [];
    $digest = [];
    $out = gzopen($path, 'wb9');
    check($out !== false, 'Database backup could not open');
    gzwrite($out, "-- KOZA staging snapshot; restore into a separate empty database first.\nSET FOREIGN_KEY_CHECKS=0;\n");
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    foreach ($tables as $table) {
        check((bool) preg_match('/^[a-zA-Z0-9_]+$/', $table), 'Unsafe table identifier');
        $create = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
        gzwrite($out, $create.";\n");
        $rows = $pdo->query('SELECT * FROM `'.$table.'`');
        $count = 0;
        $hash = hash_init('sha256');
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = implode(',', array_map(fn ($key) => '`'.str_replace('`', '``', $key).'`', array_keys($row)));
            $values = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row)));
            $sql = 'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.");\n";
            gzwrite($out, $sql);
            hash_update($hash, $sql);
            $count++;
        }
        $counts[$table] = $count;
        $digest[$table] = hash_final($hash);
    }
    $pdo->commit();
    gzwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($out);
    chmod($path, 0600);
    $verify = gzopen($path, 'rb');
    check($verify !== false, 'Database backup unreadable');
    $bytes = 0;
    while (! gzeof($verify)) {
        $chunk = gzread($verify, 1048576);
        check($chunk !== false, 'Database gzip integrity failure');
        $bytes += strlen($chunk);
    }gzclose($verify);
    check($bytes > 100, 'Database backup unexpectedly small');

    return ['path' => $path, 'sha256' => hash_file('sha256', $path), 'counts' => $counts, 'table_digests' => $digest, 'uncompressed_bytes' => $bytes];
}
$swapped = [];
$maintenance = false;
$backup = null;
try {
    check(realpath(APP_ROOT) === APP_ROOT, 'Unexpected or symbolic app root');
    check(realpath(APP_ROOT.'/public') === APP_ROOT.'/public', 'Unexpected public path');
    check(str_starts_with(realpath($work), APP_ROOT.'/updates/'), 'Deployment script must be in the private updates directory');
    check(is_file($release) && is_file($checksumFile), 'Reviewed runtime package or checksum is missing');
    $line = trim(file_get_contents($checksumFile));
    check((bool) preg_match('/^([a-f0-9]{64})\s+'.preg_quote(basename($release), '/').'$/', $line, $match), 'Invalid checksum manifest');
    check(hash_equals($match[1], hash_file('sha256', $release)), 'Runtime package checksum mismatch');
    require APP_ROOT.'/vendor/autoload.php';
    $app = require APP_ROOT.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    check(config('app.url') === EXPECTED_URL, 'Deployment URL is not the test environment');
    check(app()->environment('staging'), 'Only staging is allowed');
    check(config('database.default') === 'mysql' && config('database.connections.mysql.database') === EXPECTED_DB, 'Wrong test database');
    $pdo = DB::connection()->getPdo();
    check($pdo->query('SELECT DATABASE()')->fetchColumn() === EXPECTED_DB, 'Connected database mismatch');
    $payload = $work.'/payload';
    check(! file_exists($payload), 'Payload directory already exists; inspect previous attempt');
    mkdir($payload, 0700);
    $zip = new ZipArchive;
    check($zip->open($release, ZipArchive::CHECKCONS) === true, 'Runtime archive invalid');
    $manifest = json_decode($zip->getFromName('MANIFEST.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest as $path => $sha) {
        check(! str_contains($path, '..') && ! str_starts_with($path, '/') && ! str_contains($path, '\\'), 'Unsafe archive path');
        check($path !== '.env' && ! str_starts_with($path, 'storage/'), 'Runtime must not include private data');
        $data = $zip->getFromName($path);
        check($data !== false && hash('sha256', $data) === $sha, 'Runtime manifest mismatch: '.$path);
        $dest = $payload.'/'.$path;
        if (! is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0700, true);
        }file_put_contents($dest, $data);
        chmod($dest, str_starts_with($path, 'public/') ? 0644 : 0600);
    }$zip->close();
    foreach (['vendor/autoload.php', 'public/index.php', 'public/.htaccess', 'resources/views/login.blade.php', 'public/koza-v1.js'] as $path) {
        check(is_file($payload.'/'.$path), 'Required runtime file missing: '.$path);
    }
    check(hash_file('sha256', APP_ROOT.'/composer.lock') === hash_file('sha256', $payload.'/composer.lock'), 'In-process deployment requires unchanged framework dependencies');
    foreach (['resources/views/login.blade.php', 'resources/views/layout.blade.php', 'public/app-alpha22.css', 'public/password-toggle-alpha23.css', 'public/password-toggle-alpha23.js', 'public/assets/login-bg.jpg', 'public/assets/niateks-house-logo.png'] as $path) {
        check(hash_file('sha256', APP_ROOT.'/'.$path) === hash_file('sha256', $payload.'/'.$path), 'Login preservation failed: '.$path);
    }
    $state['package_sha256'] = $match[1];
    $state['status'] = 'backing_up';
    recordReport();
    $backup = '/home/niatekscom/BACKUP/koza-test/'.gmdate('Ymd-His').'-pre-workflows';
    check(! file_exists($backup), 'Backup target already exists');
    mkdir($backup, 0700, true);
    chmod(dirname($backup), 0700);
    command([PHP_BINARY, APP_ROOT.'/artisan', 'down', '--retry=30']);
    $maintenance = true;
    $state['database_backup'] = databaseBackup($pdo, $backup.'/database.sql.gz');
    $state['files_backup'] = zipDirectory(APP_ROOT, $backup.'/application.zip', ['updates', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions']);
    $state['backup_directory'] = $backup;
    $state['status'] = 'backup_verified';
    recordReport();
    // Move, never leave old deployment files or archives beneath the public document root.
    mkdir($backup.'/moved', 0700);
    $components = ['app', 'config', 'database', 'resources', 'routes', 'vendor', 'public'];
    foreach ($components as $component) {
        check(is_dir($payload.'/'.$component), 'Missing component '.$component);
        check(rename(APP_ROOT.'/'.$component, $backup.'/moved/'.$component), 'Could not move previous '.$component);
        $swapped[] = $component;
        check(rename($payload.'/'.$component, APP_ROOT.'/'.$component), 'Could not install '.$component);
    }
    foreach (['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php', 'INSTALL.md'] as $path) {
        if (! is_dir($backup.'/moved/'.dirname($path))) {
            mkdir($backup.'/moved/'.dirname($path), 0700, true);
        }
        if (file_exists(APP_ROOT.'/'.$path)) {
            check(rename(APP_ROOT.'/'.$path, $backup.'/moved/'.$path), 'Could not move previous file');
        }
        $swapped[] = $path;
        check(rename($payload.'/'.$path, APP_ROOT.'/'.$path), 'Could not install file');
    }
    $publicTree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT.'/public', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    chmod(APP_ROOT.'/public', 0755);
    foreach ($publicTree as $f) {
        chmod($f->getPathname(), $f->isDir() ? 0755 : 0644);
    }
    command([PHP_BINARY, APP_ROOT.'/artisan', 'optimize:clear']);
    command([PHP_BINARY, APP_ROOT.'/artisan', 'migrate', '--force']);
    command([PHP_BINARY, APP_ROOT.'/artisan', 'view:cache']);
    $after = [];
    foreach ($state['database_backup']['counts'] as $table => $count) {
        $after[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
        if (! in_array($table, ['migrations', 'cache', 'cache_locks', 'sessions'])) {
            check($after[$table] === $count, 'Existing record count changed: '.$table);
        }
    }
    $state['preserved_table_counts'] = $after;
    $state['public_files'] = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT.'/public', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $state['public_files'][] = substr($f->getPathname(), strlen(APP_ROOT.'/public/'));
        }
    }
    foreach ($state['public_files'] as $path) {
        check(! preg_match('/(\.zip$|\.sql|\.env|backup|deploy)/i', $path), 'Unexpected sensitive public file');
    }
    command([PHP_BINARY, APP_ROOT.'/artisan', 'up']);
    $maintenance = false;
    $state['status'] = 'deployed';
    $state['completed_at'] = gmdate('c');
    recordReport();
    file_put_contents($work.'/complete.json', json_encode(['completed_at' => $state['completed_at'], 'package_sha256' => $match[1], 'backup' => $backup], JSON_PRETTY_PRINT));
    chmod($work.'/complete.json', 0600);
} catch (Throwable $e) {
    // Additive migrations are retained on rollback; previous application and private data remain usable.
    $state['status'] = 'failed';
    $state['error'] = $e->getMessage();
    if ($backup && $swapped) {
        $failed = $backup.'/failed-release';
        mkdir($failed, 0700, true);
        foreach (array_reverse($swapped) as $path) {
            if (! file_exists($backup.'/moved/'.$path)) {
                continue;
            }if (! is_dir(dirname($failed.'/'.$path))) {
                mkdir(dirname($failed.'/'.$path), 0700, true);
            }if (file_exists(APP_ROOT.'/'.$path)) {
                rename(APP_ROOT.'/'.$path, $failed.'/'.$path);
            }rename($backup.'/moved/'.$path, APP_ROOT.'/'.$path);
        }try {
            command([PHP_BINARY, APP_ROOT.'/artisan', 'optimize:clear']);
            $state['rollback'] = 'previous files restored';
        } catch (Throwable) {
            $state['rollback'] = 'manual review required';
        }
    }
    if ($maintenance) {
        try {
            command([PHP_BINARY, APP_ROOT.'/artisan', 'up']);
        } catch (Throwable) {
            $state['maintenance'] = 'manual recovery required';
        }
    }
    recordReport();
    exit(1);
}
