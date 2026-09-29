# Alpha.21 tasarım yayını — 30 Eylül 2026

Test edilen uygulama kodu: 417e822777373d842ced7f3cb1e53d94dc6fa2eb.
CI: https://github.com/NiateksHouse/niateks-crm/actions/runs/36634581911

- MySQL: 29 test, 147 kontrol başarılı.
- Gerçek HTTP: giriş, çıkış, davet etkinleştirme, CSRF, firma yazımı, güvenlik başlıkları ve eski oturum reddi başarılı.
- Pint, PHP sözdizimi, Blade derleme, Composer doğrulaması/güvenlik taraması ve paket üretimi başarılı.
- Test sunucusuna yalnız public/app-alpha21.css, resources/views/layout.blade.php ve login.blade.php yüklendi. Veritabanı, kullanıcılar, davetler, APP_KEY ve .env değiştirilmedi.
- Uygulanan paket: koza-crm-v2.0.0-alpha.21-ui-patch-v1.1.0.zip. İlk ui-patch.zip aynı içeriğe sahip ama eski ZIP zaman damgası nedeniyle uygulanmadı; yalnız v1.1.0 kullanılmalı. Tam runtime paketi mevcut kurulumun üzerine uygulanmamalı; güncellemelerde Blade önbelleği/tarihleri ayrıca ele alınmalı.
- Önceki ekranlar sunucuda resources/views-before-alpha21.zip içinde korundu; yerel yedek indirilmedi. Geri dönüş: bu ZIP'i resources içine açarak önceki iki şablonu geri yükle; önbellekte eski sürüm kalırsa Blade cache'i kontrollü temizle. Eski app.css silinmedi.
- HTTPS üzerinden giriş ekranı ve fotoğraf/logosu doğrulandı. Dar telefon görünümünde form, düğme ve davet bağlantısı görsel olarak incelendi. Masaüstü girişinin tam ekran görüntüsü alınamadı; masaüstü firma listesi görsel olarak kontrol edildi.
- Nigar Mutlu hesabının mevcut oturumu ve yeni firma formunun açılması doğrulandı. Test hostinginde yeni iş kaydı oluşturulmadı. Tunç aktivasyonu bu kontrolde doğrulanmadı.

## Sınırlar

Bu sürüm bütün demo modüllerini çalışır hale getirmez. Gerçek backend giriş/davet, firma/müşteri/tedarikçi, bilgi revizyonu ve yetkilendirme ile sınırlıdır. Proje, ürün, numune, teklif, üretim, tahsilat, müzik ve mola modülleri demo sürümündedir; sıradaki aktarım işidir. Kullanım kılavuzu v0.3.0 korunmuştur; görsel/menu değişikliğine uyarlanması için ayrıca kullanıcı onayı gerekir. Canlı ve diğer siteler değiştirilmedi.
