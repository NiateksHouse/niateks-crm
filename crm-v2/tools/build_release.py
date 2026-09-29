"""Package only runtime files; never include local credentials or generated runtime state."""
from pathlib import Path
import hashlib
import json
import zipfile
import datetime

root = Path(__file__).resolve().parents[1]
out = Path('/tmp/koza-build')
out.mkdir(parents=True, exist_ok=True)
archive = out / 'koza-crm-v2.0.0-alpha.23-runtime.zip'
files = []
for name in ['app', 'config', 'database', 'public', 'vendor']:
    files.extend(f for f in (root / name).rglob('*') if f.is_file())
files.extend(f for f in (root / 'resources/views').rglob('*') if f.is_file())
files.extend([root / name for name in ['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php', 'routes/web.php', 'routes/console.php', '.env.example', 'INSTALL.md']])
manifest = {}
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as z:
    for f in sorted(set(files)):
        assert not f.is_symlink(), f'Unexpected symlink: {f}'
        rel = f.relative_to(root).as_posix()
        assert rel != '.env' and not rel.startswith(('tests/', 'storage/', 'bootstrap/cache/'))
        data = f.read_bytes()
        info = zipfile.ZipInfo(rel, datetime.datetime.now().timetuple()[:6])
        info.create_system = 3
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = (0o100644 if rel.startswith('public/') else 0o100600) << 16
        z.writestr(info, data)
        manifest[rel] = hashlib.sha256(data).hexdigest()
    for directory in ['bootstrap/cache/', 'storage/app/private/', 'storage/framework/cache/data/', 'storage/framework/sessions/', 'storage/framework/views/', 'storage/logs/']:
        info = zipfile.ZipInfo(directory)
        info.create_system = 3
        info.external_attr = (0o40755 << 16) | 0x10
        z.writestr(info, '')
    z.writestr('MANIFEST.json', json.dumps(manifest, indent=2))
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    assert 'vendor/autoload.php' in z.namelist() and '.env' not in z.namelist()
    assert not any(n.startswith('vendor/phpunit/') for n in z.namelist())
(out / 'SHA256SUMS.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n')
print('Runtime archive verified:', archive.name, archive.stat().st_size, 'bytes')
