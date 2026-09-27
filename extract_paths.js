const fs = require('fs');
const mevcut = fs.readFileSync('intro.js', 'utf8');
// el.innerHTML icindeki thread grubunu bul
const startTag = '<g transform="translate(170 90)">';
const start = mevcut.indexOf(startTag);
if (start < 0) { console.log('BASLANGIC YOK'); process.exit(1); }
const end = mevcut.indexOf('</g></g>', start);
if (end < 0) { console.log('BITIS YOK'); process.exit(1); }
const paths = mevcut.slice(start + startTag.length, end);
fs.writeFileSync('gemini_paths.frag', paths);
console.log('yol verisi cikarildi:', paths.length, 'karakter |', (paths.match(/<path/g) || []).length, 'path');
