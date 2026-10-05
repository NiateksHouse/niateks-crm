"""Release assertions for the preserved login and production workspace assets."""
import hashlib,json,re
from pathlib import Path
root=Path(__file__).resolve().parents[1]
for rel,expected in json.loads((root/'tests/login-baseline.json').read_text()).items():
    assert hashlib.sha256((root/rel).read_bytes()).hexdigest()==expected, f'Login changed: {rel}'
view=(root/'resources/views/koza/app.blade.php').read_text()
assert not re.search(r'\son\w+=|<style>|<script>',view), 'CSP requires external scripts and styles'
assert all(locale in view for locale in ['value="tr-TR"','value="en"'])
assert all((root/'public'/name).is_file() for name in ['koza-v1.css','koza-v1.js','koza-language-v1.js'])
assert '.env' not in [str(f.relative_to(root/'public')) for f in (root/'public').rglob('*')]
print('Login hashes, external assets, TR/ENG options and public separation passed.')

assert 'English · UK' not in view and 'English · USA' not in view
