// Kullanım: node test_norm_js.js
const fs = require('fs');
const html = fs.readFileSync('index.html', 'utf8');
const m = html.match(/\/\* @norm-core-start[\s\S]*?@norm-core-end \*\//);
if (!m) { console.error('norm bloğu bulunamadı'); process.exit(1); }
let code = m[0]
  .replace(/\/\* @norm-core-start[^*]*\*\/\s*/, '')
  .replace(/\s*\/\* @norm-core-end \*\//, '');
const sandbox = new Function(code + `
  return { nx_norm, nx_title, nx_sentence, nx_tr_upper, nx_tr_lower, nx_exception_pattern, NX_DEFAULT_EXCEPTIONS };
`)();
const { nx_norm, nx_exception_pattern, NX_DEFAULT_EXCEPTIONS } = sandbox;
const cases = JSON.parse(fs.readFileSync('test_norm_cases.json', 'utf8'));
let fail = 0, pass = 0;
for (const [inp, fmt, opt, expected] of cases) {
  const o = {};
  if (opt === 'conn_off') o.connectors = false;
  if (opt === 'exc_ext') o.exceptions = [...NX_DEFAULT_EXCEPTIONS, 'TSŞ'];
  const got = nx_norm(inp, fmt, o);
  if (got === expected) { pass++; }
  else { fail++; console.log('FAIL', JSON.stringify(inp), fmt, '→', JSON.stringify(got), 'beklenen', JSON.stringify(expected)); }
}
console.log(`JS: ${pass} geçti, ${fail} kaldı`);
process.exit(fail ? 1 : 0);
