# Alpha.20 doğrulama — 30 Eylül 2026

Test edilen kod: a86637da31639eae07dc20b22d3160744bb1e25c.
GitHub Actions: https://github.com/NiateksHouse/niateks-crm/actions/runs/36629249471

- MySQL: 29 test / 147 kontrol geçti.
- Gerçek HTTP: davetle etkinleştirme, yeni hesapla giriş, CSRF, oturum, ortak firma yazımı, güvenlik başlıkları ve çıkış kontrolleri geçti.
- Pint, PHP sözdizimi, Blade derleme, Composer doğrulama, bağımlılık güvenlik taraması ve çalışma paketi kontrolü geçti.
- Hosting Cron ile PHP 8.4.25 CLI; pdo_mysql, mbstring, openssl, fileinfo, bcmath ve sodium doğrulandı.
- Yalnız geçici koza-php-cli-check-alpha20 Cron görevi kullanıcı onayıyla kaldırıldı. Mevcut saatlik rapor ve özel kontrol sonucu dosyası korundu.

Sınırlar: test hosting kurulumu, gerçek DB bağlantısı, migration ve gerçek hesap etkinleştirme henüz yapılmadı. Gerçek davet/parola üretilmedi, e-posta gönderilmedi. Masaüstü/mobil görsel inceleme tamamlanmadı. Finans, üretim ve teklifler bu backend sürümünde yoktur. Kılavuz v0.3.0 korunmuştur; güncellemesi ayrıca onay gerektirir.
